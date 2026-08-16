<?php

namespace App\Http\Controllers\Admin\Concerns;

use App\Enums\AlcanceOferta;
use App\Enums\TipoDescuento;
use App\Models\Product;
use App\Models\ProductOffer;
use Illuminate\Validation\Rule;

/**
 * Usado por ProductOfferController (modal rápido desde Products/Index) y por
 * ProductOfferAdminController (vista dedicada Admin/Offers/Index) — los dos
 * puntos de entrada de ofertas del admin, para que ambos guarden el mismo
 * contrato de campos y mantengan offer_price/percentage_discount sincronizados.
 *
 * Requiere que la clase que la usa tenga una propiedad `$this->pricingService`
 * (PricingService inyectado por constructor).
 */
trait SyncsOfferDiscountFields
{
    /**
     * Reglas de validación para los campos que determinan el descuento.
     * `offer_price`/`percentage_discount` ya no se validan como input del
     * formulario: se recalculan siempre a partir de estos campos (ver
     * applyOfferDiscountMirror).
     */
    protected function offerDiscountRules(array $data, int $productId): array
    {
        return [
            'tipo_descuento' => ['required', Rule::enum(TipoDescuento::class)],
            'valor_descuento' => [
                'required',
                'numeric',
                'min:0.01',
                function ($attribute, $value, $fail) use ($data) {
                    if (($data['tipo_descuento'] ?? null) === TipoDescuento::Porcentaje->value && (float) $value > 100) {
                        $fail('El descuento porcentual no puede superar el 100%.');
                    }
                },
            ],
            'alcance' => ['required', Rule::enum(AlcanceOferta::class)],
            // Nullable a propósito: alcance=Especifico con product_price_tier_id=null
            // es un estado válido ("aplica solo al precio base"), no un error —
            // ver PricingServiceTest::test_oferta_especifica_al_precio_base_*.
            'product_price_tier_id' => [
                'nullable',
                'integer',
                Rule::exists('product_price_tiers', 'id')->where('product_id', $productId),
            ],
        ];
    }

    /**
     * Recalcula offer_price/percentage_discount como espejo derivado de
     * tipo_descuento+valor_descuento+alcance, para no romper el catálogo
     * legacy (Fase C5) que todavía lee esos campos. Fuerza `currentOffer` a
     * ser esta oferta (recién armada, puede no estar guardada todavía) para
     * que PricingService la evalúe sin depender de si is_active/las fechas
     * la harían "vigente" en este momento — el mirror es sobre la fórmula de
     * descuento, no sobre la ventana de vigencia.
     */
    protected function applyOfferDiscountMirror(Product $product, ProductOffer $offer): void
    {
        $product->setRelation('currentOffer', $offer);
        $result = $this->pricingService->calcularPrecio($product, 1);

        $offer->offer_price = $result->ofertaAplicada ? $result->precioUnitarioFinal : null;
        $offer->percentage_discount = $result->ofertaAplicada ? $result->ahorroPorcentaje : null;
    }
}
