<?php

namespace App\Exceptions;

use Exception;

class StockInsuficienteException extends Exception
{
    public function __construct(
        public readonly int $productId,
        public readonly int $cantidadSolicitada,
        public readonly int $stockDisponible,
        public readonly ?int $variantId = null,
    ) {
        parent::__construct(
            "Stock insuficiente para el producto #{$this->productId}"
                . ($this->variantId !== null ? " (variante #{$this->variantId})" : '')
                . ": se solicitaron {$this->cantidadSolicitada} unidades, hay {$this->stockDisponible} disponibles."
        );
    }
}
