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

        $products = $query->paginate(12)->withQueryString();

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

        return Inertia::render('Products/Index', [
            'products' => $products,
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

        $product->load(['category.parent', 'images']);

        // Productos relacionados - garantizar siempre 3 productos
        $relatedProducts = collect();
        
        // 1. Primero buscar productos de la misma subcategoría
        $sameSubcategory = Product::active()
            ->where('category_id', $product->category_id)
            ->where('id', '!=', $product->id)
            ->with(['images', 'category'])
            ->limit(3)
            ->get();
        
        $relatedProducts = $relatedProducts->merge($sameSubcategory);
        
        // 2. Si no hay suficientes, buscar en la categoría padre (si existe)
        if ($relatedProducts->count() < 3 && $product->category->parent_id) {
            $parentCategoryProducts = Product::active()
                ->whereHas('category', function ($q) use ($product) {
                    $q->where('parent_id', $product->category->parent_id);
                })
                ->where('id', '!=', $product->id)
                ->whereNotIn('id', $relatedProducts->pluck('id'))
                ->with(['images', 'category'])
                ->limit(3 - $relatedProducts->count())
                ->get();
            
            $relatedProducts = $relatedProducts->merge($parentCategoryProducts);
        }
        
        // 3. Si aún no hay suficientes, buscar productos destacados
        if ($relatedProducts->count() < 3) {
            $featuredProducts = Product::active()
                ->featured()
                ->where('id', '!=', $product->id)
                ->whereNotIn('id', $relatedProducts->pluck('id'))
                ->with(['images', 'category'])
                ->limit(3 - $relatedProducts->count())
                ->get();
            
            $relatedProducts = $relatedProducts->merge($featuredProducts);
        }
        
        // 4. Si aún no hay suficientes, tomar productos aleatorios
        if ($relatedProducts->count() < 3) {
            $randomProducts = Product::active()
                ->where('id', '!=', $product->id)
                ->whereNotIn('id', $relatedProducts->pluck('id'))
                ->with(['images', 'category'])
                ->inRandomOrder()
                ->limit(3 - $relatedProducts->count())
                ->get();
            
            $relatedProducts = $relatedProducts->merge($randomProducts);
        }
        
        // Asegurar exactamente 3 productos y mezclar
        $relatedProducts = $relatedProducts->shuffle()->take(3);

        return Inertia::render('Products/Show', [
            'product' => $product,
            'relatedProducts' => $relatedProducts
        ]);
    }
}