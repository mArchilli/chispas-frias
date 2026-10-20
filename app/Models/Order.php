<?php

namespace App\Models;

use App\Enums\EstadoOrden;
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

    /** Estados de la reserva en el Central (`orders.rukoz_estado`). */
    public const RESERVA_RESERVADA = 'reservado';

    public const RESERVA_CANCELADA = 'cancelado';

    /** El pedido local ya está cancelado pero el Central no respondió: la cancelación quedó en las compensaciones del paquete. */
    public const RESERVA_CANCELACION_PENDIENTE = 'cancelacion_pendiente';

    protected $casts = [
        'rukoz_version' => 'integer',
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
     * Pedido con la reserva viva en el Central y todavía sin confirmar: se cancela con `cancelarReserva`. En la 10.3
     * ninguno se confirma; la 10.4 suma "Confirmar venta".
     */
    public function tieneReservaAbierta(): bool
    {
        return $this->usaCentral() && $this->rukoz_estado === self::RESERVA_RESERVADA;
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
