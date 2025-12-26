<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CartItem extends Model
{
    protected $fillable = [
        'user_id',
        'product_id',
        'quantity'
    ];

    protected $appends = [
        'subtotal'
    ];

    /**
     * Relación con el usuario
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relación con el producto
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Calcula el subtotal del item
     */
    public function getSubtotalAttribute(): float
    {
        if (!$this->relationLoaded('product') || !$this->product) {
            return 0;
        }
        return $this->quantity * $this->product->price;
    }

    /**
     * Scope para obtener items del carrito por usuario
     */
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope para obtener items con productos cargados
     */
    public function scopeWithProduct($query)
    {
        return $query->with(['product', 'product.images']);
    }
}