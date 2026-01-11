<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'price',
        'sku',
        'category_id',
        'stock',
        'is_active',
        'is_featured'
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'is_active' => 'boolean',
        'is_featured' => 'boolean'
    ];

    protected $attributes = [
        'stock' => 9999
    ];

    protected $appends = [
        'current_price',
        'formatted_current_price', 
        'formatted_offer_price',
        'discount_percentage',
        'has_active_offer'
    ];

    /**
     * Relación con categoría
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Relación con imágenes
     */
    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order');
    }

    /**
     * Relación con ofertas
     */
    public function offers(): HasMany
    {
        return $this->hasMany(ProductOffer::class);
    }

    /**
     * Relación con la oferta activa actual
     */
    public function currentOffer()
    {
        return $this->hasOne(ProductOffer::class)
                    ->active()
                    ->latest();
    }

    /**
     * Obtener la imagen principal
     */
    public function primaryImage(): ?ProductImage
    {
        return $this->images()->where('is_primary', true)->first() 
               ?? $this->images()->first();
    }

    /**
     * Scope para productos activos
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope para productos destacados
     */
    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    /**
     * Scope para productos en stock
     */
    public function scopeInStock($query)
    {
        return $query->where('stock', '>', 0);
    }

    /**
     * Verificar si el producto está en stock
     */
    public function isInStock(): bool
    {
        return $this->stock > 0;
    }

    /**
     * Obtener el precio formateado
     */
    public function getFormattedPriceAttribute(): string
    {
        return '$' . number_format((float) $this->price, 2);
    }

    /**
     * Verificar si el producto tiene una oferta activa
     */
    public function hasActiveOffer(): bool
    {
        return $this->getCurrentOfferPrice() !== null;
    }

    /**
     * Obtener el precio de oferta actual (si existe)
     */
    public function getCurrentOfferPrice(): ?float
    {
        $currentOffer = $this->offers()
                             ->active()
                             ->latest()
                             ->first();
                             
        return $currentOffer ? (float) $currentOffer->offer_price : null;
    }

    /**
     * Obtener el precio actual (con o sin oferta)
     */
    public function getCurrentPrice(): float
    {
        return $this->getCurrentOfferPrice() ?? (float) $this->price;
    }

    /**
     * Obtener el precio actual como atributo calculado
     */
    public function getCurrentPriceAttribute(): float
    {
        return $this->getCurrentPrice();
    }

    /**
     * Obtener el precio actual formateado
     */
    public function getFormattedCurrentPriceAttribute(): string
    {
        return '$' . number_format($this->getCurrentPrice(), 2);
    }

    /**
     * Obtener el precio de oferta formateado (si existe)
     */
    public function getFormattedOfferPriceAttribute(): ?string
    {
        $offerPrice = $this->getCurrentOfferPrice();
        return $offerPrice ? '$' . number_format($offerPrice, 2) : null;
    }

    /**
     * Calcular el porcentaje de descuento (si hay oferta)
     */
    public function getDiscountPercentageAttribute(): ?int
    {
        $offerPrice = $this->getCurrentOfferPrice();
        
        if (!$offerPrice || $offerPrice >= $this->price) {
            return null;
        }
        
        return round((($this->price - $offerPrice) / $this->price) * 100);
    }

    /**
     * Atributo calculado para verificar si tiene oferta activa
     */
    public function getHasActiveOfferAttribute(): bool
    {
        return $this->hasActiveOffer();
    }

    /**
     * Obtener la categoría principal (padre de la subcategoría)
     */
    public function getMainCategoryAttribute(): ?Category
    {
        if ($this->category->isMain()) {
            return $this->category;
        }

        return $this->category->parent;
    }
}