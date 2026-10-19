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
 * combos de precio fijo, cupón de descuento, plan de pago con tarjeta, envío gratis por
 * combo, totales y las unidades de stock que el pedido consume.
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
 *  2. `aplicarDescuentoYPlan()` — cupón (lanza `DiscountCodeInvalidoException` si no vale),
 *     total y plan de tarjeta.
 *
 * Las vistas del carrito usan las piezas sueltas (`items()`, `subtotal()`,
 * `resolverCupon()`, `resolverPlan()`), que son las mismas que arman el pedido.
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
     * Etapa 2: cupón validado contra el subtotal (sin consumirlo), total y plan de tarjeta
     * sobre ese total. Un plan desactivado o borrado NO aborta: queda en `null` con su
     * `motivoPlanDescartado`.
     *
     * @param  array<string, mixed>|null  $snapshotPlan  Lo guardado por el carrito (`cart_payment_plan`).
     *
     * @throws DiscountCodeInvalidoException
     */
    public function aplicarDescuentoYPlan(CalculoPedido $calculo, ?string $codigo, ?array $snapshotPlan): CalculoPedido
    {
        $codigoDescuento = null;
        $descuento = 0.0;

        if ($codigo) {
            ['discountCode' => $codigoDescuento, 'amount' => $descuento] = $this->resolverCupon($codigo, (float) $calculo->subtotal);
        }

        $total = round($calculo->subtotal - $descuento, 2);
        $plan = $this->resolverPlan($snapshotPlan, $total);

        return $calculo->conDescuentoYPlan($codigoDescuento, $descuento, $total, $plan['paymentPlan'], $plan['paymentPlanRemovedReason']);
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
     * Revalida contra la base el plan de cuotas guardado por el carrito y calcula el recargo
     * informativo sobre `$total` (subtotal − descuento). Si el plan se desactivó o se borró
     * devuelve el motivo para que quien llama lo quite de la sesión; sin plan elegido, todo null.
     *
     * @param  array<string, mixed>|null  $snapshot
     * @return array{paymentPlan: array{id: int, name: string, installments: int, surcharge_percentage: float, surcharge_amount: float, total_with_surcharge: float, installment_amount: float}|null, paymentPlanRemovedReason: string|null}
     */
    public function resolverPlan(mixed $snapshot, float $total): array
    {
        if (! is_array($snapshot) || ! isset($snapshot['id'])) {
            return ['paymentPlan' => null, 'paymentPlanRemovedReason' => null];
        }

        $plan = CardPaymentPlan::active()->find((int) $snapshot['id']);

        if (! $plan) {
            return [
                'paymentPlan' => null,
                'paymentPlanRemovedReason' => 'La forma de pago que habías elegido ya no está disponible.',
            ];
        }

        $breakdown = $this->cardSurchargeService->calcular($total, $plan);

        return [
            'paymentPlan' => [
                'id' => $plan->id,
                'name' => $plan->name,
                'installments' => (int) $plan->installments,
                'surcharge_percentage' => (float) $plan->surcharge_percentage,
                'surcharge_amount' => $breakdown['surcharge_amount'],
                'total_with_surcharge' => $breakdown['total_with_surcharge'],
                'installment_amount' => $breakdown['installment_amount'],
            ],
            'paymentPlanRemovedReason' => null,
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
        $cartItems = collect();

        foreach ($lineas as $line) {
            // Línea de combo: se arma aparte (precio fijo, sin tiers/ofertas).
            if (isset($line['combo_id'])) {
                $comboItem = $this->buildComboItem($line, $exigirVariante);

                if ($comboItem !== null) {
                    $cartItems->push($comboItem);
                }

                continue;
            }

            $product = Product::with(['images', 'currentOffer', 'variantsActive', 'addonsActive'])
                ->find($line['product_id']);

            if (! $product) {
                continue;
            }

            $addonIds = array_map(fn ($sel) => $sel['addon_id'], $line['addon_selections']);

            try {
                $priceResult = $this->pricingService->calcularPrecio(
                    $product,
                    (int) $line['quantity'],
                    $line['variant_id'],
                    $addonIds,
                    exigirVariante: $exigirVariante,
                );
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
