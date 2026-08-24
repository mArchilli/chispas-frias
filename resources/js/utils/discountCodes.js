/**
 * Texto de vista previa para los formularios de Create/Edit de códigos de
 * descuento: se arma en memoria a partir del form, sin llamadas al server,
 * para dar feedback inmediato mientras se completan los campos.
 */
export function buildPreviewText(data) {
    const percentage = parseFloat(data.percentage);
    if (!percentage || percentage <= 0) {
        return 'Completá el porcentaje para ver la vista previa.';
    }

    const parts = [`${percentage}% off`];

    const minPurchase = parseFloat(data.min_purchase_amount);
    if (minPurchase > 0) {
        parts.push(`mínimo $${minPurchase.toLocaleString('es-AR')}`);
    }

    const usageLimit = parseInt(data.usage_limit, 10);
    if (usageLimit > 0) {
        parts.push(`hasta ${usageLimit} ${usageLimit === 1 ? 'uso' : 'usos'}`);
    } else {
        parts.push('usos ilimitados');
    }

    return parts.join(', ');
}
