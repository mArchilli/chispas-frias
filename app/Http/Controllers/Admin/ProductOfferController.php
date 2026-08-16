<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AlcanceOferta;
use App\Http\Controllers\Admin\Concerns\SyncsOfferDiscountFields;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductOffer;
use App\Services\PricingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProductOfferController extends Controller
{
    use SyncsOfferDiscountFields;

    public function __construct(protected readonly PricingService $pricingService) {}

    /**
     * Crear o actualizar una oferta para un producto
     */
    public function store(Request $request, Product $product): RedirectResponse
    {
        $validated = $request->validate(array_merge(
            $this->offerDiscountRules($request->all(), $product->id),
            [
                'start_date' => 'nullable|date|after_or_equal:today',
                'end_date' => 'nullable|date|after:start_date',
            ]
        ));

        // Desactivar ofertas previas
        $product->offers()->where('is_active', true)->update(['is_active' => false]);

        $offer = $this->buildOffer($product, $validated, true);
        $this->applyOfferDiscountMirror($product, $offer);
        $offer->save();

        return back()->with('success', 'Oferta creada exitosamente.');
    }

    /**
     * Actualizar una oferta existente
     */
    public function update(Request $request, ProductOffer $offer): RedirectResponse
    {
        $product = $offer->product;

        $validated = $request->validate(array_merge(
            $this->offerDiscountRules($request->all(), $product->id),
            [
                'start_date' => 'nullable|date',
                'end_date' => 'nullable|date|after:start_date',
                'is_active' => 'boolean',
            ]
        ));

        // Determinar si la oferta debe estar activa
        $isActive = $request->boolean('is_active', true);

        // Si se está activando esta oferta, desactivar otras ofertas activas del mismo producto
        if ($isActive && !$offer->is_active) {
            $offer->product->offers()
                ->where('id', '!=', $offer->id)
                ->where('is_active', true)
                ->update(['is_active' => false]);
        }

        $offer->fill($this->offerAttributes($validated, $isActive));
        $this->applyOfferDiscountMirror($product, $offer);
        $offer->save();

        $status = $isActive ? 'activa' : 'inactiva';
        return back()->with('success', "Oferta actualizada exitosamente ({$status}).");
    }

    /**
     * Eliminar oferta activa de un producto
     */
    public function destroy(Product $product): RedirectResponse
    {
        $activeOffer = $product->offers()->active()->first();

        if (!$activeOffer) {
            return back()->with('error', 'No hay ofertas activas para eliminar.');
        }

        $activeOffer->update(['is_active' => false]);

        return back()->with('success', 'Oferta eliminada exitosamente.');
    }

    /**
     * Activar/desactivar una oferta específica
     */
    public function toggle(ProductOffer $offer): RedirectResponse
    {
        if ($offer->is_active) {
            // Desactivar esta oferta
            $offer->update(['is_active' => false]);
            $message = 'Oferta desactivada.';
        } else {
            // Activar esta oferta y desactivar otras del mismo producto
            $offer->product->offers()->where('id', '!=', $offer->id)->update(['is_active' => false]);
            $offer->update(['is_active' => true]);
            $message = 'Oferta activada.';
        }

        return back()->with('success', $message);
    }

    /**
     * Crear oferta rápida con campos opcionales (modal desde Products/Index)
     */
    public function quickOffer(Request $request, Product $product): RedirectResponse
    {
        $validated = $request->validate(array_merge(
            $this->offerDiscountRules($request->all(), $product->id),
            [
                'start_date' => 'nullable|date',
                'end_date' => 'nullable|date|after:start_date',
                'is_active' => 'boolean',
            ]
        ));

        // Determinar si la oferta debe estar activa
        $isActive = $request->boolean('is_active', true);

        // Si se está activando una nueva oferta, desactivar ofertas previas activas
        if ($isActive) {
            $product->offers()->where('is_active', true)->update(['is_active' => false]);
        }

        $offer = $this->buildOffer($product, $validated, $isActive);
        $this->applyOfferDiscountMirror($product, $offer);
        $offer->save();

        $status = $isActive ? 'activa' : 'inactiva';
        return back()->with('success', "Oferta creada exitosamente ({$status}).");
    }

    private function buildOffer(Product $product, array $validated, bool $isActive): ProductOffer
    {
        return new ProductOffer(array_merge(
            ['product_id' => $product->id],
            $this->offerAttributes($validated, $isActive)
        ));
    }

    /**
     * Atributos comunes a store/update/quickOffer, a partir de los datos ya
     * validados. product_price_tier_id se fuerza a null cuando alcance=Todos
     * porque PricingService lo ignora en ese caso (ver ofertaAplica()).
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
