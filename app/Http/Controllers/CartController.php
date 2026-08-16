<?php

namespace App\Http\Controllers;

use App\Exceptions\StockInsuficienteException;
use App\Models\Order;
use App\Models\Product;
use App\Services\PricingService;
use App\Services\StockService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class CartController extends Controller
{
    public function __construct(private readonly PricingService $pricingService) {}

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
        $total = $this->getCartTotal($cartItems);

        return Inertia::render('Cart/Index', [
            'cartItems' => $cartItems,
            'total' => $total
        ]);
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

        $total = $this->getCartTotal($cartItems);

        // Provincias de Argentina con sus localidades principales
        $provinces = [
            'buenos-aires' => [
                'name' => 'Buenos Aires',
                'cities' => [
                    'La Plata', 'Mar del Plata', 'Bahía Blanca', 'Tandil', 'Olavarría', 
                    'Junín', 'Pergamino', 'Necochea', 'San Nicolás', 'Azul', 'Quilmes',
                    'San Isidro', 'Vicente López', 'San Martín', 'Morón', 'Avellaneda',
                    'Lanús', 'Lomas de Zamora', 'Almirante Brown', 'Esteban Echeverría'
                ]
            ],
            'caba' => [
                'name' => 'Ciudad Autónoma de Buenos Aires',
                'cities' => [
                    'Palermo', 'Recoleta', 'San Telmo', 'Puerto Madero', 'Belgrano',
                    'Villa Crespo', 'Caballito', 'Flores', 'Villa Urquiza', 'Núñez'
                ]
            ],
            'cordoba' => [
                'name' => 'Córdoba',
                'cities' => [
                    'Córdoba', 'Río Cuarto', 'Villa María', 'San Francisco', 'Villa Carlos Paz',
                    'Alta Gracia', 'Bell Ville', 'Marcos Juárez', 'Jesús María', 'La Falda'
                ]
            ],
            'santa-fe' => [
                'name' => 'Santa Fe',
                'cities' => [
                    'Rosario', 'Santa Fe', 'Rafaela', 'Reconquista', 'Venado Tuerto',
                    'Esperanza', 'Santo Tomé', 'Casilda', 'Firmat', 'Villa Gobernador Gálvez'
                ]
            ],
            'mendoza' => [
                'name' => 'Mendoza',
                'cities' => [
                    'Mendoza', 'San Rafael', 'Godoy Cruz', 'Las Heras', 'Maipú',
                    'Rivadavia', 'San Martín', 'Tupungato', 'Malargüe', 'General Alvear'
                ]
            ],
            'tucuman' => [
                'name' => 'Tucumán',
                'cities' => [
                    'San Miguel de Tucumán', 'Tafí Viejo', 'Yerba Buena', 'Banda del Río Salí',
                    'Concepción', 'Aguilares', 'Bella Vista', 'Monteros', 'Famaillá', 'Lules'
                ]
            ],
            'salta' => [
                'name' => 'Salta',
                'cities' => [
                    'Salta', 'San Ramón de la Nueva Orán', 'Tartagal', 'General Güemes',
                    'Metán', 'Cafayate', 'Rosario de Lerma', 'Campo Quijano', 'El Carmen', 'Cerrillos'
                ]
            ],
            'entre-rios' => [
                'name' => 'Entre Ríos',
                'cities' => [
                    'Paraná', 'Concordia', 'Gualeguaychú', 'Concepción del Uruguay',
                    'Victoria', 'Villaguay', 'Crespo', 'Chajarí', 'Colón', 'Federal'
                ]
            ]
        ];

        return Inertia::render('Cart/Checkout', [
            'cartItems' => $cartItems,
            'total' => $total,
            'provinces' => $provinces
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
            'customer_data.city' => 'nullable|string|max:100',
            'customer_data.address' => 'required|string|max:200',
            'customer_data.number' => 'required|string|max:20',
            'customer_data.between_streets' => 'nullable|string|max:200',
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

        $total = $this->getCartTotal($cartItems);
        
        $message = "🛒 *NUEVO PEDIDO - CHISPAS FRÍAS*\n\n";

        $message .= "👤 *Datos del Cliente:*\n";
        $message .= "Nombre: {$customerData['name']} {$customerData['lastname']}\n";
        $message .= "DNI: {$customerData['dni']}\n";
        $message .= "Teléfono: {$customerData['phone']}\n";
        $message .= "Email: {$customerData['email']}\n\n";

        $message .= "📍 *Dirección de Entrega:*\n";
        $message .= "Provincia: {$customerData['province']}\n";
        $message .= "Dirección: {$customerData['address']} {$customerData['number']}\n";
        if (!empty($customerData['between_streets'])) {
            $message .= "Entre calles: {$customerData['between_streets']}\n";
        }
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
        
        $message .= "💰 *TOTAL: $" . number_format($total, 0, ',', '.') . "*\n\n";
        $message .= "📞 Por favor COMPLETA CON TU CIUDAD: .";

        $orderId = null;

        try {
            DB::transaction(function () use ($request, $customerData, $cartItems, $total, $message, &$orderId, $stockService) {
                $order = new Order([
                    'name' => $customerData['name'],
                    'lastname' => $customerData['lastname'],
                    'dni' => $customerData['dni'],
                    'province' => $customerData['province'],
                    'city' => $customerData['city'] ?? null,
                    'address' => $customerData['address'],
                    'number' => $customerData['number'],
                    'between_streets' => $customerData['between_streets'] ?? null,
                    'postal_code' => $customerData['postal_code'],
                    'phone' => $customerData['phone'],
                    'email' => $customerData['email'],
                    'observations' => $customerData['observations'] ?? null,
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
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'No pudimos registrar tu pedido. Por favor, intentá nuevamente.'
            ], 500);
        }

        // Vaciar el carrito una vez creada la orden y generado el mensaje
        session()->forget('cart');

        return response()->json([
            'success' => true,
            'message' => $message,
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