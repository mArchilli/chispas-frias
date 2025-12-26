import { Head, Link, useForm } from '@inertiajs/react';
import { useState } from 'react';
import Navbar from '@/Components/Navbar';
import Footer from '@/Components/Footer';

export default function CartIndex({ auth, cartItems, total }) {
    const [updatingItems, setUpdatingItems] = useState({});
    
    const { delete: destroy, processing: clearingCart } = useForm();
    const { post: postWhatsApp, processing: generatingMessage } = useForm();

    // Función para actualizar cantidad de un item
    const updateQuantity = (productId, newQuantity) => {
        setUpdatingItems(prev => ({ ...prev, [productId]: true }));
        
        useForm({ product_id: productId, quantity: newQuantity }).patch(route('cart.update'), {
            preserveScroll: true,
            onSuccess: () => {
                // Disparar evento para actualizar el contador del navbar
                window.dispatchEvent(new CustomEvent('cart-updated'));
            },
            onFinish: () => {
                setUpdatingItems(prev => {
                    const updated = { ...prev };
                    delete updated[productId];
                    return updated;
                });
            }
        });
    };

    // Función para eliminar item del carrito
    const removeItem = (productId) => {
        const deleteForm = useForm({ product_id: productId });
        deleteForm.delete(route('cart.remove'), {
            preserveScroll: true,
            onSuccess: () => {
                // Disparar evento para actualizar el contador del navbar
                window.dispatchEvent(new CustomEvent('cart-updated'));
            }
        });
    };

    // Función para generar mensaje de WhatsApp
    const generateWhatsAppMessage = async () => {
        try {
            const response = await fetch(route('cart.whatsapp'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                }
            });
            
            const data = await response.json();
            
            if (data.success) {
                const message = encodeURIComponent(data.message);
                const whatsappNumber = '1133973222'; // Reemplazar con el número real
                const whatsappUrl = `https://wa.me/${whatsappNumber}?text=${message}`;
                window.open(whatsappUrl, '_blank');
            } else {
                alert('Error al generar mensaje: ' + (data.message || 'Error desconocido'));
            }
        } catch (error) {
            console.error('Error al generar mensaje:', error);
            alert('Error al generar mensaje');
        }
    };

    // Función para vaciar carrito
    const clearCart = () => {
        if (confirm('¿Estás seguro de que deseas vaciar todo el carrito?')) {
            destroy(route('cart.clear'), {
                preserveScroll: true,
                onSuccess: () => {
                    // Disparar evento para actualizar el contador del navbar
                    window.dispatchEvent(new CustomEvent('cart-updated'));
                }
            });
        }
    };

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

    return (
        <>
            <Head title="Carrito de Compras - Chispas Frías" />
            
            <Navbar auth={auth} />
            
            {/* Header del carrito */}
            <div className="bg-chalk pt-20 pb-8">
                <div className="max-w-7xl mx-auto px-6 lg:px-8">
                    <nav className="text-sm text-navy/60 mb-6">
                        <Link href="/" className="hover:text-navy">Inicio</Link>
                        <span className="mx-2">•</span>
                        <span className="text-navy">Carrito de Compras</span>
                    </nav>
                    
                    <h1 className="text-3xl md:text-4xl font-bold text-navy mb-4">
                        Mi Carrito
                    </h1>
                    
                    {cartItems.length > 0 && (
                        <p className="text-navy/70">
                            Tienes {cartItems.length} {cartItems.length === 1 ? 'producto' : 'productos'} en tu carrito
                        </p>
                    )}
                </div>
            </div>

            {/* Contenido del carrito */}
            <main className="bg-chalk pb-12">
                <div className="max-w-7xl mx-auto px-6 lg:px-8">
                    {cartItems.length === 0 ? (
                        /* Carrito vacío */
                        <div className="text-center py-16">
                            <div className="mb-6">
                                <svg className="mx-auto h-24 w-24 text-navy/30" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1} d="M3 3h2l.4 2M7 13h10l4-8H5.4m0 0L7 13m0 0l-2.5 5L21 18m-11-5v0m0 0l-2.5 5" />
                                </svg>
                            </div>
                            <h2 className="text-2xl font-bold text-navy mb-4">
                                Tu carrito está vacío
                            </h2>
                            <p className="text-navy/70 mb-8">
                                Explora nuestros productos y agrega algunos a tu carrito
                            </p>
                            <Link
                                href={route('products.index')}
                                className="inline-flex items-center px-6 py-3 bg-gold text-navy font-semibold rounded-lg hover:bg-gold/90 transition-colors"
                            >
                                Explorar productos
                                <svg className="ml-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M17 8l4 4m0 0l-4 4m4-4H3" />
                                </svg>
                            </Link>
                        </div>
                    ) : (
                        /* Items del carrito */
                        <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
                            {/* Lista de productos */}
                            <div className="lg:col-span-2 space-y-6">
                                {/* Botón para vaciar carrito */}
                                <div className="flex justify-between items-center">
                                    <h2 className="text-xl font-semibold text-navy">
                                        Productos en tu carrito
                                    </h2>
                                    <button
                                        onClick={clearCart}
                                        disabled={clearingCart}
                                        className="text-sm text-red-600 hover:text-red-800 disabled:opacity-50"
                                    >
                                        {clearingCart ? 'Vaciando...' : 'Vaciar carrito'}
                                    </button>
                                </div>

                                {cartItems.map((item) => (
                                    <div key={item.product.id} className="bg-white rounded-lg shadow-lg p-6">
                                        <div className="flex items-start space-x-4">
                                            {/* Imagen del producto */}
                                            <Link
                                                href={route('products.show', item.product.id)}
                                                className="flex-shrink-0"
                                            >
                                                {getPrimaryImageUrl(item.product) ? (
                                                    <img
                                                        src={getPrimaryImageUrl(item.product)}
                                                        alt={item.product.title}
                                                        className="w-24 h-24 object-cover rounded-lg hover:opacity-90 transition-opacity"
                                                    />
                                                ) : (
                                                    <div className="w-24 h-24 bg-gray-200 rounded-lg flex items-center justify-center">
                                                        <svg className="h-8 w-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                                        </svg>
                                                    </div>
                                                )}
                                            </Link>

                                            {/* Información del producto */}
                                            <div className="flex-1 min-w-0">
                                                <div className="flex justify-between items-start">
                                                    <div className="flex-1">
                                                        <Link
                                                            href={route('products.show', item.product.id)}
                                                            className="text-lg font-semibold text-navy hover:text-gold transition-colors"
                                                        >
                                                            {item.product.title}
                                                        </Link>
                                                        <p className="text-sm text-navy/60 mt-1">
                                                            {item.product.category?.parent?.name || item.product.category?.name}
                                                        </p>
                                                        <p className="text-xl font-bold text-navy mt-2">
                                                            ${Number(item.product.price).toLocaleString('es-CL')}
                                                        </p>
                                                    </div>

                                                    {/* Botón eliminar */}
                                                    <button
                                                        onClick={() => removeItem(item.product.id)}
                                                        className="text-red-600 hover:text-red-800 p-2"
                                                        title="Eliminar del carrito"
                                                    >
                                                        <svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                        </svg>
                                                    </button>
                                                </div>

                                                {/* Controles de cantidad y subtotal */}
                                                <div className="flex items-center justify-between mt-4">
                                                    <div className="flex items-center space-x-4">
                                                        <span className="text-sm font-medium text-navy">
                                                            Cantidad:
                                                        </span>
                                                        <div className="flex items-center border border-navy/20 rounded-lg">
                                                            <button
                                                                onClick={() => updateQuantity(item.product.id, item.quantity - 1)}
                                                                disabled={item.quantity <= 1 || updatingItems[item.product.id]}
                                                                className="px-3 py-1 text-navy hover:bg-navy/10 disabled:opacity-50 disabled:cursor-not-allowed"
                                                            >
                                                                −
                                                            </button>
                                                            <span className="px-4 py-1 text-navy font-medium min-w-[2.5rem] text-center">
                                                                {updatingItems[item.product.id] ? '...' : item.quantity}
                                                            </span>
                                                            <button
                                                                onClick={() => updateQuantity(item.product.id, item.quantity + 1)}
                                                                disabled={item.quantity >= item.product.stock || updatingItems[item.product.id]}
                                                                className="px-3 py-1 text-navy hover:bg-navy/10 disabled:opacity-50 disabled:cursor-not-allowed"
                                                            >
                                                                +
                                                            </button>
                                                        </div>
                                                    </div>
                                                    
                                                    <div className="text-right">
                                                        <span className="text-lg font-bold text-navy">
                                                            ${Number(item.subtotal).toLocaleString('es-CL')}
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                ))}
                            </div>

                            {/* Resumen del carrito */}
                            <div className="lg:col-span-1">
                                <div className="bg-white rounded-lg shadow-lg p-6 sticky top-6">
                                    <h3 className="text-xl font-semibold text-navy mb-6">
                                        Resumen del pedido
                                    </h3>

                                    {/* Desglose de productos */}
                                    <div className="space-y-3 mb-6">
                                        {cartItems.map((item) => (
                                            <div key={item.product.id} className="flex justify-between text-sm">
                                                <span className="text-navy/70 truncate flex-1 mr-2">
                                                    {item.product.title} × {item.quantity}
                                                </span>
                                                <span className="text-navy font-medium">
                                                    ${Number(item.subtotal).toLocaleString('es-CL')}
                                                </span>
                                            </div>
                                        ))}
                                    </div>

                                    <div className="border-t border-navy/10 pt-4">
                                        <div className="flex justify-between items-center mb-6">
                                            <span className="text-xl font-semibold text-navy">
                                                Total:
                                            </span>
                                            <span className="text-2xl font-bold text-navy">
                                                ${Number(total).toLocaleString('es-CL')}
                                            </span>
                                        </div>

                                        {/* Botones de acción */}
                                        <div className="space-y-3">
                                            <button 
                                                onClick={generateWhatsAppMessage}
                                                disabled={generatingMessage || cartItems.length === 0}
                                                className={`w-full py-3 font-bold rounded-lg transition-colors ${
                                                    generatingMessage || cartItems.length === 0
                                                        ? 'bg-gray-300 text-gray-500 cursor-not-allowed'
                                                        : 'bg-green-600 text-white hover:bg-green-700'
                                                }`}
                                            >
                                                {generatingMessage ? (
                                                    <span className="flex items-center justify-center">
                                                        <svg className="animate-spin -ml-1 mr-2 h-4 w-4" fill="none" viewBox="0 0 24 24">
                                                            <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                                                            <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                                                        </svg>
                                                        Generando mensaje...
                                                    </span>
                                                ) : (
                                                    <span className="flex items-center justify-center">
                                                        <svg className="mr-2 h-5 w-5" fill="currentColor" viewBox="0 0 24 24">
                                                            <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893A11.821 11.821 0 0020.885 3.488"/>
                                                        </svg>
                                                        Solicitar por WhatsApp
                                                    </span>
                                                )}
                                            </button>
                                            <Link
                                                href={route('products.index')}
                                                className="block w-full py-3 border border-navy text-navy text-center font-medium rounded-lg hover:bg-navy hover:text-chalk transition-colors"
                                            >
                                                Seguir comprando
                                            </Link>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    )}
                </div>
            </main>

            <Footer />
        </>
    );
}