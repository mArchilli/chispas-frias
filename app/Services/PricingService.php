<?php

namespace App\Services;

use App\Enums\AlcanceOferta;
use App\Enums\TipoDescuento;
use App\Models\Product;
use App\Models\ProductOffer;
use App\Models\ProductPriceTier;

class PricingService
{
    /**
     * Resuelve el precio unitario para un producto y una cantidad dada, combinando
     * la escala de precios por cantidad (tiers) con la oferta activa, si corresponde
     * aplicarla según su alcance.
     */
    public function calcularPrecio(Product $product, int $cantidad): PriceResult
    {
        $tier = $product->tierAplicable($cantidad);
        $precioLista = round((float) ($tier?->precio_unitario ?? $product->price), 2);

        $offer = $this->ofertaActiva($product);
        $ofertaAplicada = $offer && $this->ofertaAplica($offer, $tier) ? $offer : null;

        $precioUnitarioFinal = $ofertaAplicada
            ? $this->aplicarDescuento($precioLista, $ofertaAplicada)
            : $precioLista;

        $ahorroUnitario = round(max(0, $precioLista - $precioUnitarioFinal), 2);
        $ahorroPorcentaje = $precioLista > 0
            ? round(($ahorroUnitario / $precioLista) * 100, 2)
            : 0.0;

        return new PriceResult(
            precioLista: $precioLista,
            precioUnitarioFinal: $precioUnitarioFinal,
            ofertaAplicada: $ofertaAplicada,
            tierAplicado: $tier,
            ahorroUnitario: $ahorroUnitario,
            ahorroPorcentaje: $ahorroPorcentaje,
        );
    }

    /**
     * Reusa `currentOffer` si ya viene eager-loaded en el producto (evita el N+1 de
     * getCurrentOfferPrice()/getCurrentPrice()). Si no viene cargada, el propio
     * getter de Eloquent la resuelve y cachea en el modelo — como máximo una query.
     */
    private function ofertaActiva(Product $product): ?ProductOffer
    {
        return $product->currentOffer;
    }

    /**
     * La oferta aplica a todo el catálogo, o solo si el tier al que apunta coincide
     * con el tier resuelto para esta cantidad (null === null cubre "ambos apuntan al
     * precio base").
     */
    private function ofertaAplica(ProductOffer $offer, ?ProductPriceTier $tier): bool
    {
        if ($offer->alcance === AlcanceOferta::Todos) {
            return true;
        }

        return $offer->product_price_tier_id === $tier?->id;
    }

    private function aplicarDescuento(float $precioLista, ProductOffer $offer): float
    {
        $valor = (float) $offer->valor_descuento;

        $precio = $offer->tipo_descuento === TipoDescuento::Porcentaje
            ? $precioLista * (1 - $valor / 100)
            : $precioLista - $valor;

        return round(max(0, $precio), 2);
    }
}
