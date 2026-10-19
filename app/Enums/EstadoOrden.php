<?php

namespace App\Enums;

enum EstadoOrden: string
{
    case Pendiente = 'pendiente';
    case Despachado = 'despachado';
    case Cancelado = 'cancelado';

    /**
     * Estados que hoy significan VENTA CERRADA, para las métricas de ingresos.
     *
     * En esta web el pedido nace "pendiente" (el cliente lo mandó por WhatsApp y el vendedor
     * todavía lo está cerrando) y pasa a "despachado" cuando el vendedor lo despacha: es lo más
     * parecido a una venta confirmada que existe hoy (no hay estado de pago ni de confirmación).
     * "despachado" puede volver a "pendiente", así que un monto de ventas puede bajar.
     *
     * @return array<int, self>
     */
    public static function deVenta(): array
    {
        return [self::Despachado];
    }

    /**
     * Estados de pedidos todavía sin cerrar: se informan aparte, nunca como ventas.
     *
     * @return array<int, self>
     */
    public static function pendientes(): array
    {
        return [self::Pendiente];
    }

    // Cancelado no está en ninguna de las dos listas: un pedido cancelado nunca suma montos.

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
