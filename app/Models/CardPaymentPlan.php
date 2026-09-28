<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CardPaymentPlan extends Model
{
    /** @use HasFactory<\Database\Factories\CardPaymentPlanFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'installments',
        'surcharge_percentage',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'installments' => 'integer',
        'surcharge_percentage' => 'decimal:2',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * Órdenes que usaron este plan (vía orders.card_payment_plan_id). Se usa
     * para bloquear el borrado del plan una vez que ya fue usado en alguna
     * orden: la orden guarda un snapshot (payment_plan_name,
     * surcharge_percentage, ...) pero borrar el plan le quita contexto a ese
     * historial. Mismo criterio que Addon::haSidoUsado() / DiscountCode.
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Scope para planes activos, en orden de presentación (sort_order). Mismo
     * espíritu que Addon::scopeActive() / DiscountCode::scopeActive().
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->orderBy('sort_order');
    }
}
