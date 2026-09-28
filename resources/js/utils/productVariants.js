/**
 * Opciones para el <select> de "color asociado" del gestor de imágenes del form
 * de producto, a partir del estado actual del repeater de variantes. Las
 * variantes ya guardadas usan su id real; las nuevas (sin id) usan `uid:<_uid>`,
 * que el backend traduce al id real al guardar (ver ProductController::resolveVariantRef).
 */
export function variantSelectOptions(variants = []) {
    return variants
        .filter((v) => (v.name || '').trim())
        .map((v) => ({
            value: v.id ? String(v.id) : `uid:${v._uid}`,
            label: v.name.trim(),
        }));
}
