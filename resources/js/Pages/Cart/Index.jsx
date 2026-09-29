import { Head, Link, router } from '@inertiajs/react';
import { Dialog, DialogPanel, DialogTitle } from '@headlessui/react';
import toast from 'react-hot-toast';
import { getPrimaryImageUrl, getProductImageUrl } from '@/utils/images';
import { useEffect, useState } from 'react';
import Navbar from '@/Components/Navbar';
import Footer from '@/Components/Footer';
import WhatsAppButton from '@/Components/WhatsAppButton';
import CartButton from '@/Components/CartButton';
import FreeShippingProgress from '@/Components/FreeShippingProgress';
import DiscountCodeField from '@/Components/Cart/DiscountCodeField';
import PaymentMethodField from '@/Components/Cart/PaymentMethodField';
import CartLineOptions from '@/Components/Cart/CartLineOptions';
import CartComboLine from '@/Components/Cart/CartComboLine';

export default function CartIndex({ auth, cartItems, subtotal, total, discountCode, discountCodeRemovedReason, paymentPlan, paymentPlanRemovedReason, cardPaymentPlans = [], freeShippingThreshold, freeShippingByCombo = false }) {
    const [updatingItems, setUpdatingItems] = useState({});
    const [removingItems, setRemovingItems] = useState({});
    const [showClearModal, setShowClearModal] = useState(false);
    const [clearingCart, setClearingCart] = useState(false);
    const itemCount = cartItems.reduce((sum, item) => sum + Number(item.quantity), 0);
    const imageUrl = (product) => getProductImageUrl(getPrimaryImageUrl(product)) || getProductImageUrl(product.image);

    // Efecto para bloquear scroll cuando modal está abierta
    useEffect(() => {
        if (showClearModal) {
            document.body.style.overflow = 'hidden';
        } else {
            document.body.style.overflow = 'unset';
        }

        // Cleanup al desmontar el componente
        return () => {
            document.body.style.overflow = 'unset';
        };
    }, [showClearModal]);

    // Función para actualizar cantidad de una línea del carrito. Opera por
    // line_key: ahora puede haber más de una línea del mismo producto (distinto
    // color / distintos add-ons).
    const updateQuantity = (lineKey, newQuantity) => {
        if (newQuantity < 1 || updatingItems[lineKey]) return;

        setUpdatingItems(prev => ({ ...prev, [lineKey]: true }));

        router.patch(route('cart.update'), {
            line_key: lineKey,
            quantity: newQuantity
        }, {
            preserveScroll: true,
            onSuccess: () => {
                // Disparar evento para actualizar el contador del navbar
                window.dispatchEvent(new CustomEvent('cart-updated'));
                // Recargar datos con Inertia. `paymentPlan` viaja también para que
                // el recargo informativo se recalcule sobre el nuevo total.
                router.reload({ only: ['cartItems', 'subtotal', 'total', 'paymentPlan', 'paymentPlanRemovedReason'] });
            },
            onError: () => toast.error('No pudimos cambiar la cantidad.'),
            onFinish: () => {
                setUpdatingItems(prev => {
                    const updated = { ...prev };
                    delete updated[lineKey];
                    return updated;
                });
            }
        });
    };

    // Función para eliminar una línea del carrito
    const removeItem = (lineKey) => {
        setRemovingItems(prev => ({ ...prev, [lineKey]: true }));

        router.delete(route('cart.remove'), {
            data: { line_key: lineKey },
            preserveScroll: true,
            onSuccess: () => {
                // Disparar evento para actualizar el contador del navbar
                window.dispatchEvent(new CustomEvent('cart-updated'));
                // Recargar datos con Inertia. `paymentPlan` viaja también para que
                // el recargo informativo se recalcule sobre el nuevo total.
                router.reload({ only: ['cartItems', 'subtotal', 'total', 'paymentPlan', 'paymentPlanRemovedReason'] });
            },
            onError: () => toast.error('No pudimos quitar el producto.'),
            onFinish: () => {
                setRemovingItems(prev => {
                    const updated = { ...prev };
                    delete updated[lineKey];
                    return updated;
                });
            }
        });
    };

    const confirmClearCart = () => {
        setClearingCart(true);
        router.delete(route('cart.clear'), {
            preserveScroll: true,
            onSuccess: () => {
                setShowClearModal(false);
                window.dispatchEvent(new CustomEvent('cart-updated'));
            },
            onError: () => toast.error('No pudimos vaciar el carrito.'),
            onFinish: () => setClearingCart(false),
        });
    };

    return (
        <>
            <Head title="Carrito de Compras - Chispas Frías" />
            
            <Navbar auth={auth} />

            <div className="storefront-background min-h-screen text-white">
                <main className="site-shell pb-16 pt-40 sm:pb-20 sm:pt-44">
                    <nav aria-label="Ruta de navegación" className="mb-6 flex flex-wrap items-center gap-2 text-sm text-white/75">
                        <Link href={route('products.index')} className="hover:text-white focus-visible:underline">Productos</Link>
                        <span aria-hidden="true">/</span><span aria-current="page" className="text-white">Carrito</span>
                    </nav>
                    <div className="mb-8 flex flex-wrap items-end justify-between gap-4">
                        <div>
                            <h1 className="uppercase text-3xl font-bold tracking-tight sm:text-4xl lg:text-5xl">Mi carrito</h1>
                            <p className="mt-3 max-w-2xl text-white/85">{itemCount ? `${itemCount} ${itemCount === 1 ? 'unidad seleccionada' : 'unidades seleccionadas'}. Revisá los productos antes de finalizar tu pedido.` : 'Explorá el catálogo y encontrá lo que necesitás para tu evento.'}</p>
                        </div>
                        {cartItems.length > 0 && <Link href={route('products.index')} className="inline-flex min-h-11 items-center rounded-full border border-white/70 px-5 text-sm font-semibold text-white transition hover:bg-white hover:text-navy-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white">Seguir comprando <span aria-hidden="true" className="ml-2">↗</span></Link>}
                    </div>
                    {cartItems.length === 0 ? (
                        /* Carrito vacío */
                        <div className="mx-auto max-w-2xl rounded-[1.75rem] border border-gray-200 bg-surface px-6 py-14 text-center text-navy-900 shadow-card sm:px-12">
                            <div className="mb-6">
                                <svg className="mx-auto h-24 w-24 text-navy-900/30" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1} d="M3 3h2l.4 2M7 13h10l4-8H5.4m0 0L7 13m0 0l-2.5 5L21 18m-11-5v0m0 0l-2.5 5" />
                                </svg>
                            </div>
                            <h2 className="uppercase text-2xl font-bold text-navy-900 mb-4">
                                Tu carrito está vacío.
                            </h2>
                            <p className="text-navy-900/70 mb-8">
                                Explora nuestros productos y agrega algunos a tu carrito.
                            </p>
                            <Link
                                href={route('products.index')}
                                className="inline-flex items-center justify-center px-6 py-3 rounded-full font-bold text-base transition-all duration-300 whitespace-nowrap bg-storefront text-white hover:brightness-90 hover:scale-105 shadow-card focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-storefront focus-visible:ring-offset-2"
                            >
                                Explorar productos
                                <svg className="ml-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M17 8l4 4m0 0l-4 4m4-4H3" />
                                </svg>
                            </Link>
                        </div>
                    ) : (
                        /* Items del carrito */
                        <div className="grid min-w-0 grid-cols-1 gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(300px,360px)] lg:items-start">
                            {/* Barra de progreso de envío gratis */}
                            <div className="lg:col-start-1">
                                <FreeShippingProgress total={subtotal} threshold={freeShippingThreshold} freeShippingByCombo={freeShippingByCombo} />
                            </div>

                            {/* Lista de productos */}
                            <div className="space-y-4 lg:col-start-1">
                                {/* Botón para vaciar carrito */}
                                <div className="flex justify-between items-center text-white">
                                
                                    <button
                                        type="button"
                                        onClick={() => setShowClearModal(true)}
                                        disabled={clearingCart}
                                        className="rounded-full px-3 py-2 text-sm font-semibold text-white/85 underline underline-offset-4 hover:text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white disabled:opacity-50"
                                    >
                                        {clearingCart ? 'Vaciando...' : 'Vaciar carrito'}
                                    </button>
                                </div>

                                {cartItems.map((item) => (
                                    item.is_combo ? (
                                        <CartComboLine
                                            key={item.line_key}
                                            item={item}
                                            updating={updatingItems[item.line_key]}
                                            removing={removingItems[item.line_key]}
                                            onUpdateQuantity={updateQuantity}
                                            onRemove={removeItem}
                                        />
                                    ) : (
                                    <div key={item.line_key} className="rounded-[1.75rem] border border-gray-200 bg-surface p-4 text-navy-900 shadow-card sm:p-5">
                                        <div className="flex items-start space-x-4">
                                            {/* Imagen del producto */}
                                            <Link
                                                href={route('products.show', item.product.id)}
                                                className="flex-shrink-0"
                                            >
                                                {imageUrl(item.product) ? (
                                                    <img
                                                        src={imageUrl(item.product)}
                                                        alt={item.product.title}
                                                        className="h-28 w-28 rounded-2xl bg-ice-50 object-contain p-2 sm:h-32 sm:w-32"
                                                    />
                                                ) : (
                                                    <div className="flex h-28 w-28 items-center justify-center rounded-2xl bg-ice-50 sm:h-32 sm:w-32">
                                                        <svg className="h-8 w-8 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                                        </svg>
                                                    </div>
                                                )}
                                            </Link>

                                            {/* Información del producto */}
                                            <div className="flex-1 min-w-0">
                                                <div className="flex justify-between items-start">
                                                    <div className="min-w-0 flex-1">
                                                        <Link
                                                            href={route('products.show', item.product.id)}
                                                            className="break-words text-lg font-semibold text-navy-900 transition-colors hover:text-navy-700"
                                                        >
                                                            {item.product.title}
                                                        </Link>
                                                        <p className="text-sm text-navy-900/60 mt-1">
                                                            {item.product.category?.parent?.name || item.product.category?.name}
                                                        </p>

                                                        {/* Color y add-ons elegidos para esta línea */}
                                                        <CartLineOptions item={item} />

                                                        {/* Precio unitario ya resuelto por cantidad (tier + oferta) */}
                                                        <div className="mt-2">
                                                            {item.unit_savings > 0 ? (
                                                                <div className="space-y-1">
                                                                    <div className="flex items-center gap-2">
                                                                        <span className="text-xl font-bold text-navy-900">
                                                                            ${Number(item.price).toLocaleString('es-AR')}
                                                                        </span>
                                                                        <span className="text-xs font-medium text-navy-700">ARS</span>
                                                                        <span className="text-sm text-navy-900/60 line-through">
                                                                            ${Number(item.list_price).toLocaleString('es-AR')}
                                                                        </span>
                                                                    </div>
                                                                    <div className="flex items-center gap-2">
                                                                        <span className="text-xs bg-promo text-navy-900 px-2 py-1 rounded-full font-bold">
                                                                            -{item.savings_percentage}% OFF
                                                                        </span>
                                                                        <span className="text-xs text-navy-700 font-medium">
                                                                            Ahorras ${Number(item.unit_savings).toLocaleString('es-AR')} por unidad
                                                                        </span>
                                                                    </div>
                                                                </div>
                                                            ) : (
                                                                <p className="text-xl font-bold text-navy-900">
                                                                    ${Number(item.price).toLocaleString('es-AR')} <span className="text-xs font-medium text-navy-900/60">ARS</span>
                                                                </p>
                                                            )}

                                                            {/* Con recargo de color o add-ons, el precio de arriba es solo
                                                                el base: se aclara el unitario real (el que multiplica el subtotal). */}
                                                            {(Number(item.variant_surcharge) > 0 || Number(item.addons_total) > 0) && (
                                                                <p className="text-sm text-navy-900/70 mt-1">
                                                                    Precio unitario con opciones:{' '}
                                                                    <span className="font-semibold text-navy-900">
                                                                        ${Number(item.unit_price).toLocaleString('es-AR')}
                                                                    </span>
                                                                </p>
                                                            )}
                                                        </div>
                                                    </div>

                                                    {/* Botón eliminar */}
                                                    <button
                                                        onClick={() => removeItem(item.line_key)}
                                                        disabled={removingItems[item.line_key]}
                                                        className="rounded-full p-2 text-navy-700 transition hover:bg-ice-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-navy-900 disabled:opacity-50"
                                                        aria-label={`Quitar ${item.product.title} del carrito`}
                                                    >
                                                        {removingItems[item.line_key] ? (
                                                            <svg className="animate-spin h-5 w-5" fill="none" viewBox="0 0 24 24">
                                                                <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                                                                <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                                                            </svg>
                                                        ) : (
                                                            <svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                            </svg>
                                                        )}
                                                    </button>
                                                </div>

                                                {/* Controles de cantidad y subtotal */}
                                                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between mt-4 gap-3">
                                                    <div className="flex items-center gap-3">
                                                        <span className="text-sm font-medium text-navy-900 whitespace-nowrap">
                                                            Cantidad:
                                                        </span>
                                                        <div className="flex items-center rounded-full border border-navy-700">
                                                            <button
                                                                type="button"
                                                                onClick={() => updateQuantity(item.line_key, item.quantity - 1)}
                                                                disabled={item.quantity <= 1 || updatingItems[item.line_key]}
                                                                className="flex h-9 w-9 items-center justify-center rounded-full text-navy-900 hover:bg-ice-50 disabled:cursor-not-allowed disabled:opacity-35"
                                                                aria-label={`Reducir cantidad de ${item.product.title}`}
                                                            >
                                                                −
                                                            </button>
                                                            <span className="min-w-7 text-center text-sm font-bold text-navy-900">
                                                                {updatingItems[item.line_key] ? (
                                                                    <svg className="animate-spin h-4 w-4 mx-auto" fill="none" viewBox="0 0 24 24">
                                                                        <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                                                                        <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                                                                    </svg>
                                                                ) : item.quantity}
                                                            </span>
                                                            <button
                                                                type="button"
                                                                onClick={() => updateQuantity(item.line_key, item.quantity + 1)}
                                                                disabled={item.quantity >= Math.min(99, Number(item.variant?.stock ?? item.product.stock)) || updatingItems[item.line_key]}
                                                                className="flex h-9 w-9 items-center justify-center rounded-full text-navy-900 hover:bg-ice-50 disabled:cursor-not-allowed disabled:opacity-35"
                                                                aria-label={`Aumentar cantidad de ${item.product.title}`}
                                                            >
                                                                +
                                                            </button>
                                                        </div>
                                                    </div>
                                                    
                                                    <div className="flex items-center justify-between sm:justify-end border-t sm:border-t-0 pt-3 sm:pt-0 border-navy-900/10">
                                                        <span className="text-sm font-medium text-navy-900 sm:hidden">Subtotal:</span>
                                                        <span className="text-lg font-bold text-navy-900">
                                                            ${Number(item.subtotal).toLocaleString('es-AR')} <span className="text-xs font-medium text-navy-900/60">ARS</span>
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    )
                                ))}
                            </div>

                            {/* Resumen del carrito */}
                            <div className="lg:col-start-2 lg:row-start-1 lg:row-span-2">
                                <div className="rounded-[1.75rem] border border-gray-200 bg-surface p-5 text-navy-900 shadow-card sm:p-6 lg:sticky lg:top-32">
                                    <h3 className="uppercase text-xl font-semibold text-navy-900 mb-6">
                                        Resumen del pedido
                                    </h3>

                                    {/* Desglose de productos */}
                                    <div className="space-y-3 mb-6">
                                        {cartItems.map((item) => (
                                            <div key={item.line_key} className="flex justify-between text-sm">
                                                <span className="text-navy-900/70 truncate flex-1 mr-2">
                                                    {item.is_combo ? (
                                                        <>
                                                            <span className="font-medium text-navy-900/80">Combo:</span> {item.combo.title}
                                                        </>
                                                    ) : (
                                                        <>
                                                            {item.product.title}
                                                            {item.variant && (
                                                                <span className="text-navy-900/50"> · {item.variant.is_custom_color ? (item.custom_color_text || item.variant.name) : item.variant.name}</span>
                                                            )}
                                                        </>
                                                    )}
                                                    {' '}× {item.quantity}
                                                </span>
                                                <span className="text-navy-900 font-medium">
                                                    ${Number(item.subtotal).toLocaleString('es-AR')}
                                                </span>
                                            </div>
                                        ))}
                                    </div>

                                    <div className="border-t border-navy-900/10 pt-4">
                                        {/* Código de descuento */}
                                        <div className="mb-4 pb-4 border-b border-navy-900/10">
                                            <DiscountCodeField discountCode={discountCode} removedReason={discountCodeRemovedReason} />
                                        </div>

                                        <div className="flex justify-between items-center text-sm mb-2">
                                            <span className="text-navy-900/70">Subtotal:</span>
                                            <span className="text-navy-900 font-medium">
                                                ${Number(subtotal).toLocaleString('es-AR')}
                                            </span>
                                        </div>
                                        {discountCode && (
                                            <div className="flex justify-between items-center text-sm mb-2">
                                                <span className="text-navy-900/70">Descuento ({discountCode.code}):</span>
                                                <span className="text-navy-700 font-medium">
                                                    −${Number(discountCode.amount).toLocaleString('es-AR')}
                                                </span>
                                            </div>
                                        )}
                                        <div className="flex justify-between items-center mb-6 pt-3 border-t border-navy-900/10">
                                            <span className="text-xl font-semibold text-navy-900">
                                                Total:
                                            </span>
                                            <span className="text-2xl font-bold text-navy-900">
                                                ${Number(total).toLocaleString('es-AR')} <span className="text-sm font-medium text-navy-900/60">ARS</span>
                                            </span>
                                        </div>

                                        {/* Forma de pago: efectivo/transferencia (default, sin
                                            recargo) o un plan de cuotas con tarjeta. Informativa:
                                            el total de arriba y el pedido por WhatsApp no cambian. */}
                                        {cardPaymentPlans.length > 0 && (
                                            <div className="mb-6">
                                                <PaymentMethodField
                                                    plans={cardPaymentPlans}
                                                    paymentPlan={paymentPlan}
                                                    removedReason={paymentPlanRemovedReason}
                                                    subtotal={subtotal}
                                                    total={total}
                                                    discountCode={discountCode}
                                                />
                                            </div>
                                        )}

                                        {/* Botones de acción */}
                                        <div className="space-y-3">
                                            <p className="text-sm text-navy-900/70 text-center">Carga tus datos y finaliza tu compra.</p>
                                            <Link 
                                                href={route('cart.checkout')}
                                                disabled={cartItems.length === 0}
                                                className={`flex min-h-12 w-full items-center justify-center rounded-full px-5 text-center font-bold ${
                                                    cartItems.length === 0
                                                        ? 'bg-gray-200 text-gray-500 cursor-not-allowed pointer-events-none'
                                                        : 'bg-storefront text-white hover:brightness-90 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-storefront'
                                                }`}
                                            >
                                                <span className="flex items-center justify-center">
                                                    <svg className="mr-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M17 8l4 4m0 0l-4 4m4-4H3" />
                                                    </svg>
                                                    Continuar pedido
                                                </span>
                                            </Link>
                                            <Link
                                                href={route('products.index')}
                                                className="flex min-h-11 w-full items-center justify-center rounded-full border border-storefront px-5 text-center text-sm font-semibold text-storefront transition hover:bg-storefront hover:text-white focus-visible:ring-2 focus-visible:ring-storefront"
                                            >
                                                Volver a la tienda
                                            </Link>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    )}
                </main>
            </div>

            <Dialog open={showClearModal} onClose={() => !clearingCart && setShowClearModal(false)} className="relative z-[100]">
                <div className="fixed inset-0 bg-navy-900/70" aria-hidden="true" />
                <div className="fixed inset-0 flex items-center justify-center p-4">
                    <DialogPanel className="w-full max-w-md rounded-[1.75rem] bg-surface p-6 text-navy-900 shadow-2xl sm:p-8">
                        <div className="mb-5 flex h-12 w-12 items-center justify-center rounded-full bg-ice-50 text-navy-700">
                            <svg className="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" d="M12 9v4m0 4h.01M4.8 19h14.4a1.6 1.6 0 001.4-2.4l-7.2-12a1.6 1.6 0 00-2.8 0l-7.2 12A1.6 1.6 0 004.8 19z" /></svg>
                        </div>
                        <DialogTitle className="uppercase text-xl font-bold">¿Vaciar el carrito?</DialogTitle>
                        <p className="mt-2 text-sm leading-6 text-navy-900/70">Se quitarán todos los productos que seleccionaste.</p>
                        <div className="mt-7 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                            <button type="button" onClick={() => setShowClearModal(false)} disabled={clearingCart} className="min-h-11 rounded-full border border-storefront px-5 text-sm font-semibold text-storefront transition hover:bg-storefront hover:text-white focus-visible:ring-2 focus-visible:ring-storefront">Cancelar</button>
                            <button type="button" onClick={confirmClearCart} disabled={clearingCart} className="min-h-11 rounded-full bg-storefront px-5 text-sm font-semibold text-white transition hover:brightness-90 focus-visible:ring-2 focus-visible:ring-storefront disabled:opacity-50">{clearingCart ? 'Vaciando...' : 'Sí, vaciar carrito'}</button>
                        </div>
                    </DialogPanel>
                </div>
            </Dialog>

            <Footer />
            <CartButton />
            <WhatsAppButton />
        </>
    );
}
