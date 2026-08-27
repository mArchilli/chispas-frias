<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'product_variant_id',
        'product_title',
        'variant_name',
        'variant_color_hex',
        'variant_price_addon',
        'custom_color_text',
        'addons_selected',
        'addons_total',
        'cantidad',
        'precio_unitario',
        'base_unit_price',
        'subtotal',
    ];

    protected $casts = [
        'variant_price_addon' => 'decimal:2',
        'addons_selected' => 'array',
        'addons_total' => 'decimal:2',
        'precio_unitario' => 'decimal:2',
        'base_unit_price' => 'decimal:2',
        'subtotal' => 'decimal:2',
    ];

    /**
     * Relación con la orden
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Relación con el producto (opcional, para no romper el historial si se borra)
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Relación con la variante elegida (opcional). Los campos variant_* son
     * snapshots que sobreviven si la variante se borra.
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }
}
