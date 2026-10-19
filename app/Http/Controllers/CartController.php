<?php

namespace App\Http\Controllers;

use App\Exceptions\DiscountCodeInvalidoException;
use App\Exceptions\StockInsuficienteException;
use App\Exceptions\VarianteRequeridaException;
use App\Models\CardPaymentPlan;
use App\Models\Combo;
use App\Models\Order;
use App\Models\Product;
use App\Models\Setting;
use App\Services\CalculadoraPedido;
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
        $paymentPlanInfo = $this->resolvePaymentPlan($total);

        return Inertia::render('Cart/Index', [
            'cartItems' => $cartItems,
            'subtotal' => $subtotal,
            'total' => $total,
            'discountCode' => $discountInfo['discountCode'],
            'discountCodeRemovedReason' => $discountInfo['discountCodeRemovedReason'],
            'paymentPlan' => $paymentPlanInfo['paymentPlan'],
            'paymentPlanRemovedReason' => $paymentPlanInfo['paymentPlanRemovedReason'],
            'cardPaymentPlans' => $this->activePaymentPlans(),
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

        return response()->json([
            'cartItems' => $cartItems,
            'count' => $cartItems->sum('quantity'),
            'subtotal' => $subtotal,
            'total' => round($subtotal - ($discountInfo['discountCode']['amount'] ?? 0), 2),
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
     * Guardar la forma de pago sugerida (plan de cuotas con tarjeta) para el
     * carrito. Igual que el código de descuento, esto NO agrega nada al carrito
     * de productos: es un estado aparte en sesión (`cart_payment_plan`), un
     * snapshot {id, name, installments, surcharge_percentage} que las vistas del
     * carrito / checkout leen para mostrar el recargo informativo. El recargo se
     * recalcula siempre con CardSurchargeService contra el total real del
     * carrito; el snapshot nunca es la fuente de verdad del monto.
     */
    public function setPaymentPlan(Request $request): RedirectResponse|JsonResponse
    {
        $request->validate([
            'plan_id' => 'required|integer',
        ]);

        $plan = CardPaymentPlan::active()->find((int) $request->input('plan_id'));

        if (! $plan) {
            return $this->cartResponse($request, false, 'La forma de pago elegida ya no está disponible.', 422);
        }

        session(['cart_payment_plan' => [
            'id' => $plan->id,
            'name' => $plan->name,
            'installments' => (int) $plan->installments,
            'surcharge_percentage' => (float) $plan->surcharge_percentage,
        ]]);

        return $this->cartResponse($request, true, 'Forma de pago sugerida guardada.');
    }

    /**
     * Quitar la forma de pago sugerida del carrito.
     */
    public function removePaymentPlan(Request $request): RedirectResponse|JsonResponse
    {
        session()->forget('cart_payment_plan');

        return $this->cartResponse($request, true, 'Forma de pago sugerida quitada.');
    }

    /**
     * Revalida contra la DB el plan de cuotas guardado en sesión (si hay uno) y
     * calcula el recargo informativo por pago con tarjeta sobre el total ya
     * resuelto del carrito (`$total` = subtotal − descuento por código). Si el
     * plan se desactivó o se borró desde el panel, lo quita de la sesión en
     * silencio y devuelve el motivo, mismo patrón que resolveDiscountCode().
     *
     * @return array{paymentPlan: array{id: int, name: string, installments: int, surcharge_percentage: float, surcharge_amount: float, total_with_surcharge: float, installment_amount: float}|null, paymentPlanRemovedReason: string|null}
     */
    private function resolvePaymentPlan(float $total): array
    {
        $resultado = $this->calculadora->resolverPlan(session('cart_payment_plan'), $total);

        if ($resultado['paymentPlanRemovedReason'] !== null) {
            session()->forget('cart_payment_plan');
        }

        return $resultado;
    }

    /**
     * Catálogo de planes de cuotas activos para el selector "Forma de pago" del
     * resumen del carrito / checkout (PaymentMethodField.jsx). Mismo shape que
     * consume el simulador de la ficha: el mirror JS (utils/cardSurcharge.js)
     * previsualiza el recargo por opción sin ir al servidor. La fuente de verdad
     * del monto sigue siendo CardSurchargeService vía resolvePaymentPlan().
     *
     * @return \Illuminate\Support\Collection<int, array{id: int, name: string, installments: int, surcharge_percentage: float}>
     */
    private function activePaymentPlans()
    {
        return CardPaymentPlan::active()
            ->get()
            ->map(fn (CardPaymentPlan $plan) => [
                'id' => $plan->id,
                'name' => $plan->name,
                'installments' => (int) $plan->installments,
                'surcharge_percentage' => (float) $plan->surcharge_percentage,
            ])
            ->values();
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
                return "No hay stock suficiente de «{$product->title}» para este combo.";
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
     * Vaciar todo el carrito. Además del carrito de productos limpia la forma de
     * pago sugerida (`cart_payment_plan`) y el código de descuento
     * (`cart_discount_code`): son estado del carrito, no deben colarse al próximo
     * pedido si el cliente ya lo vació.
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
        $paymentPlanInfo = $this->resolvePaymentPlan($total);

        return Inertia::render('Cart/Checkout', [
            'cartItems' => $cartItems,
            'subtotal' => $subtotal,
            'total' => $total,
            'discountCode' => $discountInfo['discountCode'],
            'discountCodeRemovedReason' => $discountInfo['discountCodeRemovedReason'],
            'paymentPlan' => $paymentPlanInfo['paymentPlan'],
            'paymentPlanRemovedReason' => $paymentPlanInfo['paymentPlanRemovedReason'],
            'cardPaymentPlans' => $this->activePaymentPlans(),
            'provinces' => Provincias::all(),
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
        // línea tiene variante, el chequeo va contra el stock de la variante. Un
        // combo se expande en sus componentes (una unidad por producto, con la
        // cantidad multiplicada por la cantidad de combos pedida).
        $faltantes = $stockService->validarDisponibilidad($calculo->lineasStock);

        if (! empty($faltantes)) {
            return response()->json([
                'success' => false,
                'message' => 'Algunos productos de tu carrito ya no tienen stock suficiente.',
                'stock_insuficiente' => $this->mapStockInsuficiente($faltantes, $cartItems),
            ], 422);
        }

        // Chequeo optimista del código de descuento (si hay uno en sesión), antes
        // de abrir la transacción — mismo criterio que validarDisponibilidad() con
        // el stock. El chequeo definitivo (bajo lock) pasa dentro de
        // DiscountCodeService::registrarUso(), ya dentro de la transacción.
        //
        // Forma de pago sugerida (plan de cuotas con tarjeta) guardada en sesión.
        // El recargo se recalcula SIEMPRE server-side sobre el total (subtotal −
        // descuento por código); nunca se confía en un total mandado por el
        // frontend. Si el plan se desactivó o se borró entre que se eligió y este
        // submit, se descarta en silencio y la orden queda sin recargo, exactamente
        // como un pedido sin forma de pago elegida (esto es 100% informativo, nunca
        // aborta el pedido).
        try {
            $calculo = $this->calculadora->aplicarDescuentoYPlan(
                $calculo,
                session('cart_discount_code'),
                session('cart_payment_plan'),
            );
        } catch (DiscountCodeInvalidoException $e) {
            session()->forget('cart_discount_code');

            return response()->json([
                'success' => false,
                'message' => $e->getMessage().' Lo quitamos de tu carrito, por favor reintentá.',
            ], 422);
        }

        if ($calculo->motivoPlanDescartado !== null) {
            session()->forget('cart_payment_plan');
        }

        $subtotal = $calculo->subtotal;
        $discountCode = $calculo->codigoDescuento;
        $discountAmount = $calculo->descuento;
        $total = $calculo->total;
        $paymentPlan = $calculo->planPago;

        $freeShippingThreshold = Setting::get('free_shipping_threshold');
        $freeShippingByCombo = $calculo->envioGratisPorCombo;

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
        if ($freeShippingByCombo) {
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

        // Bloque accionable para el vendedor cuando el cliente eligió pagar con
        // tarjeta de crédito: el monto EXACTO por el que generar el link de pago
        // a mano en Mercado Pago (este proyecto no llama a ninguna API de MP). El
        // total del pedido de arriba no cambia. Sin forma de pago elegida el
        // mensaje termina en la línea de TOTAL, igual que siempre.
        if ($paymentPlan !== null) {
            $cuotas = (int) $paymentPlan['installments'];
            $totalConRecargo = '$'.number_format($paymentPlan['total_with_surcharge'], 0, ',', '.');
            $porCuota = '$'.number_format($paymentPlan['installment_amount'], 0, ',', '.');
            $planDetalle = $cuotas === 1
                ? 'pago único'
                : "{$cuotas} cuotas sin interés mensual";

            $message .= "\n\n💳 *Forma de pago: Tarjeta de crédito* — {$planDetalle}, recargo ".(float) $paymentPlan['surcharge_percentage']."%\n";
            $message .= $cuotas === 1
                ? "Total a cobrar: {$totalConRecargo}\n"
                : "Total a cobrar: {$totalConRecargo} ({$porCuota} c/u)\n";
            $message .= "👉 Generar link de pago en Mercado Pago por {$totalConRecargo}";
        }

        $orderId = null;

        try {
            DB::transaction(function () use ($request, $customerData, $cartItems, $subtotal, $discountAmount, $total, $discountCode, $paymentPlan, $message, $freeShippingByCombo, &$orderId, $stockService) {
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
                    // Envío gratis del pedido: true si trae un combo con envío
                    // gratis (el envío gratis por umbral sigue siendo informativo).
                    'free_shipping' => $freeShippingByCombo,
                    // Snapshot de la forma de pago con tarjeta (si se eligió una).
                    // Igual que discount_code guarda el texto del código, esto no
                    // depende de que el CardPaymentPlan siga existiendo para
                    // reconstruir el pedido histórico. Sin plan quedan todos null
                    // y la orden usa `total` como hoy.
                    'card_payment_plan_id' => $paymentPlan['id'] ?? null,
                    'payment_plan_name' => $paymentPlan['name'] ?? null,
                    'payment_plan_installments' => $paymentPlan['installments'] ?? null,
                    'surcharge_percentage' => $paymentPlan['surcharge_percentage'] ?? null,
                    'surcharge_amount' => $paymentPlan['surcharge_amount'] ?? null,
                    'total_with_surcharge' => $paymentPlan['total_with_surcharge'] ?? null,
                    'mensaje_whatsapp' => $message,
                ]);

                if ($request->user()) {
                    $order->user()->associate($request->user());
                }

                $order->save();

                foreach ($cartItems as $item) {
                    // Línea de combo: una sola fila con product_id null, el
                    // combo_id y el snapshot de los componentes elegidos (que
                    // StockService lee para descontar el stock de cada producto).
                    if (! empty($item['is_combo'])) {
                        $order->items()->create([
                            'product_id' => null,
                            'combo_id' => $item['combo']['id'],
                            'product_variant_id' => null,
                            'product_title' => $item['combo']['title'],
                            'combo_selections' => $item['component_selections'],
                            'cantidad' => $item['quantity'],
                            'precio_unitario' => $item['unit_price'],
                            'base_unit_price' => $item['price'],
                            'subtotal' => $item['subtotal'],
                        ]);

                        continue;
                    }

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
                    $this->discountCodeService->consumirUso($order);
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

        // Vaciar el carrito una vez creada la orden y generado el mensaje. La
        // forma de pago sugerida (cart_payment_plan) también se limpia: es un
        // estado del carrito, no debe filtrarse al próximo pedido, y ya quedó
        // snapshoteada en la orden (card_payment_plan_id + surcharge_*).
        session()->forget(['cart', 'cart_discount_code', 'cart_payment_plan']);

        return response()->json([
            'success' => true,
            'message' => $message,
            'subtotal' => $subtotal,
            'discount_amount' => $discountAmount,
            'total' => $total,
            // Desglose del recargo por tarjeta ya resuelto server-side, o null si
            // el pedido no eligió forma de pago con tarjeta.
            'payment_plan' => $paymentPlan,
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

        return collect($faltantes)->map(fn ($faltante) => [
            'product_id' => $faltante['product_id'],
            'product_title' => $titulosPorProducto[$faltante['product_id']] ?? null,
            'cantidad_solicitada' => $faltante['cantidad'],
            'stock_disponible' => $faltante['stock_disponible'],
        ])->all();
    }
}
