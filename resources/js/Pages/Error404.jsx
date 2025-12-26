import { Link } from '@inertiajs/react';
import { useState, useEffect } from 'react';
import Footer from '@/Components/Footer';

export default function Error404() {
    const [sparkles, setSparkles] = useState([]);

    // Generar partículas de chispas animadas
    useEffect(() => {
        const generateSparkles = () => {
            const newSparkles = [];
            for (let i = 0; i < 20; i++) {
                newSparkles.push({
                    id: i,
                    left: Math.random() * 100,
                    animationDelay: Math.random() * 3,
                    duration: 2 + Math.random() * 2
                });
            }
            setSparkles(newSparkles);
        };

        generateSparkles();
        const interval = setInterval(generateSparkles, 4000);
        return () => clearInterval(interval);
    }, []);

    return (
        <>
            {/* Hero Section */}
            <div className="min-h-screen bg-gradient-to-br from-navy via-navy/95 to-navy/90 flex items-center justify-center relative overflow-hidden">
                {/* Gradientes estáticos decorativos blancos y dorados */}
                <div className="absolute inset-0 bg-[radial-gradient(circle_at_20%_30%,rgba(255,255,255,0.1),transparent_50%)]"></div>
                <div className="absolute inset-0 bg-[radial-gradient(circle_at_80%_70%,rgba(212,175,55,0.1),transparent_50%)]"></div>
                <div className="absolute inset-0 bg-[radial-gradient(circle_at_50%_50%,rgba(255,255,255,0.05),transparent_70%)]"></div>

                <div className="max-w-4xl mx-auto px-6 lg:px-8 text-center relative z-10">
                    {/* Carita triste */}
                    <div className="mb-6">
                        <svg className="w-24 h-24 md:w-32 md:h-32 mx-auto text-white" fill="currentColor" viewBox="0 0 24 24">
                            {/* Cara */}
                            <circle cx="12" cy="12" r="10" fill="none" stroke="currentColor" strokeWidth="1.5" opacity="0.8"/>
                            {/* Ojos */}
                            <circle cx="9" cy="9" r="1" opacity="0.9"/>
                            <circle cx="15" cy="9" r="1" opacity="0.9"/>
                            {/* Boca triste */}
                            <path d="M8 16 Q12 13 16 16" fill="none" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" opacity="0.9"/>
                        </svg>
                    </div>
                    
                    {/* Texto ERROR 404 */}
                    <div className="mb-8">
                        <h1 className="text-6xl md:text-7xl lg:text-8xl font-black text-gold drop-shadow-2xl leading-none">
                            ERROR 404
                        </h1>
                        {/* Línea decorativa divisoria */}
                        <div className="w-32 h-1 bg-gradient-to-r from-transparent via-gold to-transparent mx-auto mt-4"></div>
                    </div>

                    {/* Mensaje principal */}
                    <div className="mb-8">
                        <h2 className="text-3xl md:text-4xl lg:text-5xl font-bold text-chalk mb-4 drop-shadow-lg">
                            ¡Ups! Esta página se esfumó
                        </h2>
                        <p className="text-lg md:text-xl text-chalk mb-2 drop-shadow">
                            Como una chispa fría que brilla y desaparece...
                        </p>
                        <p className="text-base md:text-lg text-chalk/80 drop-shadow">
                            La página que buscás no existe o fue movida a otro lugar.
                        </p>
                    </div>

                    {/* Botones de acción */}
                    <div className="flex flex-col sm:flex-row gap-4 justify-center mb-12">
                        <Link 
                            href="/"
                            className="inline-flex items-center justify-center px-8 py-4 bg-gold text-white font-bold rounded-full hover:bg-gold/90 hover:scale-105 transition-all duration-300 shadow-2xl hover:shadow-[0_20px_50px_rgba(0,0,0,0.3)]"
                        >
                            <svg className="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                            </svg>
                            Volver al inicio
                        </Link>
                        
                        <Link 
                            href="/productos"
                            className="inline-flex items-center justify-center px-8 py-4 bg-transparent text-chalk border-2 border-chalk font-bold rounded-full hover:bg-chalk/10 hover:scale-105 transition-all duration-300 shadow-lg hover:shadow-xl"
                        >
                            <svg className="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                            </svg>
                            Ver productos
                        </Link>
                    </div>

                    {/* Sugerencias */}
                    <div className="bg-white/10 backdrop-blur-md rounded-3xl p-6 md:p-8 border border-white/20 hover:bg-white/15 transition-all duration-500">
                        <h3 className="text-xl md:text-2xl font-bold text-chalk mb-6">
                            ¿Necesitás ayuda?
                        </h3>
                        <div className="flex justify-center">
                            <a 
                                href="https://wa.me/5491131004505?text=Hola!%20Necesito%20ayuda%20para%20encontrar%20algo"
                                target="_blank"
                                rel="noopener noreferrer"
                                className="inline-flex items-center text-gold hover:text-gold/80 hover:scale-105 transition-all duration-300 font-semibold text-lg"
                            >
                                Hablemos por WhatsApp
                                <svg className="w-5 h-5 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M17 8l4 4m0 0l-4 4m4-4H3" />
                                </svg>
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <Footer />
        </>
    );
}