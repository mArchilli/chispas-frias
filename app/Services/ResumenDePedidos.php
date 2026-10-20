<?php

namespace App\Services;

use App\Enums\EstadoOrden;
use App\Models\Order;
use Carbon\CarbonInterface;

/**
 * Totales de pedidos de un rango de fechas, separados por lo que significa cada estado:
 *
 *  - VENTAS: `Order::ventasEntre()`. Un pedido con `rukoz_ref` es venta desde que se confirma en el Sistema Central
 *    (`confirmado_at`: confirmado, empaquetado, despachado y entregado) y cuenta en el día de esa confirmación; uno de la web
 *    sola (sin `rukoz_ref`) es venta cuando se despacha, como siempre, en el día en que se creó. Son el único monto que se
 *    informa como ingreso.
 *  - PENDIENTES: los de `EstadoOrden::pendientes()` — pedidos todavía sin cerrar (incluidas las reservas sin confirmar). Se
 *    informan aparte (cantidad y monto) para saber cuánto hay "en juego", pero no son ingresos.
 *  - CANCELADOS: cancelados y anulados. Se cuentan, pero nunca suman monto.
 *
 * `$desde` y `$hasta` ya vienen en la zona de la base (ver ZonaHoraria::rangoDelMes/rangoDelDia).
 * Lo comparten el panel de órdenes y el dashboard para que ambos digan siempre lo mismo.
 */
class ResumenDePedidos
{
    /**
     * @return array{pedidos: int, ventas_cantidad: int, ventas_monto: float, pendientes_cantidad: int, pendientes_monto: float, cancelados_cantidad: int}
     */
    public function delRango(CarbonInterface $desde, CarbonInterface $hasta): array
    {
        // Lo recibido en el rango (por fecha de creación), por estado.
        $porEstado = Order::query()
            ->whereBetween('created_at', [$desde, $hasta])
            ->selectRaw('estado, COUNT(*) as cantidad, COALESCE(SUM(total), 0) as monto')
            ->groupBy('estado')
            ->toBase() // filas planas: sin el cast de `estado` a enum, que no sirve de clave
            ->get()
            ->keyBy('estado');

        $sumar = function (array $estados, string $campo) use ($porEstado): float|int {
            return collect($estados)->sum(fn (EstadoOrden $estado) => $porEstado->get($estado->value)?->{$campo} ?? 0);
        };

        // Las ventas se miran por su fecha de venta, no por la de creación.
        $ventas = Order::ventasEntre($desde, $hasta)
            ->toBase()
            ->selectRaw('COUNT(*) as cantidad, COALESCE(SUM(total), 0) as monto')
            ->first();

        return [
            'pedidos' => (int) $porEstado->sum('cantidad'),
            'ventas_cantidad' => (int) ($ventas->cantidad ?? 0),
            'ventas_monto' => (float) ($ventas->monto ?? 0),
            'pendientes_cantidad' => (int) $sumar(EstadoOrden::pendientes(), 'cantidad'),
            'pendientes_monto' => (float) $sumar(EstadoOrden::pendientes(), 'monto'),
            'cancelados_cantidad' => (int) $sumar([EstadoOrden::Cancelado, EstadoOrden::Anulado], 'cantidad'),
        ];
    }
}
