import { Head, Link, router } from '@inertiajs/react';
import { useState, useEffect, useRef } from 'react';
import { createPortal } from 'react-dom';
import axios from 'axios';
import toast from 'react-hot-toast';
import { motion } from 'framer-motion';
import Footer from '@/Components/Footer';
import Navbar from '@/Components/Navbar';
import WhatsAppButton from '@/Components/WhatsAppButton';
import CartButton from '@/Components/CartButton';
import TrustSection from '@/Components/TrustSection';
import { getPrimaryImageUrl } from '@/utils/images';
import { 
  FadeIn, 
  ScaleIn, 
  Stagger, 
  StaggerItem, 
  AnimatedSection,
} from '@/Components/Animated';

const HERO_SLIDES = [
    {
        id: 'efectos-para-cada-evento',
        desktopImage: '/images/carrusel-3.jpg',
        mobileImage: '/images/carrusel-3-mobile.png',
        imageAlt: 'Efectos de chispas frías iluminando una celebración',
        imagePosition: 'object-center',
        overlayClassName: 'md:bg-gradient-to-r md:from-navy-900/85 md:via-navy-900/45 md:to-transparent',
        contentPosition: 'items-end pb-28 pt-32 md:items-center md:pb-0 md:pt-20',
        content: (
            <>
                <h1 className="max-w-2xl text-[clamp(2rem,7vw,4.25rem)] font-bold uppercase leading-[0.98] tracking-[-0.04em] text-white">
                    <span className="block">EL EFECTO IDEAL</span>
                    <span className="mt-2 block">PARA TU EVENTO</span>
                </h1>
                <p className="mt-5 max-w-xl text-pretty text-base font-medium leading-relaxed text-white/90 sm:text-lg lg:text-2xl">
                    Elegí chispas frías, máquinas y accesorios para crear una puesta en escena a tu medida.
                </p>
                <Link href="/productos" className="mt-7 inline-flex min-h-12 items-center justify-center rounded-full bg-storefront px-8 py-3.5 text-center text-sm font-semibold text-white transition-colors hover:brightness-90 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-white sm:text-base">
                    Explorar el catálogo
                </Link>
            </>
        ),
    },
    {
        id: 'distribuidores-numero-uno',
        desktopImage: '/images/banner-hero-desktop.png',
        mobileImage: '/images/banner-hero-mobile.png',
        imageAlt: 'Cajas y chispas frías preparadas para su distribución',
        imagePosition: 'object-center md:object-left',
        overlayClassName: 'hidden',
        contentPosition: 'items-start pb-0 pt-40 md:items-center md:pt-20',
        content: (
            <>
                <h1 className="max-w-2xl text-[clamp(1.75rem,6vw,4.25rem)] font-bold uppercase leading-[0.98] tracking-[-0.04em] text-storefront">
                    <span className="block">CUANTO MÁS LLEVÁS,</span>
                    <span className="mt-2 block">MENOS PAGÁS</span>
                </h1>
                <p className="mt-4 max-w-xl text-pretty text-sm font-medium leading-relaxed text-graphite sm:text-lg lg:text-2xl">
                    <span className="md:hidden">Precios mayoristas en chispas frías y equipos para tus eventos.</span>
                    <span className="hidden md:inline">Precios mayoristas en chispas frías y equipos para tus eventos. Sumá productos a tu pedido y aprovechá mejores precios por cantidad.</span>
                </p>
                <Link href="/productos" className="mt-5 inline-flex min-h-12 items-center justify-center rounded-full bg-storefront px-8 py-3.5 text-center text-sm font-semibold text-white transition-colors hover:brightness-90 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-storefront sm:mt-7 sm:text-base">
                    Ver todos los productos
                </Link>
            </>
        ),
    },
    {
        id: 'momentos-inolvidables',
        desktopImage: '/images/banner-hero-equipamiento-desktop.png',
        mobileImage: '/images/banner-hero-equipamiento-mobile.png',
        imageAlt: 'Máquinas y detonadores de chispas frías en un escenario',
        imagePosition: 'object-center',
        overlayClassName: 'hidden md:block md:bg-gradient-to-r md:from-navy-900/85 md:via-navy-900/45 md:to-transparent',
        contentPosition: 'items-start pb-0 pt-40 md:items-center md:pb-0 md:pt-20',
        content: (
            <>
                <h1 className="max-w-2xl text-[clamp(2rem,7vw,4.25rem)] font-bold uppercase leading-[0.98] tracking-[-0.04em] text-white">
                    <span className="block">HACÉ BRILLAR</span>
                    <span className="mt-2 block">TU GRAN MOMENTO</span>
                </h1>
                <p className="mt-5 max-w-xl text-pretty text-base font-medium leading-relaxed text-white/90 sm:text-lg lg:text-2xl">
                    Chispas frías para entradas, bailes y celebraciones que querés recordar para siempre.
                </p>
                <Link href="/servicios/chispas" className="mt-7 inline-flex min-h-12 items-center justify-center rounded-full bg-storefront px-8 py-3.5 text-center text-sm font-semibold text-white transition-colors hover:brightness-90 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-white sm:text-base">
                    Conocé el servicio
                </Link>
            </>
        ),
    },
];

const CATEGORY_SHORTCUTS = [
    { label: 'Chispas frías', slug: 'chispa-fria', featured: true },
    { label: '2x20', slug: '2x20' },
    { label: '3x30', slug: '3x30' },
    { label: '4x30', slug: '4x30' },
    { label: '5x1', slug: '5x1' },
];

// Componente de vista previa de imagen
function ImagePreview({ image, onClose }) {
    const [scale, setScale] = useState(1);
    const [position, setPosition] = useState({ x: 0, y: 0 });
    const [isDragging, setIsDragging] = useState(false);
    const [dragStart, setDragStart] = useState({ x: 0, y: 0 });

    // Manejar zoom con rueda del mouse
    const handleWheel = (e) => {
        e.preventDefault();
        const delta = e.deltaY > 0 ? -0.1 : 0.1;
        setScale(prev => Math.min(Math.max(1, prev + delta), 3));
    };

    // Manejar zoom táctil (pinch)
    useEffect(() => {
        let initialDistance = 0;
        
        const handleTouchStart = (e) => {
            if (e.touches.length === 2) {
                const touch1 = e.touches[0];
                const touch2 = e.touches[1];
                initialDistance = Math.hypot(
                    touch2.clientX - touch1.clientX,
                    touch2.clientY - touch1.clientY
                );
            }
        };

        const handleTouchMove = (e) => {
            if (e.touches.length === 2) {
                e.preventDefault();
                const touch1 = e.touches[0];
                const touch2 = e.touches[1];
                const distance = Math.hypot(
                    touch2.clientX - touch1.clientX,
                    touch2.clientY - touch1.clientY
                );
                const delta = (distance - initialDistance) * 0.01;
                setScale(prev => Math.min(Math.max(1, prev + delta), 3));
                initialDistance = distance;
            }
        };

        window.addEventListener('touchstart', handleTouchStart);
        window.addEventListener('touchmove', handleTouchMove, { passive: false });

        return () => {
            window.removeEventListener('touchstart', handleTouchStart);
            window.removeEventListener('touchmove', handleTouchMove);
        };
    }, []);

    // Manejar arrastre
    const handleMouseDown = (e) => {
        if (scale > 1) {
            setIsDragging(true);
            setDragStart({
                x: e.clientX - position.x,
                y: e.clientY - position.y
            });
        }
    };

    const handleMouseMove = (e) => {
        if (isDragging && scale > 1) {
            setPosition({
                x: e.clientX - dragStart.x,
                y: e.clientY - dragStart.y
            });
        }
    };

    const handleMouseUp = () => {
        setIsDragging(false);
    };

    // Resetear al cerrar
    const handleClose = () => {
        setScale(1);
        setPosition({ x: 0, y: 0 });
        onClose();
    };

    // Cerrar con ESC
    useEffect(() => {
        const handleEsc = (e) => {
            if (e.key === 'Escape') handleClose();
        };
        window.addEventListener('keydown', handleEsc);
        return () => window.removeEventListener('keydown', handleEsc);
    }, []);

    // Prevenir scroll del body
    useEffect(() => {
        document.body.style.overflow = 'hidden';
        return () => {
            document.body.style.overflow = 'unset';
        };
    }, []);

    return createPortal(
        <div
            className="fixed inset-0 z-[9999] bg-graphite/95 flex items-center justify-center"
            onClick={handleClose}
            style={{ margin: 0, padding: 0 }}
        >
            {/* Botón cerrar */}
            <button
                onClick={handleClose}
                className="absolute top-4 right-4 z-[10000] w-10 h-10 flex items-center justify-center rounded-full bg-surface/10 hover:bg-surface/20 transition-colors backdrop-blur-sm"
                aria-label="Cerrar"
            >
                <svg className="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>

            {/* Controles de zoom */}
            <div className="absolute bottom-4 left-1/2 transform -translate-x-1/2 z-[10000] flex gap-2 bg-surface/10 backdrop-blur-sm rounded-full p-2">
                <button
                    onClick={(e) => {
                        e.stopPropagation();
                        setScale(prev => Math.max(1, prev - 0.25));
                    }}
                    className="w-10 h-10 flex items-center justify-center rounded-full bg-surface/10 hover:bg-surface/20 transition-colors text-white"
                    aria-label="Alejar"
                >
                    <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM13 10H7" />
                    </svg>
                </button>
                <div className="px-4 flex items-center justify-center text-white font-medium min-w-[60px]">
                    {Math.round(scale * 100)}%
                </div>
                <button
                    onClick={(e) => {
                        e.stopPropagation();
                        setScale(prev => Math.min(3, prev + 0.25));
                    }}
                    className="w-10 h-10 flex items-center justify-center rounded-full bg-surface/10 hover:bg-surface/20 transition-colors text-white"
                    aria-label="Acercar"
                >
                    <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM10 7v3m0 0v3m0-3h3m-3 0H7" />
                    </svg>
                </button>
                <button
                    onClick={(e) => {
                        e.stopPropagation();
                        setScale(1);
                        setPosition({ x: 0, y: 0 });
                    }}
                    className="w-10 h-10 flex items-center justify-center rounded-full bg-surface/10 hover:bg-surface/20 transition-colors text-white"
                    aria-label="Restablecer"
                >
                    <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                    </svg>
                </button>
            </div>

            {/* Imagen */}
            <motion.img
                src={image.url}
                alt={image.title}
                className={`max-w-[90vw] max-h-[90vh] object-contain ${scale > 1 ? 'cursor-move' : 'cursor-zoom-in'}`}
                onClick={(e) => e.stopPropagation()}
                onWheel={handleWheel}
                onMouseDown={handleMouseDown}
                onMouseMove={handleMouseMove}
                onMouseUp={handleMouseUp}
                onMouseLeave={handleMouseUp}
                initial={false}
                animate={{ 
                    scale: scale,
                    x: position.x,
                    y: position.y
                }}
                transition={{ duration: 0.1 }}
                style={{
                    touchAction: 'none'
                }}
            />

            {/* Título */}
            <div className="absolute bottom-20 left-1/2 transform -translate-x-1/2 z-[10000] bg-surface/10 backdrop-blur-sm rounded-full px-6 py-2">
                <p className="text-white font-medium">{image.title}</p>
            </div>
        </div>,
        document.body
    );
}

// Componente de Carrusel
function CollageGallery() {
    const [selectedImage, setSelectedImage] = useState(null);
    
    const images = [
        { url: '/images/carrusel-1.jpg', title: 'Chispas frías en evento corporativo' },
        { url: '/images/carrusel-2.jpg', title: 'Chispas frías en bodas y celebraciones' },
        { url: '/images/carrusel-3.jpg', title: 'Efectos especiales con pirotecnia fría' },
        { url: '/images/carrusel-4.jpg', title: 'Pirotecnia fría para fiestas y eventos' },
    ];

    return (
        <>
            {selectedImage && (
                <ImagePreview
                    image={selectedImage}
                    onClose={() => setSelectedImage(null)}
                />
            )}

            <div className="h-full p-6 lg:p-8 flex flex-col gap-4">
                {/* Imagen principal grande */}
                <div
                    className="relative h-[300px] lg:h-[350px] rounded-3xl overflow-hidden group cursor-pointer"
                    onClick={() => setSelectedImage(images[0])}
                >
                    <img
                        src={images[0].url}
                        alt={images[0].title}
                        className="w-full h-full object-cover lg:transition-transform lg:duration-500 lg:group-hover:scale-105"
                        loading="lazy"
                    />
                    <div className="absolute inset-0 bg-navy-900/45"></div>
                    {/* Indicador de clic */}
                    <div className="absolute inset-0 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity duration-300">
                        <div className="bg-surface/20 backdrop-blur-sm rounded-full p-4">
                            <svg className="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM10 7v3m0 0v3m0-3h3m-3 0H7" />
                            </svg>
                        </div>
                    </div>
                    {/* Borde dorado */}
                    <div className="absolute inset-0 border-2 border-ice-500/40 rounded-3xl"></div>
                </div>

                {/* Grid de 3 imágenes */}
                <div className="grid grid-cols-3 gap-4 flex-1">
                    {images.slice(1, 4).map((image, index) => (
                        <div
                            key={index}
                            className="relative rounded-2xl overflow-hidden group cursor-pointer"
                            onClick={() => setSelectedImage(image)}
                        >
                            <img
                                src={image.url}
                                alt={image.title}
                                className="w-full h-full object-cover lg:transition-transform lg:duration-300 lg:group-hover:scale-105"
                                loading="lazy"
                            />
                            <div className="absolute inset-0 bg-navy-900/35 opacity-60 lg:group-hover:opacity-40 lg:transition-opacity lg:duration-300"></div>
                            {/* Indicador de clic */}
                            <div className="absolute inset-0 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity duration-300">
                                <div className="bg-surface/20 backdrop-blur-sm rounded-full p-3">
                                    <svg className="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM10 7v3m0 0v3m0-3h3m-3 0H7" />
                                    </svg>
                                </div>
                            </div>
                            {/* Título al hover - solo desktop */}
                            <div className="hidden lg:flex absolute inset-0 items-end p-3 opacity-0 group-hover:opacity-100 transition-opacity duration-300">
                                <span className="text-white text-xs font-semibold drop-shadow-lg">{image.title}</span>
                            </div>
                            {/* Borde dorado sutil */}
                            <div className="absolute inset-0 border border-ice-500/30 lg:group-hover:border-ice-500/60 rounded-2xl lg:transition-all lg:duration-300"></div>
                        </div>
                    ))}
                </div>
            </div>
        </>
    );
}

// Componente de Grid de Productos
function ProductCarousel({ products, type = 'featured' }) {
    const [addingId, setAddingId] = useState(null);
    const [quantities, setQuantities] = useState({});

    // Función para obtener la cantidad de un producto
    const getQuantity = (productId) => quantities[productId] || 1;

    // Función para incrementar cantidad
    const incrementQuantity = (productId, stock) => {
        const currentQty = getQuantity(productId);
        if (currentQty < stock) {
            setQuantities(prev => ({ ...prev, [productId]: currentQty + 1 }));
        }
    };

    // Función para decrementar cantidad
    const decrementQuantity = (productId) => {
        const currentQty = getQuantity(productId);
        if (currentQty > 1) {
            setQuantities(prev => ({ ...prev, [productId]: currentQty - 1 }));
        }
    };

    // Función para agregar al carrito
    const addToCart = async (product) => {
        if (!product || addingId === product.id) return;
        
        const quantity = getQuantity(product.id);
        
        try {
            setAddingId(product.id);
            await axios.post(route('cart.add'), {
                product_id: product.id,
                quantity: quantity,
            });
            // Disparar evento para actualizar el contador del navbar
            window.dispatchEvent(new CustomEvent('cart-updated'));
            // Mostrar notificación de éxito
            toast.success(`${quantity} ${quantity > 1 ? 'unidades de' : 'unidad de'} ${product.title} agregado al carrito`);
            // Resetear la cantidad después de agregar
            setQuantities(prev => ({ ...prev, [product.id]: 1 }));
        } catch (error) {
            console.error('Error agregando al carrito:', error);
            // Un producto con variantes de color exige elegir una en su ficha:
            // el backend lo rechaza con un mensaje claro que mostramos tal cual.
            toast.error(error?.response?.data?.message || 'Error al agregar el producto');
        } finally {
            setAddingId(null);
        }
    };

    // La URL de la imagen principal la resuelve el helper canónico
    // (utils/images.js), que descarta videos y maneja paths legacy y nuevos.

    // Obtener preview de la descripción
    const getDescriptionPreview = (description, maxLength = 120) => {
        if (!description) return '';
        
        // Remover etiquetas HTML para el preview
        const tempDiv = document.createElement('div');
        tempDiv.innerHTML = description;
        const textContent = tempDiv.textContent || tempDiv.innerText || '';
        
        return textContent.length > maxLength 
            ? textContent.substring(0, maxLength) + '...' 
            : textContent;
    };

    // Filtrar productos según el tipo
    let filteredProducts = products || [];
    
    if (type === 'featured') {
        // Filtrar solo productos destacados (manejar tanto booleano como entero) y limitar a 5
        filteredProducts = products ? products.filter(product => product.is_featured === true || product.is_featured === 1).slice(0, 5) : [];
    } else if (type === 'offers') {
        // Para ofertas, usar todos los productos que se envían (ya vienen filtrados)
        filteredProducts = products || [];
    }

    if (!filteredProducts || filteredProducts.length === 0) return null;

    return (
        <>
            {/* Vista Mobile - Slide horizontal */}
            <div className="-mx-6 overflow-x-auto snap-x snap-mandatory scroll-px-6 lg:hidden" style={{ scrollbarWidth: 'none', msOverflowStyle: 'none' }}>
                <style>{`
                    .lg\\:hidden.overflow-x-auto::-webkit-scrollbar {
                        display: none;
                    }
                `}</style>
                <div className="flex gap-4 px-6 pb-6">
                    {filteredProducts.map((product, index) => (
                        <div 
                            key={product.id} 
                            className={`group flex w-[84vw] max-w-[340px] flex-shrink-0 snap-start cursor-pointer flex-col overflow-hidden rounded-[1.75rem] border border-gray-200 bg-surface text-navy-900 shadow-card transition-all duration-300 hover:-translate-y-1 hover:border-ice-500 hover:shadow-card-hover ${index === 0 ? 'ml-0' : ''} ${index === filteredProducts.length - 1 ? 'mr-0' : ''}`}
                            onClick={() => router.visit(route('products.show', product.id))}
                        >
                            {/* Imagen del producto */}
                            <div className="relative m-2 aspect-[5/4] overflow-hidden rounded-[1.35rem] bg-background/70">
                                {getPrimaryImageUrl(product) ? (
                                    <img
                                        src={getPrimaryImageUrl(product)}
                                        alt={product.title}
                                        className="h-full w-full object-contain transition-transform duration-500 group-hover:scale-105"
                                    />
                                ) : (
                                    <div className="flex h-full w-full items-center justify-center bg-navy-900/5">
                                        <svg className="h-12 w-12 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                        </svg>
                                    </div>
                                )}
                                
                                {/* Badge de oferta */}
                                {product.has_offer && (
                                    <div className="absolute top-3 right-3 z-10">
                                        <span className="bg-promo text-navy-900 text-xs font-bold px-2 py-1 rounded-full shadow-lg">
                                            -{product.discount_percentage}%
                                        </span>
                                    </div>
                                )}
                            </div>

                            {/* Información del producto */}
                            <div className="flex h-full flex-col px-5 pb-3 pt-2">
                                {/* Categoría */}
                                <div className="flex min-h-7 flex-wrap items-center gap-2">
                                    <span className="rounded-full bg-ice-100 px-2.5 py-1 text-[0.7rem] font-bold uppercase tracking-wide text-navy-700">
                                        {product.category?.parent?.name || product.category?.name}
                                    </span>
                                    {product.category?.parent && (
                                        <>
                                            <span className="hidden">•</span>
                                            <span className="text-xs font-medium text-navy-900/70">
                                                {product.category.name}
                                            </span>
                                        </>
                                    )}
                                </div>

                                {/* Título */}
                                <h3 className="uppercase mt-2 min-h-[3.25rem] line-clamp-2 text-lg font-bold leading-snug text-navy-900">
                                    {product.title}
                                </h3>

                                {/* Descripción */}
                                <p className="min-h-9 line-clamp-2 text-sm leading-tight text-navy-900/65">
                                    {getDescriptionPreview(product.description, 300)}
                                </p>

                                {/* Precio, stock y acciones */}
                                <div className="mt-auto flex flex-col pt-2">
                                    <div className="min-h-12">
                                        {product.has_offer ? (
                                            <div>
                                                <div className="flex items-baseline gap-2">
                                                    <span className="text-2xl font-bold text-navy-900">
                                                        ${Number(product.offer_price).toLocaleString('es-AR')}
                                                    </span>
                                                    <span className="text-xs font-medium text-navy-700">ARS</span>
                                                    <span className="text-sm text-navy-900/70 line-through">
                                                        ${Number(product.price).toLocaleString('es-AR')}
                                                    </span>
                                                </div>
                                                <div className="text-xs text-navy-700 font-medium">
                                                    Ahorras ${Number(product.price - product.offer_price).toLocaleString('es-AR')}
                                                </div>
                                            </div>
                                        ) : (
                                            <>
                                                <span className="text-2xl font-bold text-navy-900">
                                                    ${Number(product.price).toLocaleString('es-AR')}
                                                </span>
                                                <span className="text-xs font-medium text-navy-900/70">ARS</span>
                                            </>
                                        )}
                                    </div>

                                    {/* Contador de cantidad */}
                                    <div className="mt-2 flex min-h-12 items-center justify-between">
                                        <span className="text-xs font-bold uppercase tracking-wide text-navy-900/70">Cantidad</span>
                                        <div className="flex items-center overflow-hidden rounded-full border border-navy-900 bg-background/60">
                                            <button
                                                onClick={(e) => {
                                                    e.preventDefault();
                                                    e.stopPropagation();
                                                    decrementQuantity(product.id);
                                                }}
                                                className="flex h-9 w-9 items-center justify-center bg-navy-900/5 transition-colors hover:bg-navy-900/10"
                                            >
                                                <span className="text-navy-900 font-bold">−</span>
                                            </button>
                                            <span className="min-w-[2.5rem] px-2 text-center text-sm font-semibold text-navy-900">
                                                {getQuantity(product.id)}
                                            </span>
                                            <button
                                                onClick={(e) => {
                                                    e.preventDefault();
                                                    e.stopPropagation();
                                                    incrementQuantity(product.id, product.stock);
                                                }}
                                                disabled={getQuantity(product.id) >= product.stock}
                                                className="flex h-9 w-9 items-center justify-center bg-navy-900/5 transition-colors hover:bg-navy-900/10 disabled:cursor-not-allowed disabled:opacity-50"
                                            >
                                                <span className="text-navy-900 font-bold">+</span>
                                            </button>
                                        </div>
                                    </div>

                                    <div className="mt-2 flex gap-2">
                                        <button
                                            onClick={(e) => {
                                                e.preventDefault();
                                                e.stopPropagation();
                                                addToCart(product);
                                            }}
                                            disabled={addingId === product.id || product.stock <= 0}
                                            className={`inline-flex min-h-11 flex-1 items-center justify-center whitespace-nowrap rounded-full px-3 py-2 text-xs font-semibold transition-all duration-200 ${
                                                product.stock <= 0
                                                    ? 'bg-gray-200 text-graphite/75 cursor-not-allowed'
                                                    : 'bg-storefront text-white hover:brightness-90 shadow-lg'
                                            }`}
                                        >
                                            {addingId === product.id ? (
                                                <div className="flex items-center">
                                                    <svg className="animate-spin -ml-1 mr-1.5 h-3 w-3 text-white" fill="none" viewBox="0 0 24 24">
                                                        <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                                                        <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                                                    </svg>
                                                    Agregando...
                                                </div>
                                            ) : (
                                                'Agregar al carrito'
                                            )}
                                        </button>
                                        <Link
                                            href={route('products.show', product.id)}
                                            onClick={(e) => e.stopPropagation()}
                                            className="inline-flex min-h-11 flex-1 items-center justify-center whitespace-nowrap rounded-full border border-storefront bg-surface px-3 py-2 text-xs font-semibold text-storefront transition-all duration-200 hover:bg-storefront hover:text-white"
                                        >
                                            Ver producto
                                        </Link>
                                    </div>
                                </div>
                            </div>
                        </div>
                    ))}
                </div>
            </div>

            {/* Vista Desktop - Grid */}
            <Stagger speed="fast" className="hidden gap-5 lg:grid lg:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-5">
                {filteredProducts.map((product, index) => (
                <StaggerItem key={product.id} className="h-full">
                    <div 
                        className="h-full cursor-pointer"
                        onClick={() => router.visit(route('products.show', product.id))}
                    >
                        <motion.div 
                            className="group flex h-full flex-col overflow-hidden rounded-[1.75rem] border border-gray-200 bg-surface text-navy-900 shadow-card transition-shadow hover:border-ice-500 hover:shadow-card-hover"
                            whileHover={{ y: -4 }}
                            transition={{ duration: 0.25 }}
                        >
                    {/* Imagen del producto */}
                    <div className="relative m-2 aspect-[5/4] overflow-hidden rounded-[1.35rem] bg-background/70">
                        {getPrimaryImageUrl(product) ? (
                            <img
                                src={getPrimaryImageUrl(product)}
                                alt={product.title}
                                className="h-full w-full object-contain transition-transform duration-500 group-hover:scale-105"
                                loading="lazy"
                            />
                        ) : (
                            <div className="flex h-full w-full items-center justify-center bg-navy-900/5">
                                <svg className="h-12 w-12 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                </svg>
                            </div>
                        )}
                        
                        {/* Badge de oferta */}
                        {product.has_offer && (
                            <div className="absolute top-3 right-3 z-10">
                                <span className="bg-promo text-navy-900 text-xs font-bold px-2 py-1 rounded-full shadow-lg">
                                    -{product.discount_percentage}%
                                </span>
                            </div>
                        )}
                    </div>

                    {/* Información del producto */}
                    <div className="flex h-full flex-col px-5 pb-3 pt-2">
                        {/* Categoría */}
                        <div className="flex min-h-7 flex-wrap items-center gap-2">
                            <span className="rounded-full bg-ice-100 px-2.5 py-1 text-[0.7rem] font-bold uppercase tracking-wide text-navy-700">
                                {product.category?.parent?.name || product.category?.name}
                            </span>
                            {product.category?.parent && (
                                <>
                                    <span className="hidden">•</span>
                                    <span className="text-xs font-medium text-navy-900/70 lg:line-clamp-1">
                                        {product.category.name}
                                    </span>
                                </>
                            )}
                        </div>

                        {/* Título */}
                        <h3 className="uppercase mt-2 min-h-[3.25rem] line-clamp-2 text-lg font-bold leading-snug text-navy-900">
                            {product.title}
                        </h3>

                        {/* Descripción */}
                        <p className="min-h-9 line-clamp-2 text-sm leading-tight text-navy-900/65">
                            {getDescriptionPreview(product.description, 300)}
                        </p>

                        {/* Precio, stock y acciones (apilados) */}
                        <div className="mt-auto flex flex-col pt-2">
                            <div className="min-h-12">
                                {product.has_offer ? (
                                    <div>
                                        <div className="flex items-baseline gap-2">
                                            <span className="text-2xl font-bold text-navy-900">
                                                ${Number(product.offer_price).toLocaleString('es-AR')}
                                            </span>
                                            <span className="text-xs font-medium text-navy-700">ARS</span>
                                            <span className="text-sm text-navy-900/70 line-through">
                                                ${Number(product.price).toLocaleString('es-AR')}
                                            </span>
                                        </div>
                                        <div className="text-xs text-navy-700 font-medium">
                                            Ahorras ${Number(product.price - product.offer_price).toLocaleString('es-AR')}
                                        </div>
                                    </div>
                                ) : (
                                    <>
                                        <span className="text-2xl font-bold text-navy-900">
                                            ${Number(product.price).toLocaleString('es-AR')}
                                        </span>
                                        <span className="text-xs font-medium text-navy-900/70">ARS</span>
                                    </>
                                )}
                            </div>

                            {/* Contador de cantidad */}
                            <div className="mt-2 flex min-h-12 items-center justify-between">
                                <span className="text-xs font-bold uppercase tracking-wide text-navy-900/70">Cantidad</span>
                                <div className="flex items-center overflow-hidden rounded-full border border-navy-900 bg-background/60">
                                    <button
                                        onClick={(e) => {
                                            e.preventDefault();
                                            e.stopPropagation();
                                            decrementQuantity(product.id);
                                        }}
                                        className="flex h-9 w-9 items-center justify-center bg-navy-900/5 transition-colors hover:bg-navy-900/10"
                                    >
                                        <span className="text-navy-900 font-bold">−</span>
                                    </button>
                                    <span className="min-w-[2.5rem] px-2 text-center text-sm font-semibold text-navy-900">
                                        {getQuantity(product.id)}
                                    </span>
                                    <button
                                        onClick={(e) => {
                                            e.preventDefault();
                                            e.stopPropagation();
                                            incrementQuantity(product.id, product.stock);
                                        }}
                                        disabled={getQuantity(product.id) >= product.stock}
                                        className="flex h-9 w-9 items-center justify-center bg-navy-900/5 transition-colors hover:bg-navy-900/10 disabled:cursor-not-allowed disabled:opacity-50"
                                    >
                                        <span className="text-navy-900 font-bold">+</span>
                                    </button>
                                </div>
                            </div>

                            <div className="mt-2 flex gap-2">
                                <button
                                    onClick={(e) => {
                                        e.preventDefault();
                                        e.stopPropagation();
                                        addToCart(product);
                                    }}
                                    disabled={addingId === product.id || product.stock <= 0}
                                    className={`inline-flex min-h-11 flex-1 items-center justify-center whitespace-nowrap rounded-full px-3 py-2 text-xs font-semibold transition-all duration-200 ${
                                        product.stock <= 0
                                            ? 'bg-gray-200 text-graphite/75 cursor-not-allowed'
                                            : 'bg-storefront text-white hover:brightness-90 shadow-lg'
                                    }`}
                                >
                                    {addingId === product.id ? (
                                        <div className="flex items-center">
                                            <svg className="animate-spin -ml-1 mr-1.5 h-3 w-3 text-white" fill="none" viewBox="0 0 24 24">
                                                <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                                                <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                                            </svg>
                                            Agregando...
                                        </div>
                                    ) : (
                                        'Agregar al carrito'
                                    )}
                                </button>
                                <Link
                                    href={route('products.show', product.id)}
                                    onClick={(e) => e.stopPropagation()}
                                    className="inline-flex min-h-11 flex-1 items-center justify-center whitespace-nowrap rounded-full border border-storefront bg-surface px-3 py-2 text-xs font-semibold text-storefront transition-all duration-200 hover:bg-storefront hover:text-white"
                                >
                                    Ver producto
                                </Link>
                            </div>
                        </div>
                    </div>
                </motion.div>
                </div>
                </StaggerItem>
            ))}
            </Stagger>
        </>    );}

export default function Welcome({ auth, featuredProducts = [], offerProducts = [] }) {
    const [openFaqIndex, setOpenFaqIndex] = useState(null);
    const [currentHeroSlide, setCurrentHeroSlide] = useState(0);
    const [isHeroPaused, setIsHeroPaused] = useState(false);
    const heroTouchStart = useRef(null);

    useEffect(() => {
        if (isHeroPaused || window.matchMedia('(prefers-reduced-motion: reduce)').matches) return undefined;

        const interval = setInterval(() => {
            setCurrentHeroSlide((current) => (current + 1) % HERO_SLIDES.length);
        }, 6000);

        return () => clearInterval(interval);
    }, [isHeroPaused]);

    // Detectar si se debe abrir un FAQ específico desde la URL
    useEffect(() => {
        const urlParams = new URLSearchParams(window.location.search);
        const faqParam = urlParams.get('faq');
        if (faqParam) {
            const faqIndex = parseInt(faqParam);
            setOpenFaqIndex(faqIndex);
            
            // Scroll a la sección de FAQ después de un pequeño delay
            setTimeout(() => {
                const faqSection = document.getElementById('faq-section');
                if (faqSection) {
                    faqSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            }, 100);
        }
    }, []);

    const faqs = [
        {
            question: "¿Qué son las chispas frías?",
            answer: "Las chispas frías son un efecto especial, también llamado fuegos fríos, que genera chispas brillantes y centelleantes como fuegos artificiales, pero sin generar calor ni riesgo de incendio real, permitiendo su uso seguro en interiores como en exteriores en todo tipo de eventos. "
        },
        {
            question: "¿Cómo comprar en nuestra web?",
            answer: (<>1. Ingresá al <Link href={route('products.index')} className="font-bold text-white underline underline-offset-4 hover:decoration-2 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-white">catálogo de productos</Link> y elegí los que necesitás.<br/><br/>2. Seleccioná la cantidad y agregalos al carrito.<br/><br/>3. Completá tus datos de contacto.<br/><br/>4. Presioná "Enviar pedido por WhatsApp".<br/><br/>Se generará automáticamente un mensaje con los productos seleccionados y tus datos, para que nuestro equipo se contacte y finalice la compra.</>)
        },
        {
            question: "¿Cómo se enciende las chispas?",
            answer: "La mayoría se enciende con encendido electrónico o hay un modelo que viene con mecha como la de los fuegos artificiales. El encendido electrónico puede ser inalámbrico (detonadores profesionales) o con conexión alámbrica (cable y transformador)."
        },
        {
            question: "¿Son peligrosas, dejan restos en el porcelanato/piso?",
            answer: "No, no ensucia, ni genera fuego, sin embargo se recomienda poner alguna base para evitar que alguna chispa pueda generar alguna mancha. Y se recomienda distancia de cualquier tipo de tela o elemento inflamable porque si bien es chispa fría, el propio volcán utiliza un poco de pirotecnia para su detonación."
        },
        {
            question: "¿Son inflamables?",
            answer: "Si bien son chispas frías, se recomienda mantener distancia de cualquier tipo de tela o elemento inflamable. El producto utiliza un poco de pirotecnia para su detonación, por lo que se deben tomar las precauciones adecuadas."
        },
        {
            question: "¿Tienen certificación para interiores?",
            answer: "Viene todo certificado con la información en la misma etiqueta, aprobado por el ANMAC / RENAR."
        },
        {
            question: "¿Se pueden usar cerca de personas, niños o mascotas?",
            answer: "Sí. Las chispas frías están diseñadas para ser utilizadas de forma segura cerca de personas, ya que no generan fuego real ni altas temperaturas. No queman al contacto y no emiten materiales incandescentes. De todos modos, como con cualquier equipo técnico, se recomienda mantener una distancia mínima prudente y que su uso esté siempre supervisado por un adulto u operador responsable, especialmente en presencia de niños o mascotas."
        }
    ];

    // Dividir FAQs en dos columnas (índices pares e impares) para que cada columna se apile independientemente
    const leftFaqs = faqs.map((f, i) => ({ faq: f, index: i })).filter(x => x.index % 2 === 0);
    const rightFaqs = faqs.map((f, i) => ({ faq: f, index: i })).filter(x => x.index % 2 === 1);

    const toggleFaq = (index) => {
        setOpenFaqIndex(openFaqIndex === index ? null : index);
    };

    const heroSlide = HERO_SLIDES[currentHeroSlide];

    return (
        <div className="storefront-background min-h-screen overflow-x-clip text-white">
            <Head title="Chispas Frías | Pirotecnia Fría para Eventos - Venta en Argentina">
                <meta name="description" content="Venta y servicio de chispas frías para bodas, cumpleaños, fiestas y eventos corporativos. Pirotecnia fría certificada, equipos profesionales y envíos a toda Argentina." />
                <meta property="og:title" content="Chispas Frías | Pirotecnia Fría para Eventos" />
                <meta property="og:description" content="Venta de chispas frías y pirotecnia fría certificada para todo tipo de eventos. Productos seguros, envíos a toda Argentina." />
                <meta property="og:image" content="/images/chispas-frias-logo.png" />
                <meta property="og:type" content="website" />
                <meta name="twitter:card" content="summary_large_image" />
                <script type="application/ld+json">{JSON.stringify({
                    "@context": "https://schema.org",
                    "@type": "Organization",
                    "name": "Chispas Frías",
                    "url": "https://chispasfrias.com.ar",
                    "logo": "https://chispasfrias.com.ar/images/chispas-frias-logo.png",
                    "contactPoint": {
                        "@type": "ContactPoint",
                        "telephone": "+54-9-11-7888-6833",
                        "contactType": "sales",
                        "availableLanguage": "Spanish"
                    },
                    "sameAs": [
                        "https://instagram.com/chispasfrias.oficial"
                    ]
                })}</script>
                <script type="application/ld+json">{JSON.stringify({
                    "@context": "https://schema.org",
                    "@type": "FAQPage",
                    "mainEntity": faqs.map(faq => ({
                        "@type": "Question",
                        "name": faq.question,
                        "acceptedAnswer": {
                            "@type": "Answer",
                            "text": typeof faq.answer === 'string' ? faq.answer : faq.question === "¿Cómo comprar en nuestra web?" ? "1. Ingresá al catálogo de productos y elegí los que necesitás. 2. Seleccioná la cantidad y agregalos al carrito. 3. Completá tus datos de contacto. 4. Presioná Enviar pedido por WhatsApp. Se generará automáticamente un mensaje con los productos seleccionados y tus datos." : ""
                        }
                    }))
                })}</script>
            </Head>
            
            {/* Navbar */}
            <Navbar auth={auth} />
            
            {/* Hero Carousel */}
            <section
                className="relative isolate h-screen min-h-[680px] w-full overflow-hidden bg-surface md:min-h-[620px]"
                role="region"
                aria-roledescription="carrusel"
                aria-label="Presentación principal"
                onMouseEnter={() => setIsHeroPaused(true)}
                onMouseLeave={() => setIsHeroPaused(false)}
                onFocusCapture={() => setIsHeroPaused(true)}
                onBlurCapture={(event) => {
                    if (!event.currentTarget.contains(event.relatedTarget)) setIsHeroPaused(false);
                }}
                onTouchStart={(event) => {
                    heroTouchStart.current = {
                        x: event.touches[0].clientX,
                        y: event.touches[0].clientY,
                    };
                }}
                onTouchEnd={(event) => {
                    if (!heroTouchStart.current) return;
                    const deltaX = event.changedTouches[0].clientX - heroTouchStart.current.x;
                    const deltaY = event.changedTouches[0].clientY - heroTouchStart.current.y;
                    if (Math.abs(deltaX) > 50 && Math.abs(deltaX) > Math.abs(deltaY)) {
                        setCurrentHeroSlide((current) => (current + (deltaX < 0 ? 1 : HERO_SLIDES.length - 1)) % HERO_SLIDES.length);
                    }
                    heroTouchStart.current = null;
                }}
                onTouchCancel={() => { heroTouchStart.current = null; }}
            >
                    <article
                        key={heroSlide.id}
                        className="absolute inset-0"
                        aria-label={`Banner ${currentHeroSlide + 1} de ${HERO_SLIDES.length}`}
                    >
                        <picture className="absolute inset-0">
                            <source media="(max-width: 767px)" srcSet={heroSlide.mobileImage} />
                            <img
                                src={heroSlide.desktopImage}
                                alt={heroSlide.imageAlt}
                                className={`h-full w-full object-cover ${heroSlide.imagePosition}`}
                                fetchPriority={currentHeroSlide === 0 ? 'high' : 'auto'}
                            />
                        </picture>
                        <div className={`pointer-events-none absolute inset-0 bg-gradient-to-t from-navy-900/90 via-navy-900/35 to-transparent ${heroSlide.overlayClassName}`} aria-hidden="true" />

                        <div className={`relative z-10 flex h-full ${heroSlide.contentPosition}`}>
                            <div className="site-shell">
                                <div className="flex w-full max-w-[46rem] flex-col items-start text-left">
                                    {heroSlide.content}
                                </div>
                            </div>
                        </div>
                    </article>

                {HERO_SLIDES.length > 1 && (
                    <>
                        <button
                            type="button"
                            onClick={() => setCurrentHeroSlide((current) => (current - 1 + HERO_SLIDES.length) % HERO_SLIDES.length)}
                            className="absolute left-4 top-1/2 z-20 hidden h-11 w-11 -translate-y-1/2 items-center justify-center rounded-full bg-surface/80 text-navy-900 shadow-lg backdrop-blur-sm transition hover:bg-surface md:left-8 md:flex"
                            aria-label="Banner anterior"
                        >
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" className="h-5 w-5">
                                <path strokeLinecap="round" strokeLinejoin="round" d="m15 18-6-6 6-6" />
                            </svg>
                        </button>
                        <button
                            type="button"
                            onClick={() => setCurrentHeroSlide((current) => (current + 1) % HERO_SLIDES.length)}
                            className="absolute right-4 top-1/2 z-20 hidden h-11 w-11 -translate-y-1/2 items-center justify-center rounded-full bg-surface/80 text-navy-900 shadow-lg backdrop-blur-sm transition hover:bg-surface md:right-8 md:flex"
                            aria-label="Banner siguiente"
                        >
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" className="h-5 w-5">
                                <path strokeLinecap="round" strokeLinejoin="round" d="m9 18 6-6-6-6" />
                            </svg>
                        </button>

                        <div className="absolute bottom-7 left-1/2 z-20 flex -translate-x-1/2 gap-2" aria-label="Seleccionar banner">
                            {HERO_SLIDES.map((slide, index) => (
                                <button
                                    key={slide.id}
                                    type="button"
                                    onClick={() => setCurrentHeroSlide(index)}
                                    className={`h-2.5 rounded-full transition-all duration-300 ${
                                        currentHeroSlide === index ? 'w-8 bg-white' : 'w-2.5 bg-white/70 hover:bg-white'
                                    }`}
                                    aria-label={`Mostrar banner ${index + 1}`}
                                    aria-current={currentHeroSlide === index ? 'true' : undefined}
                                />
                            ))}
                        </div>
                    </>
                )}
            </section>
            
            <main>
                <TrustSection />

                {/* Selector de categorías */}
                <AnimatedSection className="py-10 sm:py-14 lg:py-20">
                    <div className="site-shell">
                            <div className="grid grid-cols-1 gap-10 lg:grid-cols-[minmax(0,1.15fr)_minmax(320px,0.85fr)] lg:gap-0">
                                <FadeIn direction="left">
                                    <div className="lg:pr-12">
                                        <h2 className="uppercase max-w-xl text-2xl font-bold leading-tight text-white sm:text-3xl lg:text-4xl">
                                            Elegí el formato de chispa fría que necesitás
                                        </h2>

                                        <nav
                                            className="mt-7 grid grid-cols-2 gap-3 sm:mt-8"
                                            aria-label="Categorías de chispas frías"
                                        >
                                            {CATEGORY_SHORTCUTS.map((category) => (
                                                <Link
                                                    key={category.slug}
                                                    href={route('products.index', { category: category.slug })}
                                                    className={`group flex min-h-12 items-center justify-between rounded-xl px-4 py-3 font-semibold transition-all duration-300 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white focus-visible:ring-offset-2 focus-visible:ring-offset-storefront sm:px-5 ${
                                                        category.featured
                                                            ? 'col-span-2 bg-white text-navy-900 shadow-md hover:-translate-y-0.5 hover:bg-ice-50 hover:shadow-lg'
                                                            : 'border border-white/70 text-white hover:-translate-y-0.5 hover:border-white hover:bg-white hover:text-navy-900 hover:shadow-md'
                                                    }`}
                                                >
                                                    <span>{category.label}</span>
                                                    <svg
                                                        viewBox="0 0 24 24"
                                                        fill="none"
                                                        stroke="currentColor"
                                                        strokeWidth="2"
                                                        className="h-4 w-4 transition-transform duration-300 group-hover:translate-x-1"
                                                        aria-hidden="true"
                                                    >
                                                        <path strokeLinecap="round" strokeLinejoin="round" d="m9 18 6-6-6-6" />
                                                    </svg>
                                                </Link>
                                            ))}
                                        </nav>
                                    </div>
                                </FadeIn>

                                <FadeIn direction="right" delay={0.1}>
                                    <div className="flex h-full flex-col justify-center border-t border-white/30 pt-8 text-white sm:pt-10 lg:border-l lg:border-t-0 lg:pl-12 lg:pt-0">
                                        <h3 className="uppercase text-2xl font-bold leading-tight sm:text-3xl lg:text-4xl">
                                            También tenemos equipamiento
                                        </h3>
                                        <div className="relative mt-6 flex min-h-[340px] items-end overflow-hidden rounded-[1.75rem] shadow-card sm:min-h-[380px] lg:min-h-[400px]">
                                            <img
                                                src="/images/equipamiento-inicio.png"
                                                alt="Máquinas, detonadores y accesorios para eventos"
                                                loading="lazy"
                                                className="absolute inset-0 h-full w-full object-cover"
                                            />
                                            <div className="absolute inset-0 bg-gradient-to-t from-navy-900/95 via-navy-900/50 to-transparent" aria-hidden="true" />
                                            <div className="relative z-10 p-6 sm:p-8">
                                                <h4 className="max-w-md text-lg font-bold leading-snug text-white sm:text-xl">
                                                    Encontrá máquinas, detonadores y accesorios para completar la puesta en escena de tu evento.
                                                </h4>
                                                <Link
                                                    href={route('products.index')}
                                                    className="mt-6 inline-flex min-h-12 w-full items-center justify-center gap-2 rounded-full bg-white px-6 py-3 font-semibold text-navy-900 shadow-lg transition-all duration-300 hover:-translate-y-0.5 hover:bg-ice-50 hover:shadow-xl focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white focus-visible:ring-offset-2 focus-visible:ring-offset-storefront sm:w-fit"
                                                >
                                                    Ver catálogo completo
                                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" className="h-4 w-4" aria-hidden="true">
                                                        <path strokeLinecap="round" strokeLinejoin="round" d="m9 18 6-6-6-6" />
                                                    </svg>
                                                </Link>
                                            </div>
                                        </div>
                                    </div>
                                </FadeIn>
                            </div>
                    </div>
                </AnimatedSection>

                {/* Productos Destacados */}
                <AnimatedSection className="py-10 sm:py-14 lg:py-20">
                    <div className="site-shell">
                        <FadeIn direction="up" className="mb-7 sm:mb-9">
                            <h2 className="uppercase text-3xl font-bold leading-tight text-white sm:text-4xl lg:text-5xl">
                                Productos destacados
                            </h2>
                            <p className="mt-3 max-w-2xl text-sm leading-relaxed text-white sm:text-base">
                                Nuestros productos más elegidos, listos para llevar cada evento a otro nivel.
                            </p>
                        </FadeIn>
                        
                        <div className="pb-4 md:pb-5 lg:pb-6">
                            {featuredProducts && featuredProducts.length > 0 ? (
                                <ProductCarousel products={featuredProducts} type="featured" />
                            ) : (
                                <p className="text-center text-white px-4">No hay productos disponibles en este momento.</p>
                            )}
                        </div>
                        <div className="mt-2 flex justify-center">
                            <Link
                                href={route('products.index')}
                                className="inline-flex min-h-12 w-full items-center justify-center gap-2 rounded-full bg-white px-7 py-3 text-sm font-semibold text-navy-900 shadow-lg transition hover:bg-ice-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white focus-visible:ring-offset-2 focus-visible:ring-offset-storefront sm:w-auto"
                            >
                                Ir al catálogo completo
                                <span aria-hidden="true">→</span>
                            </Link>
                        </div>

                    </div>
                </AnimatedSection>

                {/* Productos en Oferta */}
                {offerProducts && offerProducts.length > 0 && (
                    <AnimatedSection className="py-6 md:py-8 lg:py-10 relative z-10 overflow-hidden">
                        <div className="site-shell relative z-10">
                            <FadeIn direction="up" className="text-center mb-4 md:mb-5">
                                <h2 className="uppercase text-2xl md:text-3xl lg:text-5xl font-bold text-white text-left mb-2 leading-tight">
                                    Productos en oferta
                                </h2>
                                <div className="hidden h-1 w-[450px] max-w-full rounded-full bg-white md:block" />
                                <div className="block h-1 w-[250px] max-w-full rounded-full bg-white md:hidden" />
                            </FadeIn>
                            
                            <div className="pb-4 md:pb-5 lg:pb-6">
                                <ProductCarousel products={offerProducts} type="offers" />
                            </div>

                            <ScaleIn delay={0.3}>
                                <div className="group relative mt-1 overflow-hidden rounded-2xl border-2 border-white/50 bg-navy-900 p-4 shadow-card md:mt-2 md:p-5 lg:p-6">
                                    {/* Decoración de fondo */}
                                    <div className="text-center relative z-10">
                                        <h3 className="uppercase text-lg md:text-xl lg:text-2xl font-bold text-white mb-2 drop-shadow-lg">
                                            ¡No te pierdas estas ofertas!
                                        </h3>
                                        <p className="text-white text-xs md:text-sm mb-3 max-w-xl mx-auto px-4 drop-shadow">
                                            Precios especiales que no vas a encontrar en otro lado.
                                        </p>
                                        <a 
                                            href="/productos"
                                            className="inline-block rounded-full bg-promo px-6 py-2.5 text-sm font-bold text-navy-900 shadow-lg transition-all duration-200 hover:brightness-95 active:scale-95 md:px-6 md:py-2.5 md:text-sm"
                                        >
                                            Ver todas las ofertas
                                        </a>
                                        
                                        {/* Información de ofertas */}
                                        <div className="mt-3 flex flex-wrap justify-center gap-x-4 gap-y-1 text-white text-xs">
                                            <p>✓ Ofertas por tiempo limitado.</p>
                                            <p>✓ Hasta 50% de descuento.</p>
                                            <p>✓ Stock limitado disponible.</p>
                                        </div>
                                    </div>
                                </div>
                            </ScaleIn>
                        </div>
                    </AnimatedSection>
                )}

                {/* Servicios */}
                <AnimatedSection id="servicios" className="scroll-mt-32 py-12 sm:py-16 lg:py-20">
                    <div className="site-shell">
                        <FadeIn direction="up" className="mb-7 sm:mb-9">
                            <h2 className="uppercase max-w-4xl text-3xl font-bold leading-tight text-white sm:text-4xl lg:text-5xl">
                                Chispas frías para tu evento
                            </h2>
                            <p className="mt-4 max-w-3xl text-sm leading-relaxed text-white sm:text-base">
                                ¿Querés llevar tu evento al siguiente nivel? Nosotros te ayudamos a lograrlo con nuestros servicios personalizados.
                            </p>
                        </FadeIn>

                        <FadeIn direction="up">
                            <article className="overflow-hidden rounded-[1.75rem] border border-gray-200 bg-surface text-navy-900 shadow-card">
                                <div className="grid lg:grid-cols-2">
                                    <div className="order-2 flex flex-col justify-center p-6 sm:p-8 lg:order-1 lg:p-10 xl:p-12">
                                        <h3 className="uppercase text-3xl font-bold leading-tight text-navy-900 sm:text-4xl">
                                            Servicio de chispas frías
                                        </h3>
                                        <div className="mt-5 space-y-4 text-sm leading-relaxed text-navy-900/80 sm:text-base">
                                            <p>
                                                Una solución visual <strong className="font-semibold text-navy-900">moderna, elegante y segura</strong> para todo tipo de eventos. Este efecto especial genera columnas de chispas controladas que aportan un impacto visual sorprendente, sin producir calor ni humo.
                                            </p>
                                            <p>
                                                Ideales para quienes buscan un efecto tipo fuego artificial con total seguridad. Perfectas para bodas, cumpleaños, shows y celebraciones especiales.
                                            </p>
                                            <p>
                                                Tecnología segura y presentación de alto nivel para destacar tu evento con un show elegante y confiable.
                                            </p>
                                        </div>
                                        <div className="mt-7 flex flex-col gap-3 sm:flex-row sm:flex-wrap">
                                            <Link
                                                href={route('services.chispas')}
                                                className="inline-flex min-h-12 items-center justify-center rounded-full bg-storefront px-6 py-3 text-sm font-semibold text-white transition hover:brightness-90 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-storefront focus-visible:ring-offset-2"
                                            >
                                                Ver más detalles
                                            </Link>
                                            <a
                                                href="https://wa.me/5491178886833?text=Hola!%20Quisiera%20consultar%20por%20el%20servicio%20de%20chispas%20frias%20que%20ofrecen%20para%20mi%20evento"
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                className="inline-flex min-h-12 items-center justify-center rounded-full border border-storefront px-6 py-3 text-sm font-semibold text-storefront transition hover:bg-storefront hover:text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-storefront focus-visible:ring-offset-2"
                                            >
                                                Consultar por el servicio
                                            </a>
                                        </div>
                                    </div>
                                    <div className="order-1 p-3 lg:order-2 lg:pl-0">
                                        <div className="grid h-full min-h-[300px] grid-cols-[1.1fr_0.9fr] grid-rows-2 gap-3 sm:min-h-[380px] lg:min-h-[480px]">
                                            {['/images/carrusel-1.jpg', '/images/carrusel-2.jpg', '/images/carrusel-3.jpg'].map((image, index) => (
                                                <div
                                                    key={image}
                                                    className={`group relative overflow-hidden rounded-[1.35rem] bg-navy-900 ${index === 0 ? 'row-span-2' : ''}`}
                                                >
                                                    <img
                                                        src={image}
                                                        alt={`Chispas frías en evento — imagen ${index + 1}`}
                                                        loading="lazy"
                                                        className="absolute inset-0 h-full w-full object-cover transition-transform duration-500 group-hover:scale-105 motion-reduce:transform-none"
                                                    />
                                                </div>
                                            ))}
                                        </div>
                                    </div>
                                </div>
                            </article>
                        </FadeIn>
                    </div>
                </AnimatedSection>

                {/* Preguntas Frecuentes */}
                <AnimatedSection id="faq-section" className="py-12 sm:py-16 lg:py-24">
                    <div className="site-shell">
                        {/* Layout asimétrico: Info a la izquierda, FAQs a la derecha */}
                        <div className="grid grid-cols-1 gap-10 lg:grid-cols-[minmax(280px,0.75fr)_minmax(0,1.25fr)] lg:gap-16">
                            {/* Columna izquierda - Info y CTA */}
                            <div>
                                <FadeIn direction="up">
                                    <div className="lg:sticky lg:top-32">
                                        <h2 className="uppercase text-3xl font-bold leading-tight text-white sm:text-4xl lg:text-5xl">
                                            ¿Tenés alguna duda?
                                        </h2>
                                        
                                        <p className="mt-5 max-w-md text-base leading-relaxed text-white sm:text-lg">
                                            Acá respondemos las dudas más frecuentes que recibimos. Si no encontrás lo que buscás, escribinos por WhatsApp y te asesoramos al instante.
                                        </p>
                                        
                                        {/* Botón WhatsApp */}
                                        <a
                                            href="https://wa.me/5491178886833?text=Hola!%20Tengo%20una%20consulta%20sobre%20sus%20productos"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            className="group mt-7 inline-flex min-h-12 items-center gap-3 rounded-full bg-white px-5 py-3 font-bold text-navy-900 shadow-lg transition-all duration-300 hover:-translate-y-0.5 hover:bg-ice-50 hover:shadow-xl focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white focus-visible:ring-offset-2 focus-visible:ring-offset-storefront"
                                        >
                                            <div className="flex h-8 w-8 items-center justify-center rounded-full bg-navy-900 transition-transform lg:group-hover:scale-110">
                                                <svg className="h-5 w-5 text-white" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                    <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/>
                                                </svg>
                                            </div>
                                            <span className="text-sm sm:text-base">Consultar por WhatsApp</span>
                                        </a>
                                        
                                        {/* Datos adicionales */}
                                        <div className="mt-8 space-y-3 border-t border-white/30 pt-6">
                                            <div className="flex items-center gap-3 text-white">
                                                <svg className="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                                                </svg>
                                                <span className="text-sm">Seguro para interiores y exteriores</span>
                                            </div>
                                            <div className="flex items-center gap-3 text-white">
                                                <svg className="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                                                </svg>
                                                <span className="text-sm">No hay riesgo de incendio mientras se sigan las medidas de seguridad</span>
                                            </div>
                                            <div className="flex items-center gap-3 text-white">
                                                <svg className="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                                                </svg>
                                                <span className="text-sm">Apto para eventos con niños y mascotas</span>
                                            </div>
                                        </div>
                                    </div>
                                </FadeIn>
                            </div>
                            
                            {/* Columna derecha - Todas las preguntas en una sola columna */}
                            <div>
                                <Stagger speed="normal" className="border-t border-white/30">
                                    {faqs.map((faq, index) => (
                                        <StaggerItem key={index} className="border-b border-white/30">
                                            <div>
                                                <button
                                                    type="button"
                                                    onClick={() => toggleFaq(index)}
                                                    aria-expanded={openFaqIndex === index}
                                                    aria-controls={`faq-answer-${index}`}
                                                    className="group flex min-h-16 w-full items-center justify-between py-5 text-left transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white focus-visible:ring-offset-2 focus-visible:ring-offset-storefront sm:py-6"
                                                >
                                                    <span className="pr-5 text-base font-semibold leading-snug text-white underline-offset-4 group-hover:underline sm:text-lg">{faq.question}</span>
                                                    <motion.svg
                                                        className="h-9 w-9 flex-shrink-0 rounded-full border border-white/70 p-2 text-white transition-colors group-hover:border-white group-hover:bg-white group-hover:text-navy-900"
                                                        fill="none"
                                                        stroke="currentColor"
                                                        viewBox="0 0 24 24"
                                                        animate={{ rotate: openFaqIndex === index ? 180 : 0 }}
                                                        transition={{ duration: 0.2 }}
                                                    >
                                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 9l-7 7-7-7" />
                                                    </motion.svg>
                                                </button>
                                                {openFaqIndex === index && (
                                                    <div id={`faq-answer-${index}`} className="overflow-hidden">
                                                        <div className="max-w-2xl pb-6 pr-12">
                                                            <div className="text-sm leading-relaxed text-white sm:text-base">{faq.answer}</div>
                                                        </div>
                                                    </div>
                                                )}
                                            </div>
                                        </StaggerItem>
                                    ))}
                                </Stagger>
                            </div>
                        </div>
                    </div>
                </AnimatedSection>

            </main>
            
            {/* Footer */}
            <Footer />
            
            {/* Cart Button */}
            <CartButton />
            
            {/* WhatsApp Button */}
            <WhatsAppButton />
        </div>
    );
}
