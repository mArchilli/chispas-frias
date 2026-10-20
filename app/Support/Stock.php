<?php

namespace App\Support;

/**
 * Convención de «stock ilimitado» de la web, en un solo lugar.
 *
 *  - `products.stock` es NOT NULL con default 9999: 9999 es el «ilimitado» de un producto.
 *  - `product_variants.stock` admite NULL: NULL es el «ilimitado» de una variante.
 *
 * Es una convención de VALORES, no de comportamiento: el carrito, el checkout y el
 * StockService tratan el 9999 como un número más (el checkout lo descuenta, 9999 → 9998), igual que antes de
 * existir esta clase. Lo único que cambia con ella es que el 9999 ya no está escrito en cada controlador.
 *
 * El front tiene su espejo en resources/js/utils/stock.js (STOCK_ILIMITADO_PRODUCTO / esStockIlimitado).
 */
final class Stock
{
    /** Valor de `products.stock` que significa «ilimitado». */
    public const ILIMITADO_PRODUCTO = 9999;

    /** Valor de `product_variants.stock` que significa «ilimitado». */
    public const ILIMITADO_VARIANTE = null;

    /** ¿Este valor de columna es «ilimitado» (NULL, o 9999 o más)? */
    public static function esIlimitado(?int $stock): bool
    {
        return $stock === null || $stock >= self::ILIMITADO_PRODUCTO;
    }

    /**
     * El valor de columna que representa «ilimitado» para el tipo de ítem.
     *
     * @param  'producto'|'variante'  $tipo  Los tipos del Central.
     */
    public static function ilimitadoDe(string $tipo): ?int
    {
        return $tipo === 'variante' ? self::ILIMITADO_VARIANTE : self::ILIMITADO_PRODUCTO;
    }
}
