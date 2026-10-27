<?php

namespace App\Http\Controllers;

use App\Models\CardPaymentPlan;
use App\Models\Combo;
use App\Models\Product;
use App\Models\Category;
use App\Support\ProductData;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ProductController extends Controller
{
    public function __construct(private readonly ProductData $productData) {}

    /**
     * Mostrar la lista de productos para los clientes
     */
    public function index(Request $request)
    {
        $query = Product::query()
            ->with(['category.parent', 'images', 'currentOffer', 'priceTiers', 'variantsActive', 'addonsActive'])
            ->active()
            // Los productos sin stock se muestran igual (con «Sin stock»), al final del listado. Un producto con
            // variantes está sin stock cuando ninguna variante activa tiene (Product::sqlDisponible()).
            ->orderByRaw('CASE WHEN '.Product::sqlDisponible().' THEN 0 ELSE 1 END')
            ->orderBy('created_at', 'desc');

        // Filtrar por categoría si se especifica
        if ($request->has('category') && $request->category) {
            $categorySlug = $request->category;
            
            $query->whereHas('category', function ($q) use ($categorySlug) {
                $q->where('slug', $categorySlug)
                  ->orWhere(function ($subQ) use ($categorySlug) {
                      // También buscar en categorías cuyo padre tenga este slug
                      $subQ->whereHas('parent', function ($parentQ) use ($categorySlug) {
                          $parentQ->where('slug', $categorySlug);
                      });
                  });
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

        $products = $query->paginate(12)->withQueryString()
            ->through(fn (Product $product) => $this->productData->detail($product));

        // Obtener categorías para filtros - solo aquellas que tienen productos activos
        $categories = Category::active()
            ->whereNull('parent_id')
            ->where(function($query) {
                // Categorías que tienen productos directamente O que tienen subcategorías con productos
                $query->whereHas('products', function ($subQuery) {
                    $subQuery->active();
                })->orWhereHas('children', function ($subQuery) {
                    $subQuery->active()
                        ->whereHas('products', function ($productQuery) {
                            $productQuery->active();
                        });
                });
            })
            ->with(['children' => function ($query) {
                $query->active()
                    ->whereHas('products', function ($subQuery) {
                        $subQuery->active();
                    })
                    ->orderBy('name');
            }])
            ->orderBy('name')
            ->get();

        // Determinar si hay subcategorías seleccionadas
        $selectedMainCategory = null;
        $selectedSubcategories = [];
        
        if ($request->has('category') && $request->category) {
            $selectedCategory = Category::where('slug', $request->category)->first();
            if ($selectedCategory) {
                if ($selectedCategory->parent_id) {
                    // Es una subcategoría
                    $selectedMainCategory = $selectedCategory->parent;
                    $selectedSubcategories = $selectedCategory->parent->children;
                } else {
                    // Es una categoría principal
                    $selectedMainCategory = $selectedCategory;
                    $selectedSubcategories = $selectedCategory->children;
                }
            }
        }

        // Combos: se muestran como sección diferenciada arriba del grid, sólo en
        // la vista por defecto del catálogo (sin filtro de categoría ni búsqueda),
        // porque cruzan categorías y no entran en la paginación de productos.
        $combos = (! $request->filled('category') && ! $request->filled('search'))
            ? Combo::active()
                ->with(['images', 'items.product.variantsActive'])
                ->orderBy('sort_order')
                ->orderByDesc('created_at')
                ->get()
                ->map(fn (Combo $combo) => [
                    'id' => $combo->id,
                    'title' => $combo->title,
                    'price' => (float) $combo->price,
                    'is_free_shipping' => $combo->is_free_shipping,
                    'image' => $combo->primaryImage()?->url,
                    // Sin columna propia: se calcula con los componentes (Combo::estaDisponible).
                    'sin_stock' => ! $combo->estaDisponible(),
                ])
                ->values()
            : collect();

        return Inertia::render('Products/Index', [
            'products' => $products,
            'combos' => $combos,
            'categories' => $categories,
            'selectedMainCategory' => $selectedMainCategory,
            'selectedSubcategories' => $selectedSubcategories,
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

        $product->load([
            'category.parent',
            'images',
            'currentOffer',
            'priceTiers',
            'variantsActive',
            'addonsActive',
        ]);

        // Productos relacionados - garantizar siempre 3 productos
        $relatedProducts = collect();
        $relatedWith = ['images', 'category.parent', 'currentOffer', 'priceTiers'];

        // 1. Primero buscar productos de la misma subcategoría
        $sameSubcategory = Product::active()
            ->inStock()
            ->where('category_id', $product->category_id)
            ->where('id', '!=', $product->id)
            ->with($relatedWith)
            ->limit(3)
            ->get();

        $relatedProducts = $relatedProducts->merge($sameSubcategory);

        // 2. Si no hay suficientes, buscar en la categoría padre (si existe)
        if ($relatedProducts->count() < 3 && $product->category->parent_id) {
            $parentCategoryProducts = Product::active()
                ->inStock()
                ->whereHas('category', function ($q) use ($product) {
                    $q->where('parent_id', $product->category->parent_id);
                })
                ->where('id', '!=', $product->id)
                ->whereNotIn('id', $relatedProducts->pluck('id'))
                ->with($relatedWith)
                ->limit(3 - $relatedProducts->count())
                ->get();

            $relatedProducts = $relatedProducts->merge($parentCategoryProducts);
        }

        // 3. Si aún no hay suficientes, buscar productos destacados
        if ($relatedProducts->count() < 3) {
            $featuredProducts = Product::active()
                ->inStock()
                ->featured()
                ->where('id', '!=', $product->id)
                ->whereNotIn('id', $relatedProducts->pluck('id'))
                ->with($relatedWith)
                ->limit(3 - $relatedProducts->count())
                ->get();

            $relatedProducts = $relatedProducts->merge($featuredProducts);
        }

        // 4. Si aún no hay suficientes, tomar productos aleatorios
        if ($relatedProducts->count() < 3) {
            $randomProducts = Product::active()
                ->inStock()
                ->where('id', '!=', $product->id)
                ->whereNotIn('id', $relatedProducts->pluck('id'))
                ->with($relatedWith)
                ->inRandomOrder()
                ->limit(3 - $relatedProducts->count())
                ->get();

            $relatedProducts = $relatedProducts->merge($randomProducts);
        }

        // Asegurar exactamente 3 productos y mezclar
        $relatedProducts = $relatedProducts->shuffle()->take(3)->values();

        $activePaymentPlans = CardPaymentPlan::active()->get();

        return Inertia::render('Products/Show', [
            'product' => $this->productData->detail($product),
            // Planes de cuotas activos para la información de cuotas de la ficha ("o hasta N cuotas con tarjeta"). 100% informativo: no se
            // elige nada. El total depende de la cantidad y las opciones que el cliente va eligiendo en la ficha, así que el mirror JS
            // (utils/cardSurcharge.js) calcula el detalle sin ir al servidor en cada cambio; en el carrito y el checkout lo calcula el servidor.
            'cardPaymentPlans' => $activePaymentPlans
                ->map(fn (CardPaymentPlan $plan) => [
                    'id' => $plan->id,
                    'name' => $plan->name,
                    'installments' => $plan->installments,
                    'surcharge_percentage' => (float) $plan->surcharge_percentage,
                ])
                ->values(),
            'relatedProducts' => $relatedProducts->map(fn (Product $related) => $this->productData->summary($related)),
        ]);
    }
}
