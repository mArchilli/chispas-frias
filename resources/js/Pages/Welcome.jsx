import { Head, Link } from '@inertiajs/react';
import Footer from '@/Components/Footer';
import Navbar from '@/Components/Navbar';

export default function Welcome({ auth }) {
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
                        <div className="flex flex-col items-start">
                            {/* Logo */}
                            <img 
                                src="/images/chispas-frias-logo.png" 
                                alt="Chispas Frías Logo" 
                                className="w-48 md:w-64 lg:w-80 mb-8 drop-shadow-2xl"
                            />
                            
                            {/* Texto Principal */}
                            <h1 className="text-chalk text-2xl md:text-3xl lg:text-4xl font-bold text-left mb-4 max-w-3xl drop-shadow-lg">
                                Pirotecnia fría que eleva tu evento
                            </h1>
                            
                            {/* Texto Secundario */}
                            <p className="text-chalk text-base md:text-lg lg:text-xl text-left mb-10 max-w-2xl drop-shadow-lg">
                                Lideramos el mercado de chispas y pirotecnia, ofreciendo tecnología segura, moderna y confiable para experiencias inolvidables.
                            </p>
                            
                            {/* Botones */}
                            <div className="flex flex-col sm:flex-row gap-4">
                                <button className="px-8 py-4 bg-gold text-navy font-semibold rounded-lg hover:bg-gold/90 transition shadow-lg hover:shadow-xl">
                                    Conoce nuestros productos
                                </button>
                                <button className="px-8 py-4 bg-chalk text-navy font-semibold rounded-lg hover:bg-chalk/90 transition shadow-lg hover:shadow-xl">
                                    Contacta con nosotros
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            {/* Secciones adicionales irán aquí */}
            <main className="bg-chalk">
                {/* Espacio para futuras secciones */}
            </main>
            
            {/* Footer */}
            <Footer />
        </>
    );
}
