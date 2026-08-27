/**
 * Helpers para las opciones de un producto en la ficha pública (selector de
 * color + add-ons de personalización). Solo lógica de presentación/validación
 * client-side: el precio y la validación que cuentan siempre los resuelve el
 * backend (PricingService) al agregar al carrito / generar el pedido, igual que
 * el resto del catálogo.
 */

/**
 * Forma del estado de opciones que maneja la ficha:
 *   { variantId: number|null, customColor: string, customColorText: string,
 *     addons: { [addonId]: string } }  // presencia de la clave = add-on tildado
 */
export function opcionesIniciales(product) {
    const variants = product?.variants || [];

    return {
        variantId: variants.length > 0 ? variants[0].id : null,
        customColor: '',
        customColorText: '',
        addons: {},
    };
}

/**
 * Stock efectivo de una variante para cap de cantidad / disponibilidad:
 * `stock` null (ilimitado, mismo criterio que products.stock) => Infinity.
 */
export function stockVariante(variant) {
    if (!variant) return Infinity;
    return variant.stock === null || variant.stock === undefined ? Infinity : variant.stock;
}

/** ¿El producto tiene al menos una variante con unidades disponibles? */
export function hayVarianteConStock(variants = []) {
    return variants.some((v) => stockVariante(v) > 0);
}

/** Ids de add-ons tildados, listos para pasar a calcularPrecio({ addonIds }). */
export function addonIdsSeleccionados(opciones) {
    return Object.keys(opciones?.addons || {}).map(Number);
}

/**
 * Payload para POST /carrito/agregar a partir del estado de opciones de la ficha.
 * El color libre (`custom_color_text`) solo se manda cuando la variante elegida
 * es "a elección del cliente": se combina el color del picker y el texto
 * descriptivo en un solo string legible para el vendedor. El backend revalida
 * todo (pertenencia + activo + textos obligatorios) antes de agregar la línea.
 */
export function buildAddToCartPayload(product, quantity, opciones) {
    const variants = product?.variants || [];
    const variante = variants.find((v) => v.id === opciones?.variantId) || null;

    let customColorText = null;
    if (variante?.is_custom_color) {
        const parts = [];
        if ((opciones.customColorText || '').trim()) parts.push(opciones.customColorText.trim());
        if (opciones.customColor) parts.push(opciones.customColor);
        customColorText = parts.join(' · ') || null;
    }

    return {
        product_id: product.id,
        quantity,
        variant_id: opciones?.variantId ?? null,
        addon_ids: addonIdsSeleccionados(opciones),
        addon_texts: { ...(opciones?.addons || {}) },
        custom_color_text: customColorText,
    };
}

const esVideo = (m) => m?.type === 'video' || (m?.mime_type || '').startsWith('video/');

/**
 * Media a mostrar en la galería para la variante elegida. Imágenes y video se
 * resuelven por separado: los medios propios de la variante si tiene alguno, si
 * no los generales (`product_variant_id` null), si no ninguno. Se devuelven
 * combinados y ordenados por `sort_order`.
 *
 * Con `variantId` null (producto sin variantes, o antes de elegir color) el
 * "propio" y el "general" coinciden, así que degrada a "toda la media general" —
 * idéntico a como se mostraba la galería antes de las variantes.
 */
export function galeriaDeVariante(media = [], variantId = null) {
    const bucket = (quieroVideo) => {
        const propios = media.filter(
            (m) => esVideo(m) === quieroVideo && m.product_variant_id === variantId
        );
        if (propios.length > 0) return propios;

        return media.filter((m) => esVideo(m) === quieroVideo && m.product_variant_id == null);
    };

    return [...bucket(false), ...bucket(true)].sort(
        (a, b) => (a.sort_order ?? 0) - (b.sort_order ?? 0)
    );
}

/**
 * Valida las opciones elegidas contra el producto.
 *   { valido: boolean, errores: { colorPersonalizado: string|null,
 *                                 addons: { [addonId]: string } } }
 * - Variante `is_custom_color`: al menos uno de color libre / texto descriptivo.
 * - Add-on tildado con `requires_text`: texto no vacío y dentro de `max_characters`.
 */
export function validarOpciones(product, opciones) {
    const variants = product?.variants || [];
    const addons = product?.addons || [];
    const errores = { colorPersonalizado: null, addons: {} };

    const variante = variants.find((v) => v.id === opciones.variantId) || null;

    if (variante?.is_custom_color) {
        const tieneColor = (opciones.customColor || '').trim().length > 0;
        const tieneTexto = (opciones.customColorText || '').trim().length > 0;

        if (!tieneColor && !tieneTexto) {
            errores.colorPersonalizado = 'Elegí un color o describí el que querés.';
        }
    }

    for (const addon of addons) {
        const texto = opciones.addons?.[addon.id];
        if (texto === undefined) continue; // no tildado

        if (addon.requires_text && !(texto || '').trim()) {
            errores.addons[addon.id] = 'Completá este dato para agregar el producto.';
        } else if (addon.max_characters && (texto || '').length > addon.max_characters) {
            errores.addons[addon.id] = `Máximo ${addon.max_characters} caracteres.`;
        }
    }

    const valido = !errores.colorPersonalizado && Object.keys(errores.addons).length === 0;

    return { valido, errores };
}
