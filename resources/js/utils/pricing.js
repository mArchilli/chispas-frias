/**
 * Espejo en JS de App\Services\PricingService, usado SOLO para preview instantáneo
 * en el cliente (pills/tabla de precios por cantidad en la ficha de producto, y la
 * vista previa de descuentos en el admin). El precio que efectivamente se cobra
 * siempre se resuelve en el backend con PricingService — al agregar al carrito, al
 * actualizar cantidad, y al generar el pedido — así que esta duplicación nunca
 * determina un monto real, solo lo que se muestra antes de esa llamada al servidor.
 */

/**
 * Aplica un descuento (porcentaje o fijo) sobre un precio de lista.
 * Devuelve null si no hay datos suficientes para calcular.
 */
export function aplicarDescuento(precioLista, tipoDescuento, valorDescuento) {
    const valor = Number(valorDescuento);
    if (precioLista == null || isNaN(valor)) return null;

    const precio = tipoDescuento === 'porcentaje'
        ? precioLista * (1 - valor / 100)
        : precioLista - valor;

    return Math.max(0, Math.round(precio * 100) / 100);
}

/**
 * Escala de precio aplicable para una cantidad dada: la de mayor cantidad_minima
 * que sea <= cantidad. Null si ninguna aplica (corresponde el precio base).
 */
export function tierAplicable(priceTiers, cantidad) {
    const candidatos = (priceTiers || []).filter((tier) => Number(tier.cantidad_minima) <= cantidad);
    if (candidatos.length === 0) return null;

    return candidatos.reduce((mejor, actual) =>
        Number(actual.cantidad_minima) > Number(mejor.cantidad_minima) ? actual : mejor
    );
}

/**
 * La oferta aplica a todo el catálogo, o solo si el tier al que apunta coincide
 * con el tier resuelto para esta cantidad (null === null cubre "ambos apuntan al
 * precio base").
 */
export function ofertaAplica(offer, tier) {
    if (!offer) return false;
    if (offer.alcance === 'todos') return true;

    const tierId = tier?.id ?? null;
    const offerTierId = offer.product_price_tier_id ?? null;
    return offerTierId === tierId;
}

/**
 * Variante de color elegida dentro de `product.variants` (misma forma que la
 * relación `variantsActive` del backend: id, price_addon, is_active). Preview
 * client-side: NO valida pertenencia/estado como sí hace PricingService —
 * solo excluye las explícitamente inactivas. Null si no hay id o no se encuentra.
 */
export function resolverVariante(product, varianteId) {
    if (varianteId == null) return null;

    const variante = (product?.variants || []).find(
        (v) => Number(v.id) === Number(varianteId) && v.is_active !== false
    );

    return variante ?? null;
}

/**
 * Add-ons elegidos dentro de `product.addons` (id, price, is_active y el pivote
 * con price_override). Devuelve los encontrados en el orden en que vinieron los
 * ids, ignorando duplicados y los que no matchean (preview lenient).
 */
export function resolverAddons(product, addonIds) {
    const ids = [...new Set((addonIds || []).map(Number).filter((id) => id > 0))];
    if (ids.length === 0) return [];

    const disponibles = (product?.addons || []).filter((a) => a.is_active !== false);

    return ids
        .map((id) => disponibles.find((a) => Number(a.id) === id))
        .filter(Boolean);
}

/**
 * Precio efectivo de un add-on para este producto: price_override del pivote si
 * lo hay, si no el price del catálogo. Inertia serializa el pivote como
 * `addon.pivot.price_override`; se contempla también la forma aplanada.
 */
export function precioAddon(addon) {
    const override = addon?.pivot?.price_override ?? addon?.price_override ?? null;
    const precio = Number(override ?? addon?.price ?? 0);

    return Math.round(Math.max(0, precio) * 100) / 100;
}

/**
 * Resuelve el precio unitario para un producto y una cantidad dada, combinando
 * la escala de precios por cantidad (price_tiers) con la oferta activa
 * (current_offer), si corresponde aplicarla según su alcance. Espera un objeto
 * `product` con `price`, `price_tiers` (id, cantidad_minima, precio_unitario) y
 * `current_offer` (tipo_descuento, valor_descuento, alcance, product_price_tier_id) —
 * la misma forma que envían ProductController@index/@show.
 *
 * `opciones` (opcional): { varianteId, addonIds } para incluir el recargo de la
 * variante de color y el costo de los add-ons. El descuento de la oferta se
 * calcula SIEMPRE solo sobre el precio de lista/tier — nunca sobre esos recargos,
 * mismo criterio que PricingService. Los callers de precio de vidriera (pills,
 * tabla por cantidad) siguen llamando con dos argumentos y no ven ningún cambio.
 */
export function calcularPrecio(product, cantidad, opciones = {}) {
    const { varianteId = null, addonIds = [] } = opciones;

    const priceTiers = product?.price_tiers || [];
    const tier = tierAplicable(priceTiers, cantidad);
    const precioLista = Math.round((tier ? Number(tier.precio_unitario) : Number(product?.price ?? 0)) * 100) / 100;

    const offer = product?.current_offer ?? null;
    const ofertaAplicada = ofertaAplica(offer, tier) ? offer : null;

    const precioUnitarioFinal = ofertaAplicada
        ? aplicarDescuento(precioLista, ofertaAplicada.tipo_descuento, ofertaAplicada.valor_descuento)
        : precioLista;

    const ahorroUnitario = Math.round(Math.max(0, precioLista - precioUnitarioFinal) * 100) / 100;
    const ahorroPorcentaje = precioLista > 0
        ? Math.round((ahorroUnitario / precioLista) * 10000) / 100
        : 0;

    const varianteAplicada = resolverVariante(product, varianteId);
    const addonsAplicados = resolverAddons(product, addonIds);

    const recargoVariante = varianteAplicada
        ? Math.round(Math.max(0, Number(varianteAplicada.price_addon) || 0) * 100) / 100
        : 0;
    const addonsTotal = Math.round(
        addonsAplicados.reduce((acc, addon) => acc + precioAddon(addon), 0) * 100
    ) / 100;

    const precioFinalConOpciones = Math.round(
        (precioUnitarioFinal + recargoVariante + addonsTotal) * 100
    ) / 100;

    return {
        tier,
        precioLista,
        precioUnitarioFinal,
        ofertaAplicada,
        ahorroUnitario,
        ahorroPorcentaje,
        varianteAplicada,
        addonsAplicados,
        recargoVariante,
        addonsTotal,
        precioFinalConOpciones,
    };
}
