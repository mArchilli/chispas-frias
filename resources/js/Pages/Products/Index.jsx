import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import axios from 'axios';
import { motion, AnimatePresence } from 'framer-motion';
import Navbar from '@/Components/Navbar';
import Footer from '@/Components/Footer';
import WhatsAppButton from '@/Components/WhatsAppButton';
import { useReducedMotion } from '@/hooks/useAnimations';

export default function ProductsIndex({ auth, products, categories, selectedMainCategory, selectedSubcategories, filters }) {
    const [searchTerm, setSearchTerm] = useState(filters.search || '');
    const [selectedCategory, setSelectedCategory] = useState(filters.category || '');
    const [addingId, setAddingId] = useState(null);
    const reducedMotion = useReducedMotion();

    // Variantes de animación rápidas y sutiles para productos
    const containerVariants = {
        hidden: { opacity: 0 },
        visible: {
            opacity: 1,
            transition: {
                staggerChildren: 0.05, // Más rápido que en Welcome
                delayChildren: 0.05,
            }
        }
    };

    const itemVariants = {
        hidden: { opacity: 0, y: 15 },
        visible: { 
            opacity: 1, 
            y: 0,
            transition: {
                duration: 0.3, // Más rápido: 300ms vs 500ms
                ease: [0.25, 0.1, 0.25, 1.0],
            }
        }
    };

    // Función para obtener la URL de la imagen principal
    const getPrimaryImageUrl = (product) => {
        if (!product.images || product.images.length === 0) {
            return null;
        }
        
        // Buscar la imagen principal
        const primaryImage = product.images.find(img => img.is_primary);
        if (primaryImage) {
            return primaryImage.url || primaryImage.path;
        }
        
        // Si no hay imagen principal, tomar la primera
        const firstImage = product.images[0];
        return firstImage.url || firstImage.path;
    };

    // Función para truncar HTML y obtener solo texto plano para preview
    const getDescriptionPreview = (htmlDescription, maxLength = 100) => {
        if (!htmlDescription) return '';
        
        // Crear un elemento temporal para extraer solo el texto
        const tempElement = document.createElement('div');
        tempElement.innerHTML = htmlDescription;
        const textContent = tempElement.textContent || tempElement.innerText || '';
        
        // Truncar si es muy largo
        if (textContent.length <= maxLength) {
            return textContent;
        }
        
        return textContent.substring(0, maxLength) + '...';
    };

    const handleSearch = (e) => {
        e.preventDefault();
        router.get('/productos', {
            search: searchTerm,
            category: selectedCategory
        }, {
            preserveState: true,
            replace: true
        });
    };

    const handleCategoryFilter = (categorySlug) => {
        setSelectedCategory(categorySlug);
        router.get('/productos', {
            search: searchTerm,
            category: categorySlug
        }, {
            preserveState: true,
            replace: true
        });
    };

    const clearFilters = () => {
        setSearchTerm('');
        setSelectedCategory('');
        router.get('/productos', {}, {
            preserveState: true,
            replace: true
        });
    };

    const addToCart = async (product) => {
        if (!product || product.stock <= 0) return;
        try {
            setAddingId(product.id);
            await axios.post(route('cart.add'), {
                product_id: product.id,
                quantity: 1,
            });
            // notify other parts of the app to refresh cart count
            window.dispatchEvent(new Event('cart-updated'));
        } catch (error) {
            console.error('Error agregando al carrito:', error);
        } finally {
            setAddingId(null);
        }
    };

    const goBackToMainCategories = () => {
        setSelectedCategory('');
        router.get('/productos', {
            search: searchTerm
        }, {
            preserveState: true,
            replace: true
        });
    };

    return (
        <>
            <Head title="Productos - Chispas Frías" />
            
            <Navbar auth={auth} />
            
            {/* Sección superior personalizada */}
            <motion.div
                className="pt-20 pb-10"
                style={{
                    backgroundImage: 'url(/images/fondo-productos.png)',
                    backgroundSize: 'cover',
                    backgroundPosition: 'center',
                    backgroundRepeat: 'no-repeat',
                }}
                initial={{ opacity: 0 }}
                animate={{ opacity: 1 }}
                transition={{ duration: 0.4 }}
            >
                <div className="max-w-7xl mx-auto px-6 lg:px-8">
                    {/* Mobile: logo arriba, luego textos */}
                    <motion.div 
                        className="flex flex-col items-start text-left md:hidden"
                        initial={{ opacity: 0, y: 20 }}
                        animate={{ opacity: 1, y: 0 }}
                        transition={{ duration: 0.4, delay: 0.1 }}
                    >
                        <motion.div 
                            className="relative mb-6"
                            initial={{ scale: 0.9, opacity: 0 }}
                            animate={{ scale: 1, opacity: 1 }}
                            transition={{ duration: 0.4, delay: 0.2 }}
                        >
                            <div className="absolute inset-0 rounded-full bg-navy/80 filter blur-md" style={{ transform: 'scale(1.06)' }} />
                            <img src="/images/chispas-frias-logo.png" alt="Logo Chispas Frías" className="relative h-32 w-auto z-10" />
                        </motion.div>
                        <h1
                            className="text-3xl font-bold text-chalk mb-3"
                            style={{ textShadow: '0 0 15px rgba(2,18,45,1), 0 0 8px rgba(2,18,45,1), 0 2px 10px rgba(2,18,45,0.9)' }}
                        >
                            Catálogo de productos.
                        </h1>
                        <p
                            className="text-lg text-chalk/80 max-w-2xl"
                            style={{ textShadow: '0 0 15px rgba(2,18,45,1), 0 0 8px rgba(2,18,45,1), 0 2px 10px rgba(2,18,45,0.9)' }}
                        >
                            Descubre nuestra amplia gama de productos de pirotecnia fría, diseñados para crear momentos únicos y experiencias inolvidables.
                        </p>
                    </motion.div>
                    {/* Desktop: diseño anterior */}
                    <motion.div 
                        className="hidden md:flex items-center"
                        initial={{ opacity: 0, y: 20 }}
                        animate={{ opacity: 1, y: 0 }}
                        transition={{ duration: 0.4, delay: 0.1 }}
                    >
                        <motion.div 
                            className="relative mr-3"
                            initial={{ scale: 0.9, opacity: 0 }}
                            animate={{ scale: 1, opacity: 1 }}
                            transition={{ duration: 0.4, delay: 0.2 }}
                        >
                            <div className="absolute inset-0 rounded-lg bg-navy/80 filter blur-md" style={{ transform: 'scale(1.05)' }} />
                            <img src="/images/chispas-frias-logo.png" alt="Logo Chispas Frías" className="relative h-28 w-auto z-10" />
                        </motion.div>
                        <div className="h-32 w-px bg-white ml-2 mr-1" />
                        <div className="flex flex-col text-left ml-2">
                            <h1
                                className="text-4xl lg:text-5xl font-bold text-chalk mb-3"
                                style={{ textShadow: '0 0 15px rgba(2,18,45,1), 0 0 8px rgba(2,18,45,1), 0 2px 10px rgba(2,18,45,0.9)' }}
                            >
                                Catálogo de productos.
                            </h1>
                            <p
                                className="text-xl text-chalk/80 max-w-2xl"
                                style={{ textShadow: '0 0 15px rgba(2,18,45,1), 0 0 8px rgba(2,18,45,1), 0 2px 10px rgba(2,18,45,0.9)' }}
                            >
                                Descubre nuestra amplia gama de productos de pirotecnia fría, diseñados para crear momentos únicos y experiencias inolvidables.
                            </p>
                        </div>
                    </motion.div>
                </div>
            </motion.div>

            {/* Filtros y Búsqueda */}
            <motion.div 
                className="bg-chalk py-8"
                initial={{ opacity: 0, y: -10 }}
                animate={{ opacity: 1, y: 0 }}
                transition={{ duration: 0.3, delay: 0.2 }}
            >
                <div className="max-w-7xl mx-auto px-6 lg:px-8">
                    <div className="flex flex-col lg:flex-row gap-6 items-center justify-between">
                        {/* Barra de búsqueda */}
                        <form onSubmit={handleSearch} className="w-full lg:flex-1 lg:max-w-md">
                            <div className="relative">
                                <motion.input
                                    type="text"
                                    placeholder="Buscar productos..."
                                    value={searchTerm}
                                    onChange={(e) => setSearchTerm(e.target.value)}
                                    className="w-full px-4 py-3 pl-10 border border-navy rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-gold focus:border-navy transition-all duration-200"
                                    whileFocus={{ scale: 1.01 }}
                                />
                                <svg className="absolute left-3 top-3.5 h-5 w-5 text-gold" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                </svg>
                            </div>
                        </form>

                        {/* Filtro por categorías */}
                        <motion.div 
                            className="flex flex-wrap gap-3"
                            initial={{ opacity: 0 }}
                            animate={{ opacity: 1 }}
                            transition={{ duration: 0.3, delay: 0.3 }}
                        >
                            {/* Si hay subcategorías seleccionadas, mostrar botón para volver */}
                            {selectedSubcategories?.length > 0 ? (
                                <>
                                    <motion.button
                                        onClick={goBackToMainCategories}
                                        className="px-3 py-2 bg-navy/10 text-navy rounded-lg font-medium transition-all duration-200 hover:bg-navy/20 flex items-center gap-2"
                                        whileHover={!reducedMotion ? { scale: 1.02 } : {}}
                                        whileTap={!reducedMotion ? { scale: 0.98 } : {}}
                                    >
                                        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 19l-7-7 7-7" />
                                        </svg>
                                        Categorías
                                    </motion.button>
                                    
                                    {/* Mostrar categoría principal seleccionada */}
                                    <div className="px-4 py-2 bg-gold text-navy rounded-lg font-medium">
                                        {selectedMainCategory?.name}
                                    </div>
                                    
                                    {/* Mostrar subcategorías */}
                                    <motion.button
                                        onClick={() => handleCategoryFilter(selectedMainCategory?.slug)}
                                        className={`px-4 py-2 rounded-lg font-medium transition-all duration-200 ${
                                            selectedCategory === selectedMainCategory?.slug
                                                ? 'bg-gold text-navy'
                                                : 'bg-white text-navy hover:bg-gold/10'
                                        }`}
                                        whileHover={!reducedMotion ? { scale: 1.02 } : {}}
                                        whileTap={!reducedMotion ? { scale: 0.98 } : {}}
                                    >
                                        Todas las {selectedMainCategory?.name}
                                    </motion.button>
                                    
                                    {selectedSubcategories.map((subcategory) => (
                                        <motion.button
                                            key={subcategory.id}
                                            onClick={() => handleCategoryFilter(subcategory.slug)}
                                            className={`px-4 py-2 rounded-lg font-medium transition-all duration-200 ${
                                                selectedCategory === subcategory.slug
                                                    ? 'bg-gold text-navy'
                                                    : 'bg-white text-navy hover:bg-gold/10'
                                            }`}
                                            whileHover={!reducedMotion ? { scale: 1.02 } : {}}
                                            whileTap={!reducedMotion ? { scale: 0.98 } : {}}
                                        >
                                            {subcategory.name}
                                        </motion.button>
                                    ))}
                                </>
                            ) : (
                                <>
                                    {/* Mostrar categorías principales */}
                                    <motion.button
                                        onClick={() => handleCategoryFilter('')}
                                        className={`px-4 py-2 rounded-lg font-medium transition-all duration-200 ${
                                            !selectedCategory 
                                                ? 'bg-gold text-navy' 
                                                : 'bg-white text-navy hover:bg-gold/10'
                                        }`}
                                        whileHover={!reducedMotion ? { scale: 1.02 } : {}}
                                        whileTap={!reducedMotion ? { scale: 0.98 } : {}}
                                    >
                                        Todas
                                    </motion.button>
                                    {categories.map((category) => (
                                        <motion.button
                                            key={category.id}
                                            onClick={() => handleCategoryFilter(category.slug)}
                                            className={`px-4 py-2 rounded-lg font-medium transition-all duration-200 ${
                                                selectedCategory === category.slug
                                                    ? 'bg-gold text-navy'
                                                    : 'bg-white text-navy hover:bg-gold/10'
                                            }`}
                                            whileHover={!reducedMotion ? { scale: 1.02 } : {}}
                                            whileTap={!reducedMotion ? { scale: 0.98 } : {}}
                                        >
                                            {category.name}
                                            {category.children?.length > 0 && (
                                                <span className="ml-1 text-xs opacity-70">({category.children.length})</span>
                                            )}
                                        </motion.button>
                                    ))}
                                </>
                            )}
                        </motion.div>

                        {/* Limpiar filtros */}
                        {(searchTerm || selectedCategory) && (
                            <motion.button
                                onClick={clearFilters}
                                className="text-navy/70 hover:text-navy font-medium underline transition-all duration-200"
                                initial={{ opacity: 0, scale: 0.9 }}
                                animate={{ opacity: 1, scale: 1 }}
                                exit={{ opacity: 0, scale: 0.9 }}
                                whileHover={!reducedMotion ? { scale: 1.02 } : {}}
                                whileTap={!reducedMotion ? { scale: 0.98 } : {}}
                            >
                                Limpiar filtros
                            </motion.button>
                        )}
                    </div>
                </div>
            </motion.div>

            {/* Info cards: envíos y medios de pago */}
            <motion.div 
                className="bg-chalk py-6"
                initial={{ opacity: 0 }}
                animate={{ opacity: 1 }}
                transition={{ duration: 0.3, delay: 0.3 }}
            >
                <div className="max-w-7xl mx-auto px-6 lg:px-8">
                    <motion.div 
                        className="grid grid-cols-1 md:grid-cols-3 gap-4"
                        variants={containerVariants}
                        initial="hidden"
                        animate="visible"
                    >
                        <motion.div 
                            className="bg-white rounded-xl shadow-md p-4 flex items-center gap-4 group border-2 border-navy/20"
                            variants={itemVariants}
                            whileHover={!reducedMotion ? { scale: 1.01, boxShadow: "0 15px 30px rgba(0, 0, 0, 0.12)" } : {}}
                            transition={{ duration: 0.2 }}
                        >
                            <div className="flex-shrink-0 w-14 h-14 rounded-lg bg-gold/10 flex items-center justify-center">
                                <svg className="w-10 h-10 text-gold" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64" fill="none" stroke="currentColor" strokeWidth={3} strokeLinecap="round" strokeLinejoin="round">
                                    <line x1="2" y1="24" x2="18" y2="24" />
                                    <line x1="2" y1="32" x2="14" y2="32" />
                                    <line x1="2" y1="40" x2="18" y2="40" />
                                    <rect x="18" y="22" width="26" height="18" rx="2" />
                                    <path d="M44 28h8l6 6v6H44z" />
                                    <circle cx="26" cy="44" r="3" />
                                    <circle cx="50" cy="44" r="3" />
                                </svg>
                            </div>
                            <div>
                                <h4 className="text-sm font-bold text-navy">Envíos a todo el país</h4>
                                <p className="text-xs text-navy/70">Llegamos a tu domicilio con rapidez y seguridad.</p>
                            </div>
                        </motion.div>

                        <motion.div 
                            className="bg-white rounded-xl shadow-md p-4 flex items-center gap-4 group border-2 border-navy/20"
                            variants={itemVariants}
                            whileHover={!reducedMotion ? { scale: 1.01, boxShadow: "0 15px 30px rgba(0, 0, 0, 0.12)" } : {}}
                            transition={{ duration: 0.2 }}
                        >
                            <div className="flex-shrink-0 w-14 h-14 rounded-lg bg-gold/10 flex items-center justify-center">
                                <svg className="w-10 h-10 text-gold" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64" fill="none" stroke="currentColor" strokeWidth={3} strokeLinecap="round" strokeLinejoin="round">
                                    <path d="M32 14 C26 4, 12 8, 18 16 C24 22, 30 18, 32 14Z" />

                                    <path d="M32 14 C38 4, 52 8, 46 16 C40 22, 34 18, 32 14Z" />

                                    <circle cx="32" cy="16" r="2" fill="currentColor" />

                                    <rect x="6" y="20" width="52" height="8" rx="2" />

                                    <rect x="8" y="28" width="48" height="30" rx="2" />

                                    <line x1="32" y1="20" x2="32" y2="58" />
                                </svg>
                            </div>
                            <div>
                                <h4 className="text-sm font-bold text-navy">Envío gratis a compras mayoristas</h4>
                                <p className="text-xs text-navy/70">Beneficios y descuentos para compras al por mayor.</p>
                            </div>
                        </motion.div>

                        <motion.div 
                            className="bg-white rounded-xl shadow-md p-4 flex items-center gap-4 group border-2 border-navy/20"
                            variants={itemVariants}
                            whileHover={!reducedMotion ? { scale: 1.01, boxShadow: "0 15px 30px rgba(0, 0, 0, 0.12)" } : {}}
                            transition={{ duration: 0.2 }}
                        >
                            <div className="flex-shrink-0 w-14 h-14 rounded-lg bg-gold/10 flex items-center justify-center">
                                <svg className="w-10 h-10 text-gold" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64" fill="none" stroke="currentColor" strokeWidth={3} strokeLinecap="round" strokeLinejoin="round">
                                    <rect x="8" y="18" width="48" height="28" rx="3" />
                                    <line x1="8" y1="26" x2="56" y2="26" />
                                    <rect x="14" y="32" width="10" height="8" rx="1" />
                                    <line x1="19" y1="32" x2="19" y2="40" />
                                    <line x1="14" y1="36" x2="24" y2="36" />
                                    <line x1="30" y1="36" x2="48" y2="36" />
                                    <line x1="30" y1="40" x2="42" y2="40" />
                                </svg>
                            </div>
                            <div>
                                <h4 className="text-sm font-bold text-navy">Medios de pago</h4>
                                <p className="text-xs text-navy/70">Podés usar tus tarjetas asociadas a Mercado Pago a través de nuestro alias.</p>
                            </div>
                        </motion.div>
                    </motion.div>
                </div>
            </motion.div>

            {/* Lista de productos */}
            <main className="bg-chalk py-6">
                <div className="max-w-7xl mx-auto px-6 lg:px-8">
                    {products.data.length > 0 ? (
                        <>
                            <motion.div 
                                className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-8"
                                variants={containerVariants}
                                initial="hidden"
                                animate="visible"
                                key={selectedCategory} // Re-animar cuando cambie la categoría
                            >
                                {products.data.map((product, index) => (
                                    <motion.div 
                                        key={product.id} 
                                        className="bg-white rounded-lg shadow-lg overflow-hidden group border-2 border-navy/20 flex flex-col"
                                        variants={itemVariants}
                                        whileHover={!reducedMotion ? { 
                                            scale: 1.01, 
                                            y: -2,
                                            boxShadow: "0 15px 30px rgba(0, 0, 0, 0.12)" 
                                        } : {}}
                                        transition={{ duration: 0.2 }}
                                    >
                                        {/* Imagen del producto */}
                                        <div className="relative aspect-w-4 aspect-h-3 bg-gray-100 overflow-hidden">
                                            {product.images?.length > 0 ? (
                                                <motion.img
                                                    src={getPrimaryImageUrl(product)}
                                                    alt={product.title}
                                                    className="w-full h-48 object-cover"
                                                    initial={{ opacity: 0, scale: 1.05 }}
                                                    animate={{ opacity: 1, scale: 1 }}
                                                    transition={{ duration: 0.4 }}
                                                    whileHover={!reducedMotion ? { scale: 1.05 } : {}}
                                                />
                                            ) : (
                                                <div className="w-full h-48 bg-gray-200 flex items-center justify-center">
                                                    <svg className="h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                                    </svg>
                                                </div>
                                            )}
                                            
                                            {/* Badge de oferta */}
                                            {product.current_offer && (
                                                <motion.div 
                                                    className="absolute top-3 right-3 z-10"
                                                    initial={{ scale: 0, rotate: -20 }}
                                                    animate={{ scale: 1, rotate: 0 }}
                                                    transition={{ delay: 0.2, type: "spring", stiffness: 200, damping: 12 }}
                                                >
                                                    <span className="bg-red-500 text-white text-xs font-bold px-2 py-1 rounded-full shadow-lg">
                                                        -{product.discount_percentage}%
                                                    </span>
                                                </motion.div>
                                            )}
                                        </div>

                                        {/* Información del producto */}
                                        <div className="p-6 flex flex-col justify-between h-full">
                                            {/* Categoría */}
                                            <div className="flex items-center mb-2">
                                                <span className="text-sm text-gold font-medium">
                                                    {product.category?.parent?.name || product.category?.name}
                                                </span>
                                                {product.category?.parent && (
                                                    <>
                                                        <span className="mx-2 text-navy/40">•</span>
                                                        <span className="text-sm text-navy/60">
                                                            {product.category.name}
                                                        </span>
                                                    </>
                                                )}
                                            </div>

                                            {/* Título */}
                                            <h3 className="text-lg font-bold text-navy mb-2">
                                                {product.title}
                                            </h3>

                                            {/* Descripción */}
                                            <p className="text-navy/70 text-sm mb-4 line-clamp-3 flex-grow">
                                                {getDescriptionPreview(product.description, 120)}
                                            </p>

                                            {/* Precio, stock y acciones (apilados) */}
                                            <div className="flex flex-col">
                                                <div className="mb-1">
                                                    {product.current_offer ? (
                                                        <div className="space-y-1">
                                                            <div className="flex items-baseline gap-2">
                                                                <span className="text-2xl font-bold text-red-600">
                                                                    ${Number(product.current_offer.offer_price).toLocaleString('es-CL')}
                                                                </span>
                                                                <span className="text-sm text-navy/60 line-through">
                                                                    ${Number(product.price).toLocaleString('es-CL')}
                                                                </span>
                                                            </div>
                                                            <div className="text-xs text-green-600 font-medium">
                                                                Ahorras ${Number(product.price - product.current_offer.offer_price).toLocaleString('es-CL')}
                                                            </div>
                                                        </div>
                                                    ) : (
                                                        <span className="text-2xl font-bold text-navy">
                                                            ${Number(product.price).toLocaleString('es-CL')}
                                                        </span>
                                                    )}
                                                </div>

                                                <div className="mt-4 flex items-center gap-3">
                                                    <motion.button
                                                        onClick={() => addToCart(product)}
                                                        disabled={addingId === product.id || product.stock <= 0}
                                                        className={`inline-flex items-center justify-center px-4 py-2 rounded-full font-semibold text-sm transition-all duration-200 whitespace-nowrap ${
                                                            product.stock <= 0
                                                                ? 'bg-gray-300 text-gray-600 cursor-not-allowed'
                                                                : 'bg-navy text-white hover:bg-navy/90 shadow-lg'
                                                        }`}
                                                        whileHover={product.stock > 0 && !reducedMotion ? { scale: 1.03 } : {}}
                                                        whileTap={product.stock > 0 && !reducedMotion ? { scale: 0.97 } : {}}
                                                    >
                                                        {addingId === product.id ? (
                                                            <svg className="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                                                                <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                                                            </svg>
                                                        ) : null}
                                                        Agregar al carrito
                                                    </motion.button>

                                                    <Link
                                                        href={route('products.show', product.id)}
                                                        className="inline-flex items-center justify-center px-4 py-2 rounded-full font-semibold text-sm transition-all duration-200 whitespace-nowrap bg-white text-navy border-2 border-navy hover:bg-navy/10 shadow-lg"
                                                    >
                                                        <motion.span
                                                            whileHover={!reducedMotion ? { scale: 1.03 } : {}}
                                                            whileTap={!reducedMotion ? { scale: 0.97 } : {}}
                                                            className="block"
                                                        >
                                                            Ver mas
                                                        </motion.span>
                                                    </Link>
                                                </div>
                                            </div>
                                        </div>
                                    </motion.div>
                                ))}
                            </motion.div>

                            {/* Paginación */}
                            {products.links.length > 3 && (
                                <motion.div 
                                    className="mt-12 flex justify-center"
                                    initial={{ opacity: 0, y: 10 }}
                                    animate={{ opacity: 1, y: 0 }}
                                    transition={{ duration: 0.3, delay: 0.4 }}
                                >
                                    <nav className="flex space-x-2">
                                        {products.links.map((link, index) => {
                                            let label = link.label;
                                            if (label === 'Previous' || label === '&laquo; Previous') label = 'Atrás';
                                            if (label === 'Next' || label === 'Next &raquo;') label = 'Siguiente';
                                            return link.url ? (
                                                <motion.div
                                                    key={index}
                                                    whileHover={!reducedMotion ? { scale: 1.05 } : {}}
                                                    whileTap={!reducedMotion ? { scale: 0.95 } : {}}
                                                >
                                                    <Link
                                                        href={link.url}
                                                        className={`px-4 py-2 rounded-lg font-medium transition-colors duration-200 ${
                                                            link.active 
                                                                ? 'bg-gold text-navy' 
                                                                : 'bg-white text-navy hover:bg-gold/10'
                                                        }`}
                                                    >{label}</Link>
                                                </motion.div>
                                            ) : (
                                                <span 
                                                    key={index}
                                                    className="px-4 py-2 text-navy/40"
                                                >{label}</span>
                                            );
                                        })}
                                    </nav>
                                </motion.div>
                            )}
                        </>
                    ) : (
                        <motion.div 
                            className="text-center py-16"
                            initial={{ opacity: 0, scale: 0.95 }}
                            animate={{ opacity: 1, scale: 1 }}
                            transition={{ duration: 0.4 }}
                        >
                            <svg className="h-16 w-16 text-navy/40 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
                            </svg>
                            <h3 className="text-xl font-semibold text-navy mb-2">
                                No se encontraron productos
                            </h3>
                            <p className="text-navy/60 mb-6">
                                {searchTerm || selectedCategory 
                                    ? 'Prueba ajustando tus filtros de búsqueda.' 
                                    : 'Actualmente no hay productos disponibles.'
                                }
                            </p>
                            {(searchTerm || selectedCategory) && (
                                <motion.button
                                    onClick={clearFilters}
                                    className="px-6 py-3 bg-gold text-navy font-semibold rounded-lg transition-colors duration-200"
                                    whileHover={!reducedMotion ? { scale: 1.05 } : {}}
                                    whileTap={!reducedMotion ? { scale: 0.95 } : {}}
                                >
                                    Ver todos los productos
                                </motion.button>
                            )}
                        </motion.div>
                    )}
                </div>
            </main>

            <Footer />
            <WhatsAppButton />
        </>
    );
}