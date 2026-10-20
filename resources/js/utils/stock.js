/**
 * Umbral compartido de "stock bajo" entre el admin (badge en el listado) y la
 * tienda pública (badge en cards y ficha) — una sola fuente para no terminar
 * con dos números distintos en cada lugar.
 */
export const LOW_STOCK_THRESHOLD = 3;

/**
 * Convención de "stock ilimitado" (espejo de AppSupportStock): un producto lo
 * guarda como 9999 (products.stock no admite NULL) y una variante como NULL.
 */
export const STOCK_ILIMITADO_PRODUCTO = 9999;

export function esStockIlimitado(stock) {
    return stock === null || stock === undefined || Number(stock) >= STOCK_ILIMITADO_PRODUCTO;
}

export function isOutOfStock(stock) {
    return stock <= 0;
}

export function isLowStock(stock) {
    return stock > 0 && stock <= LOW_STOCK_THRESHOLD;
}
