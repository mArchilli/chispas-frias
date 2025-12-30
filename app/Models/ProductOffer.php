<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductOffer extends Model
{
    protected $fillable = [
        'product_id',
        'offer_price',
        'percentage_discount',
        'start_date',
        'end_date',
        'is_active'
    ];

    protected $casts = [
        'offer_price' => 'decimal:2',
        'percentage_discount' => 'decimal:2',
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'is_active' => 'boolean',
    ];

    /**
     * Relación con el producto
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Scope para ofertas activas (considerando fechas y estado)
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true)
                    ->where(function($q) {
                        $q->whereNull('start_date')
                          ->orWhere('start_date', '<=', now());
                    })
                    ->where(function($q) {
                        $q->whereNull('end_date')
                          ->orWhere('end_date', '>=', now());
                    });
    }

    /**
     * Scope para ofertas vigentes (solo por fechas, sin considerar is_active)
     */
    public function scopeValidDate($query)
    {
        return $query->where(function($q) {
                        $q->whereNull('start_date')
                          ->orWhere('start_date', '<=', now());
                    })
                    ->where(function($q) {
                        $q->whereNull('end_date')
                          ->orWhere('end_date', '>=', now());
                    });
    }

    /**
     * Verificar si la oferta está activa actualmente
     */
    public function isCurrentlyActive(): bool
    {
        if (!$this->is_active) {
            return false;
        }

        $now = now();
        
        // Verificar fecha de inicio
        if ($this->start_date && $this->start_date > $now) {
            return false;
        }
        
        // Verificar fecha de fin
        if ($this->end_date && $this->end_date < $now) {
            return false;
        }
        
        return true;
    }

    /**
     * Obtener precio formateado de la oferta
     */
    public function getFormattedOfferPriceAttribute(): string
    {
        return '$' . number_format((float) $this->offer_price, 2);
    }
}
