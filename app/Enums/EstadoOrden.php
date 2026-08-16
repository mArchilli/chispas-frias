<?php

namespace App\Enums;

enum EstadoOrden: string
{
    case Pendiente = 'pendiente';
    case Despachado = 'despachado';
    case Cancelado = 'cancelado';

    /**
     * Indica si la orden puede pasar de este estado al estado destino.
     */
    public function puedeTransicionarA(self $destino): bool
    {
        return match ($this) {
            self::Pendiente => in_array($destino, [self::Despachado, self::Cancelado], true),
            self::Despachado => $destino === self::Pendiente,
            self::Cancelado => false,
        };
    }
}
