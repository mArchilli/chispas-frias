import { Link } from '@inertiajs/react';
import { getProductImageUrl } from '@/utils/images';

/**
 * Tarjeta de una línea de combo en el carrito / checkout. A diferencia de una
 * línea de producto, muestra el precio fijo del combo, el badge de envío gratis
 * (si corresponde) y la lista de productos incluidos con el color elegido de
 * cada uno. Comparte controles de cantidad y quitar con la línea de producto.
 */
export default function CartComboLine({ item, updating, removing, onUpdateQuantity, onRemove, readOnly = false }) {
    const combo = item.combo || {};
    const image = getProductImageUrl(combo.image);

    return (
        <div className="rounded-[1.75rem] border-2 border-promo bg-surface p-4 text-navy-900 shadow-card sm:p-5">
            <div className="flex items-start gap-4">
                <Link href={route('combos.show', combo.id)} className="flex-shrink-0">
                    {image ? (
                        <img src={image} alt={combo.title} className="h-28 w-28 rounded-2xl bg-ice-50 object-contain p-2 sm:h-32 sm:w-32" />
                    ) : (
                        <div className="flex h-28 w-28 items-center justify-center rounded-2xl bg-ice-50 text-xs text-navy-700 sm:h-32 sm:w-32">Combo</div>
                    )}
                </Link>

                <div className="min-w-0 flex-1">
                    <div className="flex items-start justify-between gap-2">
                        <div className="min-w-0 flex-1">
                            <div className="flex flex-wrap items-center gap-2">
                                <span className="rounded-full bg-promo px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-navy-900">Combo</span>
                                {combo.is_free_shipping && (
                                    <span className="rounded-full bg-storefront px-2 py-0.5 text-[10px] font-bold text-white">🚚 Envío gratis</span>
                                )}
                            </div>
                            <Link href={route('combos.show', combo.id)} className="mt-1 block break-words text-lg font-semibold text-navy-900 transition-colors hover:text-navy-700">
                                {combo.title}
                            </Link>

                            {/* Productos incluidos con el color elegido */}
                            <ul className="mt-2 space-y-0.5 text-sm text-navy-900/70">
                                {(item.components || []).map((comp, idx) => {
                                    const color = comp.variant
                                        ? (comp.variant.is_custom_color ? (comp.custom_color_text || comp.variant.name) : comp.variant.name)
                                        : comp.custom_color_text;
                                    return (
                                        <li key={idx} className="flex flex-wrap items-center gap-x-1.5">
                                            <span className="font-medium text-navy-900/80">{comp.quantity}×</span>
                                            <span>{comp.product_title}</span>
                                            {color && (
                                                <span className="inline-flex items-center gap-1 text-navy-900/55">
                                                    ·
                                                    {comp.variant && !comp.variant.is_custom_color && comp.variant.color_hex && (
                                                        <span className="inline-block h-3 w-3 rounded-full border border-navy-900/20" style={{ backgroundColor: comp.variant.color_hex }} />
                                                    )}
                                                    {color}
                                                </span>
                                            )}
                                        </li>
                                    );
                                })}
                            </ul>

                            <p className="mt-2 text-xl font-bold text-navy-900">
                                ${Number(item.price).toLocaleString('es-AR')} <span className="text-xs font-medium text-navy-900/60">ARS</span>
                            </p>
                        </div>

                        {!readOnly && (
                            <button
                                onClick={() => onRemove(item.line_key)}
                                disabled={removing}
                                className="rounded-full p-2 text-navy-700 transition hover:bg-ice-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-navy-900 disabled:opacity-50"
                                aria-label={`Quitar ${combo.title} del carrito`}
                            >
                                <svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                </svg>
                            </button>
                        )}
                    </div>

                    <div className="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        {!readOnly ? (
                            <div className="flex items-center gap-3">
                                <span className="whitespace-nowrap text-sm font-medium text-navy-900">Cantidad:</span>
                                <div className="flex items-center rounded-full border border-navy-700">
                                    <button
                                        type="button"
                                        onClick={() => onUpdateQuantity(item.line_key, item.quantity - 1)}
                                        disabled={item.quantity <= 1 || updating}
                                        className="flex h-9 w-9 items-center justify-center rounded-full text-navy-900 hover:bg-ice-50 disabled:cursor-not-allowed disabled:opacity-35"
                                        aria-label={`Reducir cantidad de ${combo.title}`}
                                    >
                                        −
                                    </button>
                                    <span className="min-w-7 text-center text-sm font-bold text-navy-900">
                                        {updating ? '…' : item.quantity}
                                    </span>
                                    <button
                                        type="button"
                                        onClick={() => onUpdateQuantity(item.line_key, item.quantity + 1)}
                                        disabled={item.quantity >= 99 || updating}
                                        className="flex h-9 w-9 items-center justify-center rounded-full text-navy-900 hover:bg-ice-50 disabled:cursor-not-allowed disabled:opacity-35"
                                        aria-label={`Aumentar cantidad de ${combo.title}`}
                                    >
                                        +
                                    </button>
                                </div>
                            </div>
                        ) : (
                            <span className="text-sm font-medium text-navy-900">Cantidad: {item.quantity}</span>
                        )}

                        <div className="flex items-center justify-between border-t border-navy-900/10 pt-3 sm:justify-end sm:border-t-0 sm:pt-0">
                            <span className="text-sm font-medium text-navy-900 sm:hidden">Subtotal:</span>
                            <span className="text-lg font-bold text-navy-900">
                                ${Number(item.subtotal).toLocaleString('es-AR')} <span className="text-xs font-medium text-navy-900/60">ARS</span>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
}
