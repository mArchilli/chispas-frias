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
        'mensaje_whatsapp',
    ];

    protected $casts = [
        'estado' => EstadoOrden::class,
        'subtotal' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'total' => 'decimal:2',
        'discount_usage_repuesto' => 'boolean',
    ];

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
}
