import { Head, Link } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import axios from 'axios';
import toast from 'react-hot-toast';
import Navbar from '@/Components/Navbar';
import Footer from '@/Components/Footer';
import WhatsAppButton from '@/Components/WhatsAppButton';
import CartButton from '@/Components/CartButton';
import { getProductImageUrl } from '@/utils/images';
import { stockVariante } from '@/utils/productOptions';

const money = new Intl.NumberFormat('es-AR', {
    style: 'currency',
    currency: 'ARS',
    minimumFractionDigits: 0,
    maximumFractionDigits: 2,
});
const formatPrice = (value) => money.format(Number(value) || 0);

// Rueda de color para la variante "a elección del cliente" (mismo criterio que ProductOptions).
const RAINBOW =
    'conic-gradient(from 90deg, #ef4444, #f97316, #eab308, #22c55e, #3b82f6, #6366f1, #a855f7, #ef4444)';

const MAX_QUANTITY = 99;

/**
 * Selector de color de un componente del combo. Reutiliza el patrón de swatches
 * de la ficha de producto, pero sin recargo (el precio del combo es fijo).
 */
function ComponentColorPicker({ component, selection, onChange }) {
    const variants = component.variants || [];
    if (variants.length === 0) return null;

    const selectedVariant = variants.find((v) => v.id === selection.variantId) || null;
    const set = (patch) => onChange({ ...selection, ...patch });

    return (
        <div className="mt-3 space-y-2">
            <p className="text-xs font-semibold text-navy-900">
                Color
                {selectedVariant && <span className="ml-1 font-normal text-navy-900/60">— {selectedVariant.name}</span>}
            </p>
            <div className="flex flex-wrap gap-2.5">
                {variants.map((variant) => {
                    const activo = variant.id === selection.variantId;
                    const sinStock = stockVariante(variant) <= 0;
                    return (
                        <button
                            key={variant.id}
                            type="button"
                            onClick={() => !sinStock && set({ variantId: variant.id })}
                            disabled={sinStock}
                            title={sinStock ? `${variant.name} — sin stock` : variant.name}
                            aria-pressed={activo}
                            aria-label={variant.name}
                            className={`relative flex h-10 w-10 items-center justify-center rounded-full border-2 transition ${
                                activo ? 'border-navy-900 ring-2 ring-navy-900/25' : 'border-navy-900/20 hover:border-navy-900/40'
                            } ${sinStock ? 'cursor-not-allowed opacity-40' : ''}`}
                            style={variant.is_custom_color ? { backgroundImage: RAINBOW } : { backgroundColor: variant.color_hex || '#e5e7eb' }}
                        >
                            {activo && (
                                <span className="flex h-4 w-4 items-center justify-center rounded-full bg-white text-navy-900 shadow">
                                    <svg viewBox="0 0 24 24" className="h-3 w-3" fill="none" stroke="currentColor" strokeWidth="3" strokeLinecap="round" strokeLinejoin="round">
                                        <path d="m4 12 5 5L20 6" />
                                    </svg>
                                </span>
                            )}
                            {sinStock && <span className="pointer-events-none absolute h-[2px] w-8 rotate-45 rounded bg-red-500" />}
                        </button>
                    );
                })}
            </div>

            {selectedVariant?.is_custom_color && (
                <div className="space-y-2 rounded-xl border border-navy-900/15 bg-navy-900/[0.03] p-3">
                    <p className="text-xs font-medium text-navy-900">Elegí el color que querés o describilo.</p>
                    <div className="flex flex-wrap items-center gap-3">
                        <input
                            type="color"
                            value={selection.customColor || '#000000'}
                            onChange={(e) => set({ customColor: e.target.value })}
                            className="h-9 w-12 cursor-pointer rounded border border-navy-900/20 bg-white p-0.5"
                            aria-label="Color elegido"
                        />
                        {selection.customColor ? (
                            <span className="inline-flex items-center gap-2 text-xs font-medium text-navy-900/70">
                                <span className="font-mono uppercase">{selection.customColor}</span>
                                <button type="button" onClick={() => set({ customColor: '' })} className="underline hover:text-navy-900">
                                    quitar
                                </button>
                            </span>
                        ) : (
                            <span className="text-xs text-navy-900/50">Sin color elegido</span>
                        )}
                    </div>
                    <textarea
                        value={selection.customColorText || ''}
                        onChange={(e) => set({ customColorText: e.target.value })}
                        rows={2}
                        maxLength={255}
                        placeholder="Ej: violeta con destellos plateados…"
                        className="block w-full rounded-lg border border-navy-900/20 px-3 py-2 text-sm transition focus:border-navy-900 focus:outline-none focus:ring-2 focus:ring-navy-900/10"
                    />
                </div>
            )}
        </div>
    );
}

function ComboDetail({ auth, combo }) {
    const components = combo.components || [];
    const images = combo.images || [];
    const [activeImage, setActiveImage] = useState(0);
    const [adding, setAdding] = useState(false);

    const [selections, setSelections] = useState(() => {
        const initial = {};
        components.forEach((c) => {
            initial[c.product_id] = {
                variantId: (c.variants || []).length > 0 ? c.variants[0].id : null,
                customColor: '',
                customColorText: '',
            };
        });
        return initial;
    });

    const setSelection = (productId, selection) => {
        setSelections((prev) => ({ ...prev, [productId]: selection }));
    };

    // Valida que cada componente con variantes tenga color elegido, y color libre completo.
    const valido = useMemo(() => {
        return components.every((c) => {
            const variants = c.variants || [];
            if (variants.length === 0) return true;
            const sel = selections[c.product_id];
            if (!sel?.variantId) return false;
            const variante = variants.find((v) => v.id === sel.variantId);
            if (variante?.is_custom_color) {
                return Boolean((sel.customColor || '').trim() || (sel.customColorText || '').trim());
            }
            return true;
        });
    }, [components, selections]);

    const primaryImageUrl = getProductImageUrl(images[activeImage]?.url || images[0]?.url);

    const handleAddToCart = async () => {
        if (adding) return;
        if (!valido) {
            toast.error('Elegí el color de cada producto del combo.');
            return;
        }

        const payloadSelections = components.map((c) => {
            const variants = c.variants || [];
            const sel = selections[c.product_id] || {};
            const variante = variants.find((v) => v.id === sel.variantId) || null;

            let customColorText = null;
            if (variante?.is_custom_color) {
                const parts = [];
                if ((sel.customColorText || '').trim()) parts.push(sel.customColorText.trim());
                if (sel.customColor) parts.push(sel.customColor);
                customColorText = parts.join(' · ') || null;
            }

            return {
                product_id: c.product_id,
                variant_id: sel.variantId ?? null,
                custom_color_text: customColorText,
            };
        });

        try {
            setAdding(true);
            await axios.post(route('cart.combo.add'), {
                combo_id: combo.id,
                quantity: 1,
                selections: payloadSelections,
            });
            window.dispatchEvent(new Event('cart-updated'));
            toast.success(`${combo.title} agregado al carrito`);
        } catch (error) {
            toast.error(error?.response?.data?.message || 'No pudimos agregar el combo. Volvé a intentarlo.');
        } finally {
            setAdding(false);
        }
    };

    return (
        <div className="storefront-background min-h-screen overflow-x-clip text-white">
            <Head title={`${combo.title} - Combo | Chispas Frías`}>
                <meta name="description" content={`Combo ${combo.title} - Pirotecnia fría | Chispas Frías`} />
            </Head>

            <Navbar auth={auth} />

            <main className="pb-16 pt-36 sm:pb-20 sm:pt-40">
                <div className="site-shell">
                    <Link
                        href={route('products.index')}
                        className="mb-6 inline-flex min-h-11 items-center gap-2 rounded-full border border-white/70 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-white hover:text-navy-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white sm:mb-8"
                    >
                        <span aria-hidden="true">←</span>
                        Volver al catálogo
                    </Link>

                    <div className="grid items-start gap-6 lg:grid-cols-[minmax(0,1.05fr)_minmax(0,1fr)]">
                        {/* Galería */}
                        <div className="min-w-0 lg:sticky lg:top-36">
                            <div className="overflow-hidden rounded-[1.75rem] border border-gray-200 bg-surface p-3">
                                <div className="relative aspect-[5/4] overflow-hidden rounded-[1.35rem] bg-background/70">
                                    {primaryImageUrl ? (
                                        images[activeImage]?.type === 'video' ? (
                                            <video src={primaryImageUrl} className="h-full w-full object-contain" controls />
                                        ) : (
                                            <img src={primaryImageUrl} alt={combo.title} className="h-full w-full object-contain" />
                                        )
                                    ) : (
                                        <div className="flex h-full items-center justify-center text-sm text-navy-700">Imagen no disponible</div>
                                    )}
                                    <span className="absolute left-3 top-3 rounded-full bg-promo px-3 py-1 text-xs font-bold uppercase tracking-wide text-navy-900 shadow">Combo</span>
                                </div>
                                {images.length > 1 && (
                                    <div className="mt-3 flex flex-wrap gap-2">
                                        {images.map((img, i) => (
                                            <button
                                                key={img.id}
                                                type="button"
                                                onClick={() => setActiveImage(i)}
                                                className={`h-16 w-16 overflow-hidden rounded-lg border-2 transition ${i === activeImage ? 'border-navy-900' : 'border-transparent hover:border-navy-900/30'}`}
                                            >
                                                {img.type === 'video' ? (
                                                    <video src={getProductImageUrl(img.url)} className="h-full w-full object-cover" muted />
                                                ) : (
                                                    <img src={getProductImageUrl(img.url)} alt="" className="h-full w-full object-cover" />
                                                )}
                                            </button>
                                        ))}
                                    </div>
                                )}
                            </div>
                        </div>

                        {/* Info */}
                        <section className="min-w-0 rounded-[1.75rem] border border-gray-200 bg-surface p-6 text-navy-900 shadow-card sm:p-8">
                            <div className="flex flex-wrap items-center gap-2">
                                <span className="rounded-full bg-promo px-3 py-1.5 text-xs font-bold uppercase tracking-wide text-navy-900">Combo</span>
                                {combo.is_free_shipping && (
                                    <span className="rounded-full bg-storefront px-3 py-1.5 text-xs font-bold text-white">🚚 Envío gratis</span>
                                )}
                            </div>

                            <h1 className="uppercase mt-4 break-words text-3xl font-bold leading-tight tracking-[-0.035em] text-navy-900 sm:text-4xl">
                                {combo.title}
                            </h1>

                            {combo.description && (
                                <p className="mt-3 whitespace-pre-line text-sm leading-relaxed text-navy-900/75">{combo.description}</p>
                            )}

                            <div className="mt-6 rounded-2xl bg-ice-50 p-4 sm:p-5">
                                <p className="mb-1 text-xs font-semibold uppercase tracking-wide text-navy-700">Precio del combo</p>
                                <div className="flex flex-wrap items-baseline gap-x-2">
                                    <span className="break-all text-4xl font-bold tracking-tight text-navy-900">{formatPrice(combo.price)}</span>
                                    <span className="text-xs font-medium text-navy-700">ARS</span>
                                </div>
                                {combo.is_free_shipping && (
                                    <p className="mt-2 text-sm font-semibold text-storefront">Este combo incluye envío gratis.</p>
                                )}
                            </div>

                            {/* Componentes */}
                            <div className="mt-6 space-y-4">
                                <h2 className="text-sm font-semibold text-navy-900">Qué incluye</h2>
                                {components.map((c) => (
                                    <div key={c.product_id} className="rounded-2xl border border-navy-900/10 p-4">
                                        <div className="flex items-center gap-3">
                                            <div className="h-14 w-14 flex-shrink-0 overflow-hidden rounded-lg border border-navy-900/10 bg-background/70">
                                                {c.primary_image ? (
                                                    <img src={getProductImageUrl(c.primary_image)} alt={c.title} className="h-full w-full object-cover" />
                                                ) : (
                                                    <div className="flex h-full w-full items-center justify-center text-[10px] text-navy-700">—</div>
                                                )}
                                            </div>
                                            <div className="min-w-0 flex-1">
                                                <p className="text-sm font-semibold text-navy-900">{c.title}</p>
                                                <p className="text-xs text-navy-900/60">Cantidad: {c.quantity}</p>
                                            </div>
                                        </div>
                                        <ComponentColorPicker
                                            component={c}
                                            selection={selections[c.product_id] || { variantId: null, customColor: '', customColorText: '' }}
                                            onChange={(sel) => setSelection(c.product_id, sel)}
                                        />
                                    </div>
                                ))}
                            </div>

                            <button
                                type="button"
                                onClick={handleAddToCart}
                                disabled={adding || !valido}
                                className="mt-6 inline-flex min-h-12 w-full items-center justify-center gap-2 rounded-full bg-storefront px-5 py-3 text-sm font-semibold text-white shadow-soft transition hover:brightness-90 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-storefront focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60"
                            >
                                {adding && <span className="h-4 w-4 animate-spin rounded-full border-2 border-white/30 border-t-white" aria-hidden="true" />}
                                {adding ? 'Agregando...' : 'Agregar combo al carrito'}
                            </button>
                            {!valido && <p className="mt-2 text-xs text-navy-900/70">Elegí el color de cada producto para agregar el combo.</p>}
                        </section>
                    </div>
                </div>
            </main>

            <Footer />
            <CartButton />
            <WhatsAppButton />
        </div>
    );
}

export default function ComboShow(props) {
    return <ComboDetail key={props.combo.id} {...props} />;
}
