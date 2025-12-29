import { Head } from '@inertiajs/react';
import Navbar from '@/Components/Navbar';
import Footer from '@/Components/Footer';

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
                                Servicios
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

                {/* Contenido principal (vacío por ahora) */}
                <main className="flex-1 bg-chalk py-12">
                    <div className="max-w-7xl mx-auto px-6 lg:px-8 text-center">
                        <h2 className="text-2xl font-semibold text-navy mb-4">En desarrollo...</h2>
                    </div>
                </main>

                <Footer />

            </div>
        </>
    );
}
