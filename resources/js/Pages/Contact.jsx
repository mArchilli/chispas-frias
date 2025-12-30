import { Head } from '@inertiajs/react';
import Navbar from '@/Components/Navbar';
import Footer from '@/Components/Footer';
import WhatsAppButton from '@/Components/WhatsAppButton';

export default function Contact({ auth }) {
    // Componente interno: carrusel simple de los 3 svgs usados en 'Por qué elegirnos'
    
    return (
        <>
            <Head title="Contacto - Chispas Frías" />
            <Navbar auth={auth} />

            <main
                className="min-h-screen pt-16 md:pt-20 lg:pt-24 pb-16 relative overflow-hidden"
                style={{
                    backgroundImage: "url('/images/fondo-productos.png')",
                    backgroundSize: 'cover',
                    backgroundPosition: 'center',
                }}
            >
                {/* Overlay difuminado sobre la imagen de fondo */}
                <div className="absolute inset-0 bg-black/30 backdrop-blur-md pointer-events-none z-0" />
                {/* Gradientes decorativos */}
                <div className="absolute inset-0 bg-[radial-gradient(circle_at_20%_30%,rgba(255,255,255,0.1),transparent_50%)] pointer-events-none"></div>
                <div className="absolute inset-0 bg-[radial-gradient(circle_at_80%_70%,rgba(212,175,55,0.1),transparent_50%)] pointer-events-none"></div>
                <div className="absolute inset-0 bg-[radial-gradient(circle_at_50%_50%,rgba(255,255,255,0.05),transparent_70%)] pointer-events-none"></div>
                <div className="max-w-7xl mx-auto px-6 lg:px-8 relative z-10">
                    {/* Hero Section */}
                    <div className="flex flex-col md:flex-row items-start md:items-center gap-8 mb-12">
                        {/* Logo */}
                        <div className="flex-shrink-0">
                            <div className="rounded-2xl overflow-hidden transform transition-transform duration-300 hover:scale-105 cursor-pointer">
                                <img 
                                    src="/images/chispas-frias-logo.png" 
                                    alt="Chispas Frías" 
                                    className="h-24 md:h-32 w-auto block"
                                />
                            </div>
                        </div>

                        {/* Separador */}
                        <div className="hidden md:block w-px h-32 bg-white/80"></div>
                        <div className="md:hidden w-20 h-px bg-white/80"></div>

                        {/* Texto */}
                        <div className="flex-1">
                            <h1 className="text-4xl md:text-5xl lg:text-6xl font-bold text-white mb-4">
                                Estamos acá, para ayudarte.
                            </h1>
                            <p className="text-xl md:text-2xl text-white/90 leading-relaxed">
                                ¿Tenés dudas sobre nuestros productos? ¿Querés cotizar para tu evento? 
                                <span className="text-gold font-semibold"> Escribinos</span> y te respondemos al instante.
                            </p>
                        </div>
                    </div>

                    {/* Contact Cards */}
                    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 md:gap-6 lg:gap-8 mb-12">
                        {/* WhatsApp Card (compact) */}
                        <a
                            href="https://wa.me/5491131004505"
                            target="_blank"
                            rel="noopener noreferrer"
                               className="bg-gradient-to-br from-navy via-white/10 to-gold/20 border-2 border-navy/70 rounded-3xl p-6 shadow-md hover:border-gold hover:shadow-xl hover:shadow-gold/10 hover:scale-[1.02] transition-all duration-300 group"
                        >
                            <div className="flex flex-col items-center text-center">
                                <div className="w-16 h-16 bg-white shadow-lg rounded-full flex items-center justify-center mb-4 group-hover:scale-105 transition-transform duration-500">
                                    <svg className="w-8 h-8 text-gold" fill="currentColor" viewBox="0 0 24 24">
                                        <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/>
                                    </svg>
                                </div>
                                <h3 className="text-lg font-bold text-gold mb-1">WhatsApp</h3>
                                <p className="text-white/90 text-sm mb-3">
                                    Respuesta inmediata para tus consultas
                                </p>
                                <p className="text-white font-bold text-base">
                                    +54 9 11 3100-4505
                                </p>
                                <span className="mt-4 text-gold text-sm font-bold uppercase tracking-wider group-hover:translate-x-2 transition-transform duration-300">
                                    Chatea ahora →
                                </span>
                            </div>
                        </a>

                        {/* Email Card (compact) */}
                        <a
                            href="mailto:chispasfrias.oficial@gmail.com"
                               className="bg-gradient-to-br from-navy via-white/10 to-gold/20 border-2 border-navy/70 rounded-3xl p-6 shadow-md hover:border-gold hover:shadow-xl hover:shadow-gold/10 hover:scale-[1.02] transition-all duration-300 group"
                        >
                            <div className="flex flex-col items-center text-center">
                                <div className="w-16 h-16 bg-white shadow-lg rounded-full flex items-center justify-center mb-4 group-hover:scale-105 transition-transform duration-500">
                                    <svg className="w-8 h-8 text-gold" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                    </svg>
                                </div>
                                <h3 className="text-lg font-bold text-gold mb-1">Email</h3>
                                <p className="text-white/90 text-sm mb-3">
                                    Consultas detalladas y cotizaciones
                                </p>
                                <p className="text-white font-bold break-all text-base">
                                    chispasfrias.oficial@gmail.com
                                </p>
                                <span className="mt-4 text-gold text-sm font-bold uppercase tracking-wider group-hover:translate-x-2 transition-transform duration-300">
                                    Enviar email →
                                </span>
                            </div>
                        </a>

                        {/* Instagram Card (compact) */}
                        <a
                            href="https://instagram.com/chispasfrias.oficial"
                            target="_blank"
                            rel="noopener noreferrer"
                               className="bg-gradient-to-br from-navy via-white/10 to-gold/20 border-2 border-navy/70 rounded-3xl p-6 shadow-md hover:border-gold hover:shadow-xl hover:shadow-gold/10 hover:scale-[1.02] transition-all duration-300 group"
                        >
                            <div className="flex flex-col items-center text-center">
                                <div className="w-16 h-16 bg-white shadow-lg rounded-full flex items-center justify-center mb-4 group-hover:scale-105 transition-transform duration-500">
                                    <svg className="w-8 h-8 text-gold" fill="currentColor" viewBox="0 0 24 24">
                                        <path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/>
                                    </svg>
                                </div>
                                <h3 className="text-lg font-bold text-gold mb-1">Instagram</h3>
                                <p className="text-white/90 text-sm mb-3">
                                    Seguinos y mirá nuestros trabajos
                                </p>
                                <p className="text-white font-bold text-base">
                                    @chispasfrias.oficial
                                </p>
                                <span className="mt-4 text-gold text-sm font-bold uppercase tracking-wider group-hover:translate-x-2 transition-transform duration-300">
                                    Visitar perfil →
                                </span>
                            </div>
                        </a>
                    </div>

                    {/* Additional Info Section removed: keeping only social cards above */}

                    {/* CTA Section */}
                    <div className="mt-24 text-center">
                        <h3 className="text-3xl md:text-4xl font-bold text-white mb-6">
                            ¿Listo para hacer brillar tu evento?
                        </h3>
                        <p className="text-white/90 mb-10 max-w-2xl mx-auto text-lg">
                            No esperes más. Creemos momentos inolvidables.
                        </p>
                        <a
                            href="https://wa.me/5491131004505"
                            target="_blank"
                            rel="noopener noreferrer"
                            className="inline-flex items-center gap-3 bg-gold text-white px-10 py-5 rounded-full text-lg font-bold shadow-xl transition-all duration-300 hover:bg-gold/90 hover:scale-105 hover:shadow-2xl"
                        >
                            <svg className="w-6 h-6 text-white" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/>
                            </svg>
                            Consultar por WhatsApp
                        </a>
                    </div>
                </div>
            </main>

            <Footer />
            <WhatsAppButton />
        </>
    );
}
