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
     * Obtener la URL web de la imagen para usar en el frontend.
     * - Paths legacy (con '/'): se usan tal cual (ej: /images/products/file.jpg)
     * - Paths nuevos (solo nombre de archivo): se preponee VITE_PRODUCT_IMAGES_PATH
     */
    public function getUrlAttribute(): string
    {
        $path = $this->path;

        if (str_starts_with($path, 'http')) {
            return $path;
        }

        // Formato legacy: el path ya contiene la ruta web completa (ej: /images/products/file.jpg)
        if (str_contains($path, '/')) {
            return $path;
        }

        // Formato nuevo: path es solo el nombre del archivo (ej: file.jpg)
        $basePath = rtrim(env('VITE_PRODUCT_IMAGES_PATH', '/images/products/'), '/');
        return $basePath . '/' . $path;
    }

    /**
     * Obtener la ruta física del archivo en el sistema de archivos.
     * Usa PRODUCT_IMAGES_PATH para soportar distintas ubicaciones en local y producción.
     */
    public function getFilesystemPath(): string
    {
        $filename = basename($this->path); // Funciona tanto con '/images/products/file.jpg' como con 'file.jpg'
        $imagesDir = rtrim(public_path(ltrim(env('PRODUCT_IMAGES_PATH', '/images/products/'), '/')), DIRECTORY_SEPARATOR);
        return $imagesDir . DIRECTORY_SEPARATOR . $filename;
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