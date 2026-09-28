import { Head, Link } from '@inertiajs/react';
import { motion } from 'framer-motion';
import Navbar from '@/Components/Navbar';
import Footer from '@/Components/Footer';
import WhatsAppButton from '@/Components/WhatsAppButton';
import CartButton from '@/Components/CartButton';

export default function ServiceChispas({ auth }) {
    return (
        <>
            <Head title="Servicio de Chispas Frías para Eventos | Alquiler con Operadores Certificados">
                <meta name="description" content="Servicio profesional de chispas frías: alquiler de máquinas con operadores certificados para bodas, fiestas y eventos. Control remoto, sincronización con música y equipos profesionales." />
                <meta property="og:title" content="Servicio de Chispas Frías para Eventos" />
                <meta property="og:description" content="Alquiler de máquinas de chispas frías con operadores certificados. Solución moderna y segura para tu evento." />
                <meta property="og:image" content="/images/chispas-frias-logo.png" />
                <meta property="og:type" content="website" />
            </Head>

            <div className="storefront-background flex min-h-screen flex-col overflow-x-clip text-white">
                <Navbar auth={auth} />

                <header className="pb-10 pt-40 sm:pb-12 sm:pt-44">
                    <div className="site-shell">
                        <a
                            href="/#servicios"
                            className="mb-5 inline-flex items-center gap-2 text-sm font-semibold text-white underline-offset-4 hover:underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-white"
                        >
                            <span aria-hidden="true">←</span> Volver a servicios
                        </a>
                        <h1 className="uppercase max-w-4xl text-4xl font-bold leading-tight tracking-[-0.045em] text-white sm:text-5xl lg:text-6xl">
                            Servicio de chispas frías para eventos
                        </h1>
                        <p className="mt-4 max-w-3xl text-base leading-relaxed text-white sm:text-lg">
                            Mirá lo que te ofrece Chispas Frías para tu evento.
                        </p>
                    </div>
                </header>

                <main className="flex-1 pb-16 sm:pb-20">
                    <div className="site-shell">
                        {/* Bloque 1: Introducción - Texto izquierda, Video derecha */}
                        <div className="grid grid-cols-1 lg:grid-cols-2 gap-8 lg:gap-14 mb-16 lg:mb-20 items-center">
                            {/* Texto */}
                            <div>
                                <h2 className="uppercase text-3xl lg:text-4xl font-bold text-white mb-5">
                                    Solución moderna y segura
                                </h2>

                                <div className="space-y-4 text-white text-base leading-relaxed">
                                    <p>
                                        Es una solución visual moderna, segura y de alto impacto, ideal para realzar todo tipo de eventos. Este efecto especial genera columnas de chispas frías controladas, <strong className="text-white">sin calor ni humo</strong>, permitiendo su uso tanto en eventos interiores como exteriores.
                                    </p>

                                    <p>
                                        Gracias a su tecnología profesional de última generación, las chispas frías ofrecen un espectáculo visual elegante y completamente seguro, convirtiéndose en una alternativa innovadora a los fuegos tradicionales.
                                    </p>

                                    <p>
                                        Este servicio es perfecto para <strong className="text-white">bodas, eventos corporativos, fiestas empresariales, cumpleaños, recitales, presentaciones y desfiles</strong>. La sincronización precisa y el control total del efecto garantizan una puesta en escena impecable.
                                    </p>
                                </div>
                            </div>

                            {/* Video */}
                            <div className="relative">
                                <div className="overflow-hidden rounded-[1.75rem] border border-gray-200 bg-surface p-2 shadow-card">
                                    <motion.div
                                        className="overflow-hidden rounded-2xl"
                                        whileHover={{ scale: 1.02 }}
                                        transition={{ duration: 0.3 }}
                                    >
                                        <video autoPlay muted loop playsInline preload="auto" className="max-h-[520px] w-full rounded-2xl bg-navy-900 object-contain" aria-label="Video demostrativo del servicio de chispas frías" disablePictureInPicture controlsList="nodownload nofullscreen" onContextMenu={(e) => e.preventDefault()}>
                                            <source src="/videos/video-service-1.mp4" type="video/mp4" />
                                            Tu navegador no soporta la etiqueta de video.
                                        </video>
                                    </motion.div>
                                </div>
                            </div>
                        </div>

                        {/* Bloque 2: Tecnología - Imagen derecha, Texto izquierda */}
                        <div className="grid grid-cols-1 lg:grid-cols-2 gap-8 lg:gap-14 mb-16 lg:mb-20 items-center">
                            {/* Imagen (orden invertido en desktop) */}
                            <div className="relative order-2 lg:order-1">
                                <div className="overflow-hidden rounded-[1.75rem] border border-gray-200 bg-surface p-2 shadow-card">
                                    <motion.div
                                        className="overflow-hidden rounded-2xl"
                                        whileHover={{ scale: 1.02 }}
                                        transition={{ duration: 0.3 }}
                                    >
                                        <img src="/images/maquina-chispas.png" alt="Máquinas profesionales de chispas frías para eventos" className="w-full h-auto max-h-[500px] object-contain" />
                                    </motion.div>
                                </div>
                            </div>

                            {/* Texto */}
                            <div className="order-1 lg:order-2">
                                <h2 className="uppercase text-3xl lg:text-4xl font-bold text-white mb-5">
                                    Control y sincronización perfecta
                                </h2>

                                <div className="space-y-4 text-white text-base leading-relaxed">
                                    <p>
                                        Cada dispositivo cuenta con un <strong className="text-white">sistema electrónico de ignición inalámbrica</strong>, que permite controlar las máquinas de forma precisa desde una consola central o control remoto, garantizando seguridad y sincronización perfecta.
                                    </p>

                                    <p className="font-semibold text-white">El sistema es totalmente configurable:</p>

                                    <ul className="space-y-3 ml-4">
                                        <li className="flex items-start">
                                            <span className="text-white mr-3 mt-1 flex-shrink-0 text-xl">✦</span>
                                            <span><strong className="text-white">Configuración con 2, 4 u 8 máquinas</strong> según el tamaño del evento</span>
                                        </li>
                                        <li className="flex items-start">
                                            <span className="text-white mr-3 mt-1 flex-shrink-0 text-xl">✦</span>
                                            <span><strong className="text-white">Activación simultánea o secuencial</strong> para crear efectos sincronizados con música</span>
                                        </li>
                                        <li className="flex items-start">
                                            <span className="text-white mr-3 mt-1 flex-shrink-0 text-xl">✦</span>
                                            <span><strong className="text-white">Altura hasta 5 metros</strong> con intensidad regulable</span>
                                        </li>
                                    </ul>

                                    <p>
                                        Las chispas frías funcionan <strong className="text-white">sin fuego real, sin humo y sin olor</strong>, cumpliendo con los estándares de seguridad para eventos.
                                    </p>
                                </div>
                            </div>
                        </div>

                        {/* Bloque 3: Equipos profesionales - Contenedor sutil con 2 columnas */}
                        <div className="rounded-[1.75rem] border border-gray-200 bg-surface p-6 text-navy-900 shadow-card sm:p-8 lg:p-10">
                            <div className="mb-8">
                                <h2 className="uppercase text-3xl lg:text-4xl font-bold text-navy-900 mb-4">
                                    Equipos profesionales certificados
                                </h2>

                            </div>

                            <div className="grid grid-cols-1 lg:grid-cols-2 gap-8 lg:gap-12">
                                <div className="space-y-4 text-navy-900/80 text-base leading-relaxed">
                                    <p>
                                        Nuestros equipos de chispas frías de última tecnología generan un <strong className="text-navy-900">efecto visual intenso y elegante</strong>, sin utilizar fuego real, sin producir humo y sin dejar residuos. Esto los convierte en una opción segura y limpia para todo tipo de eventos.
                                    </p>

                                    <p>
                                        Las máquinas son <strong className="text-navy-900">100% inalámbricas y programables</strong>, lo que permite sincronizar las chispas con música, iluminación o momentos clave del evento, logrando una puesta en escena impactante y perfectamente coordinada.
                                    </p>
                                </div>

                                <div className="space-y-4 text-navy-900/80 text-base leading-relaxed">
                                    <p>
                                        El servicio es operado por <strong className="text-navy-900">técnicos especializados en efectos especiales</strong>, asegurando un funcionamiento preciso, seguro y confiable. Todos los equipos se encuentran certificados y sometidos a mantenimiento permanente.
                                    </p>

                                    <p>
                                        Ideales para bodas, shows en vivo, lanzamientos de marca, fiestas empresariales y grandes celebraciones, las chispas frías aportan innovación, sofisticación y un <strong className="text-navy-900">impacto visual memorable</strong>.
                                    </p>
                                </div>
                            </div>

                            {/* Volver al inicio */}
                            <div className="mt-10 flex justify-center">
                                <Link
                                    href="/"
                                    className="inline-flex min-h-12 w-full items-center justify-center rounded-full border border-storefront px-6 py-3 text-sm font-semibold text-storefront transition hover:bg-storefront hover:text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-storefront focus-visible:ring-offset-2 sm:w-fit"
                                >
                                    
                                    Volver al Inicio
                                </Link>
                            </div>
                        </div>
                    </div>
                </main>

                <Footer />
                <CartButton />
                <WhatsAppButton />
            </div>
        </>
    );
}
