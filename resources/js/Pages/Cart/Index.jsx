import { Head, Link, useForm, router } from '@inertiajs/react';
import { useState, useEffect } from 'react';
import Navbar from '@/Components/Navbar';
import Footer from '@/Components/Footer';
import WhatsAppButton from '@/Components/WhatsAppButton';

export default function CartIndex({ auth, cartItems, total }) {
    const [updatingItems, setUpdatingItems] = useState({});
    const [showClearModal, setShowClearModal] = useState(false);
    const [removingItems, setRemovingItems] = useState({});
    const { delete: destroy, processing: clearingCart } = useForm();

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

    // Función para actualizar cantidad de un item
    const updateQuantity = (productId, newQuantity) => {
        if (newQuantity < 1) return;
        
        setUpdatingItems(prev => ({ ...prev, [productId]: true }));
        
        router.patch(route('cart.update'), {
            product_id: productId, 
            quantity: newQuantity
        }, {
            preserveScroll: true,
            onSuccess: () => {
                // Disparar evento para actualizar el contador del navbar
                window.dispatchEvent(new CustomEvent('cart-updated'));
                // Recargar datos con Inertia
                router.reload({ only: ['cartItems', 'total'] });
            },
            onError: (errors) => {
                console.error('Error updating quantity:', errors);
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
        setRemovingItems(prev => ({ ...prev, [productId]: true }));
        
        router.delete(route('cart.remove'), {
            data: { product_id: productId },
            preserveScroll: true,
            onSuccess: () => {
                // Disparar evento para actualizar el contador del navbar
                window.dispatchEvent(new CustomEvent('cart-updated'));
                // Recargar datos con Inertia
                router.reload({ only: ['cartItems', 'total'] });
            },
            onError: (errors) => {
                console.error('Error removing item:', errors);
            },
            onFinish: () => {
                setRemovingItems(prev => {
                    const updated = { ...prev };
                    delete updated[productId];
                    return updated;
                });
            }
        });
    };

    // Función para mostrar modal de confirmación
    const showClearConfirmation = () => {
        setShowClearModal(true);
    };

    // Función para vaciar carrito
    const confirmClearCart = () => {
        setShowClearModal(false);
        destroy(route('cart.clear'), {
            preserveScroll: true,
            onSuccess: () => {
                // Disparar evento para actualizar el contador del navbar
                window.dispatchEvent(new CustomEvent('cart-updated'));
                // Recargar datos con Inertia
                router.reload({ only: ['cartItems', 'total'] });
            },
            onError: (errors) => {
                console.error('Error clearing cart:', errors);
            }
        });
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

            {/* Sección superior personalizada */}
            <div className="bg-gradient-to-br from-navy via-navy/95 to-navy/90 pt-20 pb-10">
                <div className="max-w-7xl mx-auto px-6 lg:px-8">
                    {/* Mobile: logo arriba, luego textos */}
                    <div className="flex flex-col items-start text-left md:hidden">
                        <img src="/images/chispas-frias-logo.png" alt="Logo Chispas Frías" className="h-32 w-auto mb-6" />
                        <h1 className="text-3xl font-bold text-chalk mb-3">Tu Carrito</h1>
                        <p className="text-lg text-chalk/80 max-w-2xl">
                            Revisa los productos seleccionados, ajusta las cantidades y procede con tu pedido de pirotecnia fría.
                        </p>
                    </div>
                    {/* Desktop: diseño anterior */}
                    <div className="hidden md:flex items-center">
                        <img src="/images/chispas-frias-logo.png" alt="Logo Chispas Frías" className="h-28 w-auto mr-3" />
                        <div className="h-32 w-px bg-white ml-2 mr-1" />
                        <div className="flex flex-col text-left ml-2">
                            <h1 className="text-4xl lg:text-5xl font-bold text-chalk mb-3">Tu carrito de compras.</h1>
                            <p className="text-xl text-chalk/80 max-w-2xl">
                                Revisa los productos seleccionados, ajusta las cantidades y procede con tu pedido de pirotecnia fría.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
            
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
                                        onClick={showClearConfirmation}
                                        disabled={clearingCart}
                                        className="text-sm text-red-600 hover:text-red-800 disabled:opacity-50 transition-colors"
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
                                                        disabled={removingItems[item.product.id]}
                                                        className="text-red-600 hover:text-red-800 p-2 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                                                        title="Eliminar del carrito"
                                                    >
                                                        {removingItems[item.product.id] ? (
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
                                                <div className="flex items-center justify-between mt-4">
                                                    <div className="flex items-center space-x-4">
                                                        <span className="text-sm font-medium text-navy">
                                                            Cantidad:
                                                        </span>
                                                        <div className="flex items-center border border-navy/20 rounded-lg">
                                                            <button
                                                                type="button"
                                                                onClick={() => updateQuantity(item.product.id, item.quantity - 1)}
                                                                disabled={item.quantity <= 1 || updatingItems[item.product.id]}
                                                                className="px-3 py-1 text-navy hover:bg-navy/10 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                                                                title="Disminuir cantidad"
                                                            >
                                                                −
                                                            </button>
                                                            <span className="px-4 py-1 text-navy font-medium min-w-[2.5rem] text-center">
                                                                {updatingItems[item.product.id] ? (
                                                                    <svg className="animate-spin h-4 w-4 mx-auto" fill="none" viewBox="0 0 24 24">
                                                                        <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                                                                        <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                                                                    </svg>
                                                                ) : item.quantity}
                                                            </span>
                                                            <button
                                                                type="button"
                                                                onClick={() => updateQuantity(item.product.id, item.quantity + 1)}
                                                                disabled={item.quantity >= item.product.stock || updatingItems[item.product.id]}
                                                                className="px-3 py-1 text-navy hover:bg-navy/10 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                                                                title="Aumentar cantidad"
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
                                            <Link 
                                                href={route('cart.checkout')}
                                                disabled={cartItems.length === 0}
                                                className={`block w-full py-3 font-bold rounded-lg text-center transition-colors ${
                                                    cartItems.length === 0
                                                        ? 'bg-gray-300 text-gray-500 cursor-not-allowed pointer-events-none'
                                                        : 'bg-navy text-chalk hover:bg-navy/90'
                                                }`}
                                            >
                                                <span className="flex items-center justify-center">
                                                    <svg className="mr-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M17 8l4 4m0 0l-4 4m4-4H3" />
                                                    </svg>
                                                    Proceder al Checkout
                                                </span>
                                            </Link>
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

            {/* Modal de confirmación para vaciar carrito */}
            {showClearModal && (
                <div className="fixed inset-0 z-50 overflow-y-auto">
                    <div className="flex items-center justify-center min-h-screen pt-4 px-4 pb-4 text-center">
                        {/* Overlay */}
                        <div 
                            className="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"
                            onClick={() => setShowClearModal(false)}
                        ></div>

                        {/* Modal */}
                        <div className="inline-block align-middle bg-white rounded-lg px-4 pt-5 pb-4 text-left overflow-hidden shadow-xl transform transition-all w-full max-w-lg mx-auto relative z-10">
                            <div className="sm:flex sm:items-start">
                                <div className="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-red-100 sm:mx-0 sm:h-10 sm:w-10">
                                    <svg className="h-6 w-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z" />
                                    </svg>
                                </div>
                                <div className="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                                    <h3 className="text-lg leading-6 font-medium text-gray-900">
                                        ¿Vaciar carrito?
                                    </h3>
                                    <div className="mt-2">
                                        <p className="text-sm text-gray-500">
                                            Esta acción eliminará todos los productos de tu carrito. ¿Estás seguro de que deseas continuar?
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <div className="mt-5 sm:mt-4 sm:flex sm:flex-row-reverse">
                                <button
                                    type="button"
                                    onClick={confirmClearCart}
                                    disabled={clearingCart}
                                    className="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-red-600 text-base font-medium text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 sm:ml-3 sm:w-auto sm:text-sm disabled:opacity-50 disabled:cursor-not-allowed"
                                >
                                    {clearingCart ? 'Vaciando...' : 'Sí, vaciar carrito'}
                                </button>
                                <button
                                    type="button"
                                    onClick={() => setShowClearModal(false)}
                                    disabled={clearingCart}
                                    className="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:text-gray-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-navy sm:mt-0 sm:w-auto sm:text-sm disabled:opacity-50"
                                >
                                    Cancelar
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            )}

            <Footer />
            <WhatsAppButton />
        </>
    );
}