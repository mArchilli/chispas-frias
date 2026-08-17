<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AlcanceOferta;
use App\Http\Controllers\Admin\Concerns\SyncsOfferDiscountFields;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductOffer;
use App\Services\PricingService;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;
use Inertia\Response;

class ProductOfferAdminController extends Controller
{
    use SyncsOfferDiscountFields;

    public function __construct(protected readonly PricingService $pricingService) {}

    /**
     * Listado de ofertas con métricas y filtros por estado/producto.
     */
    public function index(Request $request): Response
    {
        $search = $request->get('search');
        $status = $request->get('status');
        $now = now();

        $query = ProductOffer::with(['product', 'priceTier']);

        if ($search) {
            $query->whereHas('product', fn ($q) => $q->where('title', 'like', "%{$search}%"));
        }

        if ($status) {
            match ($status) {
                'activa' => $query->active(),
                'programada' => $query->where('is_active', true)->where('start_date', '>', $now),
                'expirada' => $query->where('is_active', true)->whereNotNull('end_date')->where('end_date', '<', $now),
                'inactiva' => $query->where('is_active', false),
                default => null,
            };
        }

        $offers = $query->latest()
            ->paginate(12)
            ->withQueryString()
            ->through(fn (ProductOffer $offer) => [
                'id' => $offer->id,
                'offer_price' => $offer->offer_price,
                'formatted_offer_price' => $offer->offer_price !== null
                    ? '$' . number_format((float) $offer->offer_price, 0, ',', '.')
                    : null,
                'percentage_discount' => $offer->percentage_discount,
                'alcance' => $offer->alcance?->value,
                'price_tier' => $offer->priceTier ? [
                    'cantidad_minima' => $offer->priceTier->cantidad_minima,
                ] : null,
                'start_date' => $offer->start_date,
                'end_date' => $offer->end_date,
                'is_active' => $offer->is_active,
                'status' => $this->resolveStatus($offer, $now),
                'product' => [
                    'id' => $offer->product->id,
                    'title' => $offer->product->title,
                    'price' => (float) $offer->product->price,
                    'formatted_price' => $offer->product->formatted_price,
                    'primary_image' => $offer->product->primaryImage()?->path,
                ],
            ]);

        return Inertia::render('Admin/Offers/Index', [
            'offers' => $offers,
            'stats' => $this->buildStats($now),
            'filters' => [
                'search' => $search ?? '',
                'status' => $status ?? '',
            ],
        ]);
    }

    /**
     * Formulario de creación: lista de productos (con imagen y categoría,
     * para el carrusel con filtros) y sus escalas de precio, para que
     * OfferDiscountFields arme el selector de alcance.
     */
    public function create(): Response
    {
        return Inertia::render('Admin/Offers/Create', [
            'products' => $this->productsForForm(),
            'categories' => Category::active()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $validator = Validator::make($request->all(), array_merge(
            [
                'product_id' => 'required|exists:products,id',
                'start_date' => 'nullable|date',
                'end_date' => 'nullable|date|after_or_equal:start_date',
                'is_active' => 'boolean',
            ],
            $this->offerDiscountRules($request->all(), (int) $request->input('product_id'))
        ));

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $validated = $validator->validated();

        try {
            $product = Product::findOrFail($validated['product_id']);

            $isActive = $request->boolean('is_active', true);

            if ($isActive) {
                $product->offers()->where('is_active', true)->update(['is_active' => false]);
            }

            $offer = new ProductOffer(array_merge(
                ['product_id' => $product->id],
                $this->offerAttributes($validated, $isActive)
            ));
            $this->applyOfferDiscountMirror($product, $offer);
            $offer->save();

            return redirect()->route('admin.offers.index')->with('success', 'Oferta creada exitosamente');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Error al crear la oferta: ' . $e->getMessage()])->withInput();
        }
    }

    /**
     * Formulario de edición.
     */
    public function edit(ProductOffer $offer): Response
    {
        $offer->load('product.priceTiers');
        $product = $offer->product;

        return Inertia::render('Admin/Offers/Edit', [
            'offer' => [
                'id' => $offer->id,
                'product_id' => $offer->product_id,
                'tipo_descuento' => $offer->tipo_descuento?->value,
                'valor_descuento' => $offer->valor_descuento,
                'alcance' => $offer->alcance?->value,
                'product_price_tier_id' => $offer->product_price_tier_id,
                'start_date' => $offer->start_date,
                'end_date' => $offer->end_date,
                'is_active' => $offer->is_active,
            ],
            'product' => [
                'id' => $product->id,
                'title' => $product->title,
                'price' => (float) $product->price,
                'price_tiers' => $product->priceTiers->map(fn ($tier) => [
                    'id' => $tier->id,
                    'cantidad_minima' => $tier->cantidad_minima,
                    'precio_unitario' => (float) $tier->precio_unitario,
                ])->values(),
            ],
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, ProductOffer $offer): RedirectResponse
    {
        $product = $offer->product;

        $validator = Validator::make($request->all(), array_merge(
            [
                'start_date' => 'nullable|date',
                'end_date' => 'nullable|date|after_or_equal:start_date',
                'is_active' => 'boolean',
            ],
            $this->offerDiscountRules($request->all(), $product->id)
        ));

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $validated = $validator->validated();

        try {
            $isActive = $request->boolean('is_active', true);

            if ($isActive && !$offer->is_active) {
                $product->offers()
                    ->where('id', '!=', $offer->id)
                    ->where('is_active', true)
                    ->update(['is_active' => false]);
            }

            $offer->fill($this->offerAttributes($validated, $isActive));
            $this->applyOfferDiscountMirror($product, $offer);
            $offer->save();

            return redirect()->route('admin.offers.index')->with('success', 'Oferta actualizada exitosamente');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Error al actualizar la oferta: ' . $e->getMessage()])->withInput();
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(ProductOffer $offer): RedirectResponse
    {
        try {
            $offer->delete();
            return redirect()->route('admin.offers.index')->with('success', 'Oferta eliminada exitosamente');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Error al eliminar la oferta: ' . $e->getMessage()]);
        }
    }

    /**
     * Toggle offer status
     */
    public function toggleStatus(ProductOffer $offer): RedirectResponse
    {
        try {
            $offer->update(['is_active' => !$offer->is_active]);

            $status = $offer->is_active ? 'activada' : 'desactivada';
            return back()->with('success', "Oferta {$status} exitosamente");
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Error al cambiar estado de la oferta: ' . $e->getMessage()]);
        }
    }

    /**
     * Productos con sus escalas de precio, imagen principal y categoría,
     * para el carrusel con filtros de Create y los selects de Create/Edit.
     * `has_active_offer` avisa en el form si el producto ya tiene una
     * oferta vigente (crear una nueva la reemplaza).
     */
    private function productsForForm()
    {
        return Product::with(['priceTiers', 'category'])
            ->withCount(['offers as active_offers_count' => fn ($q) => $q->active()])
            ->select('id', 'title', 'price', 'category_id')
            ->orderBy('title')
            ->get()
            ->map(fn (Product $product) => [
                'id' => $product->id,
                'title' => $product->title,
                'price' => (float) $product->price,
                'primary_image' => $product->primaryImage()?->path,
                'category' => $product->category ? [
                    'id' => $product->category->id,
                    'name' => $product->category->name,
                ] : null,
                'has_active_offer' => $product->active_offers_count > 0,
                'price_tiers' => $product->priceTiers->map(fn ($tier) => [
                    'id' => $tier->id,
                    'cantidad_minima' => $tier->cantidad_minima,
                    'precio_unitario' => (float) $tier->precio_unitario,
                ])->values(),
            ]);
    }

    /**
     * Estado visual de una oferta. Debe coincidir con la lógica que antes
     * vivía en el frontend (Admin/Offers/Index) para no romper el criterio
     * ya conocido por quien usa el panel: Inactiva pisa a las demás, después
     * Programada (todavía no arrancó) y Expirada (ya venció).
     */
    private function resolveStatus(ProductOffer $offer, \Illuminate\Support\Carbon $now): string
    {
        if (!$offer->is_active) {
            return 'inactiva';
        }
        if ($offer->start_date && $offer->start_date->gt($now)) {
            return 'programada';
        }
        if ($offer->end_date && $offer->end_date->lt($now)) {
            return 'expirada';
        }
        return 'activa';
    }

    /**
     * Métricas para el panel de ofertas: conteo por estado y descuento
     * promedio de las ofertas vigentes ahora mismo.
     */
    private function buildStats(\Illuminate\Support\Carbon $now): array
    {
        $avgDiscount = ProductOffer::active()->avg('percentage_discount');

        return [
            'total' => ProductOffer::count(),
            'active' => ProductOffer::active()->count(),
            'scheduled' => ProductOffer::where('is_active', true)->where('start_date', '>', $now)->count(),
            'expired' => ProductOffer::where('is_active', true)
                ->whereNotNull('end_date')
                ->where('end_date', '<', $now)
                ->count(),
            'inactive' => ProductOffer::where('is_active', false)->count(),
            'avg_discount' => $avgDiscount !== null ? round($avgDiscount) : null,
        ];
    }

    /**
     * Atributos comunes a store/update, a partir de los datos ya validados.
     * product_price_tier_id se fuerza a null cuando alcance=Todos porque
     * PricingService lo ignora en ese caso (ver PricingService::ofertaAplica()).
     */
    private function offerAttributes(array $validated, bool $isActive): array
    {
        return [
            'tipo_descuento' => $validated['tipo_descuento'],
            'valor_descuento' => $validated['valor_descuento'],
            'alcance' => $validated['alcance'],
            'product_price_tier_id' => $validated['alcance'] === AlcanceOferta::Especifico->value
                ? ($validated['product_price_tier_id'] ?? null)
                : null,
            'start_date' => $validated['start_date'] ?? null,
            'end_date' => $validated['end_date'] ?? null,
            'is_active' => $isActive,
        ];
    }
}
