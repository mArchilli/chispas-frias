<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductVariant extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'name',
        'color_hex',
        'is_custom_color',
        'price_addon',
        'stock',
        'sku',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'is_custom_color' => 'boolean',
        'price_addon' => 'decimal:2',
        'stock' => 'integer',
        'sort_order' => 'integer',
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
     * Imágenes/videos asociados puntualmente a esta variante (los que no tienen
     * variante son "generales" y se muestran para cualquier color).
     */
    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order');
    }

    /**
     * True si es la variante "a elección del cliente" (color personalizado): el
     * cliente elige libremente el color en vez de uno fijo. En esa fila
     * color_hex es solo un ícono de referencia, no el color real.
     */
    public function isCustomColor(): bool
    {
        return (bool) $this->is_custom_color;
    }

    /**
     * True si la variante no maneja stock (stock null = ilimitado, mismo
     * criterio que products.stock).
     */
    public function tieneStockIlimitado(): bool
    {
        return $this->stock === null;
    }

    /**
     * True si hay stock suficiente de la variante para una cantidad dada. Una
     * variante con stock ilimitado siempre tiene disponible.
     */
    public function tieneStockDisponible(int $cantidad): bool
    {
        return $this->tieneStockIlimitado() || $this->stock >= $cantidad;
    }

    /**
     * Scope para variantes activas
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
