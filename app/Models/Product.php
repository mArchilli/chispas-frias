<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'price',
        'sku',
        'category_id',
        'stock',
        'is_active',
        'is_featured'
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'is_active' => 'boolean',
        'is_featured' => 'boolean'
    ];

    protected $attributes = [
        'stock' => 9999
    ];

    protected $appends = [
        'current_price',
        'formatted_current_price', 
        'formatted_offer_price',
        'discount_percentage',
        'has_active_offer'
    ];

    /**
     * Relación con categoría
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Relación con imágenes
     */
    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order');
    }

    /**
     * Relación con ofertas
     */
    public function offers(): HasMany
    {
        return $this->hasMany(ProductOffer::class);
    }

    /**
     * Relación con los movimientos de stock
     */
    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    /**
     * Relación con las escalas de precio por cantidad
     */
    public function priceTiers(): HasMany
    {
        return $this->hasMany(ProductPriceTier::class)->orderBy('cantidad_minima');
    }

    /**
     * Obtener la escala de precio aplicable para una cantidad dada
     * (la de mayor cantidad_minima que sea <= $cantidad). Null si ninguna aplica,
     * en cuyo caso corresponde usar el precio de lista (products.price).
     */
    public function tierAplicable(int $cantidad): ?ProductPriceTier
    {
        // Si priceTiers ya viene eager-loaded (ej. catálogo público con
        // with('priceTiers') sobre un listado completo), resolver en memoria
        // para no disparar una query por producto (Fase C5 — antes esto ignoraba
        // el eager-load y siempre pegaba contra la base).
        if ($this->relationLoaded('priceTiers')) {
            return $this->priceTiers
                ->where('cantidad_minima', '<=', $cantidad)
                ->sortByDesc('cantidad_minima')
                ->first();
        }

        // Fallback: query directa cuando no viene eager-loaded (ej. el carrito,
        // que resuelve producto por producto y no se beneficia de precargar tiers).
        // reorder(): priceTiers() ya trae orderBy('cantidad_minima') asc; encadenar
        // orderByDesc() lo apila en vez de reemplazarlo (ORDER BY ... asc, ... desc),
        // y con eso el asc gana y first() devuelve el tier MÁS BAJO que califica.
        return $this->priceTiers()
            ->where('cantidad_minima', '<=', $cantidad)
            ->reorder('cantidad_minima', 'desc')
            ->first();
    }

    /**
     * Relación con las variantes de color (cada una con su propio stock y recargo)
     */
    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class)->orderBy('sort_order');
    }

    /**
     * Solo las variantes activas
     */
    public function variantsActive(): HasMany
    {
        return $this->hasMany(ProductVariant::class)
                    ->where('is_active', true)
                    ->orderBy('sort_order');
    }

    /**
     * Add-ons de personalización que ofrece este producto (catálogo global).
     * El pivote lleva price_override (precio propio para este producto,
     * null = usa addons.price) y sort_order.
     */
    public function addons(): BelongsToMany
    {
        return $this->belongsToMany(Addon::class, 'product_addon')
                    ->withPivot(['price_override', 'sort_order'])
                    ->withTimestamps()
                    ->orderByPivot('sort_order');
    }

    /**
     * Solo los add-ons activos
     */
    public function addonsActive(): BelongsToMany
    {
        return $this->addons()->where('addons.is_active', true);
    }

    /**
     * Verificar si el producto tiene variantes de color cargadas
     */
    public function hasVariants(): bool
    {
        if ($this->relationLoaded('variants')) {
            return $this->variants->isNotEmpty();
        }

        return $this->variants()->exists();
    }

    /**
     * Relación con la oferta activa actual
     */
    public function currentOffer()
    {
        return $this->hasOne(ProductOffer::class)
                    ->active()
                    ->latest();
    }

    /**
     * Obtener la imagen principal
     */
    public function primaryImage(): ?ProductImage
    {
        return $this->images()->where('is_primary', true)->first() 
               ?? $this->images()->first();
    }

    /**
     * Scope para productos activos
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope para productos destacados
     */
    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    /**
     * Scope para productos en stock
     */
    public function scopeInStock($query)
    {
        return $query->where('stock', '>', 0);
    }

    /**
     * Verificar si el producto está en stock
     */
    public function isInStock(): bool
    {
        return $this->stock > 0;
    }

    /**
     * Verificar si hay stock suficiente para una cantidad determinada
     */
    public function tieneStockDisponible(int $cantidad): bool
    {
        return $this->stock >= $cantidad;
    }

    /**
     * Obtener el precio formateado
     */
    public function getFormattedPriceAttribute(): string
    {
        return '$' . number_format((float) $this->price, 2);
    }

    /**
     * Verificar si el producto tiene una oferta activa
     */
    public function hasActiveOffer(): bool
    {
        return $this->getCurrentOfferPrice() !== null;
    }

    /**
     * Obtener el precio de oferta actual (si existe)
     */
    public function getCurrentOfferPrice(): ?float
    {
        $currentOffer = $this->offers()
                             ->active()
                             ->latest()
                             ->first();

        // offer_price puede ser null aunque haya una oferta activa: pasa cuando
        // alcance=Especifico apunta a un tier que no es el precio base (Fase C4).
        // `(float) null` da 0.0, no null, así que hay que chequear explícitamente
        // para que hasActiveOffer() siga interpretando esto como "sin oferta".
        return $currentOffer?->offer_price !== null ? (float) $currentOffer->offer_price : null;
    }

    /**
     * Obtener el precio actual (con o sin oferta)
     */
    public function getCurrentPrice(): float
    {
        return $this->getCurrentOfferPrice() ?? (float) $this->price;
    }

    /**
     * Obtener el precio actual como atributo calculado
     */
    public function getCurrentPriceAttribute(): float
    {
        return $this->getCurrentPrice();
    }

    /**
     * Obtener el precio actual formateado
     */
    public function getFormattedCurrentPriceAttribute(): string
    {
        return '$' . number_format($this->getCurrentPrice(), 2);
    }

    /**
     * Obtener el precio de oferta formateado (si existe)
     */
    public function getFormattedOfferPriceAttribute(): ?string
    {
        $offerPrice = $this->getCurrentOfferPrice();
        return $offerPrice ? '$' . number_format($offerPrice, 2) : null;
    }

    /**
     * Calcular el porcentaje de descuento (si hay oferta)
     */
    public function getDiscountPercentageAttribute(): ?int
    {
        $offerPrice = $this->getCurrentOfferPrice();
        
        if (!$offerPrice || $offerPrice >= $this->price) {
            return null;
        }
        
        return round((($this->price - $offerPrice) / $this->price) * 100);
    }

    /**
     * Atributo calculado para verificar si tiene oferta activa
     */
    public function getHasActiveOfferAttribute(): bool
    {
        return $this->hasActiveOffer();
    }

    /**
     * Obtener la categoría principal (padre de la subcategoría)
     */
    public function getMainCategoryAttribute(): ?Category
    {
        if ($this->category->isMain()) {
            return $this->category;
        }

        return $this->category->parent;
    }
}