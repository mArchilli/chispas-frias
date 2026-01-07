import { Head } from '@inertiajs/react';
import { useState, useEffect } from 'react';
import { motion, AnimatePresence } from 'framer-motion';
import Navbar from '@/Components/Navbar';
import Footer from '@/Components/Footer';
import WhatsAppButton from '@/Components/WhatsAppButton';

function ServiceGallery() {
    const images = [
        '/images/carrusel-1.jpg',
        '/images/carrusel-2.jpg',
        '/images/carrusel-3.jpg',
    ];

    return (
        <div className="h-full p-6 lg:p-8">
            {/* Grid 2 columnas - imagen grande izquierda, 2 imágenes derecha */}
            <div className="grid grid-cols-2 gap-4 h-full">
                {/* Imagen grande izquierda */}
                <motion.div 
                    className="row-span-2 relative rounded-3xl overflow-hidden group"
                    initial={{ opacity: 0, scale: 0.95 }}
                    whileInView={{ opacity: 1, scale: 1 }}
                    viewport={{ once: true }}
                    transition={{ duration: 0.6 }}
                    whileHover={{ scale: 1.02 }}
                >
                    <img
                        src={images[0]}
                        alt="Servicio de chispas frías"
                        className="w-full h-full object-cover transition-transform duration-700 group-hover:scale-110"
                    />
                    <div className="absolute inset-0 bg-gradient-to-t from-navy/60 via-transparent to-transparent"></div>
                    {/* Borde con gradiente */}
                    <div className="absolute inset-0 border-2 border-navy/30 rounded-3xl"></div>
                </motion.div>

                {/* 2 imágenes a la derecha */}
                {images.slice(1, 3).map((image, index) => (
                    <motion.div
                        key={index}
                        className="relative rounded-2xl overflow-hidden group cursor-pointer"
                        initial={{ opacity: 0, y: 20 }}
                        whileInView={{ opacity: 1, y: 0 }}
                        viewport={{ once: true }}
                        transition={{ duration: 0.6, delay: 0.1 + index * 0.1 }}
                        whileHover={{ scale: 1.05, zIndex: 10 }}
                    >
                        <img
                            src={image}
                            alt={`Imagen de servicio ${index + 2}`}
                            className="w-full h-full object-cover transition-transform duration-500 group-hover:scale-110"
                        />
                        <div className="absolute inset-0 bg-gradient-to-t from-navy/50 to-transparent opacity-60 group-hover:opacity-30 transition-opacity duration-300"></div>
                        {/* Borde sutil */}
                        <div className="absolute inset-0 border border-navy/20 group-hover:border-navy/50 rounded-2xl transition-all duration-300"></div>
                    </motion.div>
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
                        <motion.div 
                            className="relative mr-3" 
                            style={{ display: 'inline-block' }}
                            initial={{ opacity: 0, x: -30 }}
                            animate={{ opacity: 1, x: 0 }}
                            transition={{ duration: 0.6, delay: 0.1 }}
                        >
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
                        </motion.div>

                        <motion.div 
                            className="h-32 w-px bg-white ml-2 mr-1"
                            initial={{ scaleY: 0 }}
                            animate={{ scaleY: 1 }}
                            transition={{ duration: 0.6, delay: 0.3 }}
                        />

                        <div className="flex flex-col text-left ml-2">
                            <motion.h1
                                className="text-4xl lg:text-5xl font-bold text-chalk mb-3"
                                style={{ textShadow: '0 0 15px rgba(3,37,65,1), 0 0 8px rgba(3,37,65,1), 0 2px 10px rgba(3,37,65,0.9)'}}
                                initial={{ opacity: 0, y: 20 }}
                                animate={{ opacity: 1, y: 0 }}
                                transition={{ duration: 0.6, delay: 0.4 }}
                            >
                                Nuestros servicios.
                            </motion.h1>
                            <motion.p
                                className="text-xl text-chalk/80 max-w-2xl"
                                style={{ textShadow: '0 0 15px rgba(3,37,65,1), 0 0 8px rgba(3,37,65,1), 0 2px 10px rgba(3,37,65,0.9)'}}
                                initial={{ opacity: 0, y: 20 }}
                                animate={{ opacity: 1, y: 0 }}
                                transition={{ duration: 0.6, delay: 0.5 }}
                            >
                                ¿Querés llevar tu evento al siguiente nivel? Nosotros te ayudamos a lograrlo con nuestros servicios personalizados.
                            </motion.p>
                        </div>
                    </div>

                    {/* Mobile */}
                    <div className="flex flex-col items-start text-left md:hidden">
                        <motion.div 
                            className="relative mb-6" 
                            style={{ display: 'inline-block' }}
                            initial={{ opacity: 0, scale: 0.8 }}
                            animate={{ opacity: 1, scale: 1 }}
                            transition={{ duration: 0.6 }}
                        >
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
                        </motion.div>
                        <motion.h1
                            className="text-3xl font-bold text-chalk mb-3"
                            style={{ textShadow: '0 0 15px rgba(3,37,65,1), 0 0 8px rgba(3,37,65,1), 0 2px 10px rgba(3,37,65,0.9)'}}
                            initial={{ opacity: 0, y: 20 }}
                            animate={{ opacity: 1, y: 0 }}
                            transition={{ duration: 0.6, delay: 0.2 }}
                        >
                            Conoce nuestros servicios
                        </motion.h1>
                        <motion.p
                            className="text-lg text-chalk/80 max-w-2xl"
                            style={{ textShadow: '0 0 15px rgba(3,37,65,1), 0 0 8px rgba(3,37,65,1), 0 2px 10px rgba(3,37,65,0.9)'}}
                            initial={{ opacity: 0, y: 20 }}
                            animate={{ opacity: 1, y: 0 }}
                            transition={{ duration: 0.6, delay: 0.3 }}
                        >
                            Aquí va un texto descriptivo de ejemplo para rellenar la sección de servicios.
                        </motion.p>
                    </div>
                </div>
            </div>

                {/* Contenido principal */}
                <main className="flex-1 py-16 lg:py-20 relative overflow-hidden">
                    {/* Fondo premium con glassmorphism */}
                    <div className="absolute inset-0 bg-gradient-to-br from-white via-chalk to-white">
                        {/* Gradientes de fondo */}
                        <div className="absolute top-0 right-1/4 w-[500px] h-[500px] rounded-full blur-3xl" style={{
                            background: 'radial-gradient(circle, rgba(10, 31, 68, 0.06) 0%, rgba(10, 31, 68, 0.03) 50%, transparent 100%)'
                        }}></div>
                        <div className="absolute bottom-0 left-1/4 w-[450px] h-[450px] rounded-full blur-3xl" style={{
                            background: 'radial-gradient(circle, rgba(201, 169, 97, 0.10) 0%, rgba(201, 169, 97, 0.04) 50%, transparent 100%)'
                        }}></div>
                        
                        {/* Patrón sutil */}
                        <div className="absolute inset-0 opacity-10" style={{
                            backgroundImage: `radial-gradient(circle at 50px 50px, rgba(10, 31, 68, 0.15) 1px, transparent 1px)`,
                            backgroundSize: '100px 100px'
                        }}></div>
                        
                        {/* Overlay glassmorphism */}
                        <div className="absolute inset-0 backdrop-blur-[120px] bg-white/35"></div>
                    </div>

                    <div className="max-w-7xl mx-auto px-6 lg:px-8 relative z-10">
                        {/* Contenedor principal con diseño premium */}
                        <motion.div 
                            className="relative bg-white/70 backdrop-blur-2xl rounded-[3rem] shadow-2xl border border-navy/15 overflow-hidden"
                            initial={{ opacity: 0, y: 30 }}
                            whileInView={{ opacity: 1, y: 0 }}
                            viewport={{ once: true }}
                            transition={{ duration: 0.7 }}
                            whileHover={{ boxShadow: "0 40px 90px rgba(10, 31, 68, 0.15)" }}
                        >
                            {/* Brillo navy sutil en el borde superior */}
                            <div className="absolute inset-0 rounded-[3rem] bg-gradient-to-br from-navy/5 via-transparent to-navy/5 pointer-events-none"></div>
                            
                            <div className="grid grid-cols-1 lg:grid-cols-2 gap-0">
                                {/* Columna izquierda - Texto */}
                                <div className="p-8 lg:p-12 xl:p-16 flex flex-col justify-center relative order-2 lg:order-1">
                                    <motion.h2 
                                        className="text-4xl lg:text-5xl xl:text-6xl font-bold text-navy mb-6 leading-tight"
                                        initial={{ opacity: 0, x: -30 }}
                                        whileInView={{ opacity: 1, x: 0 }}
                                        viewport={{ once: true }}
                                        transition={{ duration: 0.6, delay: 0.1 }}
                                    >
                                        Servicio de <span className="text-navy/80">chispas frías</span>
                                    </motion.h2>

                                    <motion.div 
                                        className="w-20 h-1 bg-gradient-to-r from-gold to-gold/40 mb-8 rounded-full"
                                        initial={{ width: 0, opacity: 0 }}
                                        whileInView={{ width: 80, opacity: 1 }}
                                        viewport={{ once: true }}
                                        transition={{ duration: 0.7, delay: 0.2 }}
                                    ></motion.div>

                                    <motion.div 
                                        className="space-y-4 mb-10"
                                        initial={{ opacity: 0, x: -20 }}
                                        whileInView={{ opacity: 1, x: 0 }}
                                        viewport={{ once: true }}
                                        transition={{ duration: 0.6, delay: 0.3 }}
                                    >
                                        <p className="text-navy/80 text-base lg:text-lg leading-relaxed">
                                            Una solución visual <strong className="text-navy font-semibold">moderna, elegante y segura</strong> para todo tipo de eventos. Este efecto especial genera columnas de chispas controladas que aportan un impacto visual sorprendente, sin producir calor ni humo.
                                        </p>

                                        <p className="text-navy/75 text-base lg:text-lg leading-relaxed">
                                            Ideales para quienes buscan un efecto tipo fuego artificial con total seguridad. Perfectas para bodas, cumpleaños, recitales, desfiles y celebraciones especiales.
                                        </p>

                                        <p className="text-navy/70 text-base lg:text-lg leading-relaxed">
                                            Tecnología segura y presentación de alto nivel para destacar tu evento con un show elegante y confiable.
                                        </p>
                                    </motion.div>

                                    <motion.div 
                                        className="flex flex-col sm:flex-row gap-4"
                                        initial={{ opacity: 0, y: 20 }}
                                        whileInView={{ opacity: 1, y: 0 }}
                                        viewport={{ once: true }}
                                        transition={{ duration: 0.6, delay: 0.4 }}
                                    >
                                        <motion.a 
                                            href="/servicios/chispas" 
                                            className="inline-flex items-center justify-center px-6 py-3 bg-navy text-white font-semibold rounded-full hover:bg-navy/90 transition-all duration-300 shadow-lg hover:shadow-xl"
                                            whileHover={{ scale: 1.05 }}
                                            whileTap={{ scale: 0.95 }}
                                        >
                                            Ver mas detalle
                                        </motion.a>
                                        <motion.a
                                            href="https://wa.me/5491131004505?text=Hola!%20Quisiera%20consultar%20por%20el%20servicio%20de%20chispas%20frias"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            className="inline-flex items-center justify-center px-6 py-3 bg-transparent text-navy border-2 border-navy font-semibold rounded-full hover:bg-navy/10 transition-all duration-300 shadow-lg hover:shadow-xl"
                                            whileHover={{ scale: 1.05 }}
                                            whileTap={{ scale: 0.95 }}
                                        >
                                            Consultar por el servicio
                                        </motion.a>
                                    </motion.div>
                                </div>

                                {/* Columna derecha - Galería */}
                                <div className="relative min-h-[500px] lg:min-h-[700px] bg-gradient-to-br from-chalk/30 to-transparent order-1 lg:order-2">
                                    <ServiceGallery />
                                </div>
                            </div>
                        </motion.div>
                    </div>
                </main>

                <Footer />
                <WhatsAppButton />

            </div>
        </>
    );
}
