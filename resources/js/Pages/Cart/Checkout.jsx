import { Head, Link, useForm } from '@inertiajs/react';
import { useState } from 'react';
import Navbar from '@/Components/Navbar';
import Footer from '@/Components/Footer';
import WhatsAppButton from '@/Components/WhatsAppButton';

export default function CartCheckout({ auth, cartItems, total, provinces }) {
    const [selectedProvince, setSelectedProvince] = useState('');
    const [generatingMessage, setGeneratingMessage] = useState(false);

    const { data, setData, processing, errors } = useForm({
        customer_data: {
            name: '',
            lastname: '',
            dni: '',
            province: '',
            city: '',
            address: '',
            number: '',
            between_streets: '',
            postal_code: '',
            phone: '',
            email: '',
            observations: ''
        }
    });

    // Función para obtener la URL de la imagen principal
    const getPrimaryImageUrl = (product) => {
        if (!product.images || product.images.length === 0) {
            return null;
        }
        
        const primaryImage = product.images.find(img => img.is_primary);
        if (primaryImage) {
            return primaryImage.url || primaryImage.path;
        }
        
        const firstImage = product.images[0];
        return firstImage.url || firstImage.path;
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
                const whatsappNumber = '1133973222'; // Reemplazar con el número real
                const whatsappUrl = `https://wa.me/${whatsappNumber}?text=${message}`;
                window.open(whatsappUrl, '_blank');
                
                // Limpiar carrito después de enviar (esto se maneja en el backend)
                window.dispatchEvent(new CustomEvent('cart-updated'));
                
                // Redirigir al carrito vacío o página de confirmación
                window.location.href = route('cart.index');
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
            
            {/* Header */}
            <div className="bg-chalk pt-20 pb-8">
                <div className="max-w-7xl mx-auto px-6 lg:px-8">
                    <nav className="text-sm text-navy/60 mb-6">
                        <Link href="/" className="hover:text-navy">Inicio</Link>
                        <span className="mx-2">•</span>
                        <Link href={route('cart.index')} className="hover:text-navy">Carrito</Link>
                        <span className="mx-2">•</span>
                        <span className="text-navy">Finalizar Pedido</span>
                    </nav>
                    
                    <h1 className="text-3xl md:text-4xl font-bold text-navy mb-4">
                        Finalizar Pedido
                    </h1>
                    
                    <p className="text-navy/70">
                        Completa tus datos para confirmar el pedido
                    </p>
                </div>
            </div>

            {/* Contenido */}
            <main className="bg-chalk pb-12">
                <div className="max-w-7xl mx-auto px-6 lg:px-8">
                    <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
                        {/* Formulario */}
                        <div className="lg:col-span-2">
                            <div className="bg-white rounded-lg shadow-lg p-6">
                                <h2 className="text-xl font-semibold text-navy mb-6">
                                    Datos de Contacto y Entrega
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

                                    {/* Provincia y Localidad */}
                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
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
                                        </div>
                                        
                                        <div>
                                            <label className="block text-sm font-medium text-navy mb-2">
                                                Localidad/Distrito *
                                            </label>
                                            <select
                                                required
                                                value={data.customer_data.city}
                                                onChange={(e) => handleInputChange('city', e.target.value)}
                                                disabled={!selectedProvince}
                                                className="w-full px-4 py-3 border border-navy/20 rounded-lg focus:outline-none focus:ring-2 focus:ring-gold focus:border-transparent disabled:bg-gray-100 disabled:cursor-not-allowed"
                                            >
                                                <option value="">
                                                    {!selectedProvince ? 'Primero selecciona una provincia' : 'Selecciona una localidad'}
                                                </option>
                                                {getCities().map((city) => (
                                                    <option key={city} value={city}>
                                                        {city}
                                                    </option>
                                                ))}
                                            </select>
                                            {errors['customer_data.city'] && (
                                                <p className="text-red-600 text-sm mt-1">{errors['customer_data.city']}</p>
                                            )}
                                        </div>
                                    </div>

                                    {/* Dirección y Número */}
                                    <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                                        <div className="md:col-span-2">
                                            <label className="block text-sm font-medium text-navy mb-2">
                                                Dirección *
                                            </label>
                                            <input
                                                type="text"
                                                required
                                                value={data.customer_data.address}
                                                onChange={(e) => handleInputChange('address', e.target.value)}
                                                className="w-full px-4 py-3 border border-navy/20 rounded-lg focus:outline-none focus:ring-2 focus:ring-gold focus:border-transparent"
                                                placeholder="Nombre de la calle"
                                            />
                                            {errors['customer_data.address'] && (
                                                <p className="text-red-600 text-sm mt-1">{errors['customer_data.address']}</p>
                                            )}
                                        </div>
                                        
                                        <div>
                                            <label className="block text-sm font-medium text-navy mb-2">
                                                Número/Altura *
                                            </label>
                                            <input
                                                type="text"
                                                required
                                                value={data.customer_data.number}
                                                onChange={(e) => handleInputChange('number', e.target.value)}
                                                className="w-full px-4 py-3 border border-navy/20 rounded-lg focus:outline-none focus:ring-2 focus:ring-gold focus:border-transparent"
                                                placeholder="1234"
                                            />
                                            {errors['customer_data.number'] && (
                                                <p className="text-red-600 text-sm mt-1">{errors['customer_data.number']}</p>
                                            )}
                                        </div>
                                    </div>

                                    {/* Entre calles y Código postal */}
                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div>
                                            <label className="block text-sm font-medium text-navy mb-2">
                                                Entre calles (opcional)
                                            </label>
                                            <input
                                                type="text"
                                                value={data.customer_data.between_streets}
                                                onChange={(e) => handleInputChange('between_streets', e.target.value)}
                                                className="w-full px-4 py-3 border border-navy/20 rounded-lg focus:outline-none focus:ring-2 focus:ring-gold focus:border-transparent"
                                                placeholder="Ej: Entre Av. Corrientes y Sarmiento"
                                            />
                                            {errors['customer_data.between_streets'] && (
                                                <p className="text-red-600 text-sm mt-1">{errors['customer_data.between_streets']}</p>
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
                                        <button
                                            type="submit"
                                            disabled={generatingMessage || processing}
                                            className={`w-full py-4 font-bold rounded-lg transition-colors ${
                                                generatingMessage || processing
                                                    ? 'bg-gray-300 text-gray-500 cursor-not-allowed'
                                                    : 'bg-green-600 text-white hover:bg-green-700'
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
                                                    Enviar Pedido por WhatsApp
                                                </span>
                                            )}
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>

                        {/* Resumen del pedido */}
                        <div className="lg:col-span-1">
                            <div className="bg-white rounded-lg shadow-lg p-6 sticky top-6">
                                <h3 className="text-xl font-semibold text-navy mb-6">
                                    Resumen del Pedido
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
                                                    {item.quantity} × ${Number(item.product.price).toLocaleString('es-CL')}
                                                </p>
                                            </div>
                                            
                                            {/* Subtotal */}
                                            <div className="text-sm font-medium text-navy">
                                                ${Number(item.subtotal).toLocaleString('es-CL')}
                                            </div>
                                        </div>
                                    ))}
                                </div>

                                {/* Total */}
                                <div className="border-t border-navy/10 pt-4">
                                    <div className="flex justify-between items-center mb-4">
                                        <span className="text-lg font-semibold text-navy">
                                            Total:
                                        </span>
                                        <span className="text-2xl font-bold text-navy">
                                            ${Number(total).toLocaleString('es-CL')}
                                        </span>
                                    </div>
                                </div>

                                {/* Botón volver */}
                                <Link
                                    href={route('cart.index')}
                                    className="block w-full py-3 border border-navy text-navy text-center font-medium rounded-lg hover:bg-navy hover:text-chalk transition-colors"
                                >
                                    Volver al carrito
                                </Link>
                            </div>
                        </div>
                    </div>
                </div>
            </main>

            <Footer />
            <WhatsAppButton />
        </>
    );
}