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
            $product = Product::with('images')->find($productId);
            if ($product) {
                $cartItems->push([
                    'id' => $productId,
                    'product' => $product,
                    'quantity' => $quantity,
                    'subtotal' => $quantity * $product->price
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
        $product = Product::findOrFail($productId);
        
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

        $product = Product::findOrFail($productId);

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
                'subtotal' => $quantity * $product->price,
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
     * Generar mensaje para WhatsApp
     */
    public function generateWhatsAppMessage(): JsonResponse
    {
        $cartItems = $this->getCartItems();
        
        if ($cartItems->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'El carrito está vacío.'
            ], 422);
        }

        $total = $this->getCartTotal($cartItems);
        
        $message = "🛒 *NUEVO PEDIDO - CHISPAS FRÍAS*\n\n";
        $message .= "📋 *Detalle del pedido:*\n";
        
        foreach ($cartItems as $item) {
            $message .= "• {$item['product']['title']}\n";
            $message .= "  Cantidad: {$item['quantity']}\n";
            $message .= "  Precio: $" . number_format($item['product']['price'], 0, ',', '.') . "\n";
            $message .= "  Subtotal: $" . number_format($item['subtotal'], 0, ',', '.') . "\n\n";
        }
        
        $message .= "💰 *TOTAL: $" . number_format($total, 0, ',', '.') . "*\n\n";
        $message .= "📞 Por favor confirmar disponibilidad y coordinar entrega.";

        return response()->json([
            'success' => true,
            'message' => $message,
            'total' => $total,
            'itemCount' => $cartItems->count()
        ]);
    }
}