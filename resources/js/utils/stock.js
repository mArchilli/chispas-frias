/**
 * Umbral compartido de "stock bajo" entre el admin (badge en el listado) y la
 * tienda pública (badge en cards y ficha) — una sola fuente para no terminar
 * con dos números distintos en cada lugar.
 */
export const LOW_STOCK_THRESHOLD = 3;

export function isOutOfStock(stock) {
    return stock <= 0;
}

export function isLowStock(stock) {
    return stock > 0 && stock <= LOW_STOCK_THRESHOLD;
}
