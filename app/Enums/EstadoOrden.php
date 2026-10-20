<?php

namespace App\Enums;

enum EstadoOrden: string
{
    case Pendiente = 'pendiente';
    case Despachado = 'despachado';
    case Cancelado = 'cancelado';

    // 10.4 — Estados que llegan del Sistema Central una vez confirmada la venta. La web nunca los asigna a mano
    // (salvo `Confirmado`, que pone `ConfirmacionDeVenta` al confirmar): los refleja `EstadoDesdeCentral`.
    case Confirmado = 'confirmado';
    case Empaquetado = 'empaquetado';
    case Entregado = 'entregado';
    case Anulado = 'anulado';

    /**
     * Estados que significan VENTA CERRADA para los pedidos de la web SOLA (sin `rukoz_ref`, flujo de siempre).
     *
     * En esta web el pedido nace "pendiente" (el cliente lo mandó por WhatsApp y el vendedor todavía lo está cerrando) y pasa a
     * "despachado" cuando el vendedor lo despacha: es lo más parecido a una venta confirmada que existe para esos pedidos (no
     * hay estado de pago ni de confirmación). "despachado" puede volver a "pendiente", así que un monto de ventas puede bajar.
     *
     * Para los pedidos con `rukoz_ref` manda `deVentaCentral()` (ver `Order::scopeVentas`).
     *
     * @return array<int, self>
     */
    public static function deVenta(): array
    {
        return [self::Despachado];
    }

    /**
     * Estados de VENTA para los pedidos con `rukoz_ref` (10.4): la venta nace al confirmarla en el Central y cuenta desde
     * `confirmado_at`, sin esperar el despacho. `Anulado` y `Cancelado` no cuentan.
     *
     * @return array<int, self>
     */
    public static function deVentaCentral(): array
    {
        return [self::Confirmado, self::Empaquetado, self::Despachado, self::Entregado];
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

    // Cancelado y Anulado no están en ninguna lista de venta: un pedido cancelado o anulado nunca suma montos.

    public function label(): string
    {
        return match ($this) {
            self::Pendiente => 'Pendiente',
            self::Confirmado => 'Confirmado',
            self::Empaquetado => 'Empaquetado',
            self::Despachado => 'Despachado',
            self::Entregado => 'Entregado',
            self::Cancelado => 'Cancelado',
            self::Anulado => 'Anulado',
        };
    }

    /** ¿Este estado solo puede venir del Central? (la web no lo asigna a mano) */
    public function esDelCentral(): bool
    {
        return in_array($this, [self::Confirmado, self::Empaquetado, self::Entregado, self::Anulado], true);
    }

    /**
     * El estado de la web que le corresponde a lo que informa el Central (`pedido.*`), o `null` si no cambia el estado de la
     * web (`reservado` y `requiere_revision` son la reserva: el pedido sigue pendiente).
     *
     *   confirmado → Confirmado · empaquetado → Empaquetado · despachado → Despachado · retirado → Entregado
     *   anulado → Anulado · cancelado → Cancelado (reserva liberada)
     */
    public static function desdeCentral(string $estadoDelCentral): ?self
    {
        return match ($estadoDelCentral) {
            'confirmado' => self::Confirmado,
            'empaquetado' => self::Empaquetado,
            'despachado' => self::Despachado,
            'retirado' => self::Entregado,
            'anulado' => self::Anulado,
            'cancelado' => self::Cancelado,
            default => null,
        };
    }

    /**
     * Reglas de transición de estado de un pedido DE LA WEB SOLA: pendiente↔despachado es libre y no afecta stock;
     * pendiente→cancelado es la única forma de cancelar (repone stock); cancelado es terminal; despachado→cancelado directo no
     * está permitido, hay que volver primero a pendiente. Los estados del Central no tienen transiciones desde la web. Para un
     * pedido con `rukoz_ref` manda `Order::puedeTransicionarA()`.
     */
    public function puedeTransicionarA(self $destino): bool
    {
        return match ($this) {
            self::Pendiente => in_array($destino, [self::Despachado, self::Cancelado], true),
            self::Despachado => $destino === self::Pendiente,
            default => false,
        };
    }
}
