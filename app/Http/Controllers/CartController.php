<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class CartController extends Controller
{
    /**
     * Obtener items del carrito desde sesión
     */
    private function getCartItems()
    {
        $sessionCart = session('cart', []);
        $cartItems = collect();
        
        foreach ($sessionCart as $productId => $quantity) {
            $product = Product::with(['images', 'currentOffer'])->find($productId);
            if ($product) {
                $currentPrice = $product->getCurrentPrice();
                $cartItems->push([
                    'id' => $productId,
                    'product' => $product,
                    'quantity' => $quantity,
                    'price' => $currentPrice,
                    'subtotal' => $quantity * $currentPrice
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
            return response()->json([
                'success' => true,
                'message' => $message,
                'subtotal' => $quantity * $product->getCurrentPrice(),
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
    public function generateWhatsAppMessage(Request $request): JsonResponse
    {
        $cartItems = $this->getCartItems();
        
        if ($cartItems->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'El carrito está vacío.'
            ], 422);
        }

        // Validar datos del formulario si están presentes
        $customerData = [];
        if ($request->has('customer_data')) {
            $request->validate([
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
        }

        $total = $this->getCartTotal($cartItems);
        
        $message = "🛒 *NUEVO PEDIDO - CHISPAS FRÍAS*\n\n";
        
        // Si hay datos del cliente, incluirlos
        if (!empty($customerData)) {
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
        }
        
        $message .= "📋 *Detalle del pedido:*\n";
        
        foreach ($cartItems as $item) {
            $product = $item['product'];
            $currentPrice = $item['price'];
            $originalPrice = $product->price;
            
            $message .= "• {$product['title']}\n";
            $message .= "  Cantidad: {$item['quantity']}\n";
            
            // Mostrar precio original y de oferta si aplica
            if ($product->hasActiveOffer()) {
                $message .= "  Precio original: $" . number_format($originalPrice, 0, ',', '.') . "\n";
                $message .= "  Precio oferta: $" . number_format($currentPrice, 0, ',', '.') . "\n";
                $message .= "  ¡Descuento del {$product->discount_percentage}%!\n";
            } else {
                $message .= "  Precio: $" . number_format($currentPrice, 0, ',', '.') . "\n";
            }
            
            $message .= "  Subtotal: $" . number_format($item['subtotal'], 0, ',', '.') . "\n\n";
        }
        
        $message .= "💰 *TOTAL: $" . number_format($total, 0, ',', '.') . "*\n\n";
        $message .= "📞 Por favor COMPLETA CON TU CIUDAD: .";

        return response()->json([
            'success' => true,
            'message' => $message,
            'total' => $total,
            'itemCount' => $cartItems->count()
        ]);
    }
}