<?php

namespace App\Models;

use App\Enums\TipoDocumento;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Document extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'type',
        'url',
        'path',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'type' => TipoDocumento::class,
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * Scope para documentos activos
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
