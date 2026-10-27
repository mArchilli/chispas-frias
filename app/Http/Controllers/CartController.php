<?php

namespace App\Http\Controllers;

use App\Enums\TipoEntrega;
use App\Exceptions\DiscountCodeInvalidoException;
use App\Exceptions\StockInsuficienteException;
use App\Exceptions\VarianteRequeridaException;
use App\Models\Combo;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Setting;
use App\Rukoz\CheckoutCentral;
use App\Rukoz\DatosDeEnvio;
use App\Rukoz\ErroresDelCentral;
use App\Rukoz\EspejoStock;
use App\Rukoz\PedidoParaCentral;
use App\Services\CalculadoraPedido;
use App\Services\CalculoPedido;
use App\Services\DiscountCodeService;
use App\Services\PricingService;
use App\Services\StockService;
use App\Support\Provincias;
use App\Support\Stock;
use Carbon\Carbon;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Rukoz\CoreClient\Errores\CentralNoDisponibleException;
use Rukoz\CoreClient\Errores\RukozException;
use Rukoz\CoreClient\Errores\SinStockException;
use Rukoz\CoreClient\Facades\Rukoz;
use Inertia\Inertia;
use Inertia\Response;

class CartController extends Controller
{
    public function __construct(
        private readonly PricingService $pricingService,
        private readonly DiscountCodeService $discountCodeService,
        private readonly CalculadoraPedido $calculadora
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

            $quantity = (int) ($value['quantity'] ?? 0);

            if ($quantity <= 0) {
                continue;
            }

            // Línea de combo: lleva `combo_id` (y no `product_id`) más las
            // selecciones de color por componente. Se guarda por separado de las
            // líneas de producto, con su propio `line_key`.
            $comboId = isset($value['combo_id']) ? (int) $value['combo_id'] : 0;

            if ($comboId > 0) {
                $this->pushComboLine(
                    $lines,
                    $comboId,
                    $quantity,
                    $this->normalizeComponentSelections($value['component_selections'] ?? []),
                );

                continue;
            }

            $productId = (int) ($value['product_id'] ?? 0);

            if ($productId <= 0) {
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
     * Agrega (o suma cantidad a) una línea de combo. Análogo a pushLine pero la
     * identidad de la línea la da el combo + los colores elegidos por componente.
     *
     * @param  array<string, array<string, mixed>>  $lines
     * @param  array<int, array{product_id: int, variant_id: int|null, custom_color_text: string|null}>  $componentSelections
     */
    private function pushComboLine(array &$lines, int $comboId, int $quantity, array $componentSelections): void
    {
        $lineKey = $this->comboLineKey($comboId, $componentSelections);

        if (isset($lines[$lineKey])) {
            $lines[$lineKey]['quantity'] += $quantity;

            return;
        }

        $lines[$lineKey] = [
            'line_key' => $lineKey,
            'combo_id' => $comboId,
            'quantity' => $quantity,
            'component_selections' => $componentSelections,
        ];
    }

    /**
     * Limpia las selecciones de color por componente de una línea de combo: un
     * registro por producto componente con su variante elegida (o null) y el
     * color libre (sólo si la variante es "a elección del cliente"). Se ordena
     * por product_id para que el `line_key` sea estable.
     *
     * @return array<int, array{product_id: int, variant_id: int|null, custom_color_text: string|null}>
     */
    private function normalizeComponentSelections(mixed $raw): array
    {
        if (! is_array($raw)) {
            return [];
        }

        $selections = [];

        foreach ($raw as $sel) {
            if (! is_array($sel)) {
                continue;
            }

            $productId = (int) ($sel['product_id'] ?? 0);

            if ($productId <= 0 || isset($selections[$productId])) {
                continue;
            }

            $variantId = isset($sel['variant_id']) && (int) $sel['variant_id'] > 0
                ? (int) $sel['variant_id']
                : null;

            $customColorText = isset($sel['custom_color_text']) && trim((string) $sel['custom_color_text']) !== ''
                ? mb_substr(trim((string) $sel['custom_color_text']), 0, 255)
                : null;

            $selections[$productId] = [
                'product_id' => $productId,
                'variant_id' => $variantId,
                'custom_color_text' => $customColorText,
            ];
        }

        ksort($selections);

        return array_values($selections);
    }

    /**
     * Hash estable que identifica una línea de combo (combo + colores elegidos
     * por componente). Mismo criterio que lineKey para productos.
     *
     * @param  array<int, array{product_id: int, variant_id: int|null, custom_color_text: string|null}>  $componentSelections
     */
    private function comboLineKey(int $comboId, array $componentSelections): string
    {
        $components = collect($componentSelections)
            ->map(fn ($sel) => [
                'product_id' => (int) $sel['product_id'],
                'variant_id' => $sel['variant_id'] ?? null,
                'custom_color_text' => $sel['custom_color_text'] ?? null,
            ])
            ->sortBy('product_id')
            ->values()
            ->all();

        return hash('sha256', json_encode([
            'combo_id' => $comboId,
            'components' => $components,
        ]));
    }
    /**
     * Obtener items del carrito desde sesión. El precio de cada línea lo resuelve
     * CalculadoraPedido (PricingService según la cantidad real pedida: tier + oferta,
     * más las opciones elegidas), nunca con un precio plano por producto.
     *
     * `$exigirVariante`: las vistas del carrito lo dejan en false (una línea con
     * opciones irresolubles se descarta en silencio); el checkout lo pasa en true y
     * ahí una opción faltante o inválida aborta el pedido entero.
     */
    private function getCartItems(bool $exigirVariante = false)
    {
        return $this->calculadora->items($this->normalizeCart(session('cart', [])), $exigirVariante);
    }

    /**
     * Calcular total del carrito
     */
    private function getCartTotal($cartItems)
    {
        return $this->calculadora->subtotal($cartItems);
    }

    /**
     * True si el carrito incluye al menos un combo con envío gratis. Cuando lo
     * hay, el pedido califica para envío gratis sin importar el umbral global
     * (free_shipping_threshold): se muestra en el carrito/checkout y se aclara en
     * el mensaje de WhatsApp.
     */
    private function freeShippingByCombo($cartItems): bool
    {
        return $this->calculadora->envioGratisPorCombo($cartItems);
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
        $total = round($subtotal - ($discountInfo['discountCode']['amount'] ?? 0), 2);

        return Inertia::render('Cart/Index', [
            'cartItems' => $cartItems,
            'subtotal' => $subtotal,
            'total' => $total,
            'discountCode' => $discountInfo['discountCode'],
            'discountCodeRemovedReason' => $discountInfo['discountCodeRemovedReason'],
            // Cuotas con tarjeta, solo informativas (calculadas acá sobre el total con descuento).
            'cuotas' => $this->calculadora->infoCuotas($total),
            'freeShippingThreshold' => Setting::get('free_shipping_threshold'),
            'freeShippingByCombo' => $this->freeShippingByCombo($cartItems),
        ]);
    }

    /**
     * Datos actualizados para el carrito flotante.
     */
    public function preview(): JsonResponse
    {
        $cartItems = $this->getCartItems();
        $subtotal = $this->getCartTotal($cartItems);
        $discountInfo = $this->resolveDiscountCode($subtotal);
        $total = round($subtotal - ($discountInfo['discountCode']['amount'] ?? 0), 2);

        return response()->json([
            'cartItems' => $cartItems,
            'count' => $cartItems->sum('quantity'),
            'subtotal' => $subtotal,
            'total' => $total,
            'cuotas' => $this->calculadora->infoCuotas($total),
            'discountCode' => $discountInfo['discountCode'],
            'discountCodeRemovedReason' => $discountInfo['discountCodeRemovedReason'],
            'freeShippingThreshold' => Setting::get('free_shipping_threshold'),
            'freeShippingByCombo' => $this->freeShippingByCombo($cartItems),
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
            ['discountCode' => $discountCode, 'amount' => $amount] = $this->calculadora->resolverCupon($request->code, $subtotal);
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
            ['discountCode' => $discountCode, 'amount' => $amount] = $this->calculadora->resolverCupon($code, $subtotal);
        } catch (DiscountCodeInvalidoException $e) {
            session()->forget('cart_discount_code');

            return ['discountCode' => null, 'discountCodeRemovedReason' => $e->getMessage()];
        }

        return [
            'discountCode' => [
                'code' => $discountCode->code,
                'percentage' => (float) $discountCode->percentage,
                'amount' => $amount,
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
            'quantity' => 'nullable|integer|min:1',
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
            return $this->cartResponse(
                $request,
                false,
                $this->mensajeStock($product->title, $variante?->name, $stockDisponible, $currentQuantity),
                422
            );
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
     * Agregar un combo al carrito, con el color elegido para cada producto que
     * lo integra. El precio es fijo (el del combo); acá sólo se validan los
     * colores y el stock. Por cada componente con variantes activas elegir color
     * es obligatorio (mismo criterio que `add`), y la variante "a elección del
     * cliente" exige el color libre. Si ya hay una línea con el mismo `line_key`
     * (mismo combo + mismos colores) se suma la cantidad.
     */
    public function addCombo(Request $request): RedirectResponse|JsonResponse
    {
        $request->validate([
            'combo_id' => 'required|exists:combos,id',
            'quantity' => 'nullable|integer|min:1|max:99',
            'selections' => 'nullable|array',
            'selections.*.product_id' => 'required|integer',
            'selections.*.variant_id' => 'nullable|integer',
            'selections.*.custom_color_text' => 'nullable|string|max:255',
        ]);

        $comboId = (int) $request->combo_id;
        $quantity = (int) ($request->quantity ?? 1);

        $combo = Combo::with(['items.product.variantsActive'])->findOrFail($comboId);

        if (! $combo->is_active) {
            return $this->cartResponse($request, false, 'Este combo ya no está disponible.', 422);
        }

        if ($combo->items->isEmpty()) {
            return $this->cartResponse($request, false, 'Este combo no tiene productos configurados.', 422);
        }

        $selectionsInput = collect((array) $request->input('selections', []))
            ->filter(fn ($s) => is_array($s) && (int) ($s['product_id'] ?? 0) > 0)
            ->keyBy(fn ($s) => (int) $s['product_id']);

        $componentSelections = [];

        foreach ($combo->items as $comboItem) {
            $product = $comboItem->product;

            if (! $product) {
                return $this->cartResponse($request, false, 'Uno de los productos del combo ya no está disponible.', 422);
            }

            $sel = $selectionsInput->get($product->id);
            $variantId = $sel && ! empty($sel['variant_id']) ? (int) $sel['variant_id'] : null;

            try {
                $variante = $this->pricingService->resolverVariante($product, $variantId, exigirVariante: true);
            } catch (VarianteRequeridaException $e) {
                $message = $e->varianteId === null
                    ? "Elegí el color de «{$product->title}» dentro del combo."
                    : "El color elegido para «{$product->title}» ya no está disponible. Actualizá la página.";

                return $this->cartResponse($request, false, $message, 422);
            }

            $customColorText = null;

            if ($variante && $variante->is_custom_color) {
                $raw = $sel['custom_color_text'] ?? null;
                $customColorText = is_string($raw) ? trim($raw) : '';

                if ($customColorText === '') {
                    return $this->cartResponse($request, false, "Indicá el color que querés para «{$product->title}» dentro del combo.", 422);
                }

                $customColorText = mb_substr($customColorText, 0, 255);
            }

            $componentSelections[] = [
                'product_id' => $product->id,
                'variant_id' => $variante?->id,
                'custom_color_text' => $customColorText,
            ];
        }

        $componentSelections = $this->normalizeComponentSelections($componentSelections);
        $lineKey = $this->comboLineKey($comboId, $componentSelections);

        $cart = $this->normalizeCart(session('cart', []));
        $existingIndex = collect($cart)->search(fn ($l) => ($l['line_key'] ?? null) === $lineKey);

        $currentQuantity = $existingIndex !== false ? $cart[$existingIndex]['quantity'] : 0;
        $newQuantity = $currentQuantity + $quantity;

        // Chequeo optimista de stock por componente para la cantidad total de
        // combos (los actuales en carrito + los que se agregan).
        $faltante = $this->comboFaltaStock($combo, $componentSelections, $newQuantity);

        if ($faltante !== null) {
            return $this->cartResponse($request, false, $faltante, 422);
        }

        if ($existingIndex !== false) {
            $cart[$existingIndex]['quantity'] = $newQuantity;
        } else {
            $cart[] = [
                'line_key' => $lineKey,
                'combo_id' => $comboId,
                'quantity' => $newQuantity,
                'component_selections' => $componentSelections,
            ];
        }

        session(['cart' => array_values($cart)]);

        $message = $currentQuantity > 0
            ? 'Cantidad actualizada en el carrito.'
            : 'Combo agregado al carrito.';

        return $this->cartResponse($request, true, $message, 200, [
            'cartCount' => $this->getCartCount(),
        ]);
    }

    /**
     * Devuelve un mensaje de error si algún producto del combo no tiene stock
     * suficiente (contra su variante elegida si maneja stock finito, si no
     * contra el producto) para `$cantidadCombos` combos; null si hay stock para
     * todos. El combo necesita `quantity_componente * cantidadCombos` de cada uno.
     *
     * @param  array<int, array{product_id: int, variant_id: int|null, custom_color_text: string|null}>  $componentSelections
     */
    private function comboFaltaStock(Combo $combo, array $componentSelections, int $cantidadCombos): ?string
    {
        $variantByProduct = collect($componentSelections)->keyBy('product_id');

        foreach ($combo->items as $comboItem) {
            $product = $comboItem->product;

            if (! $product) {
                continue;
            }

            $variantId = $variantByProduct->get($product->id)['variant_id'] ?? null;
            $variante = $variantId
                ? $product->variantsActive->firstWhere('id', $variantId)
                : null;

            $stockDisponible = $variante && ! $variante->tieneStockIlimitado()
                ? (int) $variante->stock
                : (int) $product->stock;

            $necesario = (int) $comboItem->quantity * $cantidadCombos;

            if ($stockDisponible < $necesario) {
                $nombre = $variante ? "{$product->title} ({$variante->name})" : $product->title;

                return "No hay stock suficiente de «{$nombre}» para este combo.";
            }
        }

        return null;
    }

    /**
     * Actualizar la cantidad de una línea del carrito. Opera por `line_key`, no
     * por `product_id`: ahora puede haber más de una línea del mismo producto.
     */
    public function update(Request $request): RedirectResponse|JsonResponse
    {
        $request->validate([
            'line_key' => 'required|string',
            'quantity' => 'required|integer|min:1',
        ]);

        $lineKey = $request->line_key;
        $quantity = (int) $request->quantity;

        $cart = $this->normalizeCart(session('cart', []));
        $index = collect($cart)->search(fn ($line) => $line['line_key'] === $lineKey);

        if ($index === false) {
            return $this->cartResponse($request, false, 'Item no encontrado en el carrito.', 404);
        }

        $line = $cart[$index];

        // Línea de combo: precio fijo, el stock lo limita el componente más
        // ajustado (necesita quantity_componente * quantity_combos de cada uno).
        if (isset($line['combo_id'])) {
            $request->validate(['quantity' => 'integer|max:99']);

            $combo = Combo::with(['items.product.variantsActive'])->find($line['combo_id']);

            if (! $combo) {
                return $this->cartResponse($request, false, 'Item no encontrado en el carrito.', 404);
            }

            $faltante = $this->comboFaltaStock($combo, $line['component_selections'], $quantity);

            if ($faltante !== null) {
                return $this->cartResponse($request, false, $faltante, 422);
            }

            $cart[$index]['quantity'] = $quantity;
            session(['cart' => array_values($cart)]);

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Cantidad actualizada.',
                    'subtotal' => round($quantity * (float) $combo->price, 2),
                    'cartCount' => $this->getCartCount(),
                ]);
            }

            return back()->with('success', 'Cantidad actualizada.');
        }

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
            return $this->cartResponse(
                $request,
                false,
                $this->mensajeStock($product->title, $variante?->name, $stockDisponible),
                422
            );
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
     * Vaciar todo el carrito. Además del carrito de productos limpia el código de
     * descuento (`cart_discount_code`), que es estado del carrito y no debe colarse al próximo
     * pedido, y un `cart_payment_plan` que haya quedado en una sesión vieja (la forma de pago ya no se elige).
     */
    public function clear(): RedirectResponse|JsonResponse
    {
        session()->forget(['cart', 'cart_discount_code', 'cart_payment_plan']);

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
        $total = round($subtotal - ($discountInfo['discountCode']['amount'] ?? 0), 2);

        return Inertia::render('Cart/Checkout', [
            'cartItems' => $cartItems,
            'subtotal' => $subtotal,
            'total' => $total,
            'discountCode' => $discountInfo['discountCode'],
            'discountCodeRemovedReason' => $discountInfo['discountCodeRemovedReason'],
            'cuotas' => $this->calculadora->infoCuotas($total),
            'provinces' => Provincias::all(),
            // Retiro en oficina: hay una sola (Buenos Aires) y el cliente no la elige.
            'oficina' => TipoEntrega::OFICINA,
            'freeShippingThreshold' => Setting::get('free_shipping_threshold'),
            'freeShippingByCombo' => $this->freeShippingByCombo($cartItems),
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
        $faltanOpciones = $this->calculadora->validarOpcionesObligatorias($cart);

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
            $calculo = $this->calculadora->calcularItems($cart, exigirVariante: true);
        } catch (VarianteRequeridaException) {
            return response()->json([
                'success' => false,
                'message' => 'Algunas opciones de tu carrito ya no están disponibles. Actualizá el carrito e intentá nuevamente.',
            ], 422);
        }

        $cartItems = $calculo->items;

        if ($cartItems->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'El carrito está vacío.',
            ], 422);
        }

        // Checkout mínimo: nombre y teléfono siempre; provincia solo si es un envío (un retiro es en la oficina de Buenos Aires).
        // DNI, ciudad, código postal, email y apellido ya no los pide la tienda: se aceptan como opcionales (un cliente con la
        // versión vieja del formulario, o un test) y el vendedor completa lo que falte antes de confirmar la venta. Sin
        // `tipo_entrega` es un envío, como antes del checkout mínimo.
        $esRetiro = $request->input('customer_data.tipo_entrega') === TipoEntrega::Retiro->value;

        $request->validate([
            'customer_data' => 'required|array',
            'customer_data.tipo_entrega' => ['nullable', Rule::enum(TipoEntrega::class)],
            'customer_data.name' => 'required|string|min:2|max:100',
            'customer_data.lastname' => 'nullable|string|max:100',
            'customer_data.dni' => 'nullable|string|max:20',
            'customer_data.province' => [$esRetiro ? 'nullable' : 'required', 'string', Rule::in(Provincias::slugs())],
            'customer_data.city' => 'nullable|string|max:100',
            'customer_data.postal_code' => 'nullable|string|max:20',
            'customer_data.phone' => ['required', 'string', 'max:30', DatosDeEnvio::reglaTelefono()],
            'customer_data.email' => 'nullable|email|max:150',
            'customer_data.observations' => 'nullable|string|max:500',
        ], [
            'customer_data.name.required' => 'Ingresá tu nombre y apellido.',
            'customer_data.province.required' => 'Elegí la provincia a la que enviamos tu pedido.',
            'customer_data.province.in' => 'Elegí una provincia de la lista.',
            'customer_data.phone.required' => 'Ingresá tu WhatsApp para coordinar el pedido.',
        ]);

        $customerData = $request->customer_data;
        $tipoEntrega = $esRetiro ? TipoEntrega::Retiro : TipoEntrega::Envio;
        // Un retiro es en la oficina: no lleva provincia aunque el formulario mande una (el recuerdo del dispositivo, por ejemplo).
        $customerData['province'] = $esRetiro ? null : ($customerData['province'] ?? null);
        $customerData['tipo_entrega'] = $tipoEntrega->value;

        // Chequeo optimista de stock, antes de abrir la transacción de creación de
        // la orden. El chequeo definitivo (bajo lock) pasa dentro de
        // StockService::descontar(), ya con los OrderItem persistidos. Cuando la
        // línea tiene variante, el chequeo va contra el stock de la variante. Un
        // combo se expande en sus componentes (una unidad por producto, con la
        // cantidad multiplicada por la cantidad de combos pedida).
        $faltantes = $stockService->validarDisponibilidad($calculo->lineasStock);

        if (! empty($faltantes)) {
            return $this->respuestaStockInsuficiente($faltantes, $cartItems);
        }

        // Chequeo optimista del código de descuento (si hay uno en sesión), antes
        // de abrir la transacción — mismo criterio que validarDisponibilidad() con
        // el stock. El chequeo definitivo (bajo lock) pasa dentro de
        // DiscountCodeService::registrarUso(), ya dentro de la transacción.
        //
        // La forma de pago no se elige en la web: el medio se coordina por WhatsApp y las cuotas con tarjeta son solo información
        // (`infoCuotas`). Un `cart_payment_plan` que haya quedado en una sesión vieja se ignora (y se borra al terminar el pedido).
        try {
            $calculo = $this->calculadora->aplicarDescuento($calculo, session('cart_discount_code'));
        } catch (DiscountCodeInvalidoException $e) {
            session()->forget('cart_discount_code');

            return response()->json([
                'success' => false,
                'message' => $e->getMessage().' Lo quitamos de tu carrito, por favor reintentá.',
            ], 422);
        }

        // El mensaje previo todavía no tiene número de pedido (la orden no existe): es el que se guarda al crearla y el que se
        // ofrece si el Central no responde. Con la orden creada se rearma con "Pedido #N".
        $mensajePrevio = $this->armarMensajeWhatsApp($customerData, $tipoEntrega, $calculo, null);

        $atributosOrden = $this->atributosDeOrden($customerData, $tipoEntrega, $calculo, $mensajePrevio, $request->user()?->id);
        $atributosItems = $this->atributosDeItems($cartItems);

        // Con el checkout del Central activo el pedido reserva ALLÁ antes de guardarse y la web no toca su stock local;
        // si no, es el flujo de siempre (stock local, todo en una sola transacción).
        $resultado = CheckoutCentral::activo()
            ? $this->crearOrdenConReserva($atributosOrden, $atributosItems, $calculo, $mensajePrevio, $stockService)
            : $this->crearOrdenLocal($atributosOrden, $atributosItems, $calculo, $stockService);

        if ($resultado instanceof JsonResponse) {
            return $resultado;
        }

        $orderId = $resultado;

        // Con la orden creada el mensaje lleva su número, y lo que se guarda es el texto final (el que se le manda al vendedor).
        $message = $this->armarMensajeWhatsApp($customerData, $tipoEntrega, $calculo, $orderId);
        Order::whereKey($orderId)->update(['mensaje_whatsapp' => $message]);

        // Vaciar el carrito una vez creada la orden y generado el mensaje. `cart_payment_plan` ya no se usa: se limpia por si
        // quedó en una sesión vieja.
        session()->forget(['cart', 'cart_discount_code', 'cart_payment_plan']);

        return response()->json([
            'success' => true,
            'message' => $message,
            'subtotal' => $calculo->subtotal,
            'discount_amount' => $calculo->descuento,
            'total' => $calculo->total,
            'itemCount' => $cartItems->count(),
            'order_id' => $orderId,
        ]);
    }

    /**
     * El mensaje de WhatsApp del pedido. `$numeroDePedido` es null mientras la orden no existe (mensaje previo): sin número no se
     * escribe la línea "Pedido #N". Solo se escriben los datos que hay: sin líneas vacías.
     *
     * @param  array<string, mixed>  $customerData
     */
    private function armarMensajeWhatsApp(array $customerData, TipoEntrega $tipoEntrega, CalculoPedido $calculo, ?int $numeroDePedido): string
    {
        $cartItems = $calculo->items;
        $subtotal = $calculo->subtotal;
        $discountCode = $calculo->codigoDescuento;
        $discountAmount = $calculo->descuento;
        $total = $calculo->total;
        $freeShippingThreshold = Setting::get('free_shipping_threshold');
        $freeShippingByCombo = $calculo->envioGratisPorCombo;

        $message = "🛒 *NUEVO PEDIDO DE LA WEB*\n";

        if ($numeroDePedido !== null) {
            $message .= "*Pedido #{$numeroDePedido}*\n";
        }

        $message .= $tipoEntrega === TipoEntrega::Retiro
            ? '📍 '.TipoEntrega::OFICINA."\n\n"
            : '📍 Envío ('.Provincias::nombre($customerData['province'] ?? null).")\n\n";

        $nombre = trim(($customerData['name'] ?? '').' '.($customerData['lastname'] ?? ''));

        $message .= "👤 *Datos del Cliente:*\n";
        $message .= "Nombre: {$nombre}\n";
        $message .= "Teléfono: {$customerData['phone']}\n";

        foreach (['dni' => 'DNI', 'email' => 'Email', 'city' => 'Localidad', 'postal_code' => 'Código Postal'] as $campo => $etiqueta) {
            if (trim((string) ($customerData[$campo] ?? '')) !== '') {
                $message .= "{$etiqueta}: {$customerData[$campo]}\n";
            }
        }

        $message .= "\n";

        // Agregar observaciones si existen
        if (! empty($customerData['observations'])) {
            $message .= "📝 *Observaciones:*\n";
            $message .= "{$customerData['observations']}\n\n";
        }

        $message .= "📋 *Detalle del pedido:*\n";

        foreach ($cartItems as $item) {
            // Bloque de combo: nombre del combo + los productos incluidos con el
            // color elegido de cada uno, y el precio fijo del combo.
            if (! empty($item['is_combo'])) {
                $message .= "🎁 *Combo: {$item['combo']['title']}*\n";

                foreach ($item['components'] as $comp) {
                    $linea = "  - {$comp['quantity']}x {$comp['product_title']}";

                    if (! empty($comp['variant'])) {
                        if (! empty($comp['variant']['is_custom_color'])) {
                            $colorLibre = $comp['custom_color_text'] ?: $comp['variant']['name'];
                            $linea .= " (Color solicitado: {$colorLibre})";
                        } else {
                            $linea .= " (Color: {$comp['variant']['name']})";
                        }
                    } elseif (! empty($comp['custom_color_text'])) {
                        $linea .= " (Color solicitado: {$comp['custom_color_text']})";
                    }

                    $message .= $linea."\n";
                }

                $message .= "  Cantidad: {$item['quantity']}\n";
                $message .= '  Precio combo: $'.number_format($item['price'], 0, ',', '.')."\n";

                if (! empty($item['combo']['is_free_shipping'])) {
                    $message .= "  🚚 Envío gratis incluido\n";
                }

                $message .= '  Subtotal: $'.number_format($item['subtotal'], 0, ',', '.')."\n\n";

                continue;
            }

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

        // El envío gratis por combo tiene prioridad: si el carrito trae un combo
        // con envío gratis, el pedido va con envío gratis sin importar el umbral.
        // Un retiro en la oficina no lleva envío: no se habla de envío gratis.
        if ($tipoEntrega === TipoEntrega::Retiro) {
            // nada que decir del envío
        } elseif ($freeShippingByCombo) {
            $message .= "🚚 *¡Envío gratis incluido por tu combo!*\n\n";
        } elseif ((float) ($freeShippingThreshold ?? 0) > 0) {
            if ($subtotal >= $freeShippingThreshold) {
                $message .= "🚚 *¡Envío gratis alcanzado!*\n\n";
            } else {
                $faltante = $freeShippingThreshold - $subtotal;
                $message .= '🚚 Le faltan $'.number_format($faltante, 0, ',', '.')." para alcanzar el envío gratis.\n\n";
            }
        }

        $message .= '💰 *TOTAL: $'.number_format($total, 0, ',', '.').'*';

        // La forma de pago no se elige en la web: se coordina por WhatsApp (las cuotas con tarjeta se informan en el sitio).
        $message .= "\n\n💳 *Forma de pago:* a coordinar por WhatsApp";

        return $message;
    }

    /**
     * Columnas de `orders` de un pedido ya calculado (con `user_id`, que se asigna aparte porque no es fillable). El checkout
     * mínimo guarda «Nombre y apellido» entero en `name`; DNI, localidad, código postal y email quedan vacíos hasta que el
     * vendedor los completa. Las columnas del plan de tarjeta (`card_payment_plan_id`, `surcharge_*`…) ya no se escriben: quedan
     * solo para los pedidos históricos.
     *
     * @param  array<string, mixed>  $customerData
     * @return array<string, mixed>
     */
    private function atributosDeOrden(array $customerData, TipoEntrega $tipoEntrega, CalculoPedido $calculo, string $message, ?int $userId): array
    {
        $vacioANull = fn (string $campo) => trim((string) ($customerData[$campo] ?? '')) !== '' ? trim((string) $customerData[$campo]) : null;

        return [
            'name' => trim($customerData['name']),
            'lastname' => $vacioANull('lastname'),
            'tipo_entrega' => $tipoEntrega->value,
            'dni' => $vacioANull('dni'),
            'province' => $tipoEntrega === TipoEntrega::Retiro ? null : $vacioANull('province'),
            'city' => $vacioANull('city'),
            'postal_code' => $vacioANull('postal_code'),
            'phone' => $customerData['phone'],
            'email' => $vacioANull('email'),
            'observations' => $vacioANull('observations'),
            'discount_code_id' => $calculo->codigoDescuento?->id,
            'discount_code' => $calculo->codigoDescuento?->code,
            'subtotal' => $calculo->subtotal,
            'discount_amount' => $calculo->descuento,
            'total' => $calculo->total,
            // Envío gratis del pedido: true si trae un combo con envío
            // gratis (el envío gratis por umbral sigue siendo informativo).
            'free_shipping' => $calculo->envioGratisPorCombo,
            'mensaje_whatsapp' => $message,
            'user_id' => $userId,
        ];
    }

    /**
     * Filas de `order_items` del carrito ya calculado.
     *
     * @return list<array<string, mixed>>
     */
    private function atributosDeItems(Collection $cartItems): array
    {
        return $cartItems->map(function (array $item) {
            // Línea de combo: una sola fila con product_id null, el
            // combo_id y el snapshot de los componentes elegidos (que
            // StockService lee para descontar el stock de cada producto).
            if (! empty($item['is_combo'])) {
                return [
                    'product_id' => null,
                    'combo_id' => $item['combo']['id'],
                    'product_variant_id' => null,
                    'product_title' => $item['combo']['title'],
                    'combo_selections' => $item['component_selections'],
                    'cantidad' => $item['quantity'],
                    'precio_unitario' => $item['unit_price'],
                    'base_unit_price' => $item['price'],
                    'subtotal' => $item['subtotal'],
                ];
            }

            return [
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
            ];
        })->values()->all();
    }

    /**
     * Flujo de siempre: la orden, el cupón y el descuento de stock local en una sola transacción.
     *
     * @param  array<string, mixed>  $atributosOrden
     * @param  list<array<string, mixed>>  $atributosItems
     * @return int|JsonResponse El id de la orden, o la respuesta de error.
     */
    private function crearOrdenLocal(array $atributosOrden, array $atributosItems, CalculoPedido $calculo, StockService $stockService): int|JsonResponse
    {
        $orderId = null;

        try {
            DB::transaction(function () use ($atributosOrden, $atributosItems, $calculo, &$orderId, $stockService) {
                $order = new Order(Arr::except($atributosOrden, 'user_id'));
                $order->user_id = $atributosOrden['user_id'];
                $order->save();

                foreach ($atributosItems as $item) {
                    $order->items()->create($item);
                }

                $stockService->descontar($order);

                // Revalidación definitiva bajo lock: si el código se agotó por una
                // carrera con otro checkout concurrente, esto lanza y aborta toda
                // la transacción (orden, items y descuento de stock incluidos).
                if ($calculo->codigoDescuento) {
                    $this->discountCodeService->consumirUso($order);
                }

                $orderId = $order->id;
            });
        } catch (StockInsuficienteException $e) {
            report($e);

            return $this->respuestaStockInsuficiente([
                [
                    'product_id' => $e->productId,
                    'product_variant_id' => $e->variantId,
                    'cantidad' => $e->cantidadSolicitada,
                    'stock_disponible' => $e->stockDisponible,
                ],
            ], $calculo->items);
        } catch (DiscountCodeInvalidoException $e) {
            report($e);

            return $this->respuestaCodigoNoDisponible($e);
        } catch (\Throwable $e) {
            report($e);

            return $this->respuestaNoPudimosRegistrar();
        }

        return $orderId;
    }

    /**
     * Checkout con el Central (10.3). Orden:
     *  1. `CalculadoraPedido` (ya corrió): precios, cupón validado SIN consumir, ítems y totales.
     *  2. La referencia externa (`rukoz_ref`) se genera ANTES de reservar y es la clave de idempotencia: un doble clic o un
     *     reintento reutiliza el mismo intento (ver CheckoutCentral) y no crea dos reservas.
     *  3. `Rukoz::reservar()` — HTTP, afuera de toda transacción.
     *  4. Reserva OK → transacción local (sin HTTP): se crea la orden con su `rukoz_ref` y se consume el cupo del cupón. NO
     *     se descuenta stock local: lo reservó el Central. Si la transacción falla, el paquete cancela la reserva (o la
     *     deja en compensaciones) y vuelve a lanzar la excepción.
     *
     * @param  array<string, mixed>  $atributosOrden
     * @param  list<array<string, mixed>>  $atributosItems
     * @return int|JsonResponse El id de la orden, o la respuesta de error.
     */
    private function crearOrdenConReserva(array $atributosOrden, array $atributosItems, CalculoPedido $calculo, string $message, StockService $stockService): int|JsonResponse
    {
        $armador = app(PedidoParaCentral::class);

        // Modelos en memoria: sirven para armar el pedido del Central antes de que exista en la base.
        $ordenEnMemoria = new Order(Arr::except($atributosOrden, 'user_id'));
        $itemsEnMemoria = array_map(fn (array $item) => new OrderItem($item), $atributosItems);

        try {
            $contenido = $armador->armar($ordenEnMemoria, $itemsEnMemoria, now())->payload;
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'message' => collect($e->errors())->flatten()->first()], 422);
        }

        unset($contenido['creado_at']);
        $huella = CheckoutCentral::huella($contenido);

        try {
            return Cache::lock(CheckoutCentral::claveLock($huella), 90)->block(
                30,
                fn () => $this->reservarYCrear($atributosOrden, $atributosItems, $ordenEnMemoria, $itemsEnMemoria, $calculo, $message, $huella, $stockService)
            );
        } catch (LockTimeoutException) {
            // El mismo pedido sigue en curso en otro request (un doble clic): no se hace nada de más.
            return response()->json(['success' => false, 'message' => 'Tu pedido ya se está procesando. Esperá unos segundos.'], 409);
        }
    }

    /**
     * @param  array<string, mixed>  $atributosOrden
     * @param  list<array<string, mixed>>  $atributosItems
     * @param  list<OrderItem>  $itemsEnMemoria
     * @return int|JsonResponse El id de la orden, o la respuesta de error.
     */
    private function reservarYCrear(array $atributosOrden, array $atributosItems, Order $ordenEnMemoria, array $itemsEnMemoria, CalculoPedido $calculo, string $message, string $huella, StockService $stockService): int|JsonResponse
    {
        $intento = CheckoutCentral::intento($huella);

        // Este mismo pedido ya se creó (doble envío): se devuelve el que ya existe, sin reservar de nuevo.
        $existente = Order::query()
            ->when(
                $intento['pedido_id'] !== null,
                fn ($query) => $query->whereKey($intento['pedido_id']),
                fn ($query) => $query->where('rukoz_ref', $intento['ref'])
            )
            ->first();

        if ($existente !== null) {
            return $existente->id;
        }

        $creadoAt = Carbon::parse($intento['creado_at']);
        $armado = app(PedidoParaCentral::class)->armar($ordenEnMemoria, $itemsEnMemoria, $creadoAt);

        try {
            $reserva = Rukoz::reservar($intento['ref'], $armado->payload, function (array $central) use ($atributosOrden, $atributosItems, $calculo, $intento, $creadoAt) {
                // Dentro de la transacción del paquete: solo base local, nada de HTTP.
                $order = new Order(Arr::except($atributosOrden, 'user_id') + [
                    'rukoz_ref' => $intento['ref'],
                    'rukoz_version' => $central['version'] ?? null,
                    'rukoz_estado' => $central['estado'] ?? Order::RESERVA_RESERVADA,
                ]);
                $order->user_id = $atributosOrden['user_id'];
                // Lo que el Central recibió como `creado_at`: así un PUT posterior manda la misma hora.
                $order->created_at = $creadoAt;
                $order->save();

                foreach ($atributosItems as $item) {
                    $order->items()->create($item);
                }

                // Sin StockService::descontar(): el stock lo reservó el Central.
                // Revalidación definitiva del cupón bajo lock: si se agotó por una carrera con otro checkout, esto lanza,
                // se deshace la transacción y el paquete cancela la reserva que se acababa de hacer.
                if ($calculo->codigoDescuento) {
                    $this->discountCodeService->consumirUso($order);
                }

                return $order;
            });
        } catch (SinStockException $e) {
            CheckoutCentral::olvidarIntento($huella);
            // El espejo estaba desactualizado: se relee el stock de esos ítems para que la tienda deje de ofrecerlos.
            EspejoStock::refrescar(ErroresDelCentral::paresParaRefrescar($e, $armado));

            $faltas = ErroresDelCentral::sinStock($e, $armado);

            return response()->json([
                'success' => false,
                'message' => collect($faltas)->pluck('mensaje')->implode(' ').' Ajustá tu carrito para continuar.',
                'stock_insuficiente' => array_map(fn (array $falta) => Arr::except($falta, 'mensaje'), $faltas),
            ], 422);
        } catch (CentralNoDisponibleException) {
            // Caído, timeout o circuito abierto: no se sabe si reservó. El intento (y su ref) se conserva: el reintento del
            // cliente es idempotente.
            if (CheckoutCentral::permiteVenderSinReserva()) {
                return $this->crearOrdenLocal($atributosOrden, $atributosItems, $calculo, $stockService);
            }

            return response()->json([
                'success' => false,
                'message' => ErroresDelCentral::MENSAJE_CENTRAL_CAIDO,
                'central_no_disponible' => true,
                // El carrito ya armado, para que el cliente siga por WhatsApp sin perder el pedido.
                'whatsapp_message' => $message,
            ], 503);
        } catch (DiscountCodeInvalidoException $e) {
            // La reserva se canceló sola (el paquete compensa): un reenvío tiene que armar un intento nuevo.
            CheckoutCentral::olvidarIntento($huella);

            return $this->respuestaCodigoNoDisponible($e);
        } catch (RukozException $e) {
            // Sin vincular, selección inválida, validación, etc.: es un problema de catálogo, no de quien compra.
            CheckoutCentral::olvidarIntento($huella);
            ErroresDelCentral::registrar($e, 'reservar el pedido');

            return response()->json(['success' => false, 'message' => ErroresDelCentral::MENSAJE_ITEM_NO_DISPONIBLE], 422);
        } catch (\Throwable $e) {
            // Falló lo local DESPUÉS de reservar: el paquete ya canceló la reserva (o la dejó en compensaciones).
            CheckoutCentral::olvidarIntento($huella);
            report($e);

            return $this->respuestaNoPudimosRegistrar();
        }

        /** @var Order $order */
        $order = $reserva->local;

        CheckoutCentral::intentoCompletado($huella, $intento, $order->id);

        return $order->id;
    }

    private function respuestaCodigoNoDisponible(DiscountCodeInvalidoException $e): JsonResponse
    {
        session()->forget('cart_discount_code');

        return response()->json([
            'success' => false,
            'message' => 'El código de descuento ya no está disponible: '.$e->getMessage().' Reintentá tu pedido sin el código.',
        ], 422);
    }

    private function respuestaNoPudimosRegistrar(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'No pudimos registrar tu pedido. Por favor, intentá nuevamente.',
        ], 500);
    }

    /**
     * Enriquecer los faltantes de stock (`product_id`, `product_variant_id`,
     * `cantidad`, `stock_disponible`) con el título del producto, usando el
     * carrito ya resuelto en memoria en vez de volver a consultar la base.
     */
    private function mapStockInsuficiente(array $faltantes, $cartItems): array
    {
        // Título por product_id resuelto desde el carrito en memoria, mirando
        // tanto las líneas de producto como los componentes de los combos, para
        // no volver a consultar la base.
        $titulosPorProducto = [];

        foreach ($cartItems as $i) {
            if (! empty($i['is_combo'])) {
                foreach ($i['components'] as $comp) {
                    $titulosPorProducto[$comp['product_id']] ??= $comp['product_title'];
                }

                continue;
            }

            if (! empty($i['product'])) {
                $titulosPorProducto[$i['product']->id] ??= $i['product']->title;
            }
        }

        $colores = ProductVariant::whereIn('id', collect($faltantes)->pluck('product_variant_id')->filter()->all())->pluck('name', 'id');

        return collect($faltantes)->map(fn ($faltante) => [
            'product_id' => $faltante['product_id'],
            'product_title' => $titulosPorProducto[$faltante['product_id']] ?? null,
            'product_variant_id' => $faltante['product_variant_id'] ?? null,
            'variant_name' => $colores->get($faltante['product_variant_id'] ?? 0),
            'cantidad_solicitada' => $faltante['cantidad'],
            'stock_disponible' => $faltante['stock_disponible'],
        ])->all();
    }

    /**
     * 422 del checkout por falta de stock: el mensaje nombra el producto y el color de cada faltante (un color sin
     * stock y otro del mismo producto se informan por separado) y `stock_insuficiente` trae el detalle para el front.
     */
    private function respuestaStockInsuficiente(array $faltantes, $cartItems): JsonResponse
    {
        $detalle = $this->mapStockInsuficiente($faltantes, $cartItems);

        $frases = collect($detalle)->map(fn (array $d) => $this->mensajeStock(
            $d['product_title'] ?? 'este producto',
            $d['variant_name'],
            (int) $d['stock_disponible'],
            pedido: (int) $d['cantidad_solicitada'],
        ));

        return response()->json([
            'success' => false,
            'message' => $frases->implode(' ').' Ajustá tu carrito para continuar.',
            'stock_insuficiente' => $detalle,
        ], 422);
    }

    /**
     * Mensaje de stock que nombra el producto y, si lo hay, el color: «No queda stock de X (Rojo).» o
     * «Solo quedan 2 unidades de X (Rojo) (pediste 5).». En el carrito, `$enCarrito` son las unidades que esa
     * línea ya tiene (para decir cuántas se pueden sumar); en el checkout, `$pedido` son las que pidió.
     */
    private function mensajeStock(string $titulo, ?string $color, int $disponible, int $enCarrito = 0, ?int $pedido = null): string
    {
        return Stock::mensajeFaltante($titulo, $color, $disponible, $enCarrito, $pedido);
    }
}
