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
            <img src={images[index]} alt={`Slide ${index + 1}`} className="w-full h-64 object-cover" />

            {/* Arrows */}
            <button onClick={prev} className="absolute left-2 top-1/2 -translate-y-1/2 bg-white/60 hover:bg-white p-2 rounded-full shadow">
                ‹
            </button>
            <button onClick={next} className="absolute right-2 top-1/2 -translate-y-1/2 bg-white/60 hover:bg-white p-2 rounded-full shadow">
                ›
            </button>

            {/* Indicators */}
            <div className="absolute bottom-3 left-1/2 -translate-x-1/2 flex space-x-2">
                {images.map((_, i) => (
                    <button key={i} onClick={() => setIndex(i)} className={`w-2 h-2 rounded-full ${i === index ? 'bg-gold' : 'bg-white/60'}`} />
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
                                <h2 className="text-3xl font-bold text-navy mb-2">Chispas frias</h2>
                                <h3 className="text-lg text-navy/80 mb-4">Servicio de Chispas Frías para eventos</h3>

                                <div className="prose prose-sm text-navy/90 max-w-none mb-6">
                                    <p>
                                        Realzá tu celebración con nuestro Servicio de Chispas Frías, una solución visual moderna, elegante y segura para todo tipo de eventos. Este efecto especial genera columnas de chispas controladas que aportan un impacto visual sorprendente, sin producir calor ni humo, lo que permite su uso tanto en eventos interiores como exteriores.
                                    </p>

                                    <p>
                                        Las chispas frías para eventos son ideales para quienes buscan un efecto tipo fuego artificial con total seguridad y una estética sofisticada. Su versatilidad las convierte en la opción perfecta para bodas, cumpleaños, recitales, desfiles, fiestas y celebraciones especiales, creando momentos únicos y memorables.
                                    </p>

                                    <p>
                                        Gracias a su tecnología segura y su presentación visual de alto nivel, este servicio es una excelente elección para quienes desean destacar su evento con un show elegante, moderno y completamente confiable.
                                    </p>
                                </div>

                                <div className="flex flex-col sm:flex-row gap-3">
                                    <a href="#" className="inline-flex items-center px-6 py-3 bg-gold text-navy font-semibold rounded-lg hover:bg-gold/90 transition">
                                        Ver mas detalle
                                    </a>
                                    <a href="#" className="inline-flex items-center px-6 py-3 border-2 border-navy text-navy font-semibold rounded-lg hover:bg-navy/5 transition">
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
