import { Head, Link } from '@inertiajs/react';
import { useState, useEffect } from 'react';
import axios from 'axios';
import { motion, AnimatePresence } from 'framer-motion';
import Footer from '@/Components/Footer';
import Navbar from '@/Components/Navbar';
import WhatsAppButton from '@/Components/WhatsAppButton';
import { 
  FadeIn, 
  ScaleIn, 
  Stagger, 
  StaggerItem, 
  AnimatedCard, 
  AnimatedButton, 
  AnimatedSection,
  AnimatedText,
  AnimatedImage,
} from '@/Components/Animated';
import { useScrollAnimation, useReducedMotion } from '@/hooks/useAnimations';
import * as animations from '@/utils/animations';

// Componente de Carrusel
function ImageCarousel() {
    const [currentIndex, setCurrentIndex] = useState(0);
    const reducedMotion = useReducedMotion();
    
    // Imágenes de ejemplo - reemplazar con imágenes reales
    const images = [
        '/images/carrusel-1.jpg',
        '/images/carrusel-2.jpg',
        '/images/carrusel-3.jpg',
        '/images/carrusel-4.jpg',
        '/images/carrusel-5.jpg',
    ];

    useEffect(() => {
        const interval = setInterval(() => {
            setCurrentIndex((prevIndex) => (prevIndex + 1) % images.length);
        }, 4000);
        return () => clearInterval(interval);
    }, [images.length]);

    const goToPrevious = () => {
        setCurrentIndex((prevIndex) => (prevIndex - 1 + images.length) % images.length);
    };

    const goToNext = () => {
        setCurrentIndex((prevIndex) => (prevIndex + 1) % images.length);
    };

    return (
        <div className="relative w-full h-full group">
            {/* Imágenes */}
            <AnimatePresence mode="wait">
                {images.map((image, index) => (
                    index === currentIndex && (
                        <motion.div
                            key={index}
                            initial={{ opacity: 0, scale: reducedMotion ? 1 : 1.1 }}
                            animate={{ opacity: 1, scale: 1 }}
                            exit={{ opacity: 0, scale: reducedMotion ? 1 : 0.95 }}
                            transition={{ duration: 0.7, ease: [0.25, 0.1, 0.25, 1.0] }}
                            className="absolute inset-0"
                        >
                            <img
                                src={image}
                                alt={`Imagen ${index + 1}`}
                                className="w-full h-full object-cover"
                            />
                        </motion.div>
                    )
                ))}
            </AnimatePresence>

            {/* Flecha Izquierda */}
            <motion.button
                onClick={goToPrevious}
                whileHover={reducedMotion ? {} : { scale: 1.1, x: -4 }}
                whileTap={reducedMotion ? {} : { scale: 0.9 }}
                className="absolute left-4 top-1/2 -translate-y-1/2 w-10 h-10 rounded-full bg-white/80 backdrop-blur-sm text-navy flex items-center justify-center transition-opacity hover:bg-white shadow-lg z-10"
                aria-label="Imagen anterior"
            >
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="w-5 h-5">
                    <path d="M15 18l-6-6 6-6" />
                </svg>
            </motion.button>

            {/* Flecha Derecha */}
            <motion.button
                onClick={goToNext}
                whileHover={reducedMotion ? {} : { scale: 1.1, x: 4 }}
                whileTap={reducedMotion ? {} : { scale: 0.9 }}
                className="absolute right-4 top-1/2 -translate-y-1/2 w-10 h-10 rounded-full bg-white/80 backdrop-blur-sm text-navy flex items-center justify-center transition-opacity hover:bg-white shadow-lg z-10"
                aria-label="Imagen siguiente"
            >
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="w-5 h-5">
                    <path d="M9 18l6-6-6-6" />
                </svg>
            </motion.button>

            {/* Indicadores */}
            <div className="absolute bottom-6 left-1/2 transform -translate-x-1/2 flex gap-2">
                {images.map((_, index) => (
                    <motion.button
                        key={index}
                        onClick={() => setCurrentIndex(index)}
                        whileHover={reducedMotion ? {} : { scale: 1.2 }}
                        whileTap={reducedMotion ? {} : { scale: 0.9 }}
                        className={`h-2 rounded-full transition-all ${
                            index === currentIndex
                                ? 'bg-gold w-8'
                                : 'bg-white/50 hover:bg-white/80 w-2'
                        }`}
                        aria-label={`Ir a imagen ${index + 1}`}
                    />
                ))}
            </div>
        </div>
    );
}

// Componente de Grid de Productos
function ProductCarousel({ products, type = 'featured' }) {
    const [addingId, setAddingId] = useState(null);

    // Función para agregar al carrito
    const addToCart = async (product) => {
        if (!product || addingId === product.id) return;
        
        try {
            setAddingId(product.id);
            await axios.post(route('cart.add'), {
                product_id: product.id,
                quantity: 1,
            });
            // Disparar evento para actualizar el contador del navbar
            window.dispatchEvent(new CustomEvent('cart-updated'));
        } catch (error) {
            console.error('Error agregando al carrito:', error);
        } finally {
            setAddingId(null);
        }
    };

    // Obtener la URL de la imagen primaria
    const getPrimaryImageUrl = (product) => {
        if (!product.images || product.images.length === 0) return null;
        
        const primaryImage = product.images.find(img => img.type === 'primary') || product.images[0];
        return primaryImage.url.startsWith('/') ? primaryImage.url : `/storage/${primaryImage.url}`;
    };

    // Obtener preview de la descripción
    const getDescriptionPreview = (description, maxLength = 120) => {
        if (!description) return '';
        return description.length > maxLength 
            ? description.substring(0, maxLength) + '...' 
            : description;
    };

    // Filtrar productos según el tipo
    let filteredProducts = products || [];
    
    if (type === 'featured') {
        // Filtrar solo productos destacados (manejar tanto booleano como entero) y limitar a 4
        filteredProducts = products ? products.filter(product => product.is_featured === true || product.is_featured === 1).slice(0, 4) : [];
    } else if (type === 'offers') {
        // Para ofertas, usar todos los productos que se envían (ya vienen filtrados)
        filteredProducts = products || [];
    }

    if (!filteredProducts || filteredProducts.length === 0) return null;

    return (
        <>
            {/* Vista Mobile - Slide horizontal */}
            <div className="lg:hidden overflow-x-auto -mx-6" style={{ scrollbarWidth: 'none', msOverflowStyle: 'none' }}>
                <style>{`
                    .lg\\:hidden.overflow-x-auto::-webkit-scrollbar {
                        display: none;
                    }
                `}</style>
                <div className="flex gap-4 pb-4 px-6">
                    {filteredProducts.map((product, index) => (
                        <div key={product.id} className={`bg-white rounded-lg shadow-lg overflow-hidden hover:shadow-2xl transition-all duration-500 border-2 border-navy/20 flex-shrink-0 w-64 flex flex-col ${index === 0 ? 'ml-0' : ''} ${index === filteredProducts.length - 1 ? 'mr-0' : ''}`}>
                            {/* Imagen del producto */}
                            <div className="relative aspect-w-4 aspect-h-3 bg-gray-100">
                                {product.images?.length > 0 ? (
                                    <img
                                        src={getPrimaryImageUrl(product)}
                                        alt={product.title}
                                        className="w-full h-52 object-cover"
                                    />
                                ) : (
                                    <div className="w-full h-52 bg-gray-200 flex items-center justify-center">
                                        <svg className="h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                        </svg>
                                    </div>
                                )}
                                
                                {/* Badge de oferta */}
                                {product.has_offer && (
                                    <div className="absolute top-3 right-3 z-10">
                                        <span className="bg-red-500 text-white text-xs font-bold px-2 py-1 rounded-full shadow-lg">
                                            -{product.discount_percentage}%
                                        </span>
                                    </div>
                                )}
                            </div>

                            {/* Información del producto */}
                            <div className="p-4 flex flex-col justify-between h-full">
                                {/* Categoría */}
                                <div className="flex items-center mb-1">
                                    <span className="text-xs text-gold font-medium">
                                        {product.category?.parent?.name || product.category?.name}
                                    </span>
                                    {product.category?.parent && (
                                        <>
                                            <span className="mx-1 text-navy/40">•</span>
                                            <span className="text-xs text-navy/60">
                                                {product.category.name}
                                            </span>
                                        </>
                                    )}
                                </div>

                                {/* Título */}
                                <h3 className="text-base font-bold text-navy mb-1">
                                    {product.title}
                                </h3>

                                {/* Descripción */}
                                <p className="text-navy/70 text-xs mb-3 line-clamp-3 flex-grow">
                                    {getDescriptionPreview(product.description, 150)}
                                </p>

                                {/* Precio, stock y acciones */}
                                <div className="flex flex-col">
                                    <div className="mb-1">
                                        {product.has_offer ? (
                                            <div className="space-y-0.5">
                                                <div className="flex items-baseline gap-1.5">
                                                    <span className="text-lg font-bold text-red-600">
                                                        ${Number(product.offer_price).toLocaleString('es-CL')}
                                                    </span>
                                                    <span className="text-xs text-navy/60 line-through">
                                                        ${Number(product.price).toLocaleString('es-CL')}
                                                    </span>
                                                </div>
                                                <div className="text-xs text-green-600 font-medium">
                                                    Ahorras ${Number(product.price - product.offer_price).toLocaleString('es-CL')}
                                                </div>
                                            </div>
                                        ) : (
                                            <span className="text-lg font-bold text-navy">
                                                ${Number(product.price).toLocaleString('es-CL')}
                                            </span>
                                        )}
                                    </div>

                                    <div className="mt-3 flex flex-row gap-2">
                                        <button
                                            onClick={() => addToCart(product)}
                                            disabled={addingId === product.id || product.stock <= 0}
                                            className={`flex-1 inline-flex items-center justify-center px-2 py-1.5 rounded-full font-semibold text-xs transition-all duration-300 whitespace-nowrap ${
                                                product.stock <= 0
                                                    ? 'bg-gray-300 text-gray-600 cursor-not-allowed'
                                                    : 'bg-navy text-white hover:bg-navy/90 shadow-lg'
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
                                        <a 
                                            href={`/productos/${product.id}`}
                                            className="flex-1 inline-flex items-center justify-center px-2 py-1.5 bg-white text-navy border-2 border-navy rounded-full hover:bg-navy/10 transition-all duration-300 font-semibold text-xs whitespace-nowrap"
                                        >
                                            Ver más
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    ))}
                </div>
            </div>

            {/* Vista Desktop - Grid */}
            <Stagger speed="fast" className="hidden lg:grid lg:grid-cols-4 gap-4">
                {filteredProducts.map((product, index) => (
                <StaggerItem key={product.id}>
                    <motion.div 
                        className="bg-white rounded-lg shadow-lg overflow-hidden group border-2 border-navy/20 flex flex-col h-full"
                        whileHover={{ scale: 1.02, y: -4, boxShadow: "0 20px 40px rgba(0, 0, 0, 0.15)" }}
                        transition={{ duration: 0.3, ease: [0.25, 0.1, 0.25, 1.0] }}
                    >
                    {/* Imagen del producto */}
                    <div className="relative aspect-w-4 aspect-h-3 bg-gray-100 overflow-hidden">
                        {product.images?.length > 0 ? (
                            <motion.img
                                src={getPrimaryImageUrl(product)}
                                alt={product.title}
                                className="w-full h-52 object-cover"
                                initial={{ opacity: 0, scale: 1.1 }}
                                animate={{ opacity: 1, scale: 1 }}
                                transition={{ duration: 0.5 }}
                                whileHover={{ scale: 1.05 }}
                            />
                        ) : (
                            <div className="w-full h-52 bg-gray-200 flex items-center justify-center">
                                <svg className="h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                </svg>
                            </div>
                        )}
                        
                        {/* Badge de oferta */}
                        {product.has_offer && (
                            <motion.div 
                                className="absolute top-3 right-3 z-10"
                                initial={{ scale: 0, rotate: -180 }}
                                animate={{ scale: 1, rotate: 0 }}
                                transition={{ delay: 0.2, type: "spring", stiffness: 200, damping: 10 }}
                            >
                                <span className="bg-red-500 text-white text-xs font-bold px-2 py-1 rounded-full shadow-lg">
                                    -{product.discount_percentage}%
                                </span>
                            </motion.div>
                        )}
                    </div>

                    {/* Información del producto */}
                    <div className="p-4 flex flex-col justify-between h-full">
                        {/* Categoría */}
                        <div className="flex items-center mb-1">
                            <span className="text-xs text-gold font-medium">
                                {product.category?.parent?.name || product.category?.name}
                            </span>
                            {product.category?.parent && (
                                <>
                                    <span className="mx-1 text-navy/40">•</span>
                                    <span className="text-xs text-navy/60">
                                        {product.category.name}
                                    </span>
                                </>
                            )}
                        </div>

                        {/* Título */}
                        <h3 className="text-base font-bold text-navy mb-1">
                            {product.title}
                        </h3>

                        {/* Descripción */}
                        <p className="text-navy/70 text-xs mb-3 line-clamp-3 flex-grow">
                            {getDescriptionPreview(product.description, 150)}
                        </p>

                        {/* Precio, stock y acciones (apilados) */}
                        <div className="flex flex-col">
                            <div className="mb-1">
                                {product.has_offer ? (
                                    <div className="space-y-0.5">
                                        <div className="flex items-baseline gap-1.5">
                                            <span className="text-lg font-bold text-red-600">
                                                ${Number(product.offer_price).toLocaleString('es-CL')}
                                            </span>
                                            <span className="text-xs text-navy/60 line-through">
                                                ${Number(product.price).toLocaleString('es-CL')}
                                            </span>
                                        </div>
                                        <div className="text-xs text-green-600 font-medium">
                                            Ahorras ${Number(product.price - product.offer_price).toLocaleString('es-CL')}
                                        </div>
                                    </div>
                                ) : (
                                    <span className="text-lg font-bold text-navy">
                                        ${Number(product.price).toLocaleString('es-CL')}
                                    </span>
                                )}
                            </div>

                            <div className="mt-3 flex flex-row gap-2">
                                <motion.button
                                    onClick={() => addToCart(product)}
                                    disabled={addingId === product.id || product.stock <= 0}
                                    whileHover={product.stock > 0 ? { scale: 1.05 } : {}}
                                    whileTap={product.stock > 0 ? { scale: 0.95 } : {}}
                                    className={`flex-1 inline-flex items-center justify-center px-2 py-1.5 rounded-full font-semibold text-xs transition-all duration-300 whitespace-nowrap ${
                                        product.stock <= 0
                                            ? 'bg-gray-300 text-gray-600 cursor-not-allowed'
                                            : 'bg-navy text-white hover:bg-navy/90 shadow-lg'
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
                                </motion.button>
                                <motion.a 
                                    href={`/productos/${product.id}`}
                                    whileHover={{ scale: 1.05 }}
                                    whileTap={{ scale: 0.95 }}
                                    className="flex-1 inline-flex items-center justify-center px-2 py-1.5 bg-white text-navy border-2 border-navy rounded-full hover:bg-navy/10 transition-all duration-300 font-semibold text-xs whitespace-nowrap"
                                >
                                    Ver más
                                </motion.a>
                            </div>
                        </div>
                    </div>
                </motion.div>
                </StaggerItem>
            ))}
            </Stagger>
        </>    );}

export default function Welcome({ auth, featuredProducts = [], offerProducts = [] }) {
    const [openFaqIndex, setOpenFaqIndex] = useState(null);

    const faqs = [
        {
            question: "¿Qué son las chispas frías?",
            answer: "Las chispas frías son un efecto especial, también llamado fuegos fríos, que genera chispas brillantes y centelleantes como fuegos artificiales, pero sin generar calor ni riesgo de incendio real, permitiendo su uso seguro en interiores como en exteriores en todo tipo de eventos. "
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

    return (
        <>
            <Head title="Chispas Frías - Inicio" />
            
            {/* Navbar */}
            <Navbar auth={auth} />
            
            {/* Hero Section con Video de Fondo */}
            <div className="relative h-screen w-full overflow-hidden">
                {/* Video de Fondo Mobile (vertical) */}
                <motion.video
                    autoPlay
                    loop
                    muted
                    playsInline
                    className="absolute top-0 left-0 w-full h-full object-cover md:hidden"
                    initial={{ opacity: 0 }}
                    animate={{ opacity: 1 }}
                    transition={{ duration: 1 }}
                >
                    <source src="/videos/chispas-frias-hero-video.mp4" type="video/mp4" />
                </motion.video>
                
                {/* Video de Fondo Desktop (horizontal) */}
                <motion.video
                    autoPlay
                    loop
                    muted
                    playsInline
                    className="hidden md:block absolute top-0 left-0 w-full h-full object-cover"
                    initial={{ opacity: 0 }}
                    animate={{ opacity: 1 }}
                    transition={{ duration: 1 }}
                >
                    <source src="/videos/chispas-frias-hero-desktop.mp4" type="video/mp4" />
                </motion.video>
                
                {/* Overlay oscuro para mejorar legibilidad */}
                <motion.div 
                    className="absolute top-0 left-0 w-full h-full bg-black/40"
                    initial={{ opacity: 0 }}
                    animate={{ opacity: 1 }}
                    transition={{ duration: 0.8 }}
                ></motion.div>
                
                {/* Contenido del Hero */}
                <div className="relative z-10 flex items-end h-full pb-32 md:pb-20">
                    <div className="max-w-7xl mx-auto px-6 lg:px-8 w-full">
                        <div className="flex flex-col md:flex-row items-center md:items-end gap-16 md:gap-12 mt-0 md:mt-0">
                            {/* Logo */}
                            <motion.img 
                                src="/images/chispas-frias-logo.png" 
                                alt="Chispas Frías Logo" 
                                className="w-44 md:w-44 lg:w-56 drop-shadow-2xl flex-shrink-0"
                                initial={{ opacity: 0, y: 30, scale: 0.9 }}
                                animate={{ opacity: 1, y: 0, scale: 1 }}
                                transition={{ duration: 0.8, delay: 0.3, ease: [0.25, 0.1, 0.25, 1.0] }}
                            />
                            
                            <div className="flex flex-col justify-end">
                                {/* Texto Principal */}
                                <motion.h1 
                                    className="text-left mb-6 drop-shadow-2xl leading-none"
                                    initial={{ opacity: 0, y: 40 }}
                                    animate={{ opacity: 1, y: 0 }}
                                    transition={{ duration: 0.8, delay: 0.5, ease: [0.25, 0.1, 0.25, 1.0] }}
                                >
                                    <span className="inline text-chalk text-5xl md:text-7xl lg:text-6xl font-normal">
                                        Pirotecnia{' '}
                                    </span>
                                    <span className="inline text-chalk text-5xl md:text-7xl lg:text-6xl font-normal">
                                        fría
                                    </span>
                                    <span className="block text-gold text-6xl md:text-8xl lg:text-7xl font-black italic mt-1" style={{ fontFamily: 'Georgia, serif' }}>
                                        que eleva tu evento
                                    </span>
                                </motion.h1>
                                
                                {/* Botones */}
                                <motion.div 
                                    className="flex flex-col sm:flex-row gap-4"
                                    initial={{ opacity: 0, y: 30 }}
                                    animate={{ opacity: 1, y: 0 }}
                                    transition={{ duration: 0.8, delay: 0.7, ease: [0.25, 0.1, 0.25, 1.0] }}
                                >
                                    <motion.div
                                        className="w-full sm:w-auto"
                                        whileHover={{ scale: 1.05 }}
                                        whileTap={{ scale: 0.95 }}
                                    >
                                        <Link
                                            href={route('products.index')}
                                            className="flex items-center justify-center px-8 py-4 bg-white text-black font-semibold rounded-full transition-all duration-300 shadow-lg hover:shadow-2xl w-full"
                                        >
                                            Conoce nuestros productos
                                        </Link>
                                    </motion.div>
                                    <motion.button 
                                        className="w-full sm:w-auto px-8 py-4 bg-white/20 backdrop-blur-md border-2 border-white/50 text-white font-semibold rounded-full transition-all duration-300 shadow-lg hover:shadow-2xl"
                                        whileHover={{ scale: 1.05 }}
                                        whileTap={{ scale: 0.95 }}
                                    >
                                        Contactate con nosotros
                                    </motion.button>
                                </motion.div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            {/* Secciones adicionales irán aquí */}
            <main className="bg-chalk">
                {/* Productos Destacados */}
                <AnimatedSection className="py-6 md:py-8 lg:py-10 bg-gradient-to-t from-navy to-gray-100">
                    <div className="max-w-7xl mx-auto px-6 lg:px-8">
                        <FadeIn direction="up" className="text-center mb-4 md:mb-5">
                            <h2 className="text-2xl md:text-3xl lg:text-4xl font-bold text-navy mb-2 leading-tight">
                                Productos destacados.
                            </h2>
                            <motion.div 
                                className="w-20 h-1 bg-gradient-to-r from-transparent via-gold to-transparent mx-auto"
                                initial={{ width: 0, opacity: 0 }}
                                whileInView={{ width: 80, opacity: 1 }}
                                viewport={{ once: true }}
                                transition={{ duration: 0.6, delay: 0.2 }}
                            ></motion.div>
                        </FadeIn>
                        
                        <div className="pb-4 md:pb-5 lg:pb-6">
                            {featuredProducts && featuredProducts.length > 0 ? (
                                <ProductCarousel products={featuredProducts} type="featured" />
                            ) : (
                                <p className="text-center text-gray-600 px-4">No hay productos disponibles en este momento.</p>
                            )}
                        </div>

                        <ScaleIn delay={0.3}>
                            <motion.div 
                                className="bg-white rounded-2xl shadow-xl p-4 md:p-5 lg:p-6 mt-1 md:mt-2 transition-all duration-500"
                                whileHover={{ scale: 1.02, boxShadow: "0 25px 50px rgba(0, 0, 0, 0.15)" }}
                            >
                                <div className="text-center">
                                    <h3 className="text-lg md:text-xl lg:text-2xl font-bold text-navy mb-2">
                                        Descubrí el catálogo completo
                                    </h3>
                                    <p className="text-gray-600 text-xs md:text-sm mb-3 max-w-xl mx-auto px-4">
                                        Encontrá el producto perfecto para que tu evento sea inolvidable.
                                    </p>
                                    <motion.a 
                                        href="/productos"
                                        className="inline-block px-6 md:px-6 py-2.5 md:py-2.5 text-sm md:text-sm bg-gold text-white rounded-full font-bold transition-all duration-300 shadow-lg"
                                        whileHover={{ scale: 1.05, boxShadow: "0 15px 30px rgba(0, 0, 0, 0.2)" }}
                                        whileTap={{ scale: 0.95 }}
                                    >
                                        Ver todos los productos
                                    </motion.a>
                                    
                                    {/* Información de envíos */}
                                    <div className="mt-3 flex flex-wrap justify-center gap-x-4 gap-y-1 text-gray-600 text-xs">
                                        <p>✓ Envíos a todo el país.</p>
                                        <p>✓ Envío gratis a compras por mayor.</p>
                                        <p>✓ Varios medios de pago a través de nuestro alias.</p>
                                    </div>
                                </div>
                            </motion.div>
                        </ScaleIn>
                    </div>
                </AnimatedSection>

                {/* Productos en Oferta */}
                {offerProducts && offerProducts.length > 0 && (
                    <AnimatedSection className="py-6 md:py-8 lg:py-10 bg-gray-50 relative overflow-hidden">
                        {/* Patrón de fondo sutil */}
                        <motion.div 
                            className="absolute inset-0 opacity-5"
                            initial={{ opacity: 0 }}
                            whileInView={{ opacity: 0.05 }}
                            viewport={{ once: true }}
                            transition={{ duration: 1 }}
                        >
                            <div className="absolute inset-0" style={{
                                backgroundImage: `radial-gradient(circle at 25px 25px, #ef4444 2px, transparent 0), radial-gradient(circle at 75px 75px, #ef4444 2px, transparent 0)`,
                                backgroundSize: '100px 100px',
                                backgroundPosition: '0 0, 50px 50px'
                            }}></div>
                        </motion.div>
                        <div className="max-w-7xl mx-auto px-6 lg:px-8 relative z-10">
                            <FadeIn direction="up" className="text-center mb-4 md:mb-5">
                                <h2 className="text-2xl md:text-3xl lg:text-4xl font-bold text-red-600 mb-2 leading-tight">
                                    Productos en oferta.
                                </h2>
                                <motion.div 
                                    className="w-20 h-1 bg-gradient-to-r from-transparent via-red-500 to-transparent mx-auto"
                                    initial={{ width: 0, opacity: 0 }}
                                    whileInView={{ width: 80, opacity: 1 }}
                                    viewport={{ once: true }}
                                    transition={{ duration: 0.6, delay: 0.2 }}
                                ></motion.div>
                            </FadeIn>
                            
                            <div className="pb-4 md:pb-5 lg:pb-6">
                                <ProductCarousel products={offerProducts} type="offers" />
                            </div>

                            <ScaleIn delay={0.3}>
                                <motion.div 
                                    className="bg-white rounded-2xl shadow-xl p-4 md:p-5 lg:p-6 mt-1 md:mt-2"
                                    whileHover={{ scale: 1.02, boxShadow: "0 25px 50px rgba(0, 0, 0, 0.15)" }}
                                >
                                    <div className="text-center">
                                        <h3 className="text-lg md:text-xl lg:text-2xl font-bold text-red-600 mb-2">
                                            ¡No te pierdas estas ofertas!
                                        </h3>
                                        <p className="text-gray-600 text-xs md:text-sm mb-3 max-w-xl mx-auto px-4">
                                            Precios especiales que no vas a encontrar en otro lado.
                                        </p>
                                        <motion.a 
                                            href="/productos"
                                            className="inline-block px-6 md:px-6 py-2.5 md:py-2.5 text-sm md:text-sm bg-red-600 text-white rounded-full font-bold transition-all duration-300 shadow-lg"
                                            whileHover={{ scale: 1.05, boxShadow: "0 15px 30px rgba(239, 68, 68, 0.3)" }}
                                            whileTap={{ scale: 0.95 }}
                                        >
                                            Ver todas las ofertas
                                        </motion.a>
                                        
                                        {/* Información de ofertas */}
                                        <div className="mt-3 flex flex-wrap justify-center gap-x-4 gap-y-1 text-gray-600 text-xs">
                                            <p>🔥 Ofertas por tiempo limitado.</p>
                                            <p>💸 Hasta 50% de descuento.</p>
                                            <p>⚡ Stock limitado disponible.</p>
                                        </div>
                                    </div>
                                </motion.div>
                            </ScaleIn>
                        </div>
                    </AnimatedSection>
                )}

                {/* Preguntas Frecuentes */}
                <AnimatedSection className="py-12 lg:py-16">
                    <div className="max-w-7xl mx-auto px-6 lg:px-8">
                        <FadeIn direction="up">
                            <h2 className="text-4xl md:text-5xl font-bold text-center text-navy mb-4">
                                Preguntas y dudas frecuentes
                            </h2>
                        </FadeIn>
                        <motion.div 
                            className="w-20 h-1 bg-gold mx-auto mb-12"
                            initial={{ width: 0, opacity: 0 }}
                            whileInView={{ width: 80, opacity: 1 }}
                            viewport={{ once: true }}
                            transition={{ duration: 0.6, delay: 0.2 }}
                        ></motion.div>
                        
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <Stagger speed="normal" className="space-y-4">
                                {leftFaqs.map(({ faq, index }) => (
                                    <StaggerItem key={index}>
                                        <motion.div 
                                            className="bg-white rounded-2xl shadow-lg overflow-hidden self-start"
                                            whileHover={{ scale: 1.01, boxShadow: "0 20px 40px rgba(0, 0, 0, 0.12)" }}
                                            transition={{ duration: 0.2 }}
                                        >
                                            <motion.button
                                                onClick={() => toggleFaq(index)}
                                                className="w-full px-6 py-5 flex items-center justify-between text-left hover:bg-gray-50 transition-colors"
                                                whileTap={{ scale: 0.98 }}
                                            >
                                                <span className="text-lg font-semibold text-navy pr-4">{faq.question}</span>
                                                <motion.svg
                                                    className="w-6 h-6 text-gold flex-shrink-0"
                                                    fill="none"
                                                    stroke="currentColor"
                                                    viewBox="0 0 24 24"
                                                    animate={{ rotate: openFaqIndex === index ? 180 : 0 }}
                                                    transition={{ duration: 0.3 }}
                                                >
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 9l-7 7-7-7" />
                                                </motion.svg>
                                            </motion.button>
                                            <AnimatePresence>
                                                {openFaqIndex === index && (
                                                    <motion.div
                                                        initial={{ height: 0, opacity: 0 }}
                                                        animate={{ height: "auto", opacity: 1 }}
                                                        exit={{ height: 0, opacity: 0 }}
                                                        transition={{ duration: 0.3, ease: [0.25, 0.1, 0.25, 1.0] }}
                                                        className="overflow-hidden"
                                                    >
                                                        <div className="px-6 pb-5 pt-2">
                                                            <p className="text-gray-700 leading-relaxed">{faq.answer}</p>
                                                        </div>
                                                    </motion.div>
                                                )}
                                            </AnimatePresence>
                                        </motion.div>
                                    </StaggerItem>
                                ))}
                            </Stagger>

                            <Stagger speed="normal" className="space-y-4">
                                {rightFaqs.map(({ faq, index }) => (
                                    <StaggerItem key={index}>
                                        <motion.div 
                                            className="bg-white rounded-2xl shadow-lg overflow-hidden self-start"
                                            whileHover={{ scale: 1.01, boxShadow: "0 20px 40px rgba(0, 0, 0, 0.12)" }}
                                            transition={{ duration: 0.2 }}
                                        >
                                            <motion.button
                                                onClick={() => toggleFaq(index)}
                                                className="w-full px-6 py-5 flex items-center justify-between text-left hover:bg-gray-50 transition-colors"
                                                whileTap={{ scale: 0.98 }}
                                            >
                                                <span className="text-lg font-semibold text-navy pr-4">{faq.question}</span>
                                                <motion.svg
                                                    className="w-6 h-6 text-gold flex-shrink-0"
                                                    fill="none"
                                                    stroke="currentColor"
                                                    viewBox="0 0 24 24"
                                                    animate={{ rotate: openFaqIndex === index ? 180 : 0 }}
                                                    transition={{ duration: 0.3 }}
                                                >
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 9l-7 7-7-7" />
                                                </motion.svg>
                                            </motion.button>
                                            <AnimatePresence>
                                                {openFaqIndex === index && (
                                                    <motion.div
                                                        initial={{ height: 0, opacity: 0 }}
                                                        animate={{ height: "auto", opacity: 1 }}
                                                        exit={{ height: 0, opacity: 0 }}
                                                        transition={{ duration: 0.3, ease: [0.25, 0.1, 0.25, 1.0] }}
                                                        className="overflow-hidden"
                                                    >
                                                        <div className="px-6 pb-5 pt-2">
                                                            <p className="text-gray-700 leading-relaxed">{faq.answer}</p>
                                                        </div>
                                                    </motion.div>
                                                )}
                                            </AnimatePresence>
                                        </motion.div>
                                    </StaggerItem>
                                ))}
                            </Stagger>
                        </div>

                        {/* Card de Contacto WhatsApp */}
                        <ScaleIn delay={0.4}>
                            <motion.div 
                                className="mt-12 bg-gradient-to-br from-navy via-navy/95 to-navy/90 rounded-3xl shadow-2xl p-8 md:p-10 text-center relative overflow-hidden border-2 border-navy/30 group"
                                whileHover={{ scale: 1.02 }}
                                transition={{ duration: 0.4 }}
                            >
                                {/* Decoración de fondo */}
                                <div className="absolute inset-0 bg-[radial-gradient(circle_at_30%_50%,rgba(255,255,255,0.1),transparent_50%)] group-hover:opacity-100 transition-opacity duration-500"></div>
                                <div className="absolute inset-0 bg-[radial-gradient(circle_at_70%_50%,rgba(255,255,255,0.05),transparent_50%)]"></div>
                                
                                <div className="max-w-2xl mx-auto relative z-10">
                                    <h3 className="text-2xl md:text-3xl lg:text-4xl font-black text-white mb-4 drop-shadow-lg">
                                        ¿Tenés alguna otra duda?
                                    </h3>
                                    <p className="text-white text-base md:text-lg mb-8 drop-shadow">
                                        Contactá a nuestro equipo que te va a asesorar en lo que necesites, al instante y sin compromiso.
                                    </p>
                                    <motion.a
                                        href="https://wa.me/5491131004505?text=Hola!%20Tengo%20una%20consulta%20sobre%20sus%20productos"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        className="inline-flex items-center gap-2 px-8 py-4 bg-gold text-white font-bold rounded-full transition-all duration-300 shadow-2xl"
                                        whileHover={{ scale: 1.05, boxShadow: "0 25px 60px rgba(0,0,0,0.35)" }}
                                        whileTap={{ scale: 0.95 }}
                                    >
                                        <svg className="w-6 h-6" fill="currentColor" viewBox="0 0 24 24">
                                            <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/>
                                        </svg>
                                        Contactar por WhatsApp
                                    </motion.a>
                                </div>
                            </motion.div>
                        </ScaleIn>
                    </div>
                </AnimatedSection>

                {/* Sección Acerca de Nosotros */}
                <AnimatedSection className="py-12 lg:py-16 bg-gray-50">
                    <div className="max-w-7xl mx-auto px-6 lg:px-8">
                        {/* Contenedor unificado con fondo */}
                        <ScaleIn>
                            <motion.div 
                                className="bg-white rounded-3xl shadow-2xl overflow-hidden transition-all duration-500"
                                whileHover={{ scale: 1.01, boxShadow: "0 30px 60px rgba(0, 0, 0, 0.15)" }}
                            >
                                <div className="grid grid-cols-1 lg:grid-cols-2">
                                    {/* Contenido de Texto */}
                                    <div className="p-8 lg:p-12 xl:p-16 flex flex-col justify-center">
                                        <FadeIn direction="left">
                                            <h2 className="text-navy text-4xl lg:text-5xl font-bold">
                                                Acerca de nosotros
                                            </h2>
                                        </FadeIn>
                                        <motion.div 
                                            className="w-20 h-1 bg-gold mt-6 mb-8"
                                            initial={{ width: 0, opacity: 0 }}
                                            whileInView={{ width: 80, opacity: 1 }}
                                            viewport={{ once: true }}
                                            transition={{ duration: 0.6, delay: 0.2 }}
                                        ></motion.div>
                                        <FadeIn direction="left" delay={0.2}>
                                            <p className="text-navy/80 text-lg leading-relaxed mb-6">
                                                Somos líderes en el mercado de pirotecnia fría y efectos especiales, 
                                                dedicados a transformar cada evento en una experiencia inolvidable. 
                                                Con años de trayectoria, ofrecemos tecnología de vanguardia, 
                                                seguridad certificada y un equipo profesional comprometido con la excelencia.
                                            </p>
                                        </FadeIn>
                                        <FadeIn direction="left" delay={0.3}>
                                            <p className="hidden md:block text-navy/80 text-lg leading-relaxed mb-8">
                                                Nuestra pasión es crear momentos mágicos que permanezcan en la memoria 
                                                de nuestros clientes, combinando creatividad, innovación y la más alta 
                                                calidad en cada uno de nuestros productos y servicios.
                                            </p>
                                        </FadeIn>
                                        
                                        {/* Botones */}
                                        <FadeIn direction="up" delay={0.4}>
                                            <div className="flex flex-col sm:flex-row gap-4 mt-4">
                                                <motion.a 
                                                    href="/productos"
                                                    className="inline-flex items-center justify-center px-6 py-3 bg-navy text-white font-semibold rounded-full transition-all duration-300 shadow-lg"
                                                    whileHover={{ scale: 1.05, boxShadow: "0 15px 30px rgba(0, 0, 0, 0.2)" }}
                                                    whileTap={{ scale: 0.95 }}
                                                >
                                                    Nuestros productos
                                                </motion.a>
                                                <motion.a 
                                                    href="/servicios"
                                                    className="inline-flex items-center justify-center px-6 py-3 bg-transparent text-navy border-2 border-navy font-semibold rounded-full transition-all duration-300 shadow-lg"
                                                    whileHover={{ scale: 1.05, boxShadow: "0 15px 30px rgba(0, 0, 0, 0.15)" }}
                                                    whileTap={{ scale: 0.95 }}
                                                >
                                                    Conocé los servicios
                                                </motion.a>
                                            </div>
                                        </FadeIn>
                                    </div>

                                    {/* Carrusel de Imágenes */}
                                    <div className="relative h-full min-h-[400px] lg:min-h-[500px]">
                                        <ImageCarousel />
                                    </div>
                                </div>
                            </motion.div>
                        </ScaleIn>
                    </div>
                </AnimatedSection>
            </main>
            
            {/* Footer */}
            <Footer />
            
            {/* WhatsApp Button */}
            <WhatsAppButton />
        </>
    );
}
