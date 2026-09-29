<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Imagen/video de un combo. Espejo de ProductImage sin la parte de variantes:
 * los archivos viven en la MISMA carpeta física que los de productos
 * (PRODUCT_IMAGES_PATH), así que reutiliza el mismo criterio de url/ruta.
 */
class ComboImage extends Model
{
    use HasFactory;

    protected $fillable = [
        'combo_id',
        'path',
        'alt_text',
        'sort_order',
        'is_primary',
        'type',
        'mime_type',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
    ];

    public function combo(): BelongsTo
    {
        return $this->belongsTo(Combo::class);
    }

    public function scopePrimary($query)
    {
        return $query->where('is_primary', true);
    }

    /**
     * URL web de la imagen para el frontend. Mismo criterio que ProductImage:
     * paths legacy (con '/') tal cual; nombre de archivo suelto => se le
     * prepone VITE_PRODUCT_IMAGES_PATH.
     */
    public function getUrlAttribute(): string
    {
        $path = $this->path;

        if (str_starts_with($path, 'http')) {
            return $path;
        }

        if (str_contains($path, '/')) {
            return $path;
        }

        $basePath = rtrim(env('VITE_PRODUCT_IMAGES_PATH', '/images/products/'), '/');

        return $basePath . '/' . $path;
    }

    /**
     * Ruta física del archivo. Misma carpeta que las imágenes de producto.
     */
    public function getFilesystemPath(): string
    {
        $filename = basename($this->path);
        $imagesDir = rtrim(public_path(ltrim(env('PRODUCT_IMAGES_PATH', '/images/products/'), '/')), DIRECTORY_SEPARATOR);

        return $imagesDir . DIRECTORY_SEPARATOR . $filename;
    }

    public function isImage(): bool
    {
        return $this->type === 'image';
    }

    public function isVideo(): bool
    {
        return $this->type === 'video';
    }

    /**
     * Marca esta imagen como principal, quitándoselo a las demás del combo.
     */
    public function setPrimary(): void
    {
        $this->combo->images()->update(['is_primary' => false]);
        $this->update(['is_primary' => true]);
    }
}
