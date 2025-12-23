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
                {/* Espacio para futuras secciones */}
            </main>
            
            {/* Footer */}
            <Footer />
        </>
    );
}
