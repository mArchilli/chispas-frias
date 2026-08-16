<?php

namespace App\Services;

use App\Models\ProductOffer;
use App\Models\ProductPriceTier;

class PriceResult
{
    public function __construct(
        public readonly float $precioLista,
        public readonly float $precioUnitarioFinal,
        public readonly ?ProductOffer $ofertaAplicada,
        public readonly ?ProductPriceTier $tierAplicado,
        public readonly float $ahorroUnitario,
        public readonly float $ahorroPorcentaje,
    ) {}
}
