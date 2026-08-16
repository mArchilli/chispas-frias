<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductPriceTier extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'cantidad_minima',
        'precio_unitario',
    ];

    protected $casts = [
        'cantidad_minima' => 'integer',
        'precio_unitario' => 'decimal:2',
    ];

    /**
     * Relación con el producto
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
