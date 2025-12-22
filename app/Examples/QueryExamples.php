<?php

namespace App\Examples;

use App\Models\Category;
use App\Models\Product;

/**
 * Ejemplos de consultas Eloquent para el sistema de categorías y productos
 */
class QueryExamples
{
    /**
     * 1. OBTENER CATEGORÍAS PRINCIPALES
     */
    public function getMainCategories()
    {
        // Opción 1: Usando scope
        $mainCategories = Category::main()
            ->active()
            ->orderBy('sort_order')
            ->get();

        // Opción 2: Usando whereNull
        $mainCategories = Category::whereNull('parent_id')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        // Opción 3: Con subcategorías incluidas
        $mainCategoriesWithChildren = Category::main()
            ->active()
            ->with(['activeChildren'])
            ->orderBy('sort_order')
            ->get();

        return $mainCategoriesWithChildren;
    }

    /**
     * 2. OBTENER SUBCATEGORÍAS
     */
    public function getSubcategories($parentCategoryId = null)
    {
        // Subcategorías de una categoría específica
        if ($parentCategoryId) {
            return Category::where('parent_id', $parentCategoryId)
                ->active()
                ->orderBy('sort_order')
                ->get();
        }

        // Todas las subcategorías
        $allSubcategories = Category::subcategories()
            ->active()
            ->with('parent')
            ->orderBy('sort_order')
            ->get();

        // Subcategorías agrupadas por categoría padre
        $subcategoriesByParent = Category::subcategories()
            ->active()
            ->with('parent')
            ->get()
            ->groupBy('parent.name');

        return $subcategoriesByParent;
    }

    /**
     * 3. OBTENER UN PRODUCTO CON SU CATEGORÍA Y CATEGORÍA PADRE
     */
    public function getProductWithCategories($productId)
    {
        // Opción 1: Producto con categoría y padre
        $product = Product::with(['category.parent', 'images'])
            ->find($productId);

        // Opción 2: Con información más detallada
        $productDetailed = Product::with([
            'category' => function($query) {
                $query->with('parent');
            },
            'images' => function($query) {
                $query->orderBy('sort_order');
            }
        ])->find($productId);

        // Acceder a la información
        if ($product) {
            $categoryName = $product->category->name;
            $parentCategoryName = $product->category->parent?->name;
            $fullPath = $product->category->full_path;
            $primaryImage = $product->primaryImage();
        }

        return $product;
    }

    /**
     * 4. OBTENER PRODUCTOS POR CATEGORÍA
     */
    public function getProductsByCategory($categorySlug)
    {
        $category = Category::where('slug', $categorySlug)->first();

        if (!$category) {
            return collect();
        }

        // Solo productos de esta categoría
        $products = $category->activeProducts()
            ->with(['images', 'category'])
            ->orderBy('created_at', 'desc')
            ->get();

        // Si es categoría principal, incluir productos de subcategorías
        if ($category->isMain()) {
            $allProducts = Product::whereHas('category', function($query) use ($category) {
                $query->where('id', $category->id)
                      ->orWhere('parent_id', $category->id);
            })
            ->active()
            ->with(['images', 'category.parent'])
            ->orderBy('created_at', 'desc')
            ->get();

            return $allProducts;
        }

        return $products;
    }

    /**
     * 5. BÚSQUEDA AVANZADA DE PRODUCTOS
     */
    public function searchProducts($searchTerm, $categoryId = null, $minPrice = null, $maxPrice = null)
    {
        $query = Product::query()
            ->with(['category.parent', 'images'])
            ->active();

        // Búsqueda por término
        if ($searchTerm) {
            $query->where(function($q) use ($searchTerm) {
                $q->where('title', 'LIKE', "%{$searchTerm}%")
                  ->orWhere('description', 'LIKE', "%{$searchTerm}%");
            });
        }

        // Filtro por categoría
        if ($categoryId) {
            $category = Category::find($categoryId);
            if ($category && $category->isMain()) {
                // Si es categoría principal, incluir subcategorías
                $query->whereHas('category', function($q) use ($categoryId) {
                    $q->where('id', $categoryId)
                      ->orWhere('parent_id', $categoryId);
                });
            } else {
                $query->where('category_id', $categoryId);
            }
        }

        // Filtro por precio
        if ($minPrice) {
            $query->where('price', '>=', $minPrice);
        }
        if ($maxPrice) {
            $query->where('price', '<=', $maxPrice);
        }

        return $query->orderBy('title')->get();
    }

    /**
     * 6. OBTENER PRODUCTOS DESTACADOS POR CATEGORÍA
     */
    public function getFeaturedProductsByCategory()
    {
        $categoriesWithFeatured = Category::main()
            ->active()
            ->with([
                'activeProducts' => function($query) {
                    $query->featured()
                          ->with('images')
                          ->limit(4);
                }
            ])
            ->get()
            ->filter(function($category) {
                return $category->activeProducts->count() > 0;
            });

        return $categoriesWithFeatured;
    }

    /**
     * 7. ESTADÍSTICAS DE CATEGORÍAS
     */
    public function getCategoryStats()
    {
        $stats = Category::main()
            ->active()
            ->withCount([
                'children as subcategories_count',
                'products as direct_products_count'
            ])
            ->get()
            ->map(function($category) {
                // Contar productos en subcategorías
                $subcategoryProductsCount = Product::whereHas('category', function($query) use ($category) {
                    $query->where('parent_id', $category->id);
                })->count();

                $category->total_products_count = $category->direct_products_count + $subcategoryProductsCount;
                
                return $category;
            });

        return $stats;
    }
}