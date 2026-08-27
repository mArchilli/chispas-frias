<?php

namespace App\Services;

use App\Models\Addon;
use App\Models\ProductOffer;
use App\Models\ProductPriceTier;
use App\Models\ProductVariant;

class PriceResult
{
    /**
     * @param  array<int, Addon>  $addonsAplicados  Add-ons resueltos, en el orden en que vinieron los ids.
     */
    public function __construct(
        public readonly float $precioLista,
        public readonly float $precioUnitarioFinal,
        public readonly ?ProductOffer $ofertaAplicada,
        public readonly ?ProductPriceTier $tierAplicado,
        public readonly float $ahorroUnitario,
        public readonly float $ahorroPorcentaje,
        // --- opciones del producto (variante de color + add-ons) --------------
        // El descuento de ProductOffer NUNCA toca estos montos: se calcula solo
        // sobre precioLista/precioUnitarioFinal. recargoVariante y addonsTotal se
        // suman al precioUnitarioFinal (ya con oferta aplicada) para dar
        // precioFinalConOpciones. Con los defaults, precioFinalConOpciones ===
        // precioUnitarioFinal, así que los callers viejos no ven ningún cambio.
        public readonly ?ProductVariant $varianteAplicada = null,
        public readonly array $addonsAplicados = [],
        public readonly float $recargoVariante = 0.0,
        public readonly float $addonsTotal = 0.0,
        public readonly float $precioFinalConOpciones = 0.0,
    ) {}
}
