<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductImage extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'path',
        'alt_text',
        'sort_order',
        'is_primary',
        'type',
        'mime_type'
    ];

    protected $casts = [
        'is_primary' => 'boolean'
    ];

    /**
     * Relación con producto
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Scope para imagen principal
     */
    public function scopePrimary($query)
    {
        return $query->where('is_primary', true);
    }

    /**
     * Obtener la URL completa de la imagen
     */
    public function getUrlAttribute(): string
    {
        // Como las imágenes están en public/, no necesitamos /storage/
        return asset($this->path);
    }

    /**
     * Verificar si es una imagen
     */
    public function isImage(): bool
    {
        return $this->type === 'image';
    }

    /**
     * Verificar si es un video
     */
    public function isVideo(): bool
    {
        return $this->type === 'video';
    }

    /**
     * Establecer como imagen principal
     */
    public function setPrimary(): void
    {
        // Quitar primary de otras imágenes del mismo producto
        $this->product->images()->update(['is_primary' => false]);
        
        // Establecer esta imagen como principal
        $this->update(['is_primary' => true]);
    }
}