<?php

namespace App\Services;

use App\Enums\EstadoOrden;
use App\Models\Order;
use Carbon\CarbonInterface;

/**
 * Totales de pedidos de un rango de fechas, separados por lo que significa cada estado:
 *
 *  - VENTAS: los estados de `EstadoOrden::deVenta()` (hoy, "despachado": el vendedor ya cerró y
 *    despachó el pedido). Son el único monto que se informa como ingreso.
 *  - PENDIENTES: los de `EstadoOrden::pendientes()` — pedidos todavía sin cerrar. Se informan
 *    aparte (cantidad y monto) para saber cuánto hay "en juego", pero no son ingresos.
 *  - CANCELADOS: se cuentan, pero nunca suman monto.
 *
 * `$desde` y `$hasta` ya vienen en la zona de la base (ver ZonaHoraria::rangoDelMes/rangoDelDia).
 * Es una sola consulta agrupada por estado, compartida por el panel de órdenes y el dashboard
 * para que ambos digan siempre lo mismo.
 */
class ResumenDePedidos
{
    /**
     * @return array{pedidos: int, ventas_cantidad: int, ventas_monto: float, pendientes_cantidad: int, pendientes_monto: float, cancelados_cantidad: int}
     */
    public function delRango(CarbonInterface $desde, CarbonInterface $hasta): array
    {
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

        return [
            'pedidos' => (int) $porEstado->sum('cantidad'),
            'ventas_cantidad' => (int) $sumar(EstadoOrden::deVenta(), 'cantidad'),
            'ventas_monto' => (float) $sumar(EstadoOrden::deVenta(), 'monto'),
            'pendientes_cantidad' => (int) $sumar(EstadoOrden::pendientes(), 'cantidad'),
            'pendientes_monto' => (float) $sumar(EstadoOrden::pendientes(), 'monto'),
            'cancelados_cantidad' => (int) ($porEstado->get(EstadoOrden::Cancelado->value)?->cantidad ?? 0),
        ];
    }
}
