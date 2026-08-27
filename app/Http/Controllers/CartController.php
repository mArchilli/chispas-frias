<?php

namespace App\Http\Controllers;

use App\Exceptions\DiscountCodeInvalidoException;
use App\Exceptions\StockInsuficienteException;
use App\Exceptions\VarianteRequeridaException;
use App\Models\Addon;
use App\Models\Order;
use App\Models\Product;
use App\Models\Setting;
use App\Services\DiscountCodeService;
use App\Services\PricingService;
use App\Services\StockService;
use App\Support\Provincias;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class CartController extends Controller
{
    public function __construct(
        private readonly PricingService $pricingService,
        private readonly DiscountCodeService $discountCodeService
    ) {}

    /**
     * Normaliza el carrito de sesión al shape actual: un array de líneas, cada una
     * con `line_key`, `product_id`, `quantity`, `variant_id` (nullable),
     * `addon_selections` (array de {addon_id, custom_text}) y `custom_color_text`
     * (nullable). Dos líneas del mismo producto con distinto color o distintos
     * add-ons conviven sin sumarse; sólo se suma la cantidad cuando el `line_key`
     * coincide (mismo producto + misma variante + mismos add-ons + mismos textos).
     *
     * Acepta también el shape viejo (`{product_id: cantidad}`): un carrito abierto
     * de antes del deploy se lee como líneas sin variante ni add-ons en vez de
     * romper. El `line_key` se recalcula siempre a partir del contenido, así que
     * nunca se confía en uno guardado.
     *
     * @return array<int, array{line_key: string, product_id: int, quantity: int, variant_id: int|null, addon_selections: array<int, array{addon_id: int, custom_text: string|null}>, custom_color_text: string|null}>
     */
    private function normalizeCart(mixed $raw): array
    {
        if (! is_array($raw)) {
            return [];
        }

        $lines = [];

        foreach ($raw as $key => $value) {
            // Shape viejo: [productId => cantidad]
            if (is_int($value) || is_string($value)) {
                $productId = (int) $key;
                $quantity = (int) $value;

                if ($productId > 0 && $quantity > 0) {
                    $this->pushLine($lines, $productId, $quantity, null, [], null);
                }

                continue;
            }

            if (! is_array($value)) {
                continue;
            }

            $productId = (int) ($value['product_id'] ?? 0);
            $quantity = (int) ($value['quantity'] ?? 0);

            if ($productId <= 0 || $quantity <= 0) {
                continue;
            }

            $variantId = isset($value['variant_id']) && (int) $value['variant_id'] > 0
                ? (int) $value['variant_id']
                : null;

            $customColorText = isset($value['custom_color_text']) && trim((string) $value['custom_color_text']) !== ''
                ? (string) $value['custom_color_text']
                : null;

            $this->pushLine(
                $lines,
                $productId,
                $quantity,
                $variantId,
                $this->normalizeAddonSelections($value['addon_selections'] ?? []),
                $customColorText,
            );
        }

        return array_values($lines);
    }

    /**
     * Agrega una línea al acumulador, sumando la cantidad si ya hay otra con el
     * mismo `line_key` (defensivo: `add()` ya lo garantiza, pero un carrito viejo
     * podría traer dos entradas del mismo producto).
     *
     * @param  array<string, array<string, mixed>>  $lines
     * @param  array<int, array{addon_id: int, custom_text: string|null}>  $addonSelections
     */
    private function pushLine(array &$lines, int $productId, int $quantity, ?int $variantId, array $addonSelections, ?string $customColorText): void
    {
        $lineKey = $this->lineKey($productId, $variantId, $addonSelections, $customColorText);

        if (isset($lines[$lineKey])) {
            $lines[$lineKey]['quantity'] += $quantity;

            return;
        }

        $lines[$lineKey] = [
            'line_key' => $lineKey,
            'product_id' => $productId,
            'quantity' => $quantity,
            'variant_id' => $variantId,
            'addon_selections' => $addonSelections,
            'custom_color_text' => $customColorText,
        ];
    }

    /**
     * Limpia y ordena las selecciones de add-ons de una línea: ids positivos,
     * sin duplicados, texto vacío => null. El orden no importa para el `line_key`
     * (que ordena por addon_id), pero se deja estable para el snapshot.
     *
     * @return array<int, array{addon_id: int, custom_text: string|null}>
     */
    private function normalizeAddonSelections(mixed $raw): array
    {
        if (! is_array($raw)) {
            return [];
        }

        $selections = [];

        foreach ($raw as $sel) {
            if (! is_array($sel)) {
                continue;
            }

            $addonId = (int) ($sel['addon_id'] ?? 0);

            if ($addonId <= 0 || isset($selections[$addonId])) {
                continue;
            }

            $text = $sel['custom_text'] ?? null;
            $text = is_string($text) && trim($text) !== '' ? trim($text) : null;

            $selections[$addonId] = ['addon_id' => $addonId, 'custom_text' => $text];
        }

        ksort($selections);

        return array_values($selections);
    }

    /**
     * Hash estable que identifica una línea del carrito. Dos líneas con el mismo
     * `line_key` son "el mismo ítem" y suman cantidad; con `line_key` distinto
     * conviven por separado. Se calcula sobre producto + variante + add-ons
     * (ordenados) + textos de personalización + color libre.
     *
     * @param  array<int, array{addon_id: int, custom_text: string|null}>  $addonSelections
     */
    private function lineKey(int $productId, ?int $variantId, array $addonSelections, ?string $customColorText): string
    {
        $addons = collect($addonSelections)
            ->map(fn ($sel) => [
                'addon_id' => (int) $sel['addon_id'],
                'custom_text' => $sel['custom_text'] ?? null,
            ])
            ->sortBy('addon_id')
            ->values()
            ->all();

        return hash('sha256', json_encode([
            'product_id' => $productId,
            'variant_id' => $variantId,
            'addons' => $addons,
            'custom_color_text' => $customColorText,
        ]));
    }

    /**
     * Obtener items del carrito desde sesión. El precio de cada línea se resuelve
     * con PricingService según la cantidad real pedida (tier + oferta) y las
     * opciones elegidas (recargo de variante + costo de add-ons), nunca con un
     * precio plano por producto.
     *
     * `$exigirVariante` se propaga a PricingService: las vistas del carrito lo
     * dejan en false (una línea con opciones irresolubles se descarta en
     * silencio, mismo criterio que un producto borrado); el checkout lo pasa en
     * true y ahí una opción faltante o inválida propaga la excepción para abortar
     * el pedido entero.
     */
    private function getCartItems(bool $exigirVariante = false)
    {
        $cart = $this->normalizeCart(session('cart', []));
        $cartItems = collect();

        foreach ($cart as $line) {
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
                ] : null,
                'custom_color_text' => $line['custom_color_text'],
                'addons' => $this->mapLineAddons($priceResult->addonsAplicados, $line['addon_selections']),
            ]);
        }

        return $cartItems;
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
     * Revalida, línea por línea del carrito de sesión, que las opciones
     * obligatorias de cada producto estén completas ANTES de calcular precio o
     * tocar stock en el checkout:
     *
     *  1. producto con variantes activas y la línea sin `variant_id`;
     *  2. variante "a elección del cliente" sin `custom_color_text`;
     *  3. add-on elegido con `requires_text` cuyo texto llegó vacío.
     *
     * Devuelve el mensaje del primer hueco encontrado (nombrando el producto) o
     * null si todas las líneas están completas. A diferencia de getCartItems()
     * —que descarta en silencio una línea con opciones irresolubles, igual que un
     * producto borrado, porque alimenta vistas efímeras— acá cualquier hueco
     * tiene que abortar el pedido entero.
     *
     * @param  array<int, array{product_id: int, quantity: int, variant_id: int|null, addon_selections: array<int, array{addon_id: int, custom_text: string|null}>, custom_color_text: string|null}>  $cart
     */
    private function validarOpcionesObligatorias(array $cart): ?string
    {
        foreach ($cart as $line) {
            $product = Product::with(['variantsActive', 'addonsActive'])->find($line['product_id']);

            if (! $product) {
                // Producto borrado: getCartItems() lo descarta, mismo criterio.
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
     * Calcular total del carrito
     */
    private function getCartTotal($cartItems)
    {
        return $cartItems->sum('subtotal');
    }

    /**
     * Obtener cantidad total de items en el carrito
     */
    private function getCartCount()
    {
        return collect($this->normalizeCart(session('cart', [])))->sum('quantity');
    }

    /**
     * Respuesta homogénea para las operaciones del carrito: JSON cuando la llamada
     * lo espera (fetch/axios), redirect con flash cuando es una navegación Inertia.
     *
     * @param  array<string, mixed>  $extra
     */
    private function cartResponse(Request $request, bool $success, string $message, int $status = 200, array $extra = []): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json(array_merge(['success' => $success, 'message' => $message], $extra), $status);
        }

        return back()->with($success ? 'success' : 'error', $message);
    }

    /**
     * Mostrar el carrito del usuario
     */
    public function index(): Response
    {
        $cartItems = $this->getCartItems();
        $subtotal = $this->getCartTotal($cartItems);
        $discountInfo = $this->resolveDiscountCode($subtotal);

        return Inertia::render('Cart/Index', [
            'cartItems' => $cartItems,
            'subtotal' => $subtotal,
            'total' => round($subtotal - ($discountInfo['discountCode']['amount'] ?? 0), 2),
            'discountCode' => $discountInfo['discountCode'],
            'discountCodeRemovedReason' => $discountInfo['discountCodeRemovedReason'],
            'freeShippingThreshold' => Setting::get('free_shipping_threshold'),
        ]);
    }

    /**
     * Aplicar un código de descuento al carrito. Sólo se persiste el texto del
     * código en sesión (`cart_discount_code`); el monto se recalcula siempre
     * contra la DB, igual que el carrito nunca confía en precios de sesión.
     */
    public function applyDiscountCode(Request $request): RedirectResponse|JsonResponse
    {
        $request->validate([
            'code' => 'required|string|max:50',
        ]);

        $cartItems = $this->getCartItems();
        $subtotal = $this->getCartTotal($cartItems);

        try {
            $discountCode = $this->discountCodeService->buscarValido($request->code, $subtotal);
        } catch (DiscountCodeInvalidoException $e) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                ], 422);
            }

            return back()->with('error', $e->getMessage());
        }

        session(['cart_discount_code' => $discountCode->code]);

        $amount = $this->discountCodeService->calcularDescuento($discountCode, $subtotal);
        $message = 'Código de descuento aplicado.';

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'discountCode' => [
                    'code' => $discountCode->code,
                    'percentage' => (float) $discountCode->percentage,
                    'amount' => $amount,
                ],
                'subtotal' => $subtotal,
                'total' => round($subtotal - $amount, 2),
            ]);
        }

        return back()->with('success', $message);
    }

    /**
     * Quitar el código de descuento aplicado al carrito.
     */
    public function removeDiscountCode(Request $request): RedirectResponse|JsonResponse
    {
        session()->forget('cart_discount_code');

        $message = 'Código de descuento quitado.';

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => $message,
            ]);
        }

        return back()->with('success', $message);
    }

    /**
     * Revalida contra la DB el código de descuento guardado en sesión (si hay
     * uno) para el subtotal actual del carrito. Si dejó de ser válido —
     * desactivado, vencido, agotado, o el carrito bajó del mínimo requerido—
     * lo quita silenciosamente de la sesión y devuelve el motivo para que el
     * frontend pueda avisarle al usuario.
     */
    private function resolveDiscountCode(float $subtotal): array
    {
        $code = session('cart_discount_code');

        if (! $code) {
            return ['discountCode' => null, 'discountCodeRemovedReason' => null];
        }

        try {
            $discountCode = $this->discountCodeService->buscarValido($code, $subtotal);
        } catch (DiscountCodeInvalidoException $e) {
            session()->forget('cart_discount_code');

            return ['discountCode' => null, 'discountCodeRemovedReason' => $e->getMessage()];
        }

        return [
            'discountCode' => [
                'code' => $discountCode->code,
                'percentage' => (float) $discountCode->percentage,
                'amount' => $this->discountCodeService->calcularDescuento($discountCode, $subtotal),
            ],
            'discountCodeRemovedReason' => null,
        ];
    }

    /**
     * Agregar un producto al carrito, con sus opciones (variante de color +
     * add-ons de personalización + color libre). La variante y los add-ons se
     * validan server-side contra el producto (pertenencia + activo) reutilizando
     * PricingService, antes de tocar la sesión. Si ya hay una línea con el mismo
     * `line_key` se suma la cantidad; si no, se crea una línea nueva.
     */
    public function add(Request $request): RedirectResponse|JsonResponse
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity' => 'nullable|integer|min:1|max:99',
            'variant_id' => 'nullable|integer',
            'addon_ids' => 'nullable|array',
            'addon_ids.*' => 'integer',
            'addon_texts' => 'nullable|array',
            'custom_color_text' => 'nullable|string|max:255',
        ]);

        $productId = (int) $request->product_id;
        $quantity = (int) ($request->quantity ?? 1);

        $product = Product::with(['currentOffer', 'variantsActive', 'addonsActive'])->findOrFail($productId);

        $variantId = $request->filled('variant_id') ? (int) $request->input('variant_id') : null;
        $addonIds = array_values(array_filter(array_map('intval', (array) $request->input('addon_ids', []))));

        // Validación de opciones contra el producto, con el mismo criterio que el
        // checkout: si el producto tiene variantes activas, elegir una es
        // obligatorio ya desde el carrito (así el checkout nunca encuentra una
        // línea sin color que lo haga fallar entero).
        try {
            $variante = $this->pricingService->resolverVariante($product, $variantId, exigirVariante: true);
            $addons = $this->pricingService->resolverAddons($product, $addonIds);
        } catch (VarianteRequeridaException $e) {
            $message = $e->varianteId === null && $e->addonId === null
                ? 'Elegí el color y las personalizaciones en la página del producto.'
                : 'Una de las opciones elegidas ya no está disponible. Actualizá la página del producto.';

            return $this->cartResponse($request, false, $message, 422);
        }

        // Textos de personalización de cada add-on elegido. Un add-on con
        // `requires_text` no puede quedar sin texto.
        $addonTexts = (array) $request->input('addon_texts', []);
        $addonSelections = [];

        foreach ($addons as $addon) {
            $raw = $addonTexts[$addon->id] ?? $addonTexts[(string) $addon->id] ?? null;
            $text = is_string($raw) ? trim($raw) : '';

            if ($text === '' && $addon->requires_text) {
                return $this->cartResponse($request, false, "Completá el texto de «{$addon->name}» para agregar el producto.", 422);
            }

            if ($text !== '' && $addon->max_characters) {
                $text = mb_substr($text, 0, (int) $addon->max_characters);
            }

            $addonSelections[] = [
                'addon_id' => $addon->id,
                'custom_text' => $text === '' ? null : $text,
            ];
        }

        // Color libre: sólo aplica (y es obligatorio) cuando la variante elegida
        // es "a elección del cliente".
        $customColorText = null;

        if ($variante && $variante->is_custom_color) {
            $raw = $request->input('custom_color_text');
            $customColorText = is_string($raw) ? trim($raw) : '';

            if ($customColorText === '') {
                return $this->cartResponse($request, false, 'Indicá el color que querés para este producto.', 422);
            }

            $customColorText = mb_substr($customColorText, 0, 255);
        }

        $variantId = $variante?->id;
        $lineKey = $this->lineKey($productId, $variantId, $addonSelections, $customColorText);

        $cart = $this->normalizeCart(session('cart', []));
        $existingIndex = collect($cart)->search(fn ($line) => $line['line_key'] === $lineKey);

        $currentQuantity = $existingIndex !== false ? $cart[$existingIndex]['quantity'] : 0;
        $newQuantity = $currentQuantity + $quantity;

        // Stock de la variante elegida (si maneja stock finito) o del producto.
        $stockDisponible = $variante && ! $variante->tieneStockIlimitado()
            ? (int) $variante->stock
            : (int) $product->stock;

        if ($stockDisponible < $newQuantity) {
            $message = $currentQuantity > 0
                ? 'No hay suficiente stock para esta cantidad.'
                : 'No hay suficiente stock disponible.';

            return $this->cartResponse($request, false, $message, 422);
        }

        if ($existingIndex !== false) {
            $cart[$existingIndex]['quantity'] = $newQuantity;
        } else {
            $cart[] = [
                'line_key' => $lineKey,
                'product_id' => $productId,
                'quantity' => $newQuantity,
                'variant_id' => $variantId,
                'addon_selections' => $addonSelections,
                'custom_color_text' => $customColorText,
            ];
        }

        session(['cart' => array_values($cart)]);

        $message = $currentQuantity > 0
            ? 'Cantidad actualizada en el carrito.'
            : 'Producto agregado al carrito.';

        return $this->cartResponse($request, true, $message, 200, [
            'cartCount' => $this->getCartCount(),
        ]);
    }

    /**
     * Actualizar la cantidad de una línea del carrito. Opera por `line_key`, no
     * por `product_id`: ahora puede haber más de una línea del mismo producto.
     */
    public function update(Request $request): RedirectResponse|JsonResponse
    {
        $request->validate([
            'line_key' => 'required|string',
            'quantity' => 'required|integer|min:1|max:99',
        ]);

        $lineKey = $request->line_key;
        $quantity = (int) $request->quantity;

        $cart = $this->normalizeCart(session('cart', []));
        $index = collect($cart)->search(fn ($line) => $line['line_key'] === $lineKey);

        if ($index === false) {
            return $this->cartResponse($request, false, 'Item no encontrado en el carrito.', 404);
        }

        $line = $cart[$index];
        $product = Product::with(['currentOffer', 'variantsActive', 'addonsActive'])->find($line['product_id']);

        if (! $product) {
            return $this->cartResponse($request, false, 'Item no encontrado en el carrito.', 404);
        }

        $variante = $line['variant_id']
            ? $product->variantsActive->firstWhere('id', $line['variant_id'])
            : null;

        $stockDisponible = $variante && $variante->stock !== null
            ? (int) $variante->stock
            : (int) $product->stock;

        if ($stockDisponible < $quantity) {
            return $this->cartResponse($request, false, 'No hay suficiente stock disponible.', 422);
        }

        $cart[$index]['quantity'] = $quantity;
        session(['cart' => array_values($cart)]);

        if ($request->expectsJson()) {
            $addonIds = array_map(fn ($sel) => $sel['addon_id'], $line['addon_selections']);

            try {
                $unitPrice = $this->pricingService
                    ->calcularPrecio($product, $quantity, $line['variant_id'], $addonIds)
                    ->precioFinalConOpciones;
            } catch (VarianteRequeridaException) {
                $unitPrice = 0.0;
            }

            return response()->json([
                'success' => true,
                'message' => 'Cantidad actualizada.',
                'subtotal' => round($quantity * $unitPrice, 2),
                'cartCount' => $this->getCartCount(),
            ]);
        }

        return back()->with('success', 'Cantidad actualizada.');
    }

    /**
     * Eliminar una línea del carrito, por `line_key`.
     */
    public function remove(Request $request): RedirectResponse|JsonResponse
    {
        $request->validate([
            'line_key' => 'required|string',
        ]);

        $cart = $this->normalizeCart(session('cart', []));
        $index = collect($cart)->search(fn ($line) => $line['line_key'] === $request->line_key);

        if ($index === false) {
            return $this->cartResponse($request, false, 'Item no encontrado en el carrito.', 404);
        }

        unset($cart[$index]);
        session(['cart' => array_values($cart)]);

        return $this->cartResponse($request, true, 'Producto eliminado del carrito.', 200, [
            'cartCount' => $this->getCartCount(),
        ]);
    }

    /**
     * Vaciar todo el carrito
     */
    public function clear(): RedirectResponse|JsonResponse
    {
        session()->forget('cart');

        $message = 'Carrito vaciado.';

        if (request()->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'cartCount' => 0,
            ]);
        }

        return back()->with('success', $message);
    }

    /**
     * Obtener el número de items en el carrito
     */
    public function count(): JsonResponse
    {
        $count = $this->getCartCount();

        return response()->json(['count' => $count]);
    }

    /**
     * Mostrar página de checkout
     */
    public function checkout(): Response|RedirectResponse
    {
        $cartItems = $this->getCartItems();

        if ($cartItems->isEmpty()) {
            return redirect()->route('cart.index')
                ->with('error', 'No puedes proceder al checkout con el carrito vacío.');
        }

        $subtotal = $this->getCartTotal($cartItems);
        $discountInfo = $this->resolveDiscountCode($subtotal);

        return Inertia::render('Cart/Checkout', [
            'cartItems' => $cartItems,
            'subtotal' => $subtotal,
            'total' => round($subtotal - ($discountInfo['discountCode']['amount'] ?? 0), 2),
            'discountCode' => $discountInfo['discountCode'],
            'discountCodeRemovedReason' => $discountInfo['discountCodeRemovedReason'],
            'provinces' => Provincias::all(),
            'freeShippingThreshold' => Setting::get('free_shipping_threshold'),
        ]);
    }

    /**
     * Generar mensaje para WhatsApp
     */
    public function generateWhatsAppMessage(Request $request, StockService $stockService): JsonResponse
    {
        $cart = $this->normalizeCart(session('cart', []));

        if ($cart === []) {
            return response()->json([
                'success' => false,
                'message' => 'El carrito está vacío.',
            ], 422);
        }

        // Defensa en profundidad ANTES de calcular precio o tocar stock: si una
        // línea tiene opciones obligatorias incompletas (falta color, color libre
        // vacío, add-on con texto obligatorio sin texto) se aborta el pedido
        // entero nombrando el producto. getCartItems() por sí solo descartaría la
        // línea en silencio, aceptable para las vistas del carrito pero no para
        // crear la orden.
        $faltanOpciones = $this->validarOpcionesObligatorias($cart);

        if ($faltanOpciones !== null) {
            return response()->json([
                'success' => false,
                'message' => $faltanOpciones,
            ], 422);
        }

        // `exigirVariante: true` — redundante con el chequeo de arriba, como
        // segunda barrera dentro de PricingService; además cubre la variante o el
        // add-on que se desactivó entre que se armó el carrito y este submit.
        try {
            $cartItems = $this->getCartItems(exigirVariante: true);
        } catch (VarianteRequeridaException) {
            return response()->json([
                'success' => false,
                'message' => 'Algunas opciones de tu carrito ya no están disponibles. Actualizá el carrito e intentá nuevamente.',
            ], 422);
        }

        if ($cartItems->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'El carrito está vacío.',
            ], 422);
        }

        // Validar datos del formulario. Antes esta validación solo corría si
        // "customer_data" venía en el payload; ahora es obligatoria porque los
        // campos de contacto se persisten en `orders` (columnas NOT NULL). El
        // formulario de checkout ya envía siempre customer_data, así que esto
        // no cambia el comportamiento real para el único cliente existente.
        $request->validate([
            'customer_data' => 'required|array',
            'customer_data.name' => 'required|string|max:100',
            'customer_data.lastname' => 'required|string|max:100',
            'customer_data.dni' => 'required|string|max:20',
            'customer_data.province' => 'required|string|max:100',
            'customer_data.city' => 'required|string|max:100',
            'customer_data.postal_code' => 'required|string|max:20',
            'customer_data.phone' => 'required|string|max:30',
            'customer_data.email' => 'required|email|max:150',
            'customer_data.observations' => 'nullable|string|max:500',
        ]);

        $customerData = $request->customer_data;

        // Chequeo optimista de stock, antes de abrir la transacción de creación de
        // la orden. El chequeo definitivo (bajo lock) pasa dentro de
        // StockService::descontar(), ya con los OrderItem persistidos. Cuando la
        // línea tiene variante, el chequeo va contra el stock de la variante.
        $stockItems = $cartItems->map(fn ($item) => [
            'product_id' => $item['product']->id,
            'product_variant_id' => $item['variant']['id'] ?? null,
            'cantidad' => $item['quantity'],
        ])->all();

        $faltantes = $stockService->validarDisponibilidad($stockItems);

        if (! empty($faltantes)) {
            return response()->json([
                'success' => false,
                'message' => 'Algunos productos de tu carrito ya no tienen stock suficiente.',
                'stock_insuficiente' => $this->mapStockInsuficiente($faltantes, $cartItems),
            ], 422);
        }

        $subtotal = $this->getCartTotal($cartItems);

        // Chequeo optimista del código de descuento (si hay uno en sesión), antes
        // de abrir la transacción — mismo criterio que validarDisponibilidad() con
        // el stock. El chequeo definitivo (bajo lock) pasa dentro de
        // DiscountCodeService::registrarUso(), ya dentro de la transacción.
        $discountCode = null;
        $discountAmount = 0.0;
        $sessionDiscountCode = session('cart_discount_code');

        if ($sessionDiscountCode) {
            try {
                $discountCode = $this->discountCodeService->buscarValido($sessionDiscountCode, $subtotal);
                $discountAmount = $this->discountCodeService->calcularDescuento($discountCode, $subtotal);
            } catch (DiscountCodeInvalidoException $e) {
                session()->forget('cart_discount_code');

                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage().' Lo quitamos de tu carrito, por favor reintentá.',
                ], 422);
            }
        }

        $total = round($subtotal - $discountAmount, 2);

        $freeShippingThreshold = Setting::get('free_shipping_threshold');

        $message = "🛒 *NUEVO PEDIDO DE LA WEB*\n\n";

        $message .= "👤 *Datos del Cliente:*\n";
        $message .= "Nombre: {$customerData['name']} {$customerData['lastname']}\n";
        $message .= "DNI: {$customerData['dni']}\n";
        $message .= "Teléfono: {$customerData['phone']}\n";
        $message .= "Email: {$customerData['email']}\n\n";

        $message .= "📍 *Envío a Sucursal:*\n";
        $message .= "Provincia: {$customerData['province']}\n";
        $message .= "Ciudad: {$customerData['city']}\n";
        $message .= "Código Postal: {$customerData['postal_code']}\n\n";

        // Agregar observaciones si existen
        if (! empty($customerData['observations'])) {
            $message .= "📝 *Observaciones:*\n";
            $message .= "{$customerData['observations']}\n\n";
        }

        $message .= "📋 *Detalle del pedido:*\n";

        foreach ($cartItems as $item) {
            $product = $item['product'];
            $currentPrice = $item['price'];

            $message .= "• {$product['title']}\n";

            // Color elegido. Con una variante fija: "Color: {nombre}". Con la
            // variante "a elección del cliente": "Color solicitado: {color libre}".
            if (! empty($item['variant'])) {
                if (! empty($item['variant']['is_custom_color'])) {
                    $colorLibre = $item['custom_color_text'] ?: $item['variant']['name'];
                    $message .= "  Color solicitado: {$colorLibre}\n";
                } else {
                    $message .= "  Color: {$item['variant']['name']}\n";
                }

                if ($item['variant_surcharge'] > 0) {
                    $message .= '  Recargo color: +$'.number_format($item['variant_surcharge'], 0, ',', '.')."\n";
                }
            } elseif (! empty($item['custom_color_text'])) {
                $message .= "  Color solicitado: {$item['custom_color_text']}\n";
            }

            // Add-ons de personalización: una línea por add-on, con el texto
            // ingresado ("{nombre}: \"{texto}\"") y su costo cuando lo tiene.
            foreach ($item['addons'] as $addon) {
                $linea = "  {$addon['name']}";

                if (! empty($addon['custom_text'])) {
                    $linea .= ": \"{$addon['custom_text']}\"";
                }

                if ($addon['price'] > 0) {
                    $linea .= ' (+$'.number_format($addon['price'], 0, ',', '.').')';
                }

                $message .= $linea."\n";
            }

            $message .= "  Cantidad: {$item['quantity']}\n";

            // Precio original (de lista o de tier, según la cantidad) vs. final
            // (con oferta aplicada), si hubo algún ahorro; si no, solo el precio.
            if ($item['unit_savings'] > 0) {
                $message .= '  Precio original: $'.number_format($item['list_price'], 0, ',', '.')."\n";
                $message .= '  Precio final: $'.number_format($currentPrice, 0, ',', '.')."\n";
                $message .= "  ¡Ahorrás {$item['savings_percentage']}%!\n";
            } else {
                $message .= '  Precio: $'.number_format($currentPrice, 0, ',', '.')."\n";
            }

            // Cuando hay recargo de variante o add-ons, el precio unitario real
            // no es el de arriba (que es solo el base): se aclara aparte.
            if ($item['variant_surcharge'] > 0 || $item['addons_total'] > 0) {
                $message .= '  Precio unitario con opciones: $'.number_format($item['unit_price'], 0, ',', '.')."\n";
            }

            $message .= '  Subtotal: $'.number_format($item['subtotal'], 0, ',', '.')."\n\n";
        }

        // Línea de descuento por código, en el mismo espíritu que el desglose de
        // precio original vs. final que ya se muestra por oferta a nivel de item.
        if ($discountCode) {
            $message .= "🏷️ *Código de descuento: {$discountCode->code}*\n";
            $message .= '  Subtotal: $'.number_format($subtotal, 0, ',', '.')."\n";
            $message .= '  Descuento ('.(float) $discountCode->percentage.'%): -$'.number_format($discountAmount, 0, ',', '.')."\n\n";
        }

        if ((float) ($freeShippingThreshold ?? 0) > 0) {
            if ($subtotal >= $freeShippingThreshold) {
                $message .= "🚚 *¡Envío gratis alcanzado!*\n\n";
            } else {
                $faltante = $freeShippingThreshold - $subtotal;
                $message .= '🚚 Le faltan $'.number_format($faltante, 0, ',', '.')." para alcanzar el envío gratis.\n\n";
            }
        }

        $message .= '💰 *TOTAL: $'.number_format($total, 0, ',', '.').'*';

        $orderId = null;

        try {
            DB::transaction(function () use ($request, $customerData, $cartItems, $subtotal, $discountAmount, $total, $discountCode, $message, &$orderId, $stockService) {
                $order = new Order([
                    'name' => $customerData['name'],
                    'lastname' => $customerData['lastname'],
                    'dni' => $customerData['dni'],
                    'province' => $customerData['province'],
                    'city' => $customerData['city'],
                    'postal_code' => $customerData['postal_code'],
                    'phone' => $customerData['phone'],
                    'email' => $customerData['email'],
                    'observations' => $customerData['observations'] ?? null,
                    'discount_code_id' => $discountCode?->id,
                    'discount_code' => $discountCode?->code,
                    'subtotal' => $subtotal,
                    'discount_amount' => $discountAmount,
                    'total' => $total,
                    'mensaje_whatsapp' => $message,
                ]);

                if ($request->user()) {
                    $order->user()->associate($request->user());
                }

                $order->save();

                foreach ($cartItems as $item) {
                    $order->items()->create([
                        'product_id' => $item['product']->id,
                        'product_variant_id' => $item['variant']['id'] ?? null,
                        'product_title' => $item['product']->title,
                        // Snapshots que sobreviven si la variante / add-on se borra.
                        'variant_name' => $item['variant']['name'] ?? null,
                        'variant_color_hex' => $item['variant']['color_hex'] ?? null,
                        'variant_price_addon' => $item['variant_surcharge'],
                        'custom_color_text' => $item['custom_color_text'],
                        'addons_selected' => $item['addons'] !== [] ? $item['addons'] : null,
                        'addons_total' => $item['addons_total'],
                        'cantidad' => $item['quantity'],
                        // precio_unitario = precio FINAL con recargo de variante y
                        // add-ons incluidos; base_unit_price = sin recargos.
                        'precio_unitario' => $item['unit_price'],
                        'base_unit_price' => $item['price'],
                        'subtotal' => $item['subtotal'],
                    ]);
                }

                $stockService->descontar($order);

                // Revalidación definitiva bajo lock: si el código se agotó por una
                // carrera con otro checkout concurrente, esto lanza y aborta toda
                // la transacción (orden, items y descuento de stock incluidos).
                if ($discountCode) {
                    $this->discountCodeService->registrarUso($discountCode);
                }

                $orderId = $order->id;
            });
        } catch (StockInsuficienteException $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Algunos productos de tu carrito ya no tienen stock suficiente.',
                'stock_insuficiente' => $this->mapStockInsuficiente([
                    [
                        'product_id' => $e->productId,
                        'product_variant_id' => null,
                        'cantidad' => $e->cantidadSolicitada,
                        'stock_disponible' => $e->stockDisponible,
                    ],
                ], $cartItems),
            ], 422);
        } catch (DiscountCodeInvalidoException $e) {
            report($e);

            session()->forget('cart_discount_code');

            return response()->json([
                'success' => false,
                'message' => 'El código de descuento ya no está disponible: '.$e->getMessage().' Reintentá tu pedido sin el código.',
            ], 422);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'No pudimos registrar tu pedido. Por favor, intentá nuevamente.',
            ], 500);
        }

        // Vaciar el carrito una vez creada la orden y generado el mensaje
        session()->forget(['cart', 'cart_discount_code']);

        return response()->json([
            'success' => true,
            'message' => $message,
            'subtotal' => $subtotal,
            'discount_amount' => $discountAmount,
            'total' => $total,
            'itemCount' => $cartItems->count(),
            'order_id' => $orderId,
        ]);
    }

    /**
     * Enriquecer los faltantes de stock (`product_id`, `product_variant_id`,
     * `cantidad`, `stock_disponible`) con el título del producto, usando el
     * carrito ya resuelto en memoria en vez de volver a consultar la base.
     */
    private function mapStockInsuficiente(array $faltantes, $cartItems): array
    {
        return collect($faltantes)->map(function ($faltante) use ($cartItems) {
            $varianteId = $faltante['product_variant_id'] ?? null;

            $item = $cartItems->first(function ($i) use ($faltante, $varianteId) {
                if ($i['product']->id !== $faltante['product_id']) {
                    return false;
                }

                return $varianteId === null || ($i['variant']['id'] ?? null) === $varianteId;
            });

            return [
                'product_id' => $faltante['product_id'],
                'product_title' => $item['product']->title ?? null,
                'cantidad_solicitada' => $faltante['cantidad'],
                'stock_disponible' => $faltante['stock_disponible'],
            ];
        })->all();
    }
}
