import { Head, Link } from '@inertiajs/react';
import { useState, useEffect } from 'react';
import Footer from '@/Components/Footer';
import Navbar from '@/Components/Navbar';

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
        <div className="relative w-full h-full">
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
                className="absolute left-4 top-1/2 -translate-y-1/2 w-10 h-10 rounded-full bg-white/80 backdrop-blur-sm text-navy flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity hover:bg-white shadow-lg"
                aria-label="Imagen anterior"
            >
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="w-5 h-5">
                    <path d="M15 18l-6-6 6-6" />
                </svg>
            </button>

            {/* Flecha Derecha */}
            <button
                onClick={goToNext}
                className="absolute right-4 top-1/2 -translate-y-1/2 w-10 h-10 rounded-full bg-white/80 backdrop-blur-sm text-navy flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity hover:bg-white shadow-lg"
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

export default function Welcome({ auth, featuredProducts = [] }) {
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
                <div className="relative z-10 flex items-end h-full pb-16 md:pb-20">
                    <div className="max-w-7xl mx-auto px-6 lg:px-8 w-full">
                        <div className="flex flex-col md:flex-row items-center md:items-end gap-8 md:gap-12">
                            {/* Logo */}
                            <img 
                                src="/images/chispas-frias-logo.png" 
                                alt="Chispas Frías Logo" 
                                className="w-48 md:w-56 lg:w-72 drop-shadow-2xl flex-shrink-0"
                            />
                            
                            <div className="flex flex-col justify-end">
                                {/* Texto Principal */}
                                <h1 className="text-left mb-6 drop-shadow-2xl leading-none">
                                    <span className="block text-chalk text-5xl md:text-6xl lg:text-7xl font-normal">
                                        Pirotecnia fría que
                                    </span>
                                    <span className="block text-gold text-6xl md:text-7xl lg:text-8xl font-black italic mt-1" style={{ fontFamily: 'Georgia, serif' }}>
                                        eleva tu evento
                                    </span>
                                </h1>
                                
                                {/* Botones */}
                                <div className="flex flex-col sm:flex-row gap-4">
                                    <Link
                                        href={route('products.index')}
                                        className="inline-flex items-center justify-center px-8 py-4 bg-white text-black font-semibold rounded-full hover:bg-white/90 transition shadow-lg hover:shadow-xl"
                                    >
                                        Conoce nuestros productos
                                    </Link>
                                    <button className="px-8 py-4 bg-white/20 backdrop-blur-md border-2 border-white/50 text-white font-semibold rounded-full hover:bg-white/30 transition shadow-lg hover:shadow-xl">
                                        Contacta con nosotros
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            {/* Secciones adicionales irán aquí */}
            <main className="bg-chalk">
                {/* Sección Acerca de Nosotros */}
                <section className="py-20 lg:py-28">
                    <div className="max-w-7xl mx-auto px-6 lg:px-8">
                        {/* Contenedor unificado con fondo */}
                        <div className="bg-white rounded-3xl shadow-2xl overflow-hidden">
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
                                    <p className="text-navy/80 text-lg leading-relaxed">
                                        Nuestra pasión es crear momentos mágicos que permanezcan en la memoria 
                                        de nuestros clientes, combinando creatividad, innovación y la más alta 
                                        calidad en cada uno de nuestros productos y servicios.
                                    </p>
                                </div>

                                {/* Carrusel de Imágenes */}
                                <div className="relative h-full min-h-[400px] lg:min-h-[500px]">
                                    <ImageCarousel />
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                {/* Productos Destacados */}
                <section className="py-20 lg:py-28 bg-gray-50">
                    <div className="max-w-7xl mx-auto px-6 lg:px-8">
                        <h2 className="text-4xl md:text-5xl font-bold text-center text-navy mb-4">
                            Productos Destacados
                        </h2>
                        <p className="text-center text-gray-600 mb-12 max-w-2xl mx-auto">
                            Descubre nuestra selección de chispas frías de alta calidad para hacer tu evento inolvidable
                        </p>
                        
                        {featuredProducts && featuredProducts.length > 0 ? (
                            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                                {featuredProducts.map((product) => (
                                    <div key={product.id} className="bg-white rounded-2xl shadow-xl overflow-hidden hover:shadow-2xl transition-shadow duration-300">
                                        <div className="aspect-square bg-gray-100 relative overflow-hidden">
                                            {product.image ? (
                                                <img 
                                                    src={`/storage/${product.image}`} 
                                                    alt={product.title}
                                                    className="w-full h-full object-cover hover:scale-110 transition-transform duration-300"
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
                                            <p className="text-sm text-gold font-semibold mb-2">{product.category}</p>
                                            <h3 className="text-xl font-bold text-navy mb-3">{product.title}</h3>
                                            <p className="text-gray-600 mb-4 line-clamp-2">{product.description}</p>
                                            <div className="flex items-center justify-between">
                                                <span className="text-2xl font-bold text-navy">{product.formatted_price}</span>
                                                <a 
                                                    href={`/productos/${product.id}`}
                                                    className="px-6 py-2 bg-navy text-white rounded-full hover:bg-opacity-90 transition-all duration-300"
                                                >
                                                    Ver más
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        ) : (
                            <p className="text-center text-gray-500">No hay productos disponibles en este momento.</p>
                        )}

                        <div className="text-center mt-12">
                            <a 
                                href="/productos"
                                className="inline-block px-8 py-3 bg-gold text-white rounded-full font-semibold hover:bg-opacity-90 transition-all duration-300 shadow-lg hover:shadow-xl"
                            >
                                Ver todos los productos
                            </a>
                        </div>
                    </div>
                </section>
            </main>
            
            {/* Footer */}
            <Footer />
        </>
    );
}
