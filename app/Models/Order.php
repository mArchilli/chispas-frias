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
        'total',
        'mensaje_whatsapp',
    ];

    protected $casts = [
        'estado' => EstadoOrden::class,
        'total' => 'decimal:2',
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
}
