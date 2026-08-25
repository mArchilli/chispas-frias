import { Head, Link, useForm } from '@inertiajs/react';
import { useState } from 'react';
import Navbar from '@/Components/Navbar';
import Footer from '@/Components/Footer';
import WhatsAppButton from '@/Components/WhatsAppButton';
import CartButton from '@/Components/CartButton';
import FreeShippingProgress from '@/Components/FreeShippingProgress';
import DiscountCodeField from '@/Components/Cart/DiscountCodeField';

function ShippingSummaryLine({ freeShippingAchieved }) {
    return (
        <>
            <div className="flex justify-between items-center text-sm">
                <span className="text-navy/70">Envío:</span>
                {freeShippingAchieved ? (
                    <span className="inline-flex items-center gap-1 font-bold text-green-600">
                        <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                        </svg>
                        Gratis
                    </span>
                ) : (
                    <span className="font-medium text-navy/60">A coordinar</span>
                )}
            </div>
            {!freeShippingAchieved && (
                <p className="text-xs text-navy/50 mt-1">
                    El costo de envío te lo notifica el vendedor una vez enviado el pedido.
                </p>
            )}
        </>
    );
}

// Detecta mobile por user agent para decidir si el link de WhatsApp abre la
// app nativa (whatsapp://) o WhatsApp Web en una pestaña nueva (desktop).
function isMobileDevice() {
    if (typeof navigator === 'undefined') return false;
    return /Android|iPhone|iPad|iPod/i.test(navigator.userAgent);
}

export default function CartCheckout({ auth, cartItems, subtotal, total, discountCode, discountCodeRemovedReason, provinces, freeShippingThreshold }) {
    const [selectedProvince, setSelectedProvince] = useState('');
    const [generatingMessage, setGeneratingMessage] = useState(false);
    const [orderSubmitted, setOrderSubmitted] = useState(false);
    const [pendingWhatsAppUrl, setPendingWhatsAppUrl] = useState('');
    const [confirmedOrderId, setConfirmedOrderId] = useState(null);
    const [confirmedTotal, setConfirmedTotal] = useState(null);

    const freeShippingAchieved =
        Number(freeShippingThreshold) > 0 && Number(subtotal) >= Number(freeShippingThreshold);
    const confirmedFreeShippingAchieved =
        Number(freeShippingThreshold) > 0 && Number(confirmedTotal ?? total) >= Number(freeShippingThreshold);

    const { data, setData, processing, errors } = useForm({
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

    // Función para obtener la URL de la imagen principal
    const getPrimaryImageUrl = (product) => {
        const basePath = import.meta.env.VITE_PRODUCT_IMAGES_PATH || '/images/products/';
        
        if (!product.images || product.images.length === 0) {
            return null;
        }
        
        const primaryImage = product.images.find(img => img.is_primary);
        const imageToUse = primaryImage || product.images[0];
        
        if (!imageToUse) return null;
        
        const imagePath = imageToUse.url || imageToUse.path;
        const encodedImage = encodeURIComponent(imagePath);
        
        return `${basePath}${encodedImage}`;
    };

    // Función para manejar cambios en el formulario
    const handleInputChange = (field, value) => {
        setData('customer_data', {
            ...data.customer_data,
            [field]: value
        });

        // Si cambió la provincia, limpiar la ciudad
        if (field === 'province') {
            setSelectedProvince(value);
            setData('customer_data', {
                ...data.customer_data,
                province: value,
                city: ''
            });
        }
    };

    // Función para generar mensaje de WhatsApp
    const generateWhatsAppMessage = async (e) => {
        e.preventDefault();
        setGeneratingMessage(true);

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
            
            if (result.success) {
                const message = encodeURIComponent(result.message);
                const whatsappNumber = '5491178886833';
                // En mobile abrimos la app nativa directamente (esquema whatsapp://);
                // en desktop abrimos una pestaña con WhatsApp Web. Si por algún motivo
                // no abre (app no instalada, bloqueo del navegador, etc.), la pantalla
                // de confirmación con este mismo botón queda visible para reintentar.
                const whatsappUrl = isMobileDevice()
                    ? `whatsapp://send?phone=${whatsappNumber}&text=${message}`
                    : `https://web.whatsapp.com/send?phone=${whatsappNumber}&text=${message}`;

                // Disparar evento para actualizar contador del carrito
                window.dispatchEvent(new CustomEvent('cart-updated'));

                // Guardar URL y mostrar pantalla de éxito.
                // No usamos window.open() porque los navegadores móviles lo bloquean
                // cuando se llama desde un contexto async (después de await).
                // El usuario tocará el botón directamente → gesto directo → sin popup blocker.
                setPendingWhatsAppUrl(whatsappUrl);
                setConfirmedOrderId(result.order_id ?? null);
                setConfirmedTotal(result.total ?? total);
                setOrderSubmitted(true);
            } else {
                alert('Error al generar mensaje: ' + (result.message || 'Error desconocido'));
            }
        } catch (error) {
            console.error('Error al generar mensaje:', error);
            alert('Error al generar mensaje');
        } finally {
            setGeneratingMessage(false);
        }
    };

    const getCities = () => {
        if (!selectedProvince || !provinces[selectedProvince]) {
            return [];
        }
        return provinces[selectedProvince].cities || [];
    };

    const getProvinceName = (provinceKey) => {
        return provinces[provinceKey]?.name || provinceKey;
    };

    return (
        <>
            <Head title="Finalizar Pedido - Chispas Frías" />
            
            <Navbar auth={auth} />

            {/* Sección superior personalizada */}
            <div
                className="pt-20 pb-10"
                style={{
                    backgroundImage: "url('/images/fondo-productos.png')",
                    backgroundSize: 'cover',
                    backgroundPosition: 'center',
                }}
            >
                <div className="site-shell">
                    {/* Mobile: logo arriba, luego textos */}
                    <div className="flex flex-col items-start text-left md:hidden">
                        <div className="relative mb-6" style={{ display: 'inline-block' }}>
                            <div
                                style={{
                                    position: 'absolute',
                                    top: '50%',
                                    left: '50%',
                                    transform: 'translate(-50%, -50%)',
                                    width: '6.5rem',
                                    height: '6.5rem',
                                    borderRadius: '9999px',
                                    background: 'rgba(3,37,65,0.95)',
                                    filter: 'blur(24px)',
                                    zIndex: 0,
                                }}
                            />
                            <img src="/images/chispas-frias-logo.png" alt="Logo Chispas Frías" className="h-32 w-auto relative z-10 mb-0" />
                        </div>
                        <h1
                            className="text-3xl font-bold text-chalk mb-3"
                            style={{ textShadow: '0 0 15px rgba(3,37,65,1), 0 0 8px rgba(3,37,65,1), 0 2px 10px rgba(3,37,65,0.9)'}}
                        >Finalizar pedido.</h1>
                        <p className="text-lg text-chalk/80 max-w-2xl" style={{ textShadow: '0 0 15px rgba(3,37,65,1), 0 0 8px rgba(3,37,65,1), 0 2px 10px rgba(3,37,65,0.9)'}}>
                            Completa tus datos de contacto y entrega para confirmar tu pedido de pirotecnia fría.
                        </p>
                    </div>
                    {/* Desktop: diseño anterior */}
                    <div className="hidden md:flex items-center">
                        <div className="relative mr-3" style={{ display: 'inline-block' }}>
                            <div
                                style={{
                                    position: 'absolute',
                                    top: '50%',
                                    left: '50%',
                                    transform: 'translate(-50%, -50%)',
                                    width: '5.5rem',
                                    height: '5.5rem',
                                    borderRadius: '9999px',
                                    background: 'rgba(3,37,65,0.95)',
                                    filter: 'blur(20px)',
                                    zIndex: 0,
                                }}
                            />
                            <img src="/images/chispas-frias-logo.png" alt="Logo Chispas Frías" className="h-28 w-auto relative z-10 mr-0" />
                        </div>
                        <div className="h-32 w-px bg-white ml-2 mr-1" />
                        <div className="flex flex-col text-left ml-2">
                            <h1
                                className="text-4xl lg:text-5xl font-bold text-chalk mb-3"
                                style={{ textShadow: '0 0 15px rgba(3,37,65,1), 0 0 8px rgba(3,37,65,1), 0 2px 10px rgba(3,37,65,0.9)'}}
                            >Finalizar tu pedido.</h1>
                            <p className="text-xl text-chalk/80 max-w-2xl" style={{ textShadow: '0 0 15px rgba(3,37,65,1), 0 0 8px rgba(3,37,65,1), 0 2px 10px rgba(3,37,65,0.9)'}}>
                                Completa tus datos de contacto y entrega para confirmar tu pedido de pirotecnia fría.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
            
            {/* Header */}
            <div className="bg-chalk pt-20 pb-8">
                <div className="site-shell">
                    <nav className="text-sm text-navy/60 mb-6">
                        <Link href="/" className="hover:text-navy">Inicio</Link>
                        <span className="mx-2">•</span>
                        <Link href={route('cart.index')} className="hover:text-navy">Carrito</Link>
                        <span className="mx-2">•</span>
                        <span className="text-navy">Finalizar Pedido</span>
                    </nav>
                    
                    <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                        <div>
                            <h1 className="text-3xl md:text-4xl font-bold text-navy mb-4">
                                Finalizar pedido.
                            </h1>
                            
                            <p className="text-navy/70">
                                Completa tus datos para confirmar el pedido.
                            </p>
                        </div>
                        
                        <div className="flex-shrink-0">
                            <Link
                                href={route('cart.index')}
                                className="inline-flex items-center px-6 py-3 border-2 border-navy text-navy font-medium rounded-lg hover:bg-navy hover:text-chalk hover:scale-105 transition-all duration-300 shadow-lg hover:shadow-xl"
                            >
                                <svg className="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M15 19l-7-7 7-7" />
                                </svg>
                                Volver al carrito
                            </Link>
                        </div>
                    </div>
                </div>
            </div>

            {/* Contenido */}
            <main className="bg-chalk pb-12">
                <div className="site-shell">
                    {orderSubmitted && (
                        <div className="flex flex-col items-center justify-center py-16">
                            <div className="bg-white rounded-2xl shadow-xl p-8 md:p-12 max-w-md w-full border-2 border-green-200 text-center">
                                {/* Ícono de éxito */}
                                <div className="w-20 h-20 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-6">
                                    <svg className="w-10 h-10 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                                    </svg>
                                </div>
                                <h2 className="text-2xl font-bold text-navy mb-1">¡Pedido listo!</h2>
                                {confirmedOrderId && (
                                    <p className="text-navy/50 font-medium mb-3">Pedido #{confirmedOrderId}</p>
                                )}
                                <p className="text-navy/70 mb-6">
                                    Tu pedido fue procesado correctamente. Tocá el botón para enviarlo por WhatsApp y nuestro equipo te va a atender a la brevedad.
                                </p>

                                {/* Resumen de los productos comprados */}
                                <div className="text-left bg-chalk/60 rounded-xl border border-navy/10 p-4 mb-6">
                                    <p className="text-sm font-semibold text-navy mb-3">Resumen del pedido</p>
                                    <div className="space-y-2 mb-3">
                                        {cartItems.map((item) => (
                                            <div key={item.product.id} className="flex justify-between items-start gap-3 text-sm">
                                                <span className="text-navy/80">
                                                    {item.quantity} × {item.product.title}
                                                </span>
                                                <span className="text-navy font-medium whitespace-nowrap">
                                                    ${Number(item.subtotal).toLocaleString('es-AR')}
                                                </span>
                                            </div>
                                        ))}
                                    </div>
                                    <div className="border-t border-navy/10 pt-3">
                                        <ShippingSummaryLine freeShippingAchieved={confirmedFreeShippingAchieved} />
                                    </div>
                                    <div className="border-t border-navy/10 mt-3 pt-3 flex justify-between items-center">
                                        <span className="text-sm font-semibold text-navy">Total</span>
                                        <span className="text-lg font-bold text-navy">
                                            ${Number(confirmedTotal ?? total).toLocaleString('es-AR')}
                                        </span>
                                    </div>
                                </div>

                                {/* Botón WhatsApp — el usuario lo toca directamente (gesto directo) */}
                                <a
                                    href={pendingWhatsAppUrl}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className="flex items-center justify-center gap-3 w-full py-4 bg-green-600 hover:bg-green-700 text-white font-bold rounded-xl shadow-lg hover:scale-105 transition-all duration-300 mb-4 text-lg"
                                >
                                    <svg className="w-6 h-6" fill="currentColor" viewBox="0 0 24 24">
                                        <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/>
                                    </svg>
                                    Abrir WhatsApp
                                </a>
                                <Link
                                    href={route('cart.index')}
                                    className="block w-full py-3 border-2 border-navy text-navy text-center font-medium rounded-xl hover:bg-navy hover:text-chalk transition-all duration-300"
                                >
                                    Ver mi carrito
                                </Link>
                            </div>
                        </div>
                    )}
                    <div className={`grid grid-cols-1 lg:grid-cols-3 gap-8 ${orderSubmitted ? 'hidden' : ''}`}>
                        {/* Barra de progreso de envío gratis */}
                        <div className="lg:col-span-3">
                            <FreeShippingProgress total={subtotal} threshold={freeShippingThreshold} />
                        </div>

                        {/* Formulario */}
                        <div className="lg:col-span-2">
                            <div className="bg-white rounded-lg shadow-lg p-6 hover:shadow-xl hover:scale-[1.02] transition-all duration-300 border-2 border-navy/20">
                                <h2 className="text-xl font-semibold text-navy mb-6">
                                    Datos de contacto y entrega.
                                </h2>

                                <form onSubmit={generateWhatsAppMessage} className="space-y-6">
                                    {/* Nombre, Apellido y DNI */}
                                    <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                                        <div>
                                            <label className="block text-sm font-medium text-navy mb-2">
                                                Nombre *
                                            </label>
                                            <input
                                                type="text"
                                                required
                                                value={data.customer_data.name}
                                                onChange={(e) => handleInputChange('name', e.target.value)}
                                                className="w-full px-4 py-3 border border-navy/20 rounded-lg focus:outline-none focus:ring-2 focus:ring-gold focus:border-transparent"
                                                placeholder="Tu nombre"
                                            />
                                            {errors['customer_data.name'] && (
                                                <p className="text-red-600 text-sm mt-1">{errors['customer_data.name']}</p>
                                            )}
                                        </div>
                                        
                                        <div>
                                            <label className="block text-sm font-medium text-navy mb-2">
                                                Apellido *
                                            </label>
                                            <input
                                                type="text"
                                                required
                                                value={data.customer_data.lastname}
                                                onChange={(e) => handleInputChange('lastname', e.target.value)}
                                                className="w-full px-4 py-3 border border-navy/20 rounded-lg focus:outline-none focus:ring-2 focus:ring-gold focus:border-transparent"
                                                placeholder="Tu apellido"
                                            />
                                            {errors['customer_data.lastname'] && (
                                                <p className="text-red-600 text-sm mt-1">{errors['customer_data.lastname']}</p>
                                            )}
                                        </div>
                                        
                                        <div>
                                            <label className="block text-sm font-medium text-navy mb-2">
                                                DNI *
                                            </label>
                                            <input
                                                type="text"
                                                required
                                                value={data.customer_data.dni}
                                                onChange={(e) => handleInputChange('dni', e.target.value)}
                                                className="w-full px-4 py-3 border border-navy/20 rounded-lg focus:outline-none focus:ring-2 focus:ring-gold focus:border-transparent"
                                                placeholder="12345678"
                                            />
                                            {errors['customer_data.dni'] && (
                                                <p className="text-red-600 text-sm mt-1">{errors['customer_data.dni']}</p>
                                            )}
                                        </div>
                                    </div>

                                    {/* Provincia */}
                                    <div>
                                        <label className="block text-sm font-medium text-navy mb-2">
                                            Provincia *
                                        </label>
                                        <select
                                            required
                                            value={data.customer_data.province}
                                            onChange={(e) => handleInputChange('province', e.target.value)}
                                            className="w-full px-4 py-3 border border-navy/20 rounded-lg focus:outline-none focus:ring-2 focus:ring-gold focus:border-transparent"
                                        >
                                            <option value="">Selecciona una provincia</option>
                                            {Object.entries(provinces).map(([key, province]) => (
                                                <option key={key} value={key}>
                                                    {province.name}
                                                </option>
                                            ))}
                                        </select>
                                        {errors['customer_data.province'] && (
                                            <p className="text-red-600 text-sm mt-1">{errors['customer_data.province']}</p>
                                        )}
                                        {data.customer_data.province && (
                                            <p className="mt-2 text-sm text-gold font-medium flex items-center gap-1">
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
                                            <label className="block text-sm font-medium text-navy mb-2">
                                                Ciudad *
                                            </label>
                                            <input
                                                type="text"
                                                required
                                                value={data.customer_data.city}
                                                onChange={(e) => handleInputChange('city', e.target.value)}
                                                className="w-full px-4 py-3 border border-navy/20 rounded-lg focus:outline-none focus:ring-2 focus:ring-gold focus:border-transparent"
                                                placeholder="Tu ciudad"
                                            />
                                            {errors['customer_data.city'] && (
                                                <p className="text-red-600 text-sm mt-1">{errors['customer_data.city']}</p>
                                            )}
                                        </div>

                                        <div>
                                            <label className="block text-sm font-medium text-navy mb-2">
                                                Código Postal *
                                            </label>
                                            <input
                                                type="text"
                                                required
                                                value={data.customer_data.postal_code}
                                                onChange={(e) => handleInputChange('postal_code', e.target.value)}
                                                className="w-full px-4 py-3 border border-navy/20 rounded-lg focus:outline-none focus:ring-2 focus:ring-gold focus:border-transparent"
                                                placeholder="1234"
                                            />
                                            {errors['customer_data.postal_code'] && (
                                                <p className="text-red-600 text-sm mt-1">{errors['customer_data.postal_code']}</p>
                                            )}
                                        </div>
                                    </div>

                                    {/* Teléfono y Email */}
                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div>
                                            <label className="block text-sm font-medium text-navy mb-2">
                                                Teléfono *
                                            </label>
                                            <input
                                                type="tel"
                                                required
                                                value={data.customer_data.phone}
                                                onChange={(e) => handleInputChange('phone', e.target.value)}
                                                className="w-full px-4 py-3 border border-navy/20 rounded-lg focus:outline-none focus:ring-2 focus:ring-gold focus:border-transparent"
                                                placeholder="+54 11 1234-5678"
                                            />
                                            {errors['customer_data.phone'] && (
                                                <p className="text-red-600 text-sm mt-1">{errors['customer_data.phone']}</p>
                                            )}
                                        </div>
                                        
                                        <div>
                                            <label className="block text-sm font-medium text-navy mb-2">
                                                Correo Electrónico *
                                            </label>
                                            <input
                                                type="email"
                                                required
                                                value={data.customer_data.email}
                                                onChange={(e) => handleInputChange('email', e.target.value)}
                                                className="w-full px-4 py-3 border border-navy/20 rounded-lg focus:outline-none focus:ring-2 focus:ring-gold focus:border-transparent"
                                                placeholder="tu@email.com"
                                            />
                                            {errors['customer_data.email'] && (
                                                <p className="text-red-600 text-sm mt-1">{errors['customer_data.email']}</p>
                                            )}
                                        </div>
                                    </div>

                                    {/* Observaciones */}
                                    <div>
                                        <label className="block text-sm font-medium text-navy mb-2">
                                            Observaciones (opcional)
                                        </label>
                                        <textarea
                                            value={data.customer_data.observations}
                                            onChange={(e) => handleInputChange('observations', e.target.value)}
                                            rows={4}
                                            className="w-full px-4 py-3 border border-navy/20 rounded-lg focus:outline-none focus:ring-2 focus:ring-gold focus:border-transparent resize-none"
                                            placeholder="Agrega cualquier comentario adicional sobre tu pedido (horarios de entrega preferidos, instrucciones especiales, etc.)"
                                        />
                                        {errors['customer_data.observations'] && (
                                            <p className="text-red-600 text-sm mt-1">{errors['customer_data.observations']}</p>
                                        )}
                                    </div>

                                    {/* Botón de envío */}
                                    <div className="pt-6 border-t border-navy/10">
                                        <p className="text-sm text-navy/70 mb-4">Tu carrito y tus datos se van a enviar en forma de mensaje de WhatsApp, para que nuestro personal te atienda y puedas finalizar tu compra.</p>
                                        <button
                                            type="submit"
                                            disabled={generatingMessage || processing}
                                            className={`w-full py-4 font-bold rounded-lg ${
                                                generatingMessage || processing
                                                    ? 'bg-gray-300 text-gray-500 cursor-not-allowed'
                                                    : 'bg-green-600 text-white hover:bg-green-700 hover:scale-105 transition-all duration-300 shadow-lg hover:shadow-xl'
                                            }`}
                                        >
                                            {generatingMessage || processing ? (
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
                        <div className="lg:col-span-1">
                            <div className="bg-white rounded-lg shadow-lg p-6 sticky top-6 hover:shadow-xl hover:scale-[1.02] transition-all duration-300 border-2 border-navy/20">
                                <h3 className="text-xl font-semibold text-navy mb-6">
                                    Resumen del pedido.
                                </h3>

                                {/* Productos */}
                                <div className="space-y-4 mb-6">
                                    {cartItems.map((item) => (
                                        <div key={item.product.id} className="flex items-center space-x-3">
                                            {/* Imagen */}
                                            {getPrimaryImageUrl(item.product) ? (
                                                <img
                                                    src={getPrimaryImageUrl(item.product)}
                                                    alt={item.product.title}
                                                    className="w-12 h-12 object-cover rounded"
                                                />
                                            ) : (
                                                <div className="w-12 h-12 bg-gray-200 rounded flex items-center justify-center">
                                                    <svg className="h-6 w-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                                    </svg>
                                                </div>
                                            )}
                                            
                                            {/* Información */}
                                            <div className="flex-1 min-w-0">
                                                <p className="text-sm font-medium text-navy truncate">
                                                    {item.product.title}
                                                </p>
                                                <p className="text-sm text-navy/60">
                                                    {item.quantity} × ${Number(item.price).toLocaleString('es-AR')}
                                                </p>
                                            </div>
                                            
                                            {/* Subtotal */}
                                            <div className="text-sm font-medium text-navy">
                                                ${Number(item.subtotal).toLocaleString('es-AR')}
                                            </div>
                                        </div>
                                    ))}
                                </div>

                                {/* Código de descuento */}
                                <div className="border-t border-navy/10 pt-4 mb-4">
                                    <DiscountCodeField discountCode={discountCode} removedReason={discountCodeRemovedReason} />
                                </div>

                                {/* Envío y Total */}
                                <div className="border-t border-navy/10 pt-4">
                                    <div className="mb-4">
                                        <ShippingSummaryLine freeShippingAchieved={freeShippingAchieved} />
                                    </div>
                                    <div className="flex justify-between items-center text-sm mb-2 pt-4 border-t border-navy/10">
                                        <span className="text-navy/70">Subtotal:</span>
                                        <span className="text-navy font-medium">
                                            ${Number(subtotal).toLocaleString('es-AR')}
                                        </span>
                                    </div>
                                    {discountCode && (
                                        <div className="flex justify-between items-center text-sm mb-2">
                                            <span className="text-navy/70">Descuento ({discountCode.code}):</span>
                                            <span className="text-green-600 font-medium">
                                                −${Number(discountCode.amount).toLocaleString('es-AR')}
                                            </span>
                                        </div>
                                    )}
                                    <div className="flex justify-between items-center mb-4 pt-3 border-t border-navy/10">
                                        <span className="text-lg font-semibold text-navy">
                                            Total:
                                        </span>
                                        <span className="text-2xl font-bold text-navy">
                                            ${Number(total).toLocaleString('es-AR')} <span className="text-sm font-medium text-navy/60">ARS</span>
                                        </span>
                                    </div>
                                </div>

                                {/* Botón volver */}
                                        <Link
                                            href={route('cart.index')}
                                            className="block w-full py-3 border border-navy text-navy text-center font-medium rounded-lg hover:bg-navy hover:text-chalk hover:scale-105 transition-all duration-300 shadow-lg hover:shadow-xl"
                                        >
                                            Volver al carrito
                                        </Link>
                            </div>
                        </div>
                    </div>
                </div>
            </main>

            <Footer />
            <CartButton />
            <WhatsAppButton />
        </>
    );
}
