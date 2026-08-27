/**
 * Muestra las opciones elegidas para una línea del carrito / checkout: el color
 * (nombre de la variante, o el color libre si la variante es "a elección del
 * cliente") y los add-ons de personalización con su precio y el texto ingresado.
 *
 * No renderiza nada para una línea sin variante ni add-ons, así el carrito de
 * productos simples se ve igual que antes.
 */
export default function CartLineOptions({ item, className = '' }) {
    const variant = item.variant || null;
    const customColorText = item.custom_color_text || null;
    const addons = item.addons || [];

    if (!variant && !customColorText && addons.length === 0) {
        return null;
    }

    const colorLabel = variant
        ? (variant.is_custom_color ? (customColorText || variant.name) : variant.name)
        : customColorText;

    const money = (n) => `$${Number(n).toLocaleString('es-AR')}`;

    return (
        <div className={`mt-2 space-y-1 text-sm text-navy/70 ${className}`}>
            {colorLabel && (
                <div className="flex flex-wrap items-center gap-x-2 gap-y-0.5">
                    <span className="font-medium text-navy/80">Color:</span>
                    {variant && !variant.is_custom_color && variant.color_hex && (
                        <span
                            className="inline-block h-3.5 w-3.5 flex-shrink-0 rounded-full border border-navy/20"
                            style={{ backgroundColor: variant.color_hex }}
                        />
                    )}
                    <span>{colorLabel}</span>
                    {Number(item.variant_surcharge) > 0 && (
                        <span className="text-navy/50">(+{money(item.variant_surcharge)})</span>
                    )}
                </div>
            )}

            {addons.length > 0 && (
                <ul className="space-y-0.5">
                    {addons.map((addon) => (
                        <li key={addon.addon_id} className="flex flex-wrap items-baseline gap-x-2">
                            <span className="font-medium text-navy/80">{addon.name}:</span>
                            {addon.custom_text && (
                                <span className="italic text-navy/70">“{addon.custom_text}”</span>
                            )}
                            <span className="text-navy/50">
                                {Number(addon.price) > 0 ? `+${money(addon.price)}` : 'Sin costo'}
                            </span>
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}
