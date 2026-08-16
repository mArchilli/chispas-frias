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
 * Resuelve el precio unitario para un producto y una cantidad dada, combinando
 * la escala de precios por cantidad (price_tiers) con la oferta activa
 * (current_offer), si corresponde aplicarla según su alcance. Espera un objeto
 * `product` con `price`, `price_tiers` (id, cantidad_minima, precio_unitario) y
 * `current_offer` (tipo_descuento, valor_descuento, alcance, product_price_tier_id) —
 * la misma forma que envían ProductController@index/@show.
 */
export function calcularPrecio(product, cantidad) {
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

    return {
        tier,
        precioLista,
        precioUnitarioFinal,
        ofertaAplicada,
        ahorroUnitario,
        ahorroPorcentaje,
    };
}
