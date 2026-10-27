<?php

namespace App\Services;

use App\Exceptions\DiscountCodeInvalidoException;
use App\Exceptions\VarianteRequeridaException;
use App\Models\Addon;
use App\Models\CardPaymentPlan;
use App\Models\Combo;
use App\Models\DiscountCode;
use App\Models\Product;
use Illuminate\Support\Collection;

/**
 * Calcula un pedido del carrito: precios (escalas, ofertas, color con recargo, add-ons),
 * combos de precio fijo, cupón de descuento, envío gratis por combo, totales, las unidades de
 * stock que el pedido consume y la información de cuotas con tarjeta (informativa).
 *
 * NO ESCRIBE en la base ni en la sesión: no crea la orden ni los ítems, no descuenta stock
 * y no consume el cupón (solo lo busca y valida, y calcula cuánto descontaría; consumirlo es
 * `DiscountCodeService::registrarUso()`, dentro de la transacción que crea la orden). Tampoco
 * toca `session()`: quien llama decide qué hacer con un cupón o un plan que ya no valen.
 *
 * Se usa en dos etapas, en este orden, porque el checkout intercala entre ellas la
 * validación de los datos del cliente y la del stock:
 *
 *  1. `calcularItems()` — ítems, subtotal, envío gratis por combo y unidades de stock.
 *  2. `aplicarDescuento()` — cupón (lanza `DiscountCodeInvalidoException` si no vale) y total.
 *
 * Las vistas del carrito usan las piezas sueltas (`items()`, `subtotal()`,
 * `resolverCupon()`, `infoCuotas()`), que son las mismas que arman el pedido.
 */
class CalculadoraPedido
{
    public function __construct(
        private readonly PricingService $pricingService,
        private readonly DiscountCodeService $discountCodeService,
        private readonly CardSurchargeService $cardSurchargeService,
    ) {}

    /**
     * Etapa 1: ítems con precio resuelto server-side. Con `exigirVariante: true` (checkout)
     * una opción faltante o inválida lanza `VarianteRequeridaException` y aborta el pedido.
     *
     * @param  array<int, array<string, mixed>>  $lineas  Carrito ya normalizado (`CartController::normalizeCart`).
     *
     * @throws VarianteRequeridaException
     */
    public function calcularItems(array $lineas, bool $exigirVariante = true): CalculoPedido
    {
        $items = $this->items($lineas, $exigirVariante);

        return new CalculoPedido(
            items: $items,
            subtotal: $this->subtotal($items),
            envioGratisPorCombo: $this->envioGratisPorCombo($items),
            lineasStock: $this->lineasStock($items),
            total: (float) $this->subtotal($items),
        );
    }

    /**
     * Etapa 2: cupón validado contra el subtotal (sin consumirlo) y total. La forma de pago no se elige: las cuotas con tarjeta
     * son información (`infoCuotas()`) y el medio de pago se coordina por WhatsApp.
     *
     * @throws DiscountCodeInvalidoException
     */
    public function aplicarDescuento(CalculoPedido $calculo, ?string $codigo): CalculoPedido
    {
        $codigoDescuento = null;
        $descuento = 0.0;

        if ($codigo) {
            ['discountCode' => $codigoDescuento, 'amount' => $descuento] = $this->resolverCupon($codigo, (float) $calculo->subtotal);
        }

        return $calculo->conDescuento($codigoDescuento, $descuento, round($calculo->subtotal - $descuento, 2));
    }

    /**
     * Busca y valida un cupón para el subtotal dado y calcula su descuento. No lo consume.
     *
     * @return array{discountCode: DiscountCode, amount: float}
     *
     * @throws DiscountCodeInvalidoException
     */
    public function resolverCupon(string $codigo, float $subtotal): array
    {
        $discountCode = $this->discountCodeService->buscarValido($codigo, $subtotal);

        return [
            'discountCode' => $discountCode,
            'amount' => $this->discountCodeService->calcularDescuento($discountCode, $subtotal),
        ];
    }

    /**
     * Cómo se puede pagar `$total`, solo como información: el precio en efectivo o transferencia y, por cada plan de cuotas ACTIVO,
     * el recargo, el total con recargo y el monto por cuota (de menos a más cuotas). `null` si no hay planes activos o el total no
     * es positivo. No se elige ni se guarda nada: el medio de pago se coordina por WhatsApp.
     *
     * @return array{efectivo: float, max_cuotas: int, con_recargo: bool, planes: list<array{id: int, name: string, installments: int, surcharge_percentage: float, surcharge_amount: float, total_with_surcharge: float, installment_amount: float}>}|null
     */
    public function infoCuotas(float $total): ?array
    {
        if ($total <= 0) {
            return null;
        }

        $planes = CardPaymentPlan::active()->get()
            ->sortBy('installments')
            ->map(function (CardPaymentPlan $plan) use ($total) {
                $desglose = $this->cardSurchargeService->calcular($total, $plan);

                return [
                    'id' => $plan->id,
                    'name' => $plan->name,
                    'installments' => (int) $plan->installments,
                    'surcharge_percentage' => (float) $plan->surcharge_percentage,
                    'surcharge_amount' => $desglose['surcharge_amount'],
                    'total_with_surcharge' => $desglose['total_with_surcharge'],
                    'installment_amount' => $desglose['installment_amount'],
                ];
            })
            ->values();

        if ($planes->isEmpty()) {
            return null;
        }

        return [
            'efectivo' => round($total, 2),
            'max_cuotas' => (int) $planes->max('installments'),
            'con_recargo' => $planes->contains(fn (array $plan) => $plan['surcharge_amount'] > 0),
            'planes' => $planes->all(),
        ];
    }

    /**
     * Ítems del carrito con el precio recalculado server-side. Cada línea de producto pasa
     * por PricingService (tier + oferta + variante + add-ons); cada línea de combo es de
     * precio fijo.
     *
     * `$exigirVariante` se propaga a PricingService: las vistas del carrito lo dejan en false
     * (una línea con opciones irresolubles se descarta en silencio, mismo criterio que un
     * producto borrado); el checkout lo pasa en true y ahí una opción faltante o inválida
     * propaga la excepción para abortar el pedido entero.
     *
     * @param  array<int, array<string, mixed>>  $lineas
     * @return Collection<int, array<string, mixed>>
     */
    public function items(array $lineas, bool $exigirVariante = false): Collection
    {
        $idsDeProductos = collect($lineas)
            ->reject(fn (array $line) => isset($line['combo_id']))
            ->pluck('product_id')
            ->unique()
            ->all();

        $productos = Product::with(['images', 'currentOffer', 'priceTiers', 'variantsActive', 'addonsActive'])
            ->whereIn('id', $idsDeProductos)
            ->get()
            ->keyBy('id');

        // 1ª pasada: se descartan (o abortan, en el checkout) las líneas irresolubles ANTES de contar unidades, para que el
        // escalón sea el mismo en las vistas y en el checkout y no lo infle una línea que no se va a cobrar.
        $validas = [];

        foreach ($lineas as $line) {
            if (isset($line['combo_id'])) {
                $validas[] = $line;

                continue;
            }

            $product = $productos->get($line['product_id']);

            if (! $product) {
                continue;
            }

            try {
                $this->pricingService->resolverVariante($product, $line['variant_id'], $exigirVariante);
                $this->pricingService->resolverAddons($product, $this->idsDeAddons($line));
            } catch (VarianteRequeridaException $e) {
                // La variante o algún add-on de esta línea dejó de estar
                // disponible (se desactivó / borró), o falta elegir el color. El
                // carrito de sesión es efímero: en las vistas se descarta la
                // línea del listado, mismo criterio que un producto borrado. En
                // el checkout (`$exigirVariante`) eso no alcanza: se propaga para
                // abortar el pedido entero.
                if ($exigirVariante) {
                    throw $e;
                }

                continue;
            }

            $validas[] = $line;
        }

        $unidadesPorProducto = $this->unidadesPorProducto($validas);
        $cartItems = collect();

        foreach ($validas as $line) {
            // Línea de combo: se arma aparte (precio fijo, sin tiers/ofertas).
            if (isset($line['combo_id'])) {
                $comboItem = $this->buildComboItem($line, $exigirVariante);

                if ($comboItem !== null) {
                    $cartItems->push($comboItem);
                }

                continue;
            }

            $product = $productos->get($line['product_id']);
            $addonIds = $this->idsDeAddons($line);
            $unidadesDelProducto = $unidadesPorProducto[$product->id];

            $priceResult = $this->pricingService->calcularPrecio(
                $product,
                (int) $line['quantity'],
                $line['variant_id'],
                $addonIds,
                exigirVariante: $exigirVariante,
                cantidadParaEscala: $unidadesDelProducto,
            );

            $quantity = (int) $line['quantity'];
            $unitPrice = $priceResult->precioFinalConOpciones;

            $cartItems->push([
                'id' => $line['line_key'],
                'line_key' => $line['line_key'],
                'product' => $product,
                'quantity' => $quantity,
                // Precio base (tier + oferta), SIN opciones — misma semántica que
                // antes de variantes/add-ons.
                'price' => $priceResult->precioUnitarioFinal,
                'list_price' => $priceResult->precioLista,
                'unit_savings' => $priceResult->ahorroUnitario,
                'savings_percentage' => $priceResult->ahorroPorcentaje,
                // Precio unitario real: base + recargo de variante + add-ons.
                'unit_price' => $unitPrice,
                'variant_surcharge' => $priceResult->recargoVariante,
                'addons_total' => $priceResult->addonsTotal,
                'subtotal' => round($quantity * $unitPrice, 2),
                // «Sumá N más y pagás $X c/u»: el próximo escalón de precio por cantidad, si baja el precio.
                'siguiente_escala' => $this->siguienteEscala($product, $quantity, $unidadesDelProducto, $line['variant_id'], $addonIds, $unitPrice),
                // Unidades del producto sumando todas sus líneas: es la cantidad que define el escalón de esta línea.
                'unidades_del_producto' => $unidadesDelProducto,
                // Opciones de la línea, para la UI del carrito / checkout.
                'variant' => $priceResult->varianteAplicada ? [
                    'id' => $priceResult->varianteAplicada->id,
                    'name' => $priceResult->varianteAplicada->name,
                    'color_hex' => $priceResult->varianteAplicada->color_hex,
                    'is_custom_color' => (bool) $priceResult->varianteAplicada->is_custom_color,
                    'price_addon' => (float) $priceResult->varianteAplicada->price_addon,
                    'stock' => $priceResult->varianteAplicada->stock,
                ] : null,
                'custom_color_text' => $line['custom_color_text'],
                'addons' => $this->mapLineAddons($priceResult->addonsAplicados, $line['addon_selections']),
            ]);
        }

        return $cartItems;
    }

    /**
     * Unidades de cada producto sumando TODAS sus líneas del carrito (otros colores, otras personalizaciones): esa suma es la cantidad
     * que define el escalón de precio de cada una de ellas, igual que en Chisperío. Las unidades dentro de un combo NO suman: el combo
     * tiene precio fijo propio y no pasa por escalas (tampoco en Chisperío, que arma las escalas solo con las líneas de producto).
     *
     * @param  array<int, array<string, mixed>>  $lineas
     * @return array<int, int> product_id => unidades
     */
    public function unidadesPorProducto(array $lineas): array
    {
        $unidades = [];

        foreach ($lineas as $line) {
            if (isset($line['combo_id'])) {
                continue;
            }

            $unidades[(int) $line['product_id']] = ($unidades[(int) $line['product_id']] ?? 0) + (int) $line['quantity'];
        }

        return $unidades;
    }

    /**
     * @param  array<string, mixed>  $line
     * @return array<int, int>
     */
    private function idsDeAddons(array $line): array
    {
        return array_map(fn ($sel) => $sel['addon_id'], $line['addon_selections']);
    }

    /**
     * Próximo escalón de precio por cantidad de un producto, para «Sumá N más y pagás $X c/u». `$unidadesDelProducto` es el total de sus
     * líneas, que es lo que define el escalón al cobrar; `$cantidad` es la de ESTA línea (la que multiplica su subtotal). El precio sale
     * de PricingService fijando el escalón en `$siguiente` —la misma llamada que el checkout hará con ese total—, así que incluye la
     * oferta vigente y las opciones de la línea (color y add-ons) y es exactamente lo que se cobraría.
     * `null` si ya está en el último escalón o si el siguiente no baja el precio (una oferta acotada a otro escalón, por ejemplo).
     *
     * @param  array<int, int>  $addonIds
     * @return array{faltan: int, precio_unitario: float}|null
     */
    private function siguienteEscala(Product $product, int $cantidad, int $unidadesDelProducto, ?int $varianteId, array $addonIds, float $precioActual): ?array
    {
        $siguiente = $product->priceTiers
            ->pluck('cantidad_minima')
            ->map(fn ($minima) => (int) $minima)
            ->filter(fn (int $minima) => $minima > $unidadesDelProducto)
            ->min();

        if ($siguiente === null) {
            return null;
        }

        try {
            $precio = $this->pricingService->calcularPrecio($product, $cantidad, $varianteId, $addonIds, cantidadParaEscala: $siguiente)->precioFinalConOpciones;
        } catch (VarianteRequeridaException) {
            return null;
        }

        return $precio < $precioActual ? ['faltan' => $siguiente - $unidadesDelProducto, 'precio_unitario' => $precio] : null;
    }

    /**
     * Revalida, línea por línea del carrito, que las opciones obligatorias de cada producto
     * estén completas ANTES de calcular precio o tocar stock en el checkout:
     *
     *  1. producto con variantes activas y la línea sin `variant_id`;
     *  2. variante "a elección del cliente" sin `custom_color_text`;
     *  3. add-on elegido con `requires_text` cuyo texto llegó vacío.
     *
     * Devuelve el mensaje del primer hueco encontrado (nombrando el producto) o null si todas
     * las líneas están completas. A diferencia de items() —que descarta en silencio una línea
     * con opciones irresolubles, igual que un producto borrado, porque alimenta vistas
     * efímeras— acá cualquier hueco tiene que abortar el pedido entero.
     *
     * @param  array<int, array<string, mixed>>  $lineas
     */
    public function validarOpcionesObligatorias(array $lineas): ?string
    {
        foreach ($lineas as $line) {
            // Línea de combo: validar los colores de cada componente.
            if (isset($line['combo_id'])) {
                $error = $this->validarOpcionesComboObligatorias($line);

                if ($error !== null) {
                    return $error;
                }

                continue;
            }

            $product = Product::with(['variantsActive', 'addonsActive'])->find($line['product_id']);

            if (! $product) {
                // Producto borrado: items() lo descarta, mismo criterio.
                continue;
            }

            $variantesActivas = $product->variantsActive;

            // 1. El producto tiene variantes de color activas y esta línea no
            //    eligió ninguna.
            if ($variantesActivas->isNotEmpty() && $line['variant_id'] === null) {
                return "Elegí un color para «{$product->title}» antes de finalizar el pedido.";
            }

            // 2. La variante elegida es "a elección del cliente" y falta el color
            //    libre.
            $variante = $line['variant_id'] !== null
                ? $variantesActivas->firstWhere('id', $line['variant_id'])
                : null;

            if ($variante && $variante->is_custom_color && trim((string) $line['custom_color_text']) === '') {
                return "Indicá el color que querés para «{$product->title}» antes de finalizar el pedido.";
            }

            // 3. Cada add-on elegido con texto obligatorio tiene que traer su
            //    texto.
            $textoPorAddon = collect($line['addon_selections'])->keyBy('addon_id');

            foreach ($product->addonsActive as $addon) {
                if (! $addon->requires_text || ! $textoPorAddon->has($addon->id)) {
                    continue;
                }

                if (trim((string) ($textoPorAddon->get($addon->id)['custom_text'] ?? '')) === '') {
                    return "Completá el texto de «{$addon->name}» para «{$product->title}» antes de finalizar el pedido.";
                }
            }
        }

        return null;
    }

    /**
     * Suma de los subtotales de los ítems (antes de cupón).
     *
     * @param  Collection<int, array<string, mixed>>  $items
     */
    public function subtotal(Collection $items): float|int
    {
        return $items->sum('subtotal');
    }

    /**
     * True si el carrito incluye al menos un combo con envío gratis. Cuando lo hay, el pedido
     * califica para envío gratis sin importar el umbral global (free_shipping_threshold).
     *
     * @param  Collection<int, array<string, mixed>>  $items
     */
    public function envioGratisPorCombo(Collection $items): bool
    {
        return $items->contains(fn ($item) => ! empty($item['is_combo']) && ! empty($item['combo']['is_free_shipping']));
    }

    /**
     * Unidades de stock que consume el pedido: la variante elegida o el producto, y los
     * combos expandidos en sus componentes (una unidad por producto, con la cantidad
     * multiplicada por la cantidad de combos pedida).
     *
     * @param  Collection<int, array<string, mixed>>  $items
     * @return array<int, array{product_id: int, product_variant_id: int|null, cantidad: int}>
     */
    public function lineasStock(Collection $items): array
    {
        return $items->flatMap(function ($item) {
            if (! empty($item['is_combo'])) {
                return collect($item['component_selections'])->map(fn ($sel) => [
                    'product_id' => $sel['product_id'],
                    'product_variant_id' => $sel['product_variant_id'],
                    'cantidad' => (int) $sel['quantity'] * (int) $item['quantity'],
                ]);
            }

            return [[
                'product_id' => $item['product']->id,
                'product_variant_id' => $item['variant']['id'] ?? null,
                'cantidad' => $item['quantity'],
            ]];
        })->all();
    }

    /**
     * Combina los add-ons ya resueltos por PricingService (nombre + precio
     * efectivo) con el texto que ingresó el cliente para cada uno en esta línea.
     * Es también el shape que se snapshotea en `order_items.addons_selected`.
     *
     * @param  array<int, Addon>  $addonsAplicados
     * @param  array<int, array{addon_id: int, custom_text: string|null}>  $addonSelections
     * @return array<int, array{addon_id: int, name: string, price: float, custom_text: string|null}>
     */
    private function mapLineAddons(array $addonsAplicados, array $addonSelections): array
    {
        $textByAddon = collect($addonSelections)->keyBy('addon_id');

        return collect($addonsAplicados)->map(fn (Addon $addon) => [
            'addon_id' => $addon->id,
            'name' => $addon->name,
            'price' => round((float) ($addon->pivot?->price_override ?? $addon->price), 2),
            'custom_text' => $textByAddon->get($addon->id)['custom_text'] ?? null,
        ])->all();
    }

    /**
     * Arma el item de carrito de una línea de combo. El precio es FIJO (el del
     * combo), independiente de los productos que lo integran y de sus
     * tiers/ofertas; los recargos de variante NO se aplican (la variante sólo
     * registra el color elegido). Por cada componente valida la variante elegida
     * con el mismo criterio que un producto suelto (PricingService::resolverVariante):
     * en las vistas ($exigirVariante=false) una selección irresoluble descarta el
     * combo entero en silencio; en el checkout ($exigirVariante=true) propaga la
     * excepción para abortar el pedido.
     *
     * @param  array{line_key: string, combo_id: int, quantity: int, component_selections: array<int, array{product_id: int, variant_id: int|null, custom_color_text: string|null}>}  $line
     */
    private function buildComboItem(array $line, bool $exigirVariante): ?array
    {
        $combo = Combo::with(['items.product.variantsActive', 'images'])->find($line['combo_id']);

        if (! $combo) {
            return null;
        }

        $selectionByProduct = collect($line['component_selections'])->keyBy('product_id');

        $components = [];
        $componentSelections = [];

        foreach ($combo->items as $comboItem) {
            $product = $comboItem->product;

            if (! $product) {
                // Producto del combo borrado: el combo ya no se puede armar.
                if ($exigirVariante) {
                    throw VarianteRequeridaException::faltante($line['combo_id']);
                }

                return null;
            }

            $sel = $selectionByProduct->get($product->id);
            $variantId = $sel['variant_id'] ?? null;

            try {
                $variante = $this->pricingService->resolverVariante($product, $variantId, $exigirVariante);
            } catch (VarianteRequeridaException $e) {
                if ($exigirVariante) {
                    throw $e;
                }

                return null;
            }

            $customColorText = $variante && $variante->is_custom_color
                ? ($sel['custom_color_text'] ?? null)
                : null;

            $components[] = [
                'product_id' => $product->id,
                'product_title' => $product->title,
                'quantity' => (int) $comboItem->quantity,
                'variant' => $variante ? [
                    'id' => $variante->id,
                    'name' => $variante->name,
                    'color_hex' => $variante->color_hex,
                    'is_custom_color' => (bool) $variante->is_custom_color,
                ] : null,
                'custom_color_text' => $customColorText,
            ];

            $componentSelections[] = [
                'product_id' => $product->id,
                'product_variant_id' => $variante?->id,
                'quantity' => (int) $comboItem->quantity,
                'product_title' => $product->title,
                'variant_name' => $variante?->name,
                'variant_color_hex' => $variante?->color_hex,
                'custom_color_text' => $customColorText,
            ];
        }

        $quantity = (int) $line['quantity'];
        $price = round((float) $combo->price, 2);
        $primaryImage = $combo->primaryImage();

        return [
            'id' => $line['line_key'],
            'line_key' => $line['line_key'],
            'is_combo' => true,
            'combo' => [
                'id' => $combo->id,
                'title' => $combo->title,
                'is_free_shipping' => (bool) $combo->is_free_shipping,
                'image' => $primaryImage?->url,
            ],
            // Campos que las vistas del carrito comparten con las líneas de
            // producto; en un combo no aplican recargos ni ahorros.
            'product' => null,
            'variant' => null,
            'custom_color_text' => null,
            'addons' => [],
            'quantity' => $quantity,
            'price' => $price,
            'list_price' => $price,
            'unit_savings' => 0.0,
            'savings_percentage' => 0.0,
            'unit_price' => $price,
            'variant_surcharge' => 0.0,
            'addons_total' => 0.0,
            'subtotal' => round($quantity * $price, 2),
            // Detalle para la UI (colores elegidos por componente).
            'components' => $components,
            // Snapshot crudo para descontar stock y guardar en order_items.
            'component_selections' => $componentSelections,
        ];
    }

    /**
     * Revalida que cada producto del combo con variantes activas tenga un color
     * elegido, y que la variante "a elección del cliente" traiga su color libre.
     * Mismo criterio que validarOpcionesObligatorias() para productos sueltos.
     * Devuelve el mensaje del primer hueco (nombrando producto y combo) o null.
     *
     * @param  array{combo_id: int, component_selections: array<int, array{product_id: int, variant_id: int|null, custom_color_text: string|null}>}  $line
     */
    private function validarOpcionesComboObligatorias(array $line): ?string
    {
        $combo = Combo::with(['items.product.variantsActive'])->find($line['combo_id']);

        if (! $combo) {
            // Combo borrado: items() lo descarta, mismo criterio.
            return null;
        }

        $selByProduct = collect($line['component_selections'])->keyBy('product_id');

        foreach ($combo->items as $comboItem) {
            $product = $comboItem->product;

            if (! $product) {
                continue;
            }

            $variantesActivas = $product->variantsActive;
            $sel = $selByProduct->get($product->id);
            $variantId = $sel['variant_id'] ?? null;

            if ($variantesActivas->isNotEmpty() && $variantId === null) {
                return "Elegí un color para «{$product->title}» en el combo «{$combo->title}».";
            }

            $variante = $variantId ? $variantesActivas->firstWhere('id', $variantId) : null;

            if ($variante && $variante->is_custom_color && trim((string) ($sel['custom_color_text'] ?? '')) === '') {
                return "Indicá el color que querés para «{$product->title}» en el combo «{$combo->title}».";
            }
        }

        return null;
    }
}
