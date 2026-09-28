<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Addon extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'price',
        'requires_text',
        'text_placeholder',
        'max_characters',
        'is_active',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'requires_text' => 'boolean',
        'max_characters' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * Productos que ofrecen este add-on. El pivote lleva price_override (precio
     * propio del add-on para ese producto, null = usa addons.price) y sort_order.
     */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_addon')
            ->withPivot(['price_override', 'sort_order'])
            ->withTimestamps();
    }

    /**
     * Scope para add-ons activos
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * True si este add-on ya fue elegido en alguna orden (aparece en algún
     * order_items.addons_selected). Es el análogo de DiscountCode::agotado() /
     * usage_count > 0, pero derivado de las órdenes porque los add-ons no llevan
     * contador propio (incrementarlo sería trabajo del checkout, todavía no hecho).
     *
     * Se resuelve en PHP y no con whereJsonContains: en SQLite (tests) esa
     * cláusula compara el elemento completo del array, no hace partial-object
     * match como MySQL, así que un {"addon_id":X} nunca matchearía el objeto
     * completo {"addon_id":X,"name":...} guardado.
     */
    public function haSidoUsado(): bool
    {
        return OrderItem::query()
            ->whereNotNull('addons_selected')
            ->pluck('addons_selected')
            ->contains(fn ($seleccionados) => collect($seleccionados)->contains('addon_id', $this->id));
    }
}
