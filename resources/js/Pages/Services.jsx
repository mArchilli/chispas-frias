import { Head } from '@inertiajs/react';
import { useState, useEffect } from 'react';
import Navbar from '@/Components/Navbar';
import Footer from '@/Components/Footer';

function Carousel() {
    const images = [
        '/images/carrusel-1.jpg',
        '/images/carrusel-2.jpg',
        '/images/carrusel-3.jpg',
        '/images/carrusel-4.jpg',
        '/images/carrusel-5.jpg',
    ];
    const [index, setIndex] = useState(0);

    useEffect(() => {
        const id = setInterval(() => {
            setIndex((i) => (i + 1) % images.length);
        }, 4000);
        return () => clearInterval(id);
    }, []);

    const prev = () => setIndex((i) => (i - 1 + images.length) % images.length);
    const next = () => setIndex((i) => (i + 1) % images.length);

    return (
        <div className="relative rounded-lg overflow-hidden shadow-lg">
            <img src={images[index]} alt={`Slide ${index + 1}`} className="w-full h-80 md:h-[420px] lg:h-[560px] object-cover" />

            {/* Arrows */}
            <button onClick={prev} className="absolute left-3 top-1/2 -translate-y-1/2 w-10 h-10 rounded-full bg-white flex items-center justify-center shadow-lg border border-gray-200 text-black z-10 hover:scale-105 transition-transform" aria-label="Anterior">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="w-5 h-5">
                    <path d="M15 18l-6-6 6-6" />
                </svg>
            </button>
            <button onClick={next} className="absolute right-3 top-1/2 -translate-y-1/2 w-10 h-10 rounded-full bg-white flex items-center justify-center shadow-lg border border-gray-200 text-black z-10 hover:scale-105 transition-transform" aria-label="Siguiente">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="w-5 h-5">
                    <path d="M9 18l6-6-6-6" />
                </svg>
            </button>

            {/* Indicadores */}
            <div className="absolute bottom-6 left-1/2 transform -translate-x-1/2 flex gap-2">
                {images.map((_, i) => (
                    <button
                        key={i}
                        onClick={() => setIndex(i)}
                        className={`w-2 h-2 rounded-full transition-all ${
                            i === index ? 'bg-gold w-8' : 'bg-white/50 hover:bg-white/80'
                        }`}
                        aria-label={`Ir a imagen ${i + 1}`}
                    />
                ))}
            </div>
        </div>
    );
}

export default function Services() {
    return (
        <>
            <Head title="Servicios - Chispas Frías" />

            <div className="min-h-screen flex flex-col">

                <Navbar />

            {/* Banner similar a la sección de productos */}
            <div
                className="pt-20 pb-10"
                style={{
                    backgroundImage: "url('/images/fondo-productos.png')",
                    backgroundSize: 'cover',
                    backgroundPosition: 'center',
                }}
            >
                <div className="max-w-7xl mx-auto px-6 lg:px-8">
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
                            <img src="/images/chispas-frias-logo.png" alt="Logo Chispas Frías" className="h-28 w-auto relative z-10" />
                        </div>

                        <div className="h-32 w-px bg-white ml-2 mr-1" />

                        <div className="flex flex-col text-left ml-2">
                            <h1
                                className="text-4xl lg:text-5xl font-bold text-chalk mb-3"
                                style={{ textShadow: '0 0 15px rgba(3,37,65,1), 0 0 8px rgba(3,37,65,1), 0 2px 10px rgba(3,37,65,0.9)'}}
                            >
                                Nuestros servicios.
                            </h1>
                            <p
                                className="text-xl text-chalk/80 max-w-2xl"
                                style={{ textShadow: '0 0 15px rgba(3,37,65,1), 0 0 8px rgba(3,37,65,1), 0 2px 10px rgba(3,37,65,0.9)'}}
                            >
                                ¿Querés llevar tu evento al siguiente nivel? Nosotros te ayudamos a lograrlo con nuestros servicios personalizados.
                            </p>
                        </div>
                    </div>

                    {/* Mobile */}
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
                            <img src="/images/chispas-frias-logo.png" alt="Logo Chispas Frías" className="h-32 w-auto relative z-10" />
                        </div>
                        <h1
                            className="text-3xl font-bold text-chalk mb-3"
                            style={{ textShadow: '0 0 15px rgba(3,37,65,1), 0 0 8px rgba(3,37,65,1), 0 2px 10px rgba(3,37,65,0.9)'}}
                        >
                            Conoce nuestros servicios
                        </h1>
                        <p
                            className="text-lg text-chalk/80 max-w-2xl"
                            style={{ textShadow: '0 0 15px rgba(3,37,65,1), 0 0 8px rgba(3,37,65,1), 0 2px 10px rgba(3,37,65,0.9)'}}
                        >
                            Aquí va un texto descriptivo de ejemplo para rellenar la sección de servicios.
                        </p>
                    </div>
                </div>
            </div>

                {/* Contenido principal */}
                <main className="flex-1 bg-chalk py-12">
                    <div className="max-w-7xl mx-auto px-6 lg:px-8">
                        <div className="grid grid-cols-1 lg:grid-cols-2 gap-8 items-center">
                            {/* Texto a la izquierda */}
                            <div>
                                <h2 className="text-5xl md:text-6xl lg:text-7xl font-extrabold text-navy mb-4 leading-tight">Servicio de chispas frias</h2>

                                <div className="prose prose-sm text-navy/90 max-w-none mb-6">
                                    <p>
                                        Una solución visual moderna, elegante y segura para todo tipo de eventos. Este efecto especial genera columnas de chispas controladas que aportan un impacto visual sorprendente, sin producir calor ni humo, lo que permite su uso tanto en eventos interiores como exteriores.
                                    </p>

                                    <p>
                                        Las chispas frías para eventos son ideales para quienes buscan un efecto tipo fuego artificial con total seguridad y una estética sofisticada. Su versatilidad las convierte en la opción perfecta para bodas, cumpleaños, recitales, desfiles, fiestas y celebraciones especiales, creando momentos únicos y memorables.
                                    </p>

                                    <p>
                                        Gracias a su tecnología segura y su presentación visual de alto nivel, este servicio es una excelente elección para quienes desean destacar su evento con un show elegante, moderno y completamente confiable.
                                    </p>
                                </div>

                                <div className="flex flex-col sm:flex-row gap-3">
                                    <a href="/servicios/chispas" className="inline-flex items-center justify-center px-6 py-3 bg-navy text-white font-semibold rounded-full hover:bg-navy/90 hover:scale-105 transition-all duration-300 shadow-lg hover:shadow-xl">
                                        Ver mas detalle
                                    </a>
                                    <a
                                        href="https://wa.me/5491131004505?text=Hola!%20Quisiera%20consultar%20por%20el%20servicio%20de%20chispas%20frias"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        className="inline-flex items-center justify-center px-6 py-3 bg-transparent text-navy border-2 border-navy font-semibold rounded-full hover:bg-navy/10 hover:scale-105 transition-all duration-300 shadow-lg hover:shadow-xl"
                                    >
                                        Consultar por el servicio
                                    </a>
                                </div>
                            </div>

                            {/* Carrusel a la derecha */}
                            <div>
                                <Carousel />
                            </div>
                        </div>
                    </div>
                </main>

                <Footer />

            </div>
        </>
    );
}
