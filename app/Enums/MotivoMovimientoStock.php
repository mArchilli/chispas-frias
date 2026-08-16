<?php

namespace App\Enums;

enum MotivoMovimientoStock: string
{
    case OrdenCreada = 'orden_creada';
    case OrdenCancelada = 'orden_cancelada';
    case AjusteManual = 'ajuste_manual';
}
