import { useCallback, useEffect, useState } from 'react';
import { Link, router } from '@inertiajs/react';
import { Dialog, DialogPanel, DialogTitle } from '@headlessui/react';
import axios from 'axios';
import toast from 'react-hot-toast';
import DiscountCodeField from '@/Components/Cart/DiscountCodeField';
import FreeShippingProgress from '@/Components/FreeShippingProgress';
import { getProductImageUrl } from '@/utils/images';

const money = (value) => Number(value || 0).toLocaleString('es-AR');

function cartImage(product) {
    const images = product.images || [];
    const image = images.find((item) => item.is_primary && item.type !== 'video')
        || images.find((item) => item.type !== 'video');
    return getProductImageUrl(image?.url || image?.path || product.image);
}

export default function CartButton() {
    const [open, setOpen] = useState(false);
    const [cartCount, setCartCount] = useState(0);
    const [preview, setPreview] = useState(null);
    const [loading, setLoading] = useState(false);
    const [loadError, setLoadError] = useState(false);
    const [busyId, setBusyId] = useState(null);

    const fetchCount = useCallback(async () => {
        try {
            const { data } = await axios.get(route('cart.count'));
            setCartCount(Number(data.count) || 0);
        } catch (error) {
            console.error('Error al obtener el contador del carrito:', error);
        }
    }, []);

    const fetchPreview = useCallback(async (silent = false) => {
        if (!silent) setLoading(true);
        setLoadError(false);
        try {
            const { data } = await axios.get(route('cart.preview'));
            setPreview(data);
            setCartCount(Number(data.count) || 0);
            return data;
        } catch (error) {
            setPreview(null);
            setLoadError(true);
            toast.error('No pudimos cargar el carrito.');
            return null;
        } finally {
            setLoading(false);
        }
    }, []);

    useEffect(() => {
        fetchCount();
    }, [fetchCount]);

    useEffect(() => {
        if (open) fetchPreview();
    }, [open, fetchPreview]);

    useEffect(() => {
        const onCartUpdated = (event) => {
            if (event.detail?.source === 'floating-cart') return;
            if (open) {
                fetchPreview(true);
            } else {
                fetchCount();
            }
        };
        window.addEventListener('cart-updated', onCartUpdated);
        return () => window.removeEventListener('cart-updated', onCartUpdated);
    }, [open, fetchCount, fetchPreview]);

    // Tope de cantidad: un producto por su stock (o el de su variante); un combo
    // hasta 99 (el stock real de cada componente lo revalida el backend).
    const maxStockDe = (item) => (item.is_combo ? 99 : Math.min(99, Number(item.variant?.stock ?? item.product.stock)));

    const changeQuantity = async (item, quantity) => {
        if (busyId !== null || quantity < 1 || quantity > maxStockDe(item)) return;
        setBusyId(item.line_key);
        try {
            await axios.patch(route('cart.update'), { line_key: item.line_key, quantity }, {
                headers: { Accept: 'application/json' },
            });
            await fetchPreview(true);
            window.dispatchEvent(new CustomEvent('cart-updated', { detail: { source: 'floating-cart' } }));
        } catch (error) {
            toast.error(error.response?.data?.message || 'No pudimos cambiar la cantidad.');
        } finally {
            setBusyId(null);
        }
    };

    const removeItem = async (item) => {
        if (busyId !== null) return;
        setBusyId(item.line_key);
        try {
            await axios.delete(route('cart.remove'), {
                data: { line_key: item.line_key },
                headers: { Accept: 'application/json' },
            });
            await fetchPreview(true);
            window.dispatchEvent(new CustomEvent('cart-updated', { detail: { source: 'floating-cart' } }));
        } catch (error) {
            toast.error(error.response?.data?.message || 'No pudimos quitar el producto.');
        } finally {
            setBusyId(null);
        }
    };

    const refreshAfterDiscount = async () => {
        await fetchPreview(true);
        const path = window.location.pathname;
        const cartPaths = [route('cart.index'), route('cart.checkout')]
            .map((url) => new URL(url, window.location.origin).pathname);
        if (cartPaths.includes(path)) {
            router.reload({ only: ['cartItems', 'subtotal', 'total', 'discountCode', 'discountCodeRemovedReason'] });
        }
    };

    const items = preview?.cartItems || [];

    return (
        <>
            <button
                type="button"
                onClick={() => { setLoading(true); setOpen(true); }}
                className="fixed bottom-[88px] right-6 z-50 flex h-14 w-14 items-center justify-center rounded-full border-2 border-white bg-storefront text-white shadow-lg transition hover:-translate-y-1 hover:brightness-90 hover:shadow-xl focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-storefront focus-visible:ring-offset-2 md:bottom-[100px] md:h-16 md:w-16"
                aria-label={`Abrir carrito, ${cartCount} ${cartCount === 1 ? 'unidad' : 'unidades'}`}
                aria-haspopup="dialog"
                aria-expanded={open}
            >
                <svg className="h-6 w-6 md:h-7 md:w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.8} d="M6.5 8.5h11l1 11.5h-13l1-11.5Z" />
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.8} d="M9 10V6.75a3 3 0 0 1 6 0V10" />
                </svg>
                {cartCount > 0 && (
                    <span className="absolute -right-2 -top-2 flex h-5 min-w-5 items-center justify-center rounded-full bg-white px-1 text-xs font-bold text-storefront md:h-6 md:min-w-6 md:text-sm">
                        {cartCount > 99 ? '99+' : cartCount}
                    </span>
                )}
            </button>

            <Dialog open={open} onClose={setOpen} className="relative z-[100]">
                <div className="fixed inset-0 bg-navy-900/65" aria-hidden="true" />
                <DialogPanel className="fixed inset-y-0 right-0 flex w-full max-w-md flex-col bg-surface text-navy-900 shadow-2xl">
                    <div className="flex shrink-0 items-start justify-between gap-4 border-b border-gray-200 px-5 py-5 sm:px-6">
                        <div>
                            <DialogTitle className="uppercase text-2xl font-bold">Mi carrito</DialogTitle>
                            <p className="mt-1 text-sm text-navy-900/65">{cartCount} {cartCount === 1 ? 'unidad' : 'unidades'}</p>
                        </div>
                        <button type="button" onClick={() => setOpen(false)} aria-label="Cerrar carrito" className="flex h-10 w-10 items-center justify-center rounded-full border border-storefront text-storefront transition hover:bg-storefront hover:text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-storefront">
                            <svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.8} d="M6 6l12 12M18 6L6 18" /></svg>
                        </button>
                    </div>

                    <div className="min-h-0 flex-1 overflow-y-auto px-5 py-5 sm:px-6">
                        {loading && !preview ? (
                            <p role="status" className="py-12 text-center text-sm text-navy-900/65">Cargando tu carrito...</p>
                        ) : loadError && !preview ? (
                            <div className="py-12 text-center">
                                <p className="text-sm text-navy-900/70">No pudimos mostrar tu carrito.</p>
                                <button type="button" onClick={() => fetchPreview()} className="mt-4 rounded-full border border-storefront px-5 py-2 text-sm font-semibold text-storefront hover:bg-storefront hover:text-white">Reintentar</button>
                            </div>
                        ) : items.length === 0 ? (
                            <div className="flex flex-col items-center py-12 text-center">
                                <div className="mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-ice-50 text-navy-700">
                                    <svg className="h-8 w-8" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.8} d="M3 4h2l2.2 10.5a2 2 0 002 1.5h8.4a2 2 0 002-1.5L21 8H6M10 20h.01M18 20h.01" /></svg>
                                </div>
                                <h3 className="uppercase text-xl font-bold">Tu carrito está vacío</h3>
                                <p className="mt-2 max-w-xs text-sm leading-6 text-navy-900/65">Explorá el catálogo y sumá productos para tu evento.</p>
                                <Link href={route('products.index')} onClick={() => setOpen(false)} className="mt-6 rounded-full bg-storefront px-6 py-3 text-sm font-semibold text-white transition hover:brightness-90">Explorar productos</Link>
                            </div>
                        ) : (
                            <>
                                <div className="space-y-4">
                                    {items.map((item) => {
                                        const isCombo = item.is_combo;
                                        const title = isCombo ? item.combo.title : item.product.title;
                                        const img = isCombo ? getProductImageUrl(item.combo.image) : cartImage(item.product);
                                        const href = isCombo ? route('combos.show', item.combo.id) : route('products.show', item.product.id);
                                        const busy = busyId === item.line_key;

                                        return (
                                        <article key={item.line_key} className={`rounded-2xl border p-3.5 shadow-sm ${isCombo ? 'border-promo' : 'border-gray-200'}`}>
                                            <div className="flex gap-3">
                                                <Link href={href} onClick={() => setOpen(false)} className="flex h-20 w-20 shrink-0 items-center justify-center overflow-hidden rounded-xl bg-ice-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-navy-900" aria-label={`Ver ${title}`}>
                                                    {img ? (
                                                        <img src={img} alt={title} className="h-full w-full object-contain p-1" />
                                                    ) : (
                                                        <svg className="h-8 w-8 text-navy-700/50" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path strokeWidth="1.6" d="M4 16l5-5 4 4 3-3 4 4M5 3h14a2 2 0 012 2v14a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2z" /></svg>
                                                    )}
                                                </Link>
                                                <div className="min-w-0 flex-1">
                                                    <div className="flex items-start justify-between gap-2">
                                                        <Link href={href} onClick={() => setOpen(false)} className="line-clamp-2 text-sm font-bold leading-snug hover:text-navy-700 focus-visible:underline">
                                                            {isCombo && <span className="mr-1 rounded bg-promo px-1 py-0.5 text-[9px] font-bold uppercase text-navy-900">Combo</span>}
                                                            {title}
                                                        </Link>
                                                        <button type="button" onClick={() => removeItem(item)} disabled={busyId !== null} aria-label={`Quitar ${title} del carrito`} className="flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-navy-700 transition hover:bg-ice-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-navy-900 disabled:opacity-40"><svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path strokeWidth="1.7" strokeLinecap="round" strokeLinejoin="round" d="M4 7h16M10 11v6m4-6v6M6 7l1 13h10l1-13M9 7V4h6v3" /></svg></button>
                                                    </div>
                                                    <p className="mt-1 text-xs text-navy-900/60">${money(item.price)} por unidad</p>
                                                    {isCombo && item.combo.is_free_shipping && (
                                                        <p className="mt-0.5 text-xs font-semibold text-storefront">🚚 Envío gratis</p>
                                                    )}
                                                </div>
                                            </div>
                                            <div className="mt-3 flex items-center justify-between gap-3 border-t border-gray-200 pt-3">
                                                <div className="flex items-center rounded-full border border-navy-700" role="group" aria-label={`Cantidad de ${title}`}>
                                                    <button type="button" onClick={() => changeQuantity(item, Number(item.quantity) - 1)} disabled={Number(item.quantity) <= 1 || busyId !== null} aria-label={`Reducir cantidad de ${title}`} className="flex h-8 w-8 items-center justify-center rounded-full text-navy-900 hover:bg-ice-50 disabled:opacity-35">−</button>
                                                    <span className="min-w-7 text-center text-sm font-bold" aria-live="polite">{busy ? '…' : item.quantity}</span>
                                                    <button type="button" onClick={() => changeQuantity(item, Number(item.quantity) + 1)} disabled={Number(item.quantity) >= maxStockDe(item) || busyId !== null} aria-label={`Aumentar cantidad de ${title}`} className="flex h-8 w-8 items-center justify-center rounded-full text-navy-900 hover:bg-ice-50 disabled:opacity-35">+</button>
                                                </div>
                                                <span className="text-sm font-bold">${money(item.subtotal)} <span className="text-[0.65rem] font-medium text-navy-900/60">ARS</span></span>
                                            </div>
                                        </article>
                                        );
                                    })}
                                </div>
                                <div className="mt-5 space-y-4">
                                    <FreeShippingProgress total={preview.subtotal} threshold={preview.freeShippingThreshold} freeShippingByCombo={preview.freeShippingByCombo} />
                                    <DiscountCodeField discountCode={preview.discountCode} removedReason={preview.discountCodeRemovedReason} onChanged={refreshAfterDiscount} inputId="floating-cart-discount-code" />
                                </div>
                            </>
                        )}
                    </div>

                    {items.length > 0 && (
                        <div className="shrink-0 border-t border-gray-200 bg-surface px-5 py-4 shadow-[0_-8px_24px_rgba(10,31,68,0.06)] sm:px-6">
                            <div className="space-y-1.5 text-sm">
                                <div className="flex justify-between"><span className="text-navy-900/70">Subtotal</span><span className="font-semibold">${money(preview.subtotal)}</span></div>
                                {preview.discountCode && <div className="flex justify-between gap-3"><span className="text-navy-900/70">Descuento ({preview.discountCode.code})</span><span className="font-semibold text-navy-700">−${money(preview.discountCode.amount)}</span></div>}
                                <div className="flex justify-between border-t border-gray-200 pt-2 text-lg font-bold"><span>Total</span><span>${money(preview.total)} <span className="text-xs font-medium text-navy-900/60">ARS</span></span></div>
                            </div>
                            <Link href={route('cart.checkout')} onClick={() => setOpen(false)} className="mt-4 flex min-h-12 w-full items-center justify-center rounded-full bg-storefront px-5 text-center text-sm font-bold text-white transition hover:brightness-90 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-storefront">Finalizar pedido <span aria-hidden="true" className="ml-2">→</span></Link>
                            <Link href={route('cart.index')} onClick={() => setOpen(false)} className="mt-2 flex min-h-10 w-full items-center justify-center rounded-full border border-storefront px-5 text-center text-xs font-semibold text-storefront transition hover:bg-storefront hover:text-white">Ver carrito completo</Link>
                        </div>
                    )}
                </DialogPanel>
            </Dialog>
        </>
    );
}
