import { Head, Link, useForm } from '@inertiajs/react';
import { useState } from 'react';
import toast from 'react-hot-toast';
import { getPrimaryImageUrl, getProductImageUrl } from '@/utils/images';
import Navbar from '@/Components/Navbar';
import Footer from '@/Components/Footer';
import WhatsAppButton from '@/Components/WhatsAppButton';
import CartButton from '@/Components/CartButton';
import FreeShippingProgress from '@/Components/FreeShippingProgress';
import DiscountCodeField from '@/Components/Cart/DiscountCodeField';
import PaymentMethodField from '@/Components/Cart/PaymentMethodField';
import CartLineOptions from '@/Components/Cart/CartLineOptions';

function ShippingSummaryLine({ freeShippingAchieved }) {
    return (
        <>
            <div className="flex justify-between items-center text-sm">
                <span className="text-navy-900/70">Envío:</span>
                {freeShippingAchieved ? (
                    <span className="inline-flex items-center gap-1 font-bold text-navy-700">
                        <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                        </svg>
                        Gratis
                    </span>
                ) : (
                    <span className="font-medium text-navy-900/60">A coordinar</span>
                )}
            </div>
            {!freeShippingAchieved && (
                <p className="text-xs text-navy-900/50 mt-1">
                    El costo de envío te lo notifica el vendedor una vez enviado el pedido.
                </p>
            )}
        </>
    );
}

// Detecta mobile por user agent para decidir a qué URL de WhatsApp navegar:
// en mobile usamos wa.me (deriva a la app instalada); en desktop, WhatsApp Web.
function isMobileDevice() {
    if (typeof navigator === 'undefined') return false;
    return /Android|iPhone|iPad|iPod/i.test(navigator.userAgent);
}

export default function CartCheckout({ auth, cartItems, subtotal, total, discountCode, discountCodeRemovedReason, paymentPlan, paymentPlanRemovedReason, cardPaymentPlans = [], provinces, freeShippingThreshold }) {
    const [submissionErrors, setSubmissionErrors] = useState({});
    const [generatingMessage, setGeneratingMessage] = useState(false);
    const [orderSubmitted, setOrderSubmitted] = useState(false);
    const [pendingWhatsAppUrl, setPendingWhatsAppUrl] = useState('');
    const [confirmedOrderId, setConfirmedOrderId] = useState(null);
    const [confirmedTotal, setConfirmedTotal] = useState(null);

    const freeShippingAchieved =
        Number(freeShippingThreshold) > 0 && Number(subtotal) >= Number(freeShippingThreshold);

    const { data, setData } = useForm({
        customer_data: {
            name: '',
            lastname: '',
            dni: '',
            province: '',
            city: '',
            postal_code: '',
            phone: '',
            email: '',
            observations: ''
        }
    });

    const imageUrl = (product) => getProductImageUrl(getPrimaryImageUrl(product)) || getProductImageUrl(product.image);

    const handleInputChange = (field, value) => {
        setData('customer_data', {
            ...data.customer_data,
            [field]: value,
            ...(field === 'province' ? { city: '' } : {}),
        });
        setSubmissionErrors((current) => {
            if (!current[`customer_data.${field}`]) return current;
            const next = { ...current };
            delete next[`customer_data.${field}`];
            return next;
        });
    };

    // Función para generar mensaje de WhatsApp
    const generateWhatsAppMessage = async (e) => {
        e.preventDefault();
        setGeneratingMessage(true);
        setSubmissionErrors({});

        // Abrimos la pestaña de WhatsApp AHORA, todavía dentro del gesto de click.
        // Si esperáramos a que responda el fetch, el navegador (sobre todo en
        // mobile) trata el window.open como popup y lo bloquea. La dejamos "en
        // blanco" y le seteamos la URL real cuando llega la respuesta del server.
        let waWindow = null;
        try {
            waWindow = window.open('', '_blank');
        } catch (_) {
            waWindow = null;
        }
        try {
            waWindow?.document.write(
                '<!doctype html><meta charset="utf-8"><title>Abriendo WhatsApp…</title>' +
                '<p style="font-family:system-ui,sans-serif;padding:24px;color:#032541">' +
                'Generando tu pedido y abriendo WhatsApp…</p>'
            );
        } catch (_) {
            // Si no se pudo escribir el placeholder no pasa nada: igual
            // redirigimos la pestaña cuando responda el servidor.
        }

        try {
            const response = await fetch(route('cart.whatsapp'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify(data)
            });

            const result = await response.json();
            if (!response.ok) {
                if (waWindow && !waWindow.closed) waWindow.close();
                setSubmissionErrors(Object.fromEntries(Object.entries(result.errors || {}).map(([field, messages]) => [field, Array.isArray(messages) ? messages[0] : messages])));
                toast.error(result.message || 'Revisá los datos del formulario.');
                return;
            }

            if (result.success) {
                const message = encodeURIComponent(result.message);
                const whatsappNumber = '5491178886833';
                // Mobile: wa.me deriva a la app instalada (y si no está, muestra
                // una página con instrucciones). Desktop: WhatsApp Web directo.
                const whatsappUrl = isMobileDevice()
                    ? `https://wa.me/${whatsappNumber}?text=${message}`
                    : `https://web.whatsapp.com/send?phone=${whatsappNumber}&text=${message}`;

                // Disparar evento para actualizar contador del carrito
                window.dispatchEvent(new CustomEvent('cart-updated'));

                // Redirigir la pestaña que ya abrimos hacia WhatsApp. Si el
                // navegador la bloqueó (waWindow === null o el usuario la cerró),
                // la pantalla de éxito de abajo deja el botón "Abrir WhatsApp"
                // para reintentar con un gesto directo.
                if (waWindow && !waWindow.closed) {
                    waWindow.location.href = whatsappUrl;
                }

                setPendingWhatsAppUrl(whatsappUrl);
                setConfirmedOrderId(result.order_id ?? null);
                setConfirmedTotal(result.total ?? total);
                setOrderSubmitted(true);
            } else {
                if (waWindow && !waWindow.closed) waWindow.close();
                toast.error(result.message || 'No pudimos preparar el pedido.');
            }
        } catch (error) {
            if (waWindow && !waWindow.closed) waWindow.close();
            console.error('Error al generar mensaje:', error);
            toast.error('No pudimos preparar el pedido. Intentá nuevamente.');
        } finally {
            setGeneratingMessage(false);
        }
    };

    return (
        <>
            <Head title="Finalizar Pedido - Chispas Frías" />
            
            <Navbar auth={auth} />

            <div className="storefront-background min-h-screen text-white">
                <main className="site-shell pb-16 pt-40 sm:pb-20 sm:pt-44">
                    <nav aria-label="Ruta de navegación" className="mb-6 flex flex-wrap items-center gap-2 text-sm text-white/75">
                        <Link href={route('products.index')} className="hover:text-white focus-visible:underline">Productos</Link>
                        <span aria-hidden="true">/</span>
                        <Link href={route('cart.index')} className="hover:text-white focus-visible:underline">Carrito</Link>
                        <span aria-hidden="true">/</span><span aria-current="page" className="text-white">Finalizar pedido</span>
                    </nav>
                    <div className="mb-8 flex flex-wrap items-end justify-between gap-4">
                        <div>
                            <h1 className="uppercase text-3xl font-bold tracking-tight sm:text-4xl lg:text-5xl">{orderSubmitted ? 'Pedido preparado' : 'Finalizar pedido'}</h1>
                            <p className="mt-3 max-w-2xl text-white/85">{orderSubmitted ? 'Abrí WhatsApp para enviarnos tu pedido y coordinar la compra.' : 'Completá tus datos de contacto y coordinamos la entrega por WhatsApp.'}</p>
                        </div>
                        {!orderSubmitted && <Link href={route('cart.index')} className="inline-flex min-h-11 items-center rounded-full border border-white/70 px-5 text-sm font-semibold text-white transition hover:bg-white hover:text-navy-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white"><span aria-hidden="true" className="mr-2">←</span> Volver al carrito</Link>}
                    </div>
                    {orderSubmitted && (
                        <div className="flex justify-center py-4 sm:py-10">
                            <div className="w-full max-w-xl rounded-[1.75rem] border border-gray-200 bg-surface p-6 text-center text-navy-900 shadow-card sm:p-10">
                                {/* Ícono de éxito */}
                                <div className="w-20 h-20 bg-ice-100 rounded-full flex items-center justify-center mx-auto mb-6">
                                    <svg className="w-10 h-10 text-navy-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                                    </svg>
                                </div>
                                <h2 className="uppercase text-2xl font-bold text-navy-900 mb-1">¡Pedido listo!</h2>
                                {confirmedOrderId && (
                                    <p className="text-navy-900/50 font-medium mb-3">Pedido #{confirmedOrderId}</p>
                                )}
                                <p className="text-navy-900/70 mb-6">
                                    Tu pedido fue procesado correctamente y abrimos WhatsApp en otra pestaña con el mensaje listo para enviar. Si no se abrió o cerraste esa ventana, tocá el botón de abajo para enviarlo.
                                </p>

                                {/* Resumen de los productos comprados */}
                                <div className="mb-6 rounded-2xl border border-gray-200 bg-ice-50/60 p-4 text-left sm:p-5">
                                    <p className="text-sm font-semibold text-navy-900 mb-3">Resumen del pedido</p>
                                    <div className="space-y-2 mb-3">
                                        {cartItems.map((item) => (
                                            <div key={item.line_key} className="flex justify-between items-start gap-3 text-sm">
                                                <span className="text-navy-900/80">
                                                    {item.quantity} × {item.product.title}
                                                    {item.variant && (
                                                        <span className="text-navy/50"> · {item.variant.is_custom_color ? (item.custom_color_text || item.variant.name) : item.variant.name}</span>
                                                    )}
                                                </span>
                                                <span className="text-navy-900 font-medium whitespace-nowrap">
                                                    ${Number(item.subtotal).toLocaleString('es-AR')}
                                                </span>
                                            </div>
                                        ))}
                                    </div>
                                    <div className="border-t border-navy-900/10 pt-3">
                                        <ShippingSummaryLine freeShippingAchieved={freeShippingAchieved} />
                                    </div>
                                    <div className="border-t border-navy-900/10 mt-3 pt-3 flex justify-between items-center">
                                        <span className="text-sm font-semibold text-navy-900">Total</span>
                                        <span className="text-lg font-bold text-navy-900">
                                            ${Number(confirmedTotal ?? total).toLocaleString('es-AR')}
                                        </span>
                                    </div>
                                </div>

                                {/* Botón WhatsApp — fallback si la pestaña no se abrió sola;
                                    el usuario lo toca directamente (gesto directo) */}
                                <a
                                    href={pendingWhatsAppUrl}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className="mb-4 flex min-h-12 w-full items-center justify-center gap-3 rounded-full bg-storefront px-5 py-3 text-lg font-bold text-white transition hover:brightness-90 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-storefront focus-visible:ring-offset-2"
                                >
                                    <svg className="w-6 h-6" fill="currentColor" viewBox="0 0 24 24">
                                        <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/>
                                    </svg>
                                    Abrir WhatsApp
                                </a>
                                <Link
                                    href={route('products.index')}
                                    className="flex min-h-11 w-full items-center justify-center rounded-full border border-storefront px-5 text-center text-sm font-semibold text-storefront transition hover:bg-storefront hover:text-white focus-visible:ring-2 focus-visible:ring-storefront"
                                >
                                    Volver a la tienda
                                </Link>
                            </div>
                        </div>
                    )}
                    <div className={`grid min-w-0 grid-cols-1 gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(300px,360px)] lg:items-start ${orderSubmitted ? 'hidden' : ''}`}>
                        {/* Barra de progreso de envío gratis */}
                        <div className="lg:col-start-1">
                            <FreeShippingProgress total={subtotal} threshold={freeShippingThreshold} />
                        </div>

                        {/* Formulario */}
                        <div className="lg:col-start-1">
                            <div className="rounded-[1.75rem] border border-gray-200 bg-surface p-5 text-navy-900 shadow-card sm:p-7">
                                <h2 className="uppercase text-xl font-semibold text-navy-900 mb-6">
                                    Datos de contacto y entrega
                                </h2>

                                <form onSubmit={generateWhatsAppMessage} className="space-y-6">
                                    {/* Nombre, Apellido y DNI */}
                                    <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                                        <div>
                                            <label htmlFor="customer-name" className="block text-sm font-medium text-navy-900 mb-2">
                                                Nombre *
                                            </label>
                                            <input id="customer-name"
                                                type="text"
                                                required
                                                value={data.customer_data.name}
                                                onChange={(e) => handleInputChange('name', e.target.value)}
                                                className="w-full rounded-xl border border-navy-900/20 bg-white px-4 py-3 text-navy-900 outline-none transition placeholder:text-navy-900/40 focus:border-navy-700 focus:ring-2 focus:ring-ice-500"
                                                placeholder="Tu nombre"
                                            />
                                            {submissionErrors['customer_data.name'] && (
                                                <p className="text-navy-700 text-sm mt-1">{submissionErrors['customer_data.name']}</p>
                                            )}
                                        </div>
                                        
                                        <div>
                                            <label htmlFor="customer-lastname" className="block text-sm font-medium text-navy-900 mb-2">
                                                Apellido *
                                            </label>
                                            <input id="customer-lastname"
                                                type="text"
                                                required
                                                value={data.customer_data.lastname}
                                                onChange={(e) => handleInputChange('lastname', e.target.value)}
                                                className="w-full rounded-xl border border-navy-900/20 bg-white px-4 py-3 text-navy-900 outline-none transition placeholder:text-navy-900/40 focus:border-navy-700 focus:ring-2 focus:ring-ice-500"
                                                placeholder="Tu apellido"
                                            />
                                            {submissionErrors['customer_data.lastname'] && (
                                                <p className="text-navy-700 text-sm mt-1">{submissionErrors['customer_data.lastname']}</p>
                                            )}
                                        </div>
                                        
                                        <div>
                                            <label htmlFor="customer-dni" className="block text-sm font-medium text-navy-900 mb-2">
                                                DNI *
                                            </label>
                                            <input id="customer-dni"
                                                type="text"
                                                required
                                                value={data.customer_data.dni}
                                                onChange={(e) => handleInputChange('dni', e.target.value)}
                                                className="w-full rounded-xl border border-navy-900/20 bg-white px-4 py-3 text-navy-900 outline-none transition placeholder:text-navy-900/40 focus:border-navy-700 focus:ring-2 focus:ring-ice-500"
                                                placeholder="12345678"
                                            />
                                            {submissionErrors['customer_data.dni'] && (
                                                <p className="text-navy-700 text-sm mt-1">{submissionErrors['customer_data.dni']}</p>
                                            )}
                                        </div>
                                    </div>

                                    {/* Provincia */}
                                    <div>
                                        <label htmlFor="customer-province" className="block text-sm font-medium text-navy-900 mb-2">
                                            Provincia *
                                        </label>
                                        <select id="customer-province"
                                            required
                                            value={data.customer_data.province}
                                            onChange={(e) => handleInputChange('province', e.target.value)}
                                            className="w-full rounded-xl border border-navy-900/20 bg-white px-4 py-3 text-navy-900 outline-none transition placeholder:text-navy-900/40 focus:border-navy-700 focus:ring-2 focus:ring-ice-500"
                                        >
                                            <option value="">Selecciona una provincia</option>
                                            {Object.entries(provinces).map(([key, province]) => (
                                                <option key={key} value={key}>
                                                    {province.name}
                                                </option>
                                            ))}
                                        </select>
                                        {submissionErrors['customer_data.province'] && (
                                            <p className="text-navy-700 text-sm mt-1">{submissionErrors['customer_data.province']}</p>
                                        )}
                                        {data.customer_data.province && (
                                            <p className="mt-2 text-sm text-navy-700 font-medium flex items-center gap-1">
                                                <svg className="w-4 h-4 flex-shrink-0" fill="currentColor" viewBox="0 0 24 24">
                                                    <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/>
                                                </svg>
                                                Solo hacemos envíos a sucursal, no a domicilio. Coordinamos el retiro por WhatsApp.
                                            </p>
                                        )}
                                    </div>

                                    {/* Ciudad y Código postal */}
                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div>
                                            <label htmlFor="customer-city" className="block text-sm font-medium text-navy-900 mb-2">
                                                Ciudad *
                                            </label>
                                            <input id="customer-city"
                                                type="text"
                                                required
                                                value={data.customer_data.city}
                                                onChange={(e) => handleInputChange('city', e.target.value)}
                                                className="w-full rounded-xl border border-navy-900/20 bg-white px-4 py-3 text-navy-900 outline-none transition placeholder:text-navy-900/40 focus:border-navy-700 focus:ring-2 focus:ring-ice-500"
                                                placeholder="Tu ciudad"
                                            />
                                            {submissionErrors['customer_data.city'] && (
                                                <p className="text-navy-700 text-sm mt-1">{submissionErrors['customer_data.city']}</p>
                                            )}
                                        </div>

                                        <div>
                                            <label htmlFor="customer-postal-code" className="block text-sm font-medium text-navy-900 mb-2">
                                                Código Postal *
                                            </label>
                                            <input id="customer-postal-code"
                                                type="text"
                                                required
                                                value={data.customer_data.postal_code}
                                                onChange={(e) => handleInputChange('postal_code', e.target.value)}
                                                className="w-full rounded-xl border border-navy-900/20 bg-white px-4 py-3 text-navy-900 outline-none transition placeholder:text-navy-900/40 focus:border-navy-700 focus:ring-2 focus:ring-ice-500"
                                                placeholder="1234"
                                            />
                                            {submissionErrors['customer_data.postal_code'] && (
                                                <p className="text-navy-700 text-sm mt-1">{submissionErrors['customer_data.postal_code']}</p>
                                            )}
                                        </div>
                                    </div>

                                    {/* Teléfono y Email */}
                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div>
                                            <label htmlFor="customer-phone" className="block text-sm font-medium text-navy-900 mb-2">
                                                Teléfono *
                                            </label>
                                            <input id="customer-phone"
                                                type="tel"
                                                required
                                                value={data.customer_data.phone}
                                                onChange={(e) => handleInputChange('phone', e.target.value)}
                                                className="w-full rounded-xl border border-navy-900/20 bg-white px-4 py-3 text-navy-900 outline-none transition placeholder:text-navy-900/40 focus:border-navy-700 focus:ring-2 focus:ring-ice-500"
                                                placeholder="+54 11 1234-5678"
                                            />
                                            {submissionErrors['customer_data.phone'] && (
                                                <p className="text-navy-700 text-sm mt-1">{submissionErrors['customer_data.phone']}</p>
                                            )}
                                        </div>
                                        
                                        <div>
                                            <label htmlFor="customer-email" className="block text-sm font-medium text-navy-900 mb-2">
                                                Correo Electrónico *
                                            </label>
                                            <input id="customer-email"
                                                type="email"
                                                required
                                                value={data.customer_data.email}
                                                onChange={(e) => handleInputChange('email', e.target.value)}
                                                className="w-full rounded-xl border border-navy-900/20 bg-white px-4 py-3 text-navy-900 outline-none transition placeholder:text-navy-900/40 focus:border-navy-700 focus:ring-2 focus:ring-ice-500"
                                                placeholder="tu@email.com"
                                            />
                                            {submissionErrors['customer_data.email'] && (
                                                <p className="text-navy-700 text-sm mt-1">{submissionErrors['customer_data.email']}</p>
                                            )}
                                        </div>
                                    </div>

                                    {/* Observaciones */}
                                    <div>
                                        <label htmlFor="customer-observations" className="block text-sm font-medium text-navy-900 mb-2">
                                            Observaciones (opcional)
                                        </label>
                                        <textarea id="customer-observations"
                                            value={data.customer_data.observations}
                                            onChange={(e) => handleInputChange('observations', e.target.value)}
                                            rows={4}
                                            className="w-full rounded-xl border border-navy-900/20 bg-white px-4 py-3 text-navy-900 outline-none transition placeholder:text-navy-900/40 focus:border-navy-700 focus:ring-2 focus:ring-ice-500 resize-none"
                                            placeholder="Agrega cualquier comentario adicional sobre tu pedido (horarios de entrega preferidos, instrucciones especiales, etc.)"
                                        />
                                        {submissionErrors['customer_data.observations'] && (
                                            <p className="text-navy-700 text-sm mt-1">{submissionErrors['customer_data.observations']}</p>
                                        )}
                                    </div>

                                    {/* Botón de envío */}
                                    <div className="pt-6 border-t border-navy-900/10">
                                        <p className="text-sm text-navy-900/70 mb-4">Tu carrito y tus datos se van a enviar en forma de mensaje de WhatsApp, para que nuestro personal te atienda y puedas finalizar tu compra.</p>
                                        <button
                                            type="submit"
                                            disabled={generatingMessage}
                                            className={`w-full py-4 font-bold rounded-xl ${
                                                generatingMessage
                                                    ? 'bg-gray-200 text-gray-500 cursor-not-allowed'
                                                    : 'bg-storefront text-white hover:brightness-90 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-storefront'
                                            }`}
                                        >
                                            {generatingMessage ? (
                                                <span className="flex items-center justify-center">
                                                    <svg className="animate-spin -ml-1 mr-2 h-5 w-5" fill="none" viewBox="0 0 24 24">
                                                        <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                                                        <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                                                    </svg>
                                                    Generando pedido...
                                                </span>
                                            ) : (
                                                <span className="flex items-center justify-center">
                                                    <svg className="mr-2 h-5 w-5" fill="currentColor" viewBox="0 0 24 24">
                                                        <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893A11.821 11.821 0 0020.885 3.488"/>
                                                    </svg>
                                                    Enviar pedido por WhatsApp
                                                </span>
                                            )}
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>

                        {/* Resumen del pedido */}
                        <div className="lg:col-start-2 lg:row-start-1 lg:row-span-2">
                            <div className="rounded-[1.75rem] border border-gray-200 bg-surface p-5 text-navy-900 shadow-card sm:p-6 lg:sticky lg:top-32">
                                <h3 className="uppercase text-xl font-semibold text-navy-900 mb-6">
                                    Resumen del pedido
                                </h3>

                                {/* Productos */}
                                <div className="space-y-4 mb-6">
                                    {cartItems.map((item) => (
                                        <div key={item.line_key} className="flex items-start space-x-3">
                                            {/* Imagen */}
                                            {imageUrl(item.product) ? (
                                                <img
                                                    src={imageUrl(item.product)}
                                                    alt={item.product.title}
                                                    className="h-14 w-14 rounded-xl bg-ice-50 object-contain p-1"
                                                />
                                            ) : (
                                                <div className="flex h-14 w-14 items-center justify-center rounded-xl bg-ice-50">
                                                    <svg className="h-6 w-6 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                                    </svg>
                                                </div>
                                            )}

                                            {/* Información */}
                                            <div className="flex-1 min-w-0">
                                                <p className="text-sm font-medium text-navy-900 truncate">
                                                    {item.product.title}
                                                </p>
                                                <p className="text-sm text-navy-900/60">
                                                    {item.quantity} × ${Number(item.unit_price).toLocaleString('es-AR')}
                                                </p>
                                                <CartLineOptions item={item} />
                                            </div>

                                            {/* Subtotal */}
                                            <div className="text-sm font-medium text-navy-900">
                                                ${Number(item.subtotal).toLocaleString('es-AR')}
                                            </div>
                                        </div>
                                    ))}
                                </div>

                                {/* Código de descuento */}
                                <div className="border-t border-navy-900/10 pt-4 mb-4">
                                    <DiscountCodeField discountCode={discountCode} removedReason={discountCodeRemovedReason} />
                                </div>

                                {/* Envío y Total */}
                                <div className="border-t border-navy-900/10 pt-4">
                                    <div className="mb-4">
                                        <ShippingSummaryLine freeShippingAchieved={freeShippingAchieved} />
                                    </div>
                                    <div className="flex justify-between items-center text-sm mb-2 pt-4 border-t border-navy-900/10">
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
                                    <div className="flex justify-between items-center mb-4 pt-3 border-t border-navy-900/10">
                                        <span className="text-lg font-semibold text-navy-900">
                                            Total:
                                        </span>
                                        <span className="text-2xl font-bold text-navy-900">
                                            ${Number(total).toLocaleString('es-AR')} <span className="text-sm font-medium text-navy-900/60">ARS</span>
                                        </span>
                                    </div>

                                    {/* Forma de pago: efectivo/transferencia (default, sin
                                        recargo) o un plan de cuotas con tarjeta. Informativa:
                                        el total del pedido y el mensaje de WhatsApp no cambian. */}
                                    {cardPaymentPlans.length > 0 && (
                                        <div className="mt-4">
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
                                </div>

                                {/* Botón volver */}
                                        <Link
                                            href={route('cart.index')}
                                            className="flex min-h-11 w-full items-center justify-center rounded-full border border-storefront px-5 text-center text-sm font-semibold text-storefront transition hover:bg-storefront hover:text-white focus-visible:ring-2 focus-visible:ring-storefront"
                                        >
                                            Volver al carrito
                                        </Link>
                            </div>
                        </div>
                    </div>
                </main>
            </div>

            <Footer />
            <CartButton />
            <WhatsAppButton />
        </>
    );
}
