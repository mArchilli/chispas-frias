import { Head } from '@inertiajs/react';
import Navbar from '@/Components/Navbar';
import Footer from '@/Components/Footer';

export default function ServiceChispas() {
    return (
        <>
            <Head title="Servicio de chispas frias - Detalle" />

            <div className="min-h-screen flex flex-col">
                <Navbar />

                {/* Banner */}
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
                                <h1 className="text-4xl lg:text-5xl font-bold text-chalk mb-3" style={{ textShadow: '0 0 15px rgba(3,37,65,1), 0 0 8px rgba(3,37,65,1), 0 2px 10px rgba(3,37,65,0.9)'}}>
                                    Conoce el servicio en profundidad
                                </h1>
                                <p className="text-xl text-chalk/80 max-w-2xl" style={{ textShadow: '0 0 15px rgba(3,37,65,1), 0 0 8px rgba(3,37,65,1), 0 2px 10px rgba(3,37,65,0.9)'}}>
                                    Mira lo que te ofrece Chispas frias para tu evento
                                </p>
                            </div>
                        </div>

                        {/* Mobile banner text */}
                        <div className="md:hidden pt-6 pb-6">
                            <h2 className="text-2xl font-bold text-chalk">Conoce el servicio en profundidad</h2>
                            <p className="text-md text-chalk/80">Mira lo que te ofrece Chispas frias para tu evento</p>
                        </div>
                    </div>
                </div>

                {/* Body */}
                <main className="flex-1 bg-chalk py-12">
                    <div className="max-w-7xl mx-auto px-6 lg:px-8">
                        <div className="bg-white rounded-2xl shadow-lg p-8 text-center">
                            <h2 className="text-2xl font-bold text-navy mb-4">En desarrollo</h2>
                            <p className="text-navy/80">Estamos trabajando en el contenido del detalle del servicio. Volvé pronto.</p>
                        </div>
                    </div>
                </main>

                <Footer />
            </div>
        </>
    );
}
