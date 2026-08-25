<?php

namespace App\Http\Controllers;

use App\Exceptions\DiscountCodeInvalidoException;
use App\Exceptions\StockInsuficienteException;
use App\Models\Order;
use App\Models\Product;
use App\Models\Setting;
use App\Services\DiscountCodeService;
use App\Services\PricingService;
use App\Services\StockService;
use App\Support\Provincias;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
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
     * Obtener items del carrito desde sesión. El precio de cada línea se resuelve
     * con PricingService según la cantidad real pedida (tier + oferta), no con un
     * precio plano por producto.
     */
    private function getCartItems()
    {
        $sessionCart = session('cart', []);
        $cartItems = collect();

        foreach ($sessionCart as $productId => $quantity) {
            $product = Product::with(['images', 'currentOffer'])->find($productId);
            if ($product) {
                $priceResult = $this->pricingService->calcularPrecio($product, (int) $quantity);
                $cartItems->push([
                    'id' => $productId,
                    'product' => $product,
                    'quantity' => $quantity,
                    'price' => $priceResult->precioUnitarioFinal,
                    'list_price' => $priceResult->precioLista,
                    'unit_savings' => $priceResult->ahorroUnitario,
                    'savings_percentage' => $priceResult->ahorroPorcentaje,
                    'subtotal' => $quantity * $priceResult->precioUnitarioFinal,
                ]);
            }
        }

        return $cartItems;
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
        return array_sum(session('cart', []));
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
     * Agregar producto al carrito
     */
    public function add(Request $request): RedirectResponse|JsonResponse
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity' => 'integer|min:1|max:99'
        ]);

        $productId = $request->product_id;
        $quantity = $request->quantity ?? 1;

        // Verificar que el producto existe y está en stock
        $product = Product::with('currentOffer')->findOrFail($productId);
        
        if ($product->stock < $quantity) {
            $message = 'No hay suficiente stock disponible.';
            
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $message
                ], 422);
            }
            
            return back()->with('error', $message);
        }

        // Obtener carrito de sesión
        $cart = session('cart', []);
        $currentQuantity = $cart[$productId] ?? 0;
        $newQuantity = $currentQuantity + $quantity;
        
        if ($product->stock < $newQuantity) {
            $message = 'No hay suficiente stock para esta cantidad.';
            
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $message
                ], 422);
            }
            
            return back()->with('error', $message);
        }
        
        $cart[$productId] = $newQuantity;
        session(['cart' => $cart]);
        
        $message = $currentQuantity > 0 
            ? 'Cantidad actualizada en el carrito.' 
            : 'Producto agregado al carrito.';

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'cartCount' => $this->getCartCount()
            ]);
        }

        return back()->with('success', $message);
    }

    /**
     * Actualizar cantidad de un item en el carrito
     */
    public function update(Request $request): RedirectResponse|JsonResponse
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:1|max:99'
        ]);

        $productId = $request->product_id;
        $quantity = $request->quantity;

        $product = Product::with('currentOffer')->findOrFail($productId);

        // Verificar stock
        if ($product->stock < $quantity) {
            $message = 'No hay suficiente stock disponible.';
            
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $message
                ], 422);
            }
            
            return back()->with('error', $message);
        }

        // Actualizar en sesión
        $cart = session('cart', []);
        
        if (!isset($cart[$productId])) {
            $message = 'Item no encontrado en el carrito.';
            
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $message
                ], 404);
            }
            
            return back()->with('error', $message);
        }
        
        $cart[$productId] = $quantity;
        session(['cart' => $cart]);

        $message = 'Cantidad actualizada.';

        if ($request->expectsJson()) {
            $priceResult = $this->pricingService->calcularPrecio($product, $quantity);

            return response()->json([
                'success' => true,
                'message' => $message,
                'subtotal' => $quantity * $priceResult->precioUnitarioFinal,
                'cartCount' => $this->getCartCount()
            ]);
        }

        return back()->with('success', $message);
    }

    /**
     * Eliminar item del carrito
     */
    public function remove(Request $request): RedirectResponse|JsonResponse
    {
        $request->validate([
            'product_id' => 'required|exists:products,id'
        ]);

        $productId = $request->product_id;

        // Eliminar de sesión
        $cart = session('cart', []);
        
        if (!isset($cart[$productId])) {
            $message = 'Item no encontrado en el carrito.';
            
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $message
                ], 404);
            }
            
            return back()->with('error', $message);
        }
        
        unset($cart[$productId]);
        session(['cart' => $cart]);

        $message = 'Producto eliminado del carrito.';

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'cartCount' => $this->getCartCount()
            ]);
        }

        return back()->with('success', $message);
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
                'cartCount' => 0
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
        $cartItems = $this->getCartItems();
        
        if ($cartItems->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'El carrito está vacío.'
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
            'customer_data.observations' => 'nullable|string|max:500'
        ]);

        $customerData = $request->customer_data;

        // Chequeo optimista de stock, antes de abrir la transacción de creación de
        // la orden. El chequeo definitivo (bajo lock) pasa dentro de
        // StockService::descontar(), ya con los OrderItem persistidos.
        $stockItems = $cartItems->map(fn ($item) => [
            'product_id' => $item['product']->id,
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
                    'message' => $e->getMessage() . ' Lo quitamos de tu carrito, por favor reintentá.',
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
        if (!empty($customerData['observations'])) {
            $message .= "📝 *Observaciones:*\n";
            $message .= "{$customerData['observations']}\n\n";
        }

        $message .= "📋 *Detalle del pedido:*\n";
        
        foreach ($cartItems as $item) {
            $product = $item['product'];
            $currentPrice = $item['price'];

            $message .= "• {$product['title']}\n";
            $message .= "  Cantidad: {$item['quantity']}\n";

            // Precio original (de lista o de tier, según la cantidad) vs. final
            // (con oferta aplicada), si hubo algún ahorro; si no, solo el precio.
            if ($item['unit_savings'] > 0) {
                $message .= "  Precio original: $" . number_format($item['list_price'], 0, ',', '.') . "\n";
                $message .= "  Precio final: $" . number_format($currentPrice, 0, ',', '.') . "\n";
                $message .= "  ¡Ahorrás {$item['savings_percentage']}%!\n";
            } else {
                $message .= "  Precio: $" . number_format($currentPrice, 0, ',', '.') . "\n";
            }

            $message .= "  Subtotal: $" . number_format($item['subtotal'], 0, ',', '.') . "\n\n";
        }

        // Línea de descuento por código, en el mismo espíritu que el desglose de
        // precio original vs. final que ya se muestra por oferta a nivel de item.
        if ($discountCode) {
            $message .= "🏷️ *Código de descuento: {$discountCode->code}*\n";
            $message .= "  Subtotal: $" . number_format($subtotal, 0, ',', '.') . "\n";
            $message .= "  Descuento (" . (float) $discountCode->percentage . "%): -$" . number_format($discountAmount, 0, ',', '.') . "\n\n";
        }

        if ((float) ($freeShippingThreshold ?? 0) > 0) {
            if ($subtotal >= $freeShippingThreshold) {
                $message .= "🚚 *¡Envío gratis alcanzado!*\n\n";
            } else {
                $faltante = $freeShippingThreshold - $subtotal;
                $message .= "🚚 Le faltan $" . number_format($faltante, 0, ',', '.') . " para alcanzar el envío gratis.\n\n";
            }
        }

        $message .= "💰 *TOTAL: $" . number_format($total, 0, ',', '.') . "*";

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
                        'product_title' => $item['product']->title,
                        'cantidad' => $item['quantity'],
                        'precio_unitario' => $item['price'],
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
                'message' => 'El código de descuento ya no está disponible: ' . $e->getMessage() . ' Reintentá tu pedido sin el código.',
            ], 422);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'No pudimos registrar tu pedido. Por favor, intentá nuevamente.'
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
            'order_id' => $orderId
        ]);
    }

    /**
     * Enriquecer los faltantes de stock (`product_id`, `cantidad`, `stock_disponible`)
     * con el título del producto, usando el carrito ya resuelto en memoria en vez de
     * volver a consultar la base.
     */
    private function mapStockInsuficiente(array $faltantes, $cartItems): array
    {
        return collect($faltantes)->map(function ($faltante) use ($cartItems) {
            $item = $cartItems->first(fn ($i) => $i['product']->id === $faltante['product_id']);

            return [
                'product_id' => $faltante['product_id'],
                'product_title' => $item['product']->title ?? null,
                'cantidad_solicitada' => $faltante['cantidad'],
                'stock_disponible' => $faltante['stock_disponible'],
            ];
        })->all();
    }
}