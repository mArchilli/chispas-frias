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
                                    Conoce el servicio en profundidad.
                                </h1>
                                <p className="text-xl text-chalk/80 max-w-2xl" style={{ textShadow: '0 0 15px rgba(3,37,65,1), 0 0 8px rgba(3,37,65,1), 0 2px 10px rgba(3,37,65,0.9)'}}>
                                    Mira lo que te ofrece Chispas frias para tu evento.
                                </p>
                            </div>
                        </div>

                        {/* Mobile banner text */}
                        <div className="md:hidden pt-6 pb-6">
                            <h2 className="text-2xl font-bold text-chalk">Conoce el servicio en profundidad.</h2>
                            <p className="text-md text-chalk/80">Mira lo que te ofrece Chispas frias para tu evento.</p>
                        </div>
                    </div>
                </div>

                {/* Body */}
                <main className="flex-1 bg-chalk py-12">
                    <div className="max-w-7xl mx-auto px-6 lg:px-8">
                        <h2 className="text-4xl md:text-5xl lg:text-6xl font-extrabold text-navy mb-8">El servicio de chispas frias.</h2>
                        <div className="grid grid-cols-1 lg:grid-cols-2 gap-8 items-center">
                            {/* Texto a la izquierda */}
                            <div className="flex items-center">
                                <div className="prose lg:prose-lg text-navy/90 max-w-none">
                                    <p>
                                        Es una solución visual moderna, segura y de alto impacto, ideal para realzar todo tipo de eventos. Este efecto especial genera columnas de chispas frías controladas, sin calor ni humo, permitiendo su uso tanto en eventos interiores como exteriores.
                                    </p>

                                    <p>
                                        Gracias a su tecnología profesional de última generación, las chispas frías ofrecen un espectáculo visual elegante y completamente seguro, convirtiéndose en una alternativa innovadora a los fuegos tradicionales. Su funcionamiento confiable permite crear momentos impactantes sin riesgos, incluso en espacios cerrados o cerca del público.
                                    </p>

                                    <p>
                                        Este servicio es perfecto para bodas, eventos corporativos, fiestas empresariales, cumpleaños, recitales, presentaciones y desfiles. La sincronización precisa y el control total del efecto garantizan una puesta en escena impecable, aportando un diferencial visual que eleva la experiencia del evento y deja una impresión memorable en los invitados.
                                    </p>
                                </div>
                            </div>

                            {/* Video a la derecha */}
                            <div className="flex items-center justify-center">
                                <div className="w-full overflow-hidden rounded-2xl shadow-lg hover:shadow-xl hover:scale-[1.02] transition-all duration-300">
                                    <video autoPlay muted loop playsInline preload="auto" className="w-full rounded-2xl" disablePictureInPicture controlsList="nodownload nofullscreen" onContextMenu={(e) => e.preventDefault()}>
                                        <source src="/videos/video-service-1.mp4" type="video/mp4" />
                                        Tu navegador no soporta la etiqueta de video.
                                    </video>
                                </div>
                            </div>
                        </div>
                        {/* Detalles técnicos (sin card) */}
                        <div className="mt-12">
                            <div className="grid grid-cols-1 lg:grid-cols-2 gap-6 items-stretch">
                                <div className="flex items-center justify-center overflow-hidden rounded-xl">
                                    <img src="/images/maquina-chispas.png" alt="Máquinas de chispas" className="h-full max-h-[420px] md:max-h-[520px] w-auto max-w-full rounded-xl shadow-md object-contain hover:shadow-xl hover:scale-[1.02] transition-all duration-300" />
                                </div>

                                <div className="prose lg:prose-lg text-navy/90">
                                    <h3 className="text-2xl font-bold text-navy">Tecnología y control del servicio de Chispas Frías.</h3>

                                    <p>
                                        Cada dispositivo del Servicio de Chispas Frías para eventos cuenta con un sistema electrónico de ignición inalámbrica, que permite controlar las máquinas de forma precisa desde una consola central o control remoto, garantizando seguridad y sincronización perfecta durante el espectáculo.
                                    </p>

                                    <p>
                                        El sistema es totalmente configurable y se adapta a distintos tipos de eventos y puestas en escena:
                                    </p>

                                    <p>
                                        <strong>Configuración con 2, 4 u 8 máquinas</strong> de chispas frías, según el tamaño del evento y el efecto visual buscado.
                                    </p>

                                    <p>
                                        <strong>Activación simultánea o secuencial</strong>, ideal para crear recorridos de chispas, entradas impactantes o efectos sincronizados con música.
                                    </p>

                                    <p>
                                        <strong>Altura e intensidad regulables</strong>, alcanzando hasta 5 metros de altura, para lograr el impacto visual exacto que el evento necesita.
                                    </p>

                                    <p>
                                        Las chispas frías funcionan sin fuego real, sin humo y sin olor, lo que las convierte en una solución segura para eventos en interiores y exteriores, incluso en zonas cercanas al público o a la pista de baile, cumpliendo con los estándares de seguridad para eventos.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* Texto final full-width debajo de todo el contenido */}
                    <section className="w-full py-12">
                        <div className="max-w-7xl mx-auto px-6 lg:px-8">
                            <div className="prose lg:prose-lg text-navy/90">
                                <h3 className="text-2xl font-bold text-navy">Equipos profesionales de Chispas Frías para eventos.</h3>

                                <p>
                                    Nuestros equipos de chispas frías de última tecnología generan un efecto visual intenso y elegante, sin utilizar fuego real, sin producir humo y sin dejar residuos. Esto los convierte en una opción segura y limpia para todo tipo de eventos, tanto en interiores como en exteriores.
                                </p>

                                <p>
                                    Las máquinas son 100% inalámbricas y programables, lo que permite sincronizar las chispas con música, iluminación o momentos clave del evento, logrando una puesta en escena impactante y perfectamente coordinada.
                                </p>

                                <p>
                                    El servicio es operado por técnicos especializados en efectos especiales, asegurando un funcionamiento preciso, seguro y confiable. Todos los equipos se encuentran certificados y sometidos a mantenimiento permanente, garantizando un espectáculo sin imprevistos.
                                </p>

                                <p>
                                    Ideales para bodas, shows en vivo, lanzamientos de marca, fiestas empresariales y grandes celebraciones, las chispas frías aportan innovación, sofisticación y un impacto visual memorable. Confiá en profesionales del rubro y hacé que tu evento se destaque con un efecto que sorprende y emociona.
                                </p>
                                <div className="mt-6">
                                    <a
                                        href="https://wa.me/5491131004505?text=Hola!%20Quisiera%20consultar%20por%20el%20servicio%20de%20chispas%20frias"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        className="w-full inline-flex items-center justify-center py-4 font-bold rounded-full no-underline hover:no-underline focus:no-underline active:no-underline bg-green-600 text-white hover:bg-green-700 hover:scale-105 transform-gpu will-change-transform transition-transform transition-shadow transition-colors duration-300 ease-[cubic-bezier(0.4,0,0.2,1)] shadow-lg hover:shadow-2xl"
                                        style={{ textDecoration: 'none' }}
                                    >
                                        <svg className="mr-2 h-5 w-5" fill="currentColor" viewBox="0 0 24 24">
                                            <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893A11.821 11.821 0 0020.885 3.488"/>
                                        </svg>
                                        Consultar por servicio en WhatsApp
                                    </a>
                                </div>
                            </div>
                        </div>
                    </section>
                </main>

                <Footer />
            </div>
        </>
    );
}
