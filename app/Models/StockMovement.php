<?php

namespace App\Models;

use App\Enums\MotivoMovimientoStock;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'product_variant_id',
        'order_id',
        'cantidad',
        'motivo',
        'stock_resultante',
    ];

    protected $casts = [
        'motivo' => MotivoMovimientoStock::class,
    ];

    /**
     * Relación con el producto
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Relación con la variante (opcional). null = el movimiento aplica al stock
     * del producto.
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    /**
     * Relación con la orden (opcional, un movimiento puede no venir de una orden)
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
