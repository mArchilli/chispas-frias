<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AlcanceOferta;
use App\Http\Controllers\Admin\Concerns\SyncsOfferDiscountFields;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductOffer;
use App\Services\PricingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;

class ProductOfferAdminController extends Controller
{
    use SyncsOfferDiscountFields;

    public function __construct(protected readonly PricingService $pricingService) {}

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $offers = ProductOffer::with(['product', 'priceTier'])
            ->latest()
            ->paginate(15);

        $products = Product::with('priceTiers')
            ->select('id', 'title', 'price')
            ->orderBy('title')
            ->get()
            ->map(fn (Product $product) => [
                'id' => $product->id,
                'title' => $product->title,
                'price' => (float) $product->price,
                'price_tiers' => $product->priceTiers->map(fn ($tier) => [
                    'id' => $tier->id,
                    'cantidad_minima' => $tier->cantidad_minima,
                    'precio_unitario' => (float) $tier->precio_unitario,
                ])->values(),
            ]);

        return Inertia::render('Admin/Offers/Index', [
            'offers' => $offers,
            'products' => $products
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
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

            return back()->with('success', 'Oferta creada exitosamente');

        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Error al crear la oferta: ' . $e->getMessage()]);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, ProductOffer $offer)
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

            return back()->with('success', 'Oferta actualizada exitosamente');

        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Error al actualizar la oferta: ' . $e->getMessage()]);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(ProductOffer $offer)
    {
        try {
            $offer->delete();
            return back()->with('success', 'Oferta eliminada exitosamente');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Error al eliminar la oferta: ' . $e->getMessage()]);
        }
    }

    /**
     * Toggle offer status
     */
    public function toggleStatus(ProductOffer $offer)
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
