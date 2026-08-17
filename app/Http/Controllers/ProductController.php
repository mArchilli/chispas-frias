<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Category;
use App\Services\PriceResult;
use App\Services\PricingService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ProductController extends Controller
{
    public function __construct(private readonly PricingService $pricingService) {}

    /**
     * Mostrar la lista de productos para los clientes
     */
    public function index(Request $request)
    {
        $query = Product::query()
            ->with(['category.parent', 'images', 'currentOffer', 'priceTiers'])
            ->active()
            ->inStock()
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

        $products = $query->paginate(12)->withQueryString()->through(function (Product $product) {
            return [
                'id' => $product->id,
                'title' => $product->title,
                'description' => $product->description,
                'sku' => $product->sku,
                'stock' => $product->stock,
                'price' => (float) $product->price,
                'category' => $this->mapCategory($product->category),
                'images' => $this->mapImages($product->images),
                'pricing' => $this->buildPricingProp($product),
            ];
        });

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

        $product->load(['category.parent', 'images', 'currentOffer', 'priceTiers']);

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

        return Inertia::render('Products/Show', [
            'product' => $this->mapProductDetail($product),
            'relatedProducts' => $relatedProducts->map(function (Product $related) {
                return [
                    'id' => $related->id,
                    'title' => $related->title,
                    'price' => (float) $related->price,
                    'category' => $this->mapCategory($related->category),
                    'images' => $this->mapImages($related->images),
                    'pricing' => $this->buildPricingProp($related),
                ];
            }),
        ]);
    }

    /**
     * Payload completo de un producto para la ficha: datos base + price_tiers +
     * current_offer (solo los campos que necesita el mirror JS de PricingService,
     * ver resources/js/utils/pricing.js) + el precio "de entrada" (cantidad=1).
     */
    private function mapProductDetail(Product $product): array
    {
        return [
            'id' => $product->id,
            'title' => $product->title,
            'description' => $product->description,
            'sku' => $product->sku,
            'stock' => $product->stock,
            'price' => (float) $product->price,
            'category' => $this->mapCategory($product->category),
            'images' => $this->mapImages($product->images),
            'is_active' => $product->is_active,
            'price_tiers' => $product->priceTiers->map(fn ($tier) => [
                'id' => $tier->id,
                'cantidad_minima' => $tier->cantidad_minima,
                'precio_unitario' => (float) $tier->precio_unitario,
            ])->values(),
            'current_offer' => $product->currentOffer ? [
                'tipo_descuento' => $product->currentOffer->tipo_descuento?->value,
                'valor_descuento' => $product->currentOffer->valor_descuento !== null
                    ? (float) $product->currentOffer->valor_descuento
                    : null,
                'alcance' => $product->currentOffer->alcance?->value,
                'product_price_tier_id' => $product->currentOffer->product_price_tier_id,
            ] : null,
            'pricing' => $this->buildPricingProp($product),
        ];
    }

    /**
     * Precio "de entrada" (cantidad=1, el nivel base) resuelto con PricingService,
     * más un indicador de escalas por cantidad disponibles y el ahorro máximo
     * posible entre todas ellas — reemplaza los $appends legacy de Product
     * (current_price, discount_percentage, etc.) en todo el catálogo público.
     */
    private function buildPricingProp(Product $product): array
    {
        $result = $this->pricingService->calcularPrecio($product, 1);

        return [
            'list_price' => $result->precioLista,
            'final_price' => $result->precioUnitarioFinal,
            'has_discount' => $result->ofertaAplicada !== null,
            'savings_amount' => $result->ahorroUnitario,
            'savings_percentage' => $result->ahorroPorcentaje,
            'has_tiers' => $product->priceTiers->isNotEmpty(),
            'max_tier_savings_percentage' => $this->maxTierSavingsPercentage($product, $result),
        ];
    }

    /**
     * Mayor ahorro posible entre las escalas de precio del producto, comparado
     * contra el precio de entrada (cantidad=1). Null si no hay tiers o ninguno
     * resulta más barato que el precio de entrada.
     */
    private function maxTierSavingsPercentage(Product $product, PriceResult $baseResult): ?float
    {
        if ($product->priceTiers->isEmpty() || $baseResult->precioUnitarioFinal <= 0) {
            return null;
        }

        $mejorPrecio = $product->priceTiers
            ->map(fn ($tier) => $this->pricingService->calcularPrecio($product, $tier->cantidad_minima)->precioUnitarioFinal)
            ->min();

        if ($mejorPrecio >= $baseResult->precioUnitarioFinal) {
            return null;
        }

        return round((1 - $mejorPrecio / $baseResult->precioUnitarioFinal) * 100, 2);
    }

    private function mapCategory(?Category $category): ?array
    {
        if (!$category) {
            return null;
        }

        return [
            'id' => $category->id,
            'name' => $category->name,
            'slug' => $category->slug,
            'parent' => $category->parent ? [
                'id' => $category->parent->id,
                'name' => $category->parent->name,
                'slug' => $category->parent->slug,
            ] : null,
        ];
    }

    private function mapImages($images): array
    {
        return $images->map(function ($image) {
            return [
                'id' => $image->id,
                'path' => $image->path,
                'url' => $image->url,
                'alt_text' => $image->alt_text,
                'sort_order' => $image->sort_order,
                'is_primary' => $image->is_primary,
                'type' => $image->type,
                'mime_type' => $image->mime_type,
            ];
        })->values()->all();
    }
}
