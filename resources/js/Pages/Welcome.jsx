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

export default function Welcome({ auth, featuredProducts = [] }) {
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
        }
    ];

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
                <div className="relative z-10 flex items-end h-full pb-16 md:pb-20">
                    <div className="max-w-7xl mx-auto px-6 lg:px-8 w-full">
                        <div className="flex flex-col md:flex-row items-center md:items-end gap-8 md:gap-12">
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
                <section className="py-20 lg:py-28 relative overflow-hidden bg-gradient-to-br from-navy via-navy/95 to-navy/90">
                    {/* Lunares difuminados decorativos */}
                    <div className="absolute inset-0 overflow-hidden pointer-events-none">
                        {/* Lunares dorados */}
                        <div className="absolute top-10 left-10 w-64 h-64 bg-gold/15 rounded-full blur-3xl"></div>
                        <div className="absolute top-60 left-1/3 w-48 h-48 bg-gold/10 rounded-full blur-3xl"></div>
                        <div className="absolute bottom-20 left-1/4 w-80 h-80 bg-gold/15 rounded-full blur-3xl"></div>
                        <div className="absolute bottom-60 left-2/3 w-56 h-56 bg-gold/10 rounded-full blur-3xl"></div>
                        
                        {/* Lunares blancos */}
                        <div className="absolute top-40 right-20 w-96 h-96 bg-white/8 rounded-full blur-3xl"></div>
                        <div className="absolute top-1/2 right-1/4 w-64 h-64 bg-white/5 rounded-full blur-3xl"></div>
                        <div className="absolute bottom-40 right-1/3 w-72 h-72 bg-white/8 rounded-full blur-3xl"></div>
                        <div className="absolute bottom-10 right-10 w-52 h-52 bg-white/5 rounded-full blur-3xl"></div>
                        
                        {/* Gradientes adicionales en los bordes */}
                        <div className="absolute top-0 left-0 w-full h-32 bg-gradient-to-b from-gold/5 to-transparent"></div>
                        <div className="absolute bottom-0 right-0 w-full h-32 bg-gradient-to-t from-white/5 to-transparent"></div>
                    </div>
                    
                    <div className="max-w-7xl mx-auto px-6 lg:px-8 relative z-10">
                        <h2 className="text-4xl md:text-5xl font-bold text-center text-white mb-8">
                            Productos Destacados
                        </h2>
                        
                        {/* Badges de Envío */}
                        <div className="flex flex-wrap justify-center gap-8 md:gap-12 mb-12">
                            {/* Envíos a todo el país */}
                            <div className="flex flex-col items-center gap-2">
                                <div className="w-12 h-12 rounded-lg bg-gold/20 flex items-center justify-center">
                                    <svg className="w-7 h-7 text-gold" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7" />
                                    </svg>
                                </div>
                                <span className="text-white font-medium text-sm">Envíos a todo el país</span>
                            </div>
                            
                            {/* Envío gratis */}
                            <div className="flex flex-col items-center gap-2">
                                <div className="w-12 h-12 rounded-lg bg-gold/20 flex items-center justify-center">
                                    <svg className="w-7 h-7 text-gold" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4" />
                                    </svg>
                                </div>
                                <span className="text-white font-medium text-sm">Envío gratis desde $150.000</span>
                            </div>
                        </div>
                        
                        {featuredProducts && featuredProducts.length > 0 ? (
                            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                                {featuredProducts.map((product) => (
                                    <div key={product.id} className="bg-white rounded-2xl shadow-xl overflow-hidden hover:shadow-2xl transition-shadow duration-300">
                                        <div className="aspect-square bg-gray-100 relative overflow-hidden">
                                            {product.image ? (
                                                <img 
                                                    src={product.image.startsWith('/') ? product.image : `/storage/${product.image}`} 
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
                            <p className="text-center text-white/70">No hay productos disponibles en este momento.</p>
                        )}

                        <div className="text-center mt-16">
                            <p className="text-white text-lg md:text-xl mb-6 max-w-3xl mx-auto px-4">
                                Descubrí todo nuestro catálogo para encontrar el producto perfecto para que tu evento sea inolvidable.
                            </p>
                            <a 
                                href="/productos"
                                className="inline-block px-10 py-4 text-lg bg-gold text-white rounded-full font-bold hover:bg-opacity-90 transition-all duration-300 shadow-xl hover:shadow-2xl hover:scale-105"
                            >
                                Ver todos los productos
                            </a>
                        </div>
                    </div>
                </section>

                {/* Preguntas Frecuentes */}
                <section className="py-20 lg:py-28">
                    <div className="max-w-4xl mx-auto px-6 lg:px-8">
                        <h2 className="text-4xl md:text-5xl font-bold text-center text-navy mb-4">
                            Preguntas y dudas frecuentes
                        </h2>
                        <div className="w-20 h-1 bg-gold mx-auto mb-12"></div>
                        
                        <div className="space-y-4">
                            {faqs.map((faq, index) => (
                                <div key={index} className="bg-white rounded-2xl shadow-lg overflow-hidden">
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

                        {/* Card de Contacto WhatsApp */}
                        <div className="mt-12 bg-gradient-to-br from-navy to-navy/90 rounded-3xl shadow-2xl p-8 md:p-10 text-center">
                            <div className="max-w-2xl mx-auto">
                                <h3 className="text-2xl md:text-3xl font-bold text-white mb-4">
                                    ¿Tenés alguna otra duda?
                                </h3>
                                <p className="text-white/90 text-lg mb-6">
                                    Contactá a nuestro equipo que te va a asesorar en lo que necesites, al instante y sin compromiso.
                                </p>
                                <a
                                    href="https://wa.me/5491131004505?text=Hola!%20Tengo%20una%20consulta%20sobre%20sus%20productos"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className="inline-block px-8 py-4 bg-gold text-white font-bold rounded-full hover:bg-gold/90 transition-all duration-300 shadow-lg hover:shadow-xl"
                                >
                                    Contactar por WhatsApp
                                </a>
                            </div>
                        </div>
                    </div>
                </section>

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
                                    <p className="hidden md:block text-navy/80 text-lg leading-relaxed">
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
            </main>
            
            {/* Footer */}
            <Footer />
        </>
    );
}
