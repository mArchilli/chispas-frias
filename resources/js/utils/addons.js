/**
 * Texto de vista previa para los formularios de Create/Edit de add-ons: se arma
 * en memoria a partir del form, sin llamadas al server, para dar feedback
 * inmediato mientras se completan los campos. Espejo de utils/discountCodes.js.
 */
export function buildAddonPreviewText(data) {
    const name = (data.name || '').trim();
    if (!name) {
        return 'Completá el nombre para ver la vista previa.';
    }

    const parts = [name];

    const price = parseFloat(data.price);
    if (!isNaN(price)) {
        parts.push(price > 0 ? `+$${price.toLocaleString('es-AR')}` : 'sin costo');
    }

    if (data.requires_text) {
        const max = parseInt(data.max_characters, 10);
        parts.push(max > 0 ? `requiere texto (máx. ${max} caracteres)` : 'requiere texto');
    }

    return parts.join(' · ');
}
