/**
 * Clave temporal estable para filas nuevas de un repeater (variantes de color)
 * que todavía no existen en la base: el front la usa como `_uid` y el backend la
 * traduce al id real recién al guardar, dentro de la misma transacción (ver
 * ProductController::syncVariants / resolveVariantRef).
 */
export const genUid = () =>
    'v-' +
    (globalThis.crypto?.randomUUID?.() ??
        `${Date.now()}-${Math.random().toString(36).slice(2, 10)}`);
