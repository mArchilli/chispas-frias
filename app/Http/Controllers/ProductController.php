<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Category;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ProductController extends Controller
{
    /**
     * Mostrar la lista de productos para los clientes
     */
    public function index(Request $request)
    {
        $query = Product::query()
            ->with(['category.parent', 'images'])
            ->active()
            ->orderBy('created_at', 'desc');

        // Filtrar por categoría si se especifica
        if ($request->has('category') && $request->category) {
            $query->whereHas('category', function ($q) use ($request) {
                $q->where('slug', $request->category);
            });
        }

        // Filtrar por búsqueda
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $products = $query->paginate(12)->withQueryString();

        // Obtener categorías para filtros
        $categories = Category::active()
            ->whereNull('parent_id')
            ->with('children')
            ->orderBy('name')
            ->get();

        return Inertia::render('Products/Index', [
            'products' => $products,
            'categories' => $categories,
            'filters' => $request->only(['category', 'search'])
        ]);
    }

    /**
     * Mostrar un producto específico
     */
    public function show(Product $product)
    {
        // Solo mostrar productos activos
        if (!$product->is_active) {
            abort(404);
        }

        $product->load(['category.parent', 'images']);

        // Productos relacionados (de la misma categoría)
        $relatedProducts = Product::active()
            ->where('category_id', $product->category_id)
            ->where('id', '!=', $product->id)
            ->with(['images'])
            ->limit(4)
            ->get();

        return Inertia::render('Products/Show', [
            'product' => $product,
            'relatedProducts' => $relatedProducts
        ]);
    }
}