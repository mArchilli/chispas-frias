<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Combo extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'price',
        'is_free_shipping',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'is_free_shipping' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * Productos que integran el combo (con su cantidad).
     */
    public function items(): HasMany
    {
        return $this->hasMany(ComboItem::class)->orderBy('sort_order');
    }

    /**
     * Galería de imágenes/videos del combo.
     */
    public function images(): HasMany
    {
        return $this->hasMany(ComboImage::class)->orderBy('sort_order');
    }

    /**
     * Imagen principal para miniaturas/tarjetas: SIEMPRE una imagen, nunca un
     * video. Prefiere la marcada como principal; si esa es un video o no hay,
     * cae en la primera imagen por orden. Null si el combo sólo tiene videos.
     */
    public function primaryImage(): ?ComboImage
    {
        if ($this->relationLoaded('images')) {
            $imagenes = $this->images->where('type', '!=', 'video');

            return $imagenes->firstWhere('is_primary', true) ?? $imagenes->first();
        }

        return $this->images()->where('type', '!=', 'video')->where('is_primary', true)->first()
            ?? $this->images()->where('type', '!=', 'video')->first();
    }

    /**
     * Scope para combos activos.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Precio formateado, mismo criterio de formato que el resto del catálogo.
     */
    public function getFormattedPriceAttribute(): string
    {
        return '$' . number_format((float) $this->price, 2);
    }
}
