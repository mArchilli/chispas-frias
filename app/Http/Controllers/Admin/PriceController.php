<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\Request;

class PriceController extends Controller
{
    /**
     * Listado de precios de productos, de solo lectura. Es el único punto de
     * acceso del vendedor al catálogo de precios (ver Gate 'gestionar-productos'
     * en routes/web.php, que le reserva el ABM de productos al admin).
     */
    public function index(Request $request): Response
    {
        $search = $request->get('search');
        $categoryFilter = $request->get('category');

        $selectedCategory = $categoryFilter ? Category::find($categoryFilter) : null;

        $query = Product::with(['category.parent', 'images', 'currentOffer', 'priceTiers']);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhere('sku', 'like', "%{$search}%");
            });
        }

        if ($selectedCategory) {
            if ($selectedCategory->parent_id) {
                // Subcategoría: sólo sus productos.
                $query->where('category_id', $selectedCategory->id);
            } else {
                // Categoría principal: sus productos + los de todas sus subcategorías.
                $query->where(function ($q) use ($selectedCategory) {
                    $q->where('category_id', $selectedCategory->id)
                      ->orWhereHas('category', function ($subQ) use ($selectedCategory) {
                          $subQ->where('parent_id', $selectedCategory->id);
                      });
                });
            }
        }

        $products = $query->orderBy('title')
            ->paginate(20)
            ->through(function ($product) {
                return [
                    'id' => $product->id,
                    'title' => $product->title,
                    'sku' => $product->sku,
                    'price' => $product->price,
                    'formatted_price' => $product->formatted_price,
                    'category' => [
                        'id' => $product->category->id,
                        'name' => $product->category->name,
                        'parent_name' => $product->category->parent?->name,
                    ],
                    'primary_image' => $product->primaryImage()?->path,
                    'is_active' => $product->is_active,
                    'price_tiers' => $product->priceTiers->map(fn ($tier) => [
                        'id' => $tier->id,
                        'cantidad_minima' => $tier->cantidad_minima,
                        'precio_unitario' => (float) $tier->precio_unitario,
                    ])->values(),
                    'current_offer' => $product->currentOffer ? [
                        'formatted_offer_price' => $product->currentOffer->offer_price !== null
                            ? '$' . number_format($product->currentOffer->offer_price, 0, ',', '.')
                            : null,
                        'percentage_discount' => $product->currentOffer->percentage_discount,
                    ] : null,
                    'has_active_offer' => $product->hasActiveOffer(),
                ];
            });

        // Categorías principales con sus subcategorías, para el filtro en dos
        // niveles del front (mismo criterio que el catálogo público, ver
        // ProductController@index): primero se listan las principales, y al
        // elegir una se listan sus subcategorías.
        $categories = Category::active()
            ->main()
            ->with(['children' => fn ($q) => $q->active()->orderBy('name')])
            ->orderBy('name')
            ->get(['id', 'name', 'parent_id']);

        // Si la categoría elegida es una subcategoría, la principal a mostrar
        // seleccionada (y de la que listar hermanas) es su padre.
        $selectedMainCategory = null;
        $selectedSubcategories = [];

        if ($selectedCategory) {
            $selectedMainCategory = $selectedCategory->parent_id
                ? $selectedCategory->parent
                : $selectedCategory;
            $selectedSubcategories = Category::active()
                ->where('parent_id', $selectedMainCategory->id)
                ->orderBy('name')
                ->get(['id', 'name', 'parent_id']);
        }

        return Inertia::render('Admin/Prices/Index', [
            'products' => $products,
            'categories' => $categories,
            'selectedMainCategory' => $selectedMainCategory,
            'selectedSubcategories' => $selectedSubcategories,
            'filters' => [
                'search' => $search ?? '',
                'category' => $categoryFilter ?? '',
            ],
        ]);
    }
}
