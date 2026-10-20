<?php

namespace App\Models;

use App\Enums\EstadoOrden;
use App\Rukoz\ConfirmacionDeVenta;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'lastname',
        'dni',
        'province',
        'city',
        'address',
        'number',
        'between_streets',
        'postal_code',
        'phone',
        'email',
        'observations',
        'estado',
        'discount_code_id',
        'discount_code',
        'subtotal',
        'discount_amount',
        'discount_usage_repuesto',
        'total',
        'free_shipping',
        'card_payment_plan_id',
        'payment_plan_name',
        'payment_plan_installments',
        'surcharge_percentage',
        'surcharge_amount',
        'total_with_surcharge',
        'mensaje_whatsapp',
        'rukoz_ref',
        'rukoz_version',
        'rukoz_estado',
        'motivo_cancelacion',
    ];

    // Los datos de la venta del Central (`confirmado_at`, `rukoz_pagado`, `rukoz_saldo`, seguimiento…) NO están en $fillable: los
    // escribe solo `App\Rukoz\EstadoDesdeCentral` / `ConfirmacionDeVenta` con forceFill.

    /** Estados de la reserva en el Central (`orders.rukoz_estado`). */
    public const RESERVA_RESERVADA = 'reservado';

    public const RESERVA_CANCELADA = 'cancelado';

    /** El pedido local ya está cancelado pero el Central no respondió: la cancelación quedó en las compensaciones del paquete. */
    public const RESERVA_CANCELACION_PENDIENTE = 'cancelacion_pendiente';

    protected $casts = [
        'rukoz_version' => 'integer',
        'confirmado_at' => 'datetime',
        'confirmando_at' => 'datetime',
        'despachado_at' => 'datetime',
        'rukoz_empaquetado_at' => 'datetime',
        'rukoz_retirado_at' => 'datetime',
        'rukoz_anulado_at' => 'datetime',
        'rukoz_total_venta' => 'decimal:2',
        'rukoz_pagado' => 'decimal:2',
        'rukoz_saldo' => 'decimal:2',
        'envio_costo_cobrado' => 'decimal:2',
        'rukoz_retenido_por_pago' => 'boolean',
        'estado' => EstadoOrden::class,
        'subtotal' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'total' => 'decimal:2',
        'discount_usage_repuesto' => 'boolean',
        'discount_usage_consumido_at' => 'datetime',
        'discount_usage_liberado_at' => 'datetime',
        'free_shipping' => 'boolean',
        'payment_plan_installments' => 'integer',
        'surcharge_percentage' => 'decimal:2',
        'surcharge_amount' => 'decimal:2',
        'total_with_surcharge' => 'decimal:2',
    ];

    /**
     * ¿El stock de este pedido lo lleva el Central (reservó allá al crearse)? Los pedidos sin `rukoz_ref` son los de
     * siempre: descuentan y reponen stock local, aunque el interruptor del checkout esté encendido.
     */
    public function usaCentral(): bool
    {
        return $this->rukoz_ref !== null;
    }

    /**
     * Pedido con la reserva viva en el Central y todavía sin confirmar: se cancela con `cancelarReserva` o se confirma.
     */
    public function tieneReservaAbierta(): bool
    {
        return $this->usaCentral()
            && $this->confirmado_at === null
            && $this->rukoz_estado === self::RESERVA_RESERVADA;
    }

    /**
     * 10.4 — ¿La venta de este pedido ya se confirmó en el Central? Desde ahí el pedido es de SOLO LECTURA en la web: las
     * correcciones se hacen en el Central (anular y rehacer). Sigue valiendo si después se empaquetó, despachó, entregó o anuló.
     */
    public function estaConfirmado(): bool
    {
        return $this->usaCentral() && $this->confirmado_at !== null;
    }

    /**
     * 10.4 — ¿Se puede confirmar la venta desde el admin? Pedido con reserva abierta (`reservado`), pendiente y con el
     * interruptor `INTEGRACION_CONFIRMACION` encendido.
     */
    public function puedeConfirmarse(): bool
    {
        return ConfirmacionDeVenta::activa()
            && $this->tieneReservaAbierta()
            && $this->estado === EstadoOrden::Pendiente;
    }

    /**
     * Transiciones de estado que se piden DESDE LA WEB. Un pedido de la web sola sigue la regla de siempre
     * (`EstadoOrden::puedeTransicionarA`). Uno con `rukoz_ref`:
     *  - confirmado (o más allá), anulado o cancelado: ninguna — el Central manda;
     *  - con reserva abierta: cancelar; y «despachado» manual solo si la confirmación está apagada (con la confirmación
     *    encendida «Confirmar venta» lo reemplaza, que es lo que manda la venta al Central).
     */
    public function puedeTransicionarA(EstadoOrden $destino): bool
    {
        if (! $this->usaCentral()) {
            return $this->estado->puedeTransicionarA($destino);
        }

        if ($this->estaConfirmado() || $this->estado->esDelCentral() || $this->estado === EstadoOrden::Cancelado) {
            return false;
        }

        if ($destino === EstadoOrden::Despachado && ConfirmacionDeVenta::activa()) {
            return false;
        }

        return $this->estado->puedeTransicionarA($destino);
    }

    /**
     * La fecha con la que cuenta la venta en las métricas: la confirmación en el Central para los pedidos con `rukoz_ref`
     * (`confirmado_at`), la de creación para los de siempre.
     */
    public function fechaDeVenta(): ?\Carbon\CarbonInterface
    {
        return $this->usaCentral() ? $this->confirmado_at : $this->created_at;
    }

    /**
     * Ventas cerradas. Dos orígenes:
     *  - pedidos de la web sola (sin `rukoz_ref`): los despachados, como siempre;
     *  - pedidos con `rukoz_ref` (10.4): los confirmados, empaquetados, despachados y entregados EN EL CENTRAL (con
     *    `confirmado_at`). Los anulados, los cancelados y las reservas sin confirmar no entran.
     */
    public function scopeVentas(Builder $query): Builder
    {
        $valores = fn (array $estados) => array_map(fn (EstadoOrden $estado) => $estado->value, $estados);

        return $query->where(function (Builder $q) use ($valores) {
            $q->where(fn (Builder $l) => $l->whereNull('rukoz_ref')->whereIn('estado', $valores(EstadoOrden::deVenta())))
                ->orWhere(fn (Builder $c) => $c->whereNotNull('rukoz_ref')->whereNotNull('confirmado_at')->whereIn('estado', $valores(EstadoOrden::deVentaCentral())));
        });
    }

    /**
     * Las ventas (`ventas()`) cuya FECHA DE VENTA cae en [$desde, $hasta]: `confirmado_at` para los pedidos con `rukoz_ref`,
     * `created_at` para los de siempre. Los límites van en la zona de la base (ver ZonaHoraria::rangoDelDia/rangoDelMes).
     */
    public function scopeVentasEntre(Builder $query, $desde, $hasta): Builder
    {
        return $query->ventas()->where(function (Builder $q) use ($desde, $hasta) {
            $q->where(fn (Builder $l) => $l->whereNull('rukoz_ref')->whereBetween('created_at', [$desde, $hasta]))
                ->orWhere(fn (Builder $c) => $c->whereNotNull('rukoz_ref')->whereBetween('confirmado_at', [$desde, $hasta]));
        });
    }

    /**
     * Relación con los items de la orden
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Relación con el usuario (opcional, la orden no requiere cuenta)
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relación con el código de descuento aplicado (opcional)
     */
    public function discountCode(): BelongsTo
    {
        return $this->belongsTo(DiscountCode::class);
    }

    /**
     * Relación con el plan de cuotas de tarjeta elegido (opcional). Sin plan,
     * los campos snapshot (payment_plan_name, surcharge_amount, ...) quedan null
     * y la orden usa `total` como hoy.
     */
    public function cardPaymentPlan(): BelongsTo
    {
        return $this->belongsTo(CardPaymentPlan::class);
    }
}
