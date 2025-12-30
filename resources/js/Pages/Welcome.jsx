import { Head, Link } from '@inertiajs/react';
import { useState, useEffect } from 'react';
import axios from 'axios';
import Footer from '@/Components/Footer';
import Navbar from '@/Components/Navbar';
import WhatsAppButton from '@/Components/WhatsAppButton';

// Componente de Carrusel
function ImageCarousel() {
    const [currentIndex, setCurrentIndex] = useState(0);
    
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
            {images.map((image, index) => (
                <div
                    key={index}
                    className={`absolute inset-0 transition-opacity duration-1000 ${
                        index === currentIndex ? 'opacity-100' : 'opacity-0'
                    }`}
                >
                    <img
                        src={image}
                        alt={`Imagen ${index + 1}`}
                        className="w-full h-full object-cover"
                    />
                </div>
            ))}

            {/* Flecha Izquierda */}
            <button
                onClick={goToPrevious}
                className="absolute left-4 top-1/2 -translate-y-1/2 w-10 h-10 rounded-full bg-white/80 backdrop-blur-sm text-navy flex items-center justify-center transition-opacity hover:bg-white shadow-lg z-10"
                aria-label="Imagen anterior"
            >
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="w-5 h-5">
                    <path d="M15 18l-6-6 6-6" />
                </svg>
            </button>

            {/* Flecha Derecha */}
            <button
                onClick={goToNext}
                className="absolute right-4 top-1/2 -translate-y-1/2 w-10 h-10 rounded-full bg-white/80 backdrop-blur-sm text-navy flex items-center justify-center transition-opacity hover:bg-white shadow-lg z-10"
                aria-label="Imagen siguiente"
            >
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="w-5 h-5">
                    <path d="M9 18l6-6-6-6" />
                </svg>
            </button>

            {/* Indicadores */}
            <div className="absolute bottom-6 left-1/2 transform -translate-x-1/2 flex gap-2">
                {images.map((_, index) => (
                    <button
                        key={index}
                        onClick={() => setCurrentIndex(index)}
                        className={`w-2 h-2 rounded-full transition-all ${
                            index === currentIndex
                                ? 'bg-gold w-8'
                                : 'bg-white/50 hover:bg-white/80'
                        }`}
                        aria-label={`Ir a imagen ${index + 1}`}
                    />
                ))}
            </div>
        </div>
    );
}

// Componente de Carrusel de Productos
function ProductCarousel({ products, type = 'featured' }) {
    const [currentIndex, setCurrentIndex] = useState(0);
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

    // Filtrar productos según el tipo
    let filteredProducts = products || [];
    
    if (type === 'featured') {
        // Filtrar solo productos destacados (manejar tanto booleano como entero)
        filteredProducts = products ? products.filter(product => product.is_featured === true || product.is_featured === 1) : [];
    } else if (type === 'offers') {
        // Para ofertas, usar todos los productos que se envían (ya vienen filtrados)
        filteredProducts = products || [];
    }

    const goToPrevious = () => {
        setCurrentIndex((prevIndex) => 
            prevIndex === 0 ? filteredProducts.length - 1 : prevIndex - 1
        );
    };

    const goToNext = () => {
        setCurrentIndex((prevIndex) => 
            prevIndex === filteredProducts.length - 1 ? 0 : prevIndex + 1
        );
    };

    if (!filteredProducts || filteredProducts.length === 0) return null;

    const currentProduct = filteredProducts[currentIndex];

    return (
        <>
            {/* Vista Mobile - Carrusel */}
            <div className="lg:hidden relative max-w-2xl mx-auto">
                {/* Producto actual */}
                <div className="bg-gray-50 rounded-2xl shadow-lg overflow-hidden hover:shadow-xl hover:scale-[1.02] transition-all duration-300">
                    <div className="aspect-square bg-gray-200 relative overflow-hidden">
                        {currentProduct.image ? (
                            <img 
                                src={currentProduct.image.startsWith('/') ? currentProduct.image : `/storage/${currentProduct.image}`} 
                                alt={currentProduct.title}
                                className="w-full h-full object-cover"
                            />
                        ) : (
                            <div className="w-full h-full flex items-center justify-center text-gray-400">
                                <svg className="w-24 h-24" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                </svg>
                            </div>
                        )}
                    </div>
                    <div className="p-6">
                        <h3 className="text-xl md:text-2xl font-bold text-navy mb-3">{currentProduct.title}</h3>
                        <p className="text-gray-600 mb-4 text-sm md:text-base line-clamp-3">{currentProduct.description}</p>
                        <div className="flex items-center justify-between flex-wrap gap-4">
                            <div className="flex flex-col">
                                {currentProduct.has_offer ? (
                                    <div className="flex items-center gap-2 flex-wrap">
                                        <span className="text-lg text-gray-500 line-through">{currentProduct.formatted_price}</span>
                                        <span className="text-2xl md:text-3xl font-bold text-red-600">{currentProduct.formatted_offer_price}</span>
                                        {currentProduct.discount_percentage && (
                                            <span className="bg-red-100 text-red-800 px-2 py-1 rounded text-xs font-semibold">
                                                -{currentProduct.discount_percentage}%
                                            </span>
                                        )}
                                    </div>
                                ) : (
                                    <span className="text-2xl md:text-3xl font-bold text-navy">{currentProduct.formatted_current_price}</span>
                                )}
                            </div>
                            <div className="flex gap-2">
                                <button 
                                    onClick={() => addToCart(currentProduct)}
                                    disabled={addingId === currentProduct.id}
                                    className={`px-4 py-2 rounded-full hover:scale-105 transition-all duration-300 font-semibold text-sm ${
                                        addingId === currentProduct.id
                                            ? 'bg-gray-400 text-white cursor-not-allowed'
                                            : 'bg-navy text-white hover:bg-navy/90'
                                    }`}
                                >
                                    {addingId === currentProduct.id ? (
                                        <div className="flex items-center">
                                            <svg className="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                                                <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                                                <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                                            </svg>
                                            Agregando...
                                        </div>
                                    ) : (
                                        'Añadir al carrito'
                                    )}
                                </button>
                                <a 
                                    href={`/productos/${currentProduct.id}`}
                                    className="px-4 py-2 bg-white text-navy border-2 border-navy rounded-full hover:bg-navy/10 hover:scale-105 transition-all duration-300 font-semibold text-sm"
                                >
                                    Ver producto
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                {/* Flechas de navegación */}
                {filteredProducts.length > 1 && (
                    <>
                        <button
                            onClick={goToPrevious}
                            className="absolute left-0 top-1/2 -translate-y-1/2 -translate-x-3 md:-translate-x-6 w-10 h-10 md:w-12 md:h-12 rounded-full bg-white shadow-lg text-navy flex items-center justify-center hover:bg-gray-50 transition-colors z-10 border border-gray-200"
                            aria-label="Producto anterior"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round" className="w-5 h-5 md:w-6 md:h-6">
                                <path d="M15 18l-6-6 6-6" />
                            </svg>
                        </button>

                        <button
                            onClick={goToNext}
                            className="absolute right-0 top-1/2 -translate-y-1/2 translate-x-3 md:translate-x-6 w-10 h-10 md:w-12 md:h-12 rounded-full bg-white shadow-lg text-navy flex items-center justify-center hover:bg-gray-50 transition-colors z-10 border border-gray-200"
                            aria-label="Producto siguiente"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round" className="w-5 h-5 md:w-6 md:h-6">
                                <path d="M9 18l6-6-6-6" />
                            </svg>
                        </button>
                    </>
                )}

                {/* Indicadores */}
                {filteredProducts.length > 1 && (
                    <div className="flex justify-center gap-2 mt-6">
                        {filteredProducts.map((_, index) => (
                            <button
                                key={index}
                                onClick={() => setCurrentIndex(index)}
                                className={`h-2 rounded-full transition-all ${
                                    index === currentIndex
                                        ? 'bg-gold w-8'
                                        : 'bg-gray-300 w-2 hover:bg-gray-400'
                                }`}
                                aria-label={`Ir al producto ${index + 1}`}
                            />
                        ))}
                    </div>
                )}
            </div>

            {/* Vista Desktop - Grid de 5 productos */}
            <div className="hidden lg:grid lg:grid-cols-5 gap-4">
                {filteredProducts.map((product) => (
                    <div key={product.id} className="bg-gray-50 rounded-xl shadow-lg overflow-hidden hover:shadow-xl hover:scale-[1.03] transition-all duration-300">
                        <div className="aspect-square bg-gray-200 relative overflow-hidden">
                            {product.image ? (
                                <img 
                                    src={product.image.startsWith('/') ? product.image : `/storage/${product.image}`} 
                                    alt={product.title}
                                    className="w-full h-full object-cover"
                                />
                            ) : (
                                <div className="w-full h-full flex items-center justify-center text-gray-400">
                                    <svg className="w-24 h-24" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                    </svg>
                                </div>
                            )}
                        </div>
                        <div className="p-4">
                            <h3 className="text-lg font-bold text-navy mb-2 line-clamp-2">{product.title}</h3>
                            <p className="text-gray-600 mb-3 text-sm line-clamp-2">{product.description}</p>
                            <div className="flex flex-col gap-3">
                                {product.has_offer ? (
                                    <div className="flex flex-col">
                                        <div className="flex items-center gap-1 mb-1">
                                            <span className="text-sm text-gray-500 line-through">{product.formatted_price}</span>
                                            {product.discount_percentage && (
                                                <span className="bg-red-100 text-red-800 px-1 py-0.5 rounded text-xs font-semibold">
                                                    -{product.discount_percentage}%
                                                </span>
                                            )}
                                        </div>
                                        <span className="text-xl font-bold text-red-600">{product.formatted_offer_price}</span>
                                    </div>
                                ) : (
                                    <span className="text-xl font-bold text-navy">{product.formatted_current_price}</span>
                                )}
                                <div className="flex gap-2">
                                    <button 
                                        onClick={() => addToCart(product)}
                                        disabled={addingId === product.id}
                                        className={`flex-1 px-3 py-2 rounded-full hover:scale-105 transition-all duration-300 font-semibold text-xs ${
                                            addingId === product.id
                                                ? 'bg-gray-400 text-white cursor-not-allowed'
                                                : 'bg-navy text-white hover:bg-navy/90'
                                        }`}
                                    >
                                        {addingId === product.id ? (
                                            <div className="flex items-center justify-center">
                                                <svg className="animate-spin h-3 w-3 text-white" fill="none" viewBox="0 0 24 24">
                                                    <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                                                    <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                                                </svg>
                                            </div>
                                        ) : (
                                            'Añadir al carrito'
                                        )}
                                    </button>
                                    <a 
                                        href={`/productos/${product.id}`}
                                        className="flex-1 text-center px-3 py-2 bg-white text-navy border-2 border-navy rounded-full hover:bg-navy/10 hover:scale-105 transition-all duration-300 font-semibold text-xs">
                                        Ver producto
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                ))}
            </div>
        </>
    );
}

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
                <video
                    autoPlay
                    loop
                    muted
                    playsInline
                    className="absolute top-0 left-0 w-full h-full object-cover md:hidden"
                >
                    <source src="/videos/chispas-frias-hero-video.mp4" type="video/mp4" />
                </video>
                
                {/* Video de Fondo Desktop (horizontal) */}
                <video
                    autoPlay
                    loop
                    muted
                    playsInline
                    className="hidden md:block absolute top-0 left-0 w-full h-full object-cover"
                >
                    <source src="/videos/chispas-frias-hero-desktop.mp4" type="video/mp4" />
                </video>
                
                {/* Overlay oscuro para mejorar legibilidad */}
                <div className="absolute top-0 left-0 w-full h-full bg-black/40"></div>
                
                {/* Contenido del Hero */}
                <div className="relative z-10 flex items-end h-full pb-32 md:pb-20">
                    <div className="max-w-7xl mx-auto px-6 lg:px-8 w-full">
                        <div className="flex flex-col md:flex-row items-center md:items-end gap-16 md:gap-12 mt-0 md:mt-0">
                            {/* Logo */}
                            <img 
                                src="/images/chispas-frias-logo.png" 
                                alt="Chispas Frías Logo" 
                                className="w-44 md:w-44 lg:w-56 drop-shadow-2xl flex-shrink-0"
                            />
                            
                            <div className="flex flex-col justify-end">
                                {/* Texto Principal */}
                                <h1 className="text-left mb-6 drop-shadow-2xl leading-none">
                                    <span className="inline text-chalk text-5xl md:text-7xl lg:text-6xl font-normal">
                                        Pirotecnia{' '}
                                    </span>
                                    <span className="inline text-chalk text-5xl md:text-7xl lg:text-6xl font-normal">
                                        fría
                                    </span>
                                    <span className="block text-gold text-6xl md:text-8xl lg:text-7xl font-black italic mt-1" style={{ fontFamily: 'Georgia, serif' }}>
                                        que eleva tu evento
                                    </span>
                                </h1>
                                
                                {/* Botones */}
                                <div className="flex flex-col sm:flex-row gap-4">
                                    <Link
                                        href={route('products.index')}
                                        className="inline-flex items-center justify-center px-8 py-4 bg-white text-black font-semibold rounded-full hover:scale-105 transition-all duration-300 shadow-lg hover:shadow-2xl"
                                    >
                                        Conoce nuestros productos
                                    </Link>
                                    <button className="px-8 py-4 bg-white/20 backdrop-blur-md border-2 border-white/50 text-white font-semibold rounded-full hover:scale-105 transition-all duration-300 shadow-lg hover:shadow-2xl">
                                        Contactate con nosotros
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            {/* Secciones adicionales irán aquí */}
            <main className="bg-chalk">
                {/* Productos Destacados */}
                <section className="py-6 md:py-8 lg:py-10 bg-gradient-to-t from-navy to-gray-100">
                    <div className="max-w-7xl lg:max-w-full mx-auto px-4 md:px-6 lg:px-8">
                        {/* Card contenedor grande */}
                        <div className="bg-white rounded-2xl shadow-xl p-5 md:p-6 lg:p-8 lg:mx-8">
                            <div className="text-center mb-5 md:mb-6">
                                <h2 className="text-3xl md:text-4xl lg:text-5xl font-bold text-navy mb-2 leading-tight">
                                    Productos Destacados
                                </h2>
                                <div className="w-24 h-1 bg-gradient-to-r from-transparent via-gold to-transparent mx-auto"></div>
                            </div>
                            
                            {featuredProducts && featuredProducts.length > 0 ? (
                                <ProductCarousel products={featuredProducts} type="featured" />
                            ) : (
                                <p className="text-center text-gray-600">No hay productos disponibles en este momento.</p>
                            )}

                            <div className="text-center mt-5 md:mt-6">
                                <h3 className="text-xl md:text-2xl lg:text-3xl font-bold text-navy mb-2">
                                    Descubrí el catálogo completo
                                </h3>
                                <p className="text-gray-600 text-sm md:text-base mb-4 max-w-xl mx-auto px-4">
                                    Encontrá el producto perfecto para que tu evento sea inolvidable.
                                </p>
                                <a 
                                    href="/productos"
                                    className="inline-block px-6 md:px-8 py-2.5 md:py-3 text-sm md:text-base bg-gold text-white rounded-full font-bold hover:bg-opacity-90 transition-all duration-300 shadow-lg hover:shadow-xl hover:scale-105">
                                
                                    Ver todos los productos
                                </a>
                                
                                {/* Información de envíos */}
                                <div className="mt-4 flex flex-wrap justify-center gap-x-4 gap-y-1 text-gray-600 text-xs md:text-sm">
                                    <p>✓ Envíos a todo el país</p>
                                    <p>✓ Envío gratis a compras por mayor</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                {/* Productos en Oferta */}
                <section className="py-6 md:py-8 lg:py-10">
                    <div className="max-w-7xl lg:max-w-full mx-auto px-4 md:px-6 lg:px-8">
                        {/* Card contenedor grande */}
                        <div className="bg-white rounded-2xl shadow-xl p-5 md:p-6 lg:p-8 lg:mx-8 border-2 border-red-100">
                            <div className="text-center mb-5 md:mb-6">
                                <h2 className="text-3xl md:text-4xl lg:text-5xl font-bold text-red-600 mb-2 leading-tight">
                                    🏷️ Productos en Oferta
                                </h2>
                                <p className="text-gray-600 text-base md:text-lg mb-4">
                                    Aprovechá estos precios especiales por tiempo limitado
                                </p>
                                <div className="w-24 h-1 bg-gradient-to-r from-transparent via-red-500 to-transparent mx-auto"></div>
                            </div>
                            
                            {offerProducts && offerProducts.length > 0 ? (
                                <ProductCarousel products={offerProducts} type="offers" />
                            ) : (
                                <div className="text-center py-8">
                                    <div className="max-w-md mx-auto">
                                        <svg className="w-16 h-16 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.5" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
                                        </svg>
                                        <h3 className="text-xl font-semibold text-gray-900 mb-2">
                                            No hay ofertas disponibles
                                        </h3>
                                        <p className="text-gray-600">
                                            Por el momento no tenemos productos en oferta, pero no te preocupes, 
                                            ¡pronto habrá promociones increíbles!
                                        </p>
                                    </div>
                                </div>
                            )}

                            {offerProducts && offerProducts.length > 0 && (
                                <div className="text-center mt-5 md:mt-6">
                                    <h3 className="text-xl md:text-2xl lg:text-3xl font-bold text-red-600 mb-2">
                                        ¡No te pierdas estas ofertas!
                                    </h3>
                                    <p className="text-gray-600 text-sm md:text-base mb-4 max-w-xl mx-auto px-4">
                                        Precios especiales que no vas a encontrar en otro lado.
                                    </p>
                                    <a 
                                        href="/productos"
                                        className="inline-block px-6 md:px-8 py-2.5 md:py-3 text-sm md:text-base bg-red-600 text-white rounded-full font-bold hover:bg-red-700 transition-all duration-300 shadow-lg hover:shadow-xl hover:scale-105">
                                        Ver todas las ofertas
                                    </a>
                                </div>
                            )}
                        </div>
                    </div>
                </section>

                {/* Preguntas Frecuentes */}
                <section className="py-12 lg:py-16">
                    <div className="max-w-7xl mx-auto px-6 lg:px-8">
                        <h2 className="text-4xl md:text-5xl font-bold text-center text-navy mb-4">
                            Preguntas y dudas frecuentes
                        </h2>
                        <div className="w-20 h-1 bg-gold mx-auto mb-12"></div>
                        
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div className="space-y-4">
                                {leftFaqs.map(({ faq, index }) => (
                                    <div key={index} className="bg-white rounded-2xl shadow-lg overflow-hidden hover:shadow-xl hover:scale-[1.01] transition-all duration-300 self-start">
                                        <button
                                            onClick={() => toggleFaq(index)}
                                            className="w-full px-6 py-5 flex items-center justify-between text-left hover:bg-gray-50 transition-colors"
                                        >
                                            <span className="text-lg font-semibold text-navy pr-4">{faq.question}</span>
                                            <svg
                                                className={`w-6 h-6 text-gold flex-shrink-0 transition-transform duration-300 ${
                                                    openFaqIndex === index ? 'rotate-180' : ''
                                                }`}
                                                fill="none"
                                                stroke="currentColor"
                                                viewBox="0 0 24 24"
                                            >
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 9l-7 7-7-7" />
                                            </svg>
                                        </button>
                                        <div
                                            className={`overflow-hidden transition-all duration-300 ${
                                                openFaqIndex === index ? 'max-h-96' : 'max-h-0'
                                            }`}
                                        >
                                            <div className="px-6 pb-5 pt-2">
                                                <p className="text-gray-700 leading-relaxed">{faq.answer}</p>
                                            </div>
                                        </div>
                                    </div>
                                ))}
                            </div>

                            <div className="space-y-4">
                                {rightFaqs.map(({ faq, index }) => (
                                    <div key={index} className="bg-white rounded-2xl shadow-lg overflow-hidden hover:shadow-xl hover:scale-[1.01] transition-all duration-300 self-start">
                                        <button
                                            onClick={() => toggleFaq(index)}
                                            className="w-full px-6 py-5 flex items-center justify-between text-left hover:bg-gray-50 transition-colors"
                                        >
                                            <span className="text-lg font-semibold text-navy pr-4">{faq.question}</span>
                                            <svg
                                                className={`w-6 h-6 text-gold flex-shrink-0 transition-transform duration-300 ${
                                                    openFaqIndex === index ? 'rotate-180' : ''
                                                }`}
                                                fill="none"
                                                stroke="currentColor"
                                                viewBox="0 0 24 24"
                                            >
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 9l-7 7-7-7" />
                                            </svg>
                                        </button>
                                        <div
                                            className={`overflow-hidden transition-all duration-300 ${
                                                openFaqIndex === index ? 'max-h-96' : 'max-h-0'
                                            }`}
                                        >
                                            <div className="px-6 pb-5 pt-2">
                                                <p className="text-gray-700 leading-relaxed">{faq.answer}</p>
                                            </div>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </div>

                        {/* Card de Contacto WhatsApp */}
                        <div className="mt-12 bg-gradient-to-br from-navy via-navy/95 to-navy/90 rounded-3xl shadow-2xl p-8 md:p-10 text-center relative overflow-hidden border-2 border-navy/30 hover:scale-[1.02] transition-all duration-500 group">
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
                                <a
                                    href="https://wa.me/5491131004505?text=Hola!%20Tengo%20una%20consulta%20sobre%20sus%20productos"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className="inline-flex items-center gap-2 px-8 py-4 bg-gold text-white font-bold rounded-full hover:bg-gold/90 hover:scale-105 transition-all duration-300 shadow-2xl hover:shadow-[0_20px_50px_rgba(0,0,0,0.3)]"
                                >
                                    <svg className="w-6 h-6" fill="currentColor" viewBox="0 0 24 24">
                                        <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/>
                                    </svg>
                                    Contactar por WhatsApp
                                </a>
                            </div>
                        </div>
                    </div>
                </section>

                {/* Sección Acerca de Nosotros */}
                <section className="py-12 lg:py-16 bg-gray-50">
                    <div className="max-w-7xl mx-auto px-6 lg:px-8">
                        {/* Contenedor unificado con fondo */}
                        <div className="bg-white rounded-3xl shadow-2xl overflow-hidden hover:shadow-3xl hover:scale-[1.01] transition-all duration-500">
                            <div className="grid grid-cols-1 lg:grid-cols-2">
                                {/* Contenido de Texto */}
                                <div className="p-8 lg:p-12 xl:p-16 flex flex-col justify-center">
                                    <h2 className="text-navy text-4xl lg:text-5xl font-bold">
                                        Acerca de nosotros
                                    </h2>
                                    <div className="w-20 h-1 bg-gold mt-6 mb-8"></div>
                                    <p className="text-navy/80 text-lg leading-relaxed mb-6">
                                        Somos líderes en el mercado de pirotecnia fría y efectos especiales, 
                                        dedicados a transformar cada evento en una experiencia inolvidable. 
                                        Con años de trayectoria, ofrecemos tecnología de vanguardia, 
                                        seguridad certificada y un equipo profesional comprometido con la excelencia.
                                    </p>
                                    <p className="hidden md:block text-navy/80 text-lg leading-relaxed mb-8">
                                        Nuestra pasión es crear momentos mágicos que permanezcan en la memoria 
                                        de nuestros clientes, combinando creatividad, innovación y la más alta 
                                        calidad en cada uno de nuestros productos y servicios.
                                    </p>
                                    
                                    {/* Botones */}
                                    <div className="flex flex-col sm:flex-row gap-4 mt-4">
                                        <a 
                                            href="/productos"
                                            className="inline-flex items-center justify-center px-6 py-3 bg-navy text-white font-semibold rounded-full hover:bg-navy/90 hover:scale-105 transition-all duration-300 shadow-lg hover:shadow-xl"
                                        >
                                            Nuestros productos
                                        </a>
                                        <a 
                                            href="/servicios"
                                            className="inline-flex items-center justify-center px-6 py-3 bg-transparent text-navy border-2 border-navy font-semibold rounded-full hover:bg-navy/10 hover:scale-105 transition-all duration-300 shadow-lg hover:shadow-xl"
                                        >
                                            Conocé los servicios
                                        </a>
                                    </div>
                                </div>

                                {/* Carrusel de Imágenes */}
                                <div className="relative h-full min-h-[400px] lg:min-h-[500px]">
                                    <ImageCarousel />
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
            </main>
            
            {/* Footer */}
            <Footer />
            
            {/* WhatsApp Button */}
            <WhatsAppButton />
        </>
    );
}
