import { Head } from '@inertiajs/react';
import { motion } from 'framer-motion';
import Navbar from '@/Components/Navbar';
import Footer from '@/Components/Footer';
import WhatsAppButton from '@/Components/WhatsAppButton';
import CartButton from '@/Components/CartButton';

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
                <main className="flex-1 relative overflow-hidden">
                    {/* Fondo premium unificado */}
                    <div className="absolute inset-0 bg-gradient-to-b from-chalk via-white to-chalk">
                        <div className="absolute top-20 left-1/4 w-[600px] h-[600px] rounded-full blur-3xl" style={{
                            background: 'radial-gradient(circle, rgba(10, 31, 68, 0.05) 0%, rgba(10, 31, 68, 0.02) 50%, transparent 100%)'
                        }}></div>
                        <div className="absolute top-1/2 right-1/4 w-[500px] h-[500px] rounded-full blur-3xl" style={{
                            background: 'radial-gradient(circle, rgba(201, 169, 97, 0.07) 0%, rgba(201, 169, 97, 0.03) 50%, transparent 100%)'
                        }}></div>
                        <div className="absolute bottom-20 left-1/3 w-[550px] h-[550px] rounded-full blur-3xl" style={{
                            background: 'radial-gradient(circle, rgba(10, 31, 68, 0.04) 0%, rgba(10, 31, 68, 0.02) 50%, transparent 100%)'
                        }}></div>
                        <div className="absolute inset-0 backdrop-blur-[120px] bg-white/25"></div>
                    </div>

                    <div className="max-w-7xl mx-auto px-6 lg:px-8 py-16 lg:py-24 relative z-10">
                        {/* Título principal */}
                        <motion.div
                            className="text-center mb-20"
                            initial={{ opacity: 0, y: 20 }}
                            whileInView={{ opacity: 1, y: 0 }}
                            viewport={{ once: true }}
                            transition={{ duration: 0.6 }}
                        >
                            <h2 className="text-4xl md:text-5xl lg:text-6xl font-bold text-navy mb-4">
                                El servicio de <span className="text-gold">chispas frías</span>
                            </h2>
                            <div className="w-24 h-1 bg-gradient-to-r from-gold to-gold/40 mx-auto rounded-full"></div>
                        </motion.div>

                        {/* Bloque 1: Introducción - Texto izquierda, Video derecha */}
                        <div className="grid grid-cols-1 lg:grid-cols-2 gap-12 lg:gap-16 mb-24 items-center">
                            {/* Texto */}
                            <motion.div
                                initial={{ opacity: 0, x: -30 }}
                                whileInView={{ opacity: 1, x: 0 }}
                                viewport={{ once: true }}
                                transition={{ duration: 0.6 }}
                            >
                                <div className="inline-block px-4 py-2 bg-gold/10 rounded-full mb-6">
                                    <span className="text-gold/90 text-sm font-semibold tracking-wide uppercase">Espectáculo Visual</span>
                                </div>
                                
                                <h3 className="text-3xl lg:text-4xl font-bold text-navy mb-6">
                                    Solución moderna y segura
                                </h3>

                                <div className="w-20 h-1 bg-gradient-to-r from-gold to-gold/40 mb-6 rounded-full"></div>

                                <div className="space-y-5 text-navy/80 text-base lg:text-lg leading-relaxed">
                                    <p>
                                        Es una solución visual moderna, segura y de alto impacto, ideal para realzar todo tipo de eventos. Este efecto especial genera columnas de chispas frías controladas, <strong className="text-navy">sin calor ni humo</strong>, permitiendo su uso tanto en eventos interiores como exteriores.
                                    </p>

                                    <p>
                                        Gracias a su tecnología profesional de última generación, las chispas frías ofrecen un espectáculo visual elegante y completamente seguro, convirtiéndose en una alternativa innovadora a los fuegos tradicionales.
                                    </p>

                                    <p>
                                        Este servicio es perfecto para <strong className="text-navy">bodas, eventos corporativos, fiestas empresariales, cumpleaños, recitales, presentaciones y desfiles</strong>. La sincronización precisa y el control total del efecto garantizan una puesta en escena impecable.
                                    </p>
                                </div>
                            </motion.div>

                            {/* Video */}
                            <motion.div
                                className="relative"
                                initial={{ opacity: 0, x: 30 }}
                                whileInView={{ opacity: 1, x: 0 }}
                                viewport={{ once: true }}
                                transition={{ duration: 0.6, delay: 0.2 }}
                            >
                                <div className="overflow-hidden rounded-3xl shadow-2xl border-2 border-gold/30 bg-white/40 backdrop-blur-sm p-2">
                                    <motion.div
                                        className="overflow-hidden rounded-2xl"
                                        whileHover={{ scale: 1.02 }}
                                        transition={{ duration: 0.3 }}
                                    >
                                        <video autoPlay muted loop playsInline preload="auto" className="w-full rounded-2xl" disablePictureInPicture controlsList="nodownload nofullscreen" onContextMenu={(e) => e.preventDefault()}>
                                            <source src="/videos/video-service-1.mp4" type="video/mp4" />
                                            Tu navegador no soporta la etiqueta de video.
                                        </video>
                                    </motion.div>
                                </div>
                            </motion.div>
                        </div>

                        {/* Bloque 2: Tecnología - Imagen derecha, Texto izquierda */}
                        <div className="grid grid-cols-1 lg:grid-cols-2 gap-12 lg:gap-16 mb-24 items-center">
                            {/* Imagen (orden invertido en desktop) */}
                            <motion.div
                                className="relative order-2 lg:order-1"
                                initial={{ opacity: 0, x: -30 }}
                                whileInView={{ opacity: 1, x: 0 }}
                                viewport={{ once: true }}
                                transition={{ duration: 0.6, delay: 0.2 }}
                            >
                                <div className="overflow-hidden rounded-3xl shadow-2xl border-2 border-navy/30 bg-white/40 backdrop-blur-sm p-2">
                                    <motion.div
                                        className="overflow-hidden rounded-2xl"
                                        whileHover={{ scale: 1.02 }}
                                        transition={{ duration: 0.3 }}
                                    >
                                        <img src="/images/maquina-chispas.png" alt="Máquinas de chispas" className="w-full h-auto max-h-[500px] object-contain" />
                                    </motion.div>
                                </div>
                            </motion.div>

                            {/* Texto */}
                            <motion.div
                                className="order-1 lg:order-2"
                                initial={{ opacity: 0, x: 30 }}
                                whileInView={{ opacity: 1, x: 0 }}
                                viewport={{ once: true }}
                                transition={{ duration: 0.6 }}
                            >
                                <div className="inline-block px-4 py-2 bg-navy/10 rounded-full mb-6">
                                    <span className="text-navy/90 text-sm font-semibold tracking-wide uppercase">Tecnología Avanzada</span>
                                </div>
                                
                                <h3 className="text-3xl lg:text-4xl font-bold text-navy mb-6">
                                    Control y sincronización perfecta
                                </h3>

                                <div className="w-20 h-1 bg-gradient-to-r from-navy to-navy/40 mb-6 rounded-full"></div>

                                <div className="space-y-5 text-navy/80 text-base lg:text-lg leading-relaxed">
                                    <p>
                                        Cada dispositivo cuenta con un <strong className="text-navy">sistema electrónico de ignición inalámbrica</strong>, que permite controlar las máquinas de forma precisa desde una consola central o control remoto, garantizando seguridad y sincronización perfecta.
                                    </p>

                                    <p className="font-semibold text-navy">El sistema es totalmente configurable:</p>

                                    <ul className="space-y-3 ml-4">
                                        <li className="flex items-start">
                                            <span className="text-gold mr-3 mt-1 flex-shrink-0 text-xl">✦</span>
                                            <span><strong className="text-navy">Configuración con 2, 4 u 8 máquinas</strong> según el tamaño del evento</span>
                                        </li>
                                        <li className="flex items-start">
                                            <span className="text-gold mr-3 mt-1 flex-shrink-0 text-xl">✦</span>
                                            <span><strong className="text-navy">Activación simultánea o secuencial</strong> para crear efectos sincronizados con música</span>
                                        </li>
                                        <li className="flex items-start">
                                            <span className="text-gold mr-3 mt-1 flex-shrink-0 text-xl">✦</span>
                                            <span><strong className="text-navy">Altura hasta 5 metros</strong> con intensidad regulable</span>
                                        </li>
                                    </ul>

                                    <p>
                                        Las chispas frías funcionan <strong className="text-navy">sin fuego real, sin humo y sin olor</strong>, cumpliendo con los estándares de seguridad para eventos.
                                    </p>
                                </div>
                            </motion.div>
                        </div>

                        {/* Bloque 3: Equipos profesionales - Contenedor sutil con 2 columnas */}
                        <motion.div
                            className="bg-white/50 backdrop-blur-lg rounded-3xl shadow-xl border border-gold/20 p-8 lg:p-12 mb-16"
                            initial={{ opacity: 0, y: 30 }}
                            whileInView={{ opacity: 1, y: 0 }}
                            viewport={{ once: true }}
                            transition={{ duration: 0.6 }}
                        >
                            <div className="mb-8">
                                <div className="inline-block px-4 py-2 bg-gradient-to-r from-gold/10 to-navy/10 rounded-full mb-6">
                                    <span className="text-navy/90 text-sm font-semibold tracking-wide uppercase">Equipamiento Premium</span>
                                </div>
                                
                                <h3 className="text-3xl lg:text-4xl font-bold text-navy mb-4">
                                    Equipos profesionales certificados
                                </h3>

                                <div className="w-20 h-1 bg-gradient-to-r from-gold to-navy/40 rounded-full"></div>
                            </div>

                            <div className="grid grid-cols-1 lg:grid-cols-2 gap-8 lg:gap-12">
                                <motion.div 
                                    className="space-y-5 text-navy/80 text-base lg:text-lg leading-relaxed"
                                    initial={{ opacity: 0, x: -20 }}
                                    whileInView={{ opacity: 1, x: 0 }}
                                    viewport={{ once: true }}
                                    transition={{ duration: 0.6, delay: 0.2 }}
                                >
                                    <p>
                                        Nuestros equipos de chispas frías de última tecnología generan un <strong className="text-navy">efecto visual intenso y elegante</strong>, sin utilizar fuego real, sin producir humo y sin dejar residuos. Esto los convierte en una opción segura y limpia para todo tipo de eventos.
                                    </p>

                                    <p>
                                        Las máquinas son <strong className="text-navy">100% inalámbricas y programables</strong>, lo que permite sincronizar las chispas con música, iluminación o momentos clave del evento, logrando una puesta en escena impactante y perfectamente coordinada.
                                    </p>
                                </motion.div>

                                <motion.div 
                                    className="space-y-5 text-navy/80 text-base lg:text-lg leading-relaxed"
                                    initial={{ opacity: 0, x: 20 }}
                                    whileInView={{ opacity: 1, x: 0 }}
                                    viewport={{ once: true }}
                                    transition={{ duration: 0.6, delay: 0.3 }}
                                >
                                    <p>
                                        El servicio es operado por <strong className="text-navy">técnicos especializados en efectos especiales</strong>, asegurando un funcionamiento preciso, seguro y confiable. Todos los equipos se encuentran certificados y sometidos a mantenimiento permanente.
                                    </p>

                                    <p>
                                        Ideales para bodas, shows en vivo, lanzamientos de marca, fiestas empresariales y grandes celebraciones, las chispas frías aportan innovación, sofisticación y un <strong className="text-navy">impacto visual memorable</strong>.
                                    </p>
                                </motion.div>
                            </div>

                            {/* Botón de WhatsApp */}
                            <motion.div 
                                className="mt-10 flex justify-center"
                                initial={{ opacity: 0, scale: 0.9 }}
                                whileInView={{ opacity: 1, scale: 1 }}
                                viewport={{ once: true }}
                                transition={{ duration: 0.6, delay: 0.4 }}
                            >
                                <motion.a
                                    href="https://wa.me/5491131004505?text=Hola!%20Quisiera%20consultar%20por%20el%20servicio%20de%20chispas%20frias"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className="inline-flex items-center justify-center gap-3 px-8 py-4 rounded-full font-bold text-lg transition-all duration-300 bg-green-600 text-white shadow-xl"
                                    whileHover={{ scale: 1.05 }}
                                    whileTap={{ scale: 0.95 }}
                                >
                                    <svg className="h-6 w-6" fill="currentColor" viewBox="0 0 24 24">
                                        <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893A11.821 11.821 0 0020.885 3.488"/>
                                    </svg>
                                    Consultar servicio por WhatsApp
                                </motion.a>
                            </motion.div>
                        </motion.div>
                    </div>
                </main>

                <Footer />
                <CartButton />
                <WhatsAppButton />
            </div>
        </>
    );
}
