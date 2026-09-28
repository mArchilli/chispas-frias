import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import axios from 'axios';
import toast from 'react-hot-toast';
import { motion } from 'framer-motion';
import Navbar from '@/Components/Navbar';
import Footer from '@/Components/Footer';
import WhatsAppButton from '@/Components/WhatsAppButton';
import CartButton from '@/Components/CartButton';
import { useReducedMotion } from '@/hooks/useAnimations';
import { getProductImageUrl } from '@/utils/images';

export default function ProductsIndex({ auth, products, categories, selectedMainCategory, selectedSubcategories, filters }) {
    const [searchTerm, setSearchTerm] = useState(filters.search || '');
    const [selectedCategory, setSelectedCategory] = useState(filters.category || '');
    const [addingId, setAddingId] = useState(null);
    const [quantities, setQuantities] = useState({});
    const reducedMotion = useReducedMotion();

    // Función para obtener la cantidad de un producto
    const getQuantity = (productId) => quantities[productId] || 1;

    // Función para incrementar cantidad
    const incrementQuantity = (productId, stock) => {
        const currentQty = getQuantity(productId);
        if (currentQty < Math.min(99, Number(stock))) {
            setQuantities(prev => ({ ...prev, [productId]: currentQty + 1 }));
        }
    };

    // Función para decrementar cantidad
    const decrementQuantity = (productId) => {
        const currentQty = getQuantity(productId);
        if (currentQty > 1) {
            setQuantities(prev => ({ ...prev, [productId]: currentQty - 1 }));
        }
    };

    // Función para obtener la URL de la imagen principal
    const getPrimaryImageUrl = (product) => {
        if (!product.images || product.images.length === 0) {
            return null;
        }
        const primaryImage = product.images.find(img => img.is_primary && img.type !== 'video') || product.images.find(img => img.type !== 'video');
        return getProductImageUrl(primaryImage?.url || primaryImage?.path);
    };

    const getDescriptionPreview = (description) => {
        if (!description) return '';
        const element = document.createElement('div');
        element.innerHTML = description;
        const plainText = element.textContent || '';
        return plainText.length > 300 ? `${plainText.slice(0, 300)}...` : plainText;
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
        
        const quantity = getQuantity(product.id);
        
        try {
            setAddingId(product.id);
            await axios.post(route('cart.add'), {
                product_id: product.id,
                quantity: quantity,
            });
            // notify other parts of the app to refresh cart count
            window.dispatchEvent(new Event('cart-updated'));
            // Mostrar notificación de éxito
            toast.success(`${quantity} ${quantity > 1 ? 'unidades de' : 'unidad de'} ${product.title} agregado al carrito`);
            // Resetear la cantidad después de agregar
            setQuantities(prev => ({ ...prev, [product.id]: 1 }));
        } catch (error) {
            console.error('Error agregando al carrito:', error);
            toast.error('Error al agregar el producto');
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
        <div className="storefront-background min-h-screen text-white">
            <Head title="Catálogo de Chispas Frías y Pirotecnia Fría | Compra Online">
                <meta name="description" content="Catálogo completo de chispas frías y pirotecnia fría. Chispas de fuego frío para bodas, cumpleaños, fiestas de 15 y eventos. Compra online con envíos a toda Argentina." />
                <meta property="og:title" content="Catálogo de Chispas Frías | Compra Online" />
                <meta property="og:description" content="Explorá nuestro catálogo de chispas frías y pirotecnia fría certificada para todo tipo de eventos." />
                <meta property="og:image" content="/images/chispas-frias-logo.png" />
                <meta property="og:type" content="website" />
            </Head>
            
            <Navbar auth={auth} />
            
            <header className="pb-10 pt-40 sm:pb-12 sm:pt-44">
                <div className="site-shell">
                    <div className="flex items-center justify-between gap-4">
                        <h1 className="min-w-0 max-w-4xl uppercase text-4xl font-bold tracking-[-0.045em] text-white sm:text-5xl lg:text-6xl">
                            Catálogo completo
                        </h1>
                        <img
                            src="/images/chispas-frias-logo.png"
                            alt="Logo de Chispas Frías"
                            className="mr-3.5 h-20 w-20 shrink-0 object-contain sm:h-24 sm:w-24 lg:h-28 lg:w-28"
                        />
                    </div>
                    <p className="mt-4 max-w-3xl text-base leading-relaxed text-white sm:text-lg">
                        Encontrá todo para tu evento en un solo lugar. Hacemos envíos a todo el país,
                        ofrecemos envío gratis en compras mayoristas y aceptamos tarjetas asociadas a Mercado Pago.
                    </p>
                </div>
            </header>

            {/* Filtros y Búsqueda */}
            <div className="pb-6">
                <div className="site-shell">
                    <div className="flex flex-col items-stretch gap-3 rounded-[2rem] border-2 border-gray-200 bg-surface p-3 text-graphite shadow-soft lg:flex-row lg:items-center">
                        {/* Barra de búsqueda */}
                        <form onSubmit={handleSearch} className="order-2 w-full lg:order-3 lg:ml-auto lg:w-80 lg:flex-none">
                            <div className="relative">
                                <motion.input
                                    type="text"
                                    placeholder="Buscar productos..."
                                    aria-label="Buscar productos de pirotecnia fría"
                                    value={searchTerm}
                                    onChange={(e) => setSearchTerm(e.target.value)}
                                    className="h-12 w-full rounded-full border-2 border-gray-200 bg-surface py-3 pl-11 pr-4 text-sm text-graphite outline-none transition placeholder:text-navy-900/70 focus:border-navy-900 focus:ring-2 focus:ring-navy-900"
                                    whileFocus={{ scale: 1.01 }}
                                />
                                <svg className="absolute left-4 top-3.5 h-5 w-5 text-navy-900/65" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                </svg>
                            </div>
                        </form>

                        {/* Filtro por categorías */}
                        <div className="order-1 flex flex-wrap gap-2 lg:flex-1">
                            {/* Si hay subcategorías seleccionadas, mostrar botón para volver */}
                            {selectedSubcategories?.length > 0 ? (
                                <>
                                    <motion.button
                                        onClick={goBackToMainCategories}
                                        className="flex items-center gap-2 rounded-full border border-navy-900/10 bg-navy-900/5 px-4 py-2.5 text-sm font-semibold text-navy-900 transition hover:border-navy-900/20 hover:bg-navy-900/10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-navy-900 focus-visible:ring-offset-2 focus-visible:ring-offset-white"
                                        whileHover={!reducedMotion ? { scale: 1.02 } : {}}
                                        whileTap={!reducedMotion ? { scale: 0.98 } : {}}
                                    >
                                        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 19l-7-7 7-7" />
                                        </svg>
                                        Categorías
                                    </motion.button>
                                    
                                    {/* Mostrar categoría principal seleccionada */}
                                    <div className="rounded-full border border-ice-500/30 bg-ice-100 px-4 py-2.5 text-sm font-semibold text-navy-900">
                                        {selectedMainCategory?.name}
                                    </div>
                                    
                                    {/* Mostrar subcategorías */}
                                    <motion.button
                                        onClick={() => handleCategoryFilter(selectedMainCategory?.slug)}
                                        className={`rounded-full px-4 py-2.5 text-sm font-semibold transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-navy-900 focus-visible:ring-offset-2 focus-visible:ring-offset-white ${
                                            selectedCategory === selectedMainCategory?.slug
                                                ? 'border border-storefront bg-storefront text-white shadow-sm'
                                                : 'border border-gray-200 bg-ice-50 text-graphite hover:border-ice-500 hover:bg-ice-100'
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
                                            className={`rounded-full px-4 py-2.5 text-sm font-semibold transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-navy-900 focus-visible:ring-offset-2 focus-visible:ring-offset-white ${
                                                selectedCategory === subcategory.slug
                                                    ? 'border border-storefront bg-storefront text-white shadow-sm'
                                                    : 'border border-gray-200 bg-ice-50 text-graphite hover:border-ice-500 hover:bg-ice-100'
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
                                        className={`rounded-full px-4 py-2.5 text-sm font-semibold transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-navy-900 focus-visible:ring-offset-2 focus-visible:ring-offset-white ${
                                            !selectedCategory 
                                                ? 'border border-storefront bg-storefront text-white shadow-sm'
                                                : 'border border-gray-200 bg-ice-50 text-graphite hover:border-ice-500 hover:bg-ice-100'
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
                                            className={`rounded-full px-4 py-2.5 text-sm font-semibold transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-navy-900 focus-visible:ring-offset-2 focus-visible:ring-offset-white ${
                                                selectedCategory === category.slug
                                                    ? 'border border-storefront bg-storefront text-white shadow-sm'
                                                    : 'border border-gray-200 bg-ice-50 text-graphite hover:border-ice-500 hover:bg-ice-100'
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
                        </div>

                        {/* Limpiar filtros */}
                        {(searchTerm || selectedCategory) && (
                            <motion.button
                                onClick={clearFilters}
                                className="order-1 self-center whitespace-nowrap rounded-full px-3 py-2 text-sm font-semibold text-navy-900/70 transition hover:text-navy-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-navy-900 focus-visible:ring-offset-2 focus-visible:ring-offset-white lg:order-2"
                                whileHover={!reducedMotion ? { scale: 1.02 } : {}}
                                whileTap={!reducedMotion ? { scale: 0.98 } : {}}
                            >
                                Limpiar filtros
                            </motion.button>
                        )}
                    </div>
                </div>
            </div>

            {/* Lista de productos */}
            <main className="min-h-[50vh] pb-16 pt-3 sm:pb-20">
                <div className="site-shell">
                    {products.data.length > 0 ? (
                        <>
                            <div
                                className="grid grid-cols-1 gap-4 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-5 lg:gap-5"
                                key={selectedCategory}
                            >
                                {products.data.map((product) => {
                                    const pricing = product.pricing || {};
                                    const hasDiscount = Boolean(pricing.has_discount);
                                    const price = pricing.final_price ?? product.price;
                                    const listPrice = pricing.list_price ?? product.price;
                                    const image = getPrimaryImageUrl(product);

                                    return (
                                        <motion.article
                                            key={product.id}
                                            whileHover={!reducedMotion ? { y: -4 } : {}}
                                            className="group flex h-full min-w-0 flex-col overflow-hidden rounded-[1.75rem] border border-gray-200 bg-surface text-navy-900 shadow-card transition-shadow hover:border-ice-500 hover:shadow-card-hover"
                                        >
                                            <Link
                                                href={route('products.show', product.id)}
                                                className="relative m-2 block aspect-[5/4] overflow-hidden rounded-[1.35rem] bg-background/70 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-navy-900"
                                                aria-label={`Ver ${product.title}`}
                                            >
                                                {image ? (
                                                    <img src={image} alt={product.title} loading="lazy" className="h-full w-full object-contain transition-transform duration-500 group-hover:scale-105" />
                                                ) : (
                                                    <div className="flex h-full w-full items-center justify-center bg-navy-900/5">
                                                        <svg className="h-12 w-12 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                                        </svg>
                                                    </div>
                                                )}
                                                {hasDiscount && (
                                                    <span className="absolute right-3 top-3 rounded-full bg-promo px-2 py-1 text-xs font-bold text-navy-900 shadow-lg">
                                                        -{Number(pricing.savings_percentage).toLocaleString('es-AR')}%
                                                    </span>
                                                )}
                                            </Link>

                                            <div className="flex min-w-0 flex-1 flex-col px-5 pb-3 pt-2">
                                                <div className="flex min-h-7 flex-wrap items-center gap-2">
                                                    {product.category && (
                                                        <span className="rounded-full bg-ice-100 px-2.5 py-1 text-[0.7rem] font-bold uppercase tracking-wide text-navy-700">
                                                            {product.category.parent?.name || product.category.name}
                                                        </span>
                                                    )}
                                                    {product.category?.parent && <span className="text-xs font-medium text-navy-900/70">{product.category.name}</span>}
                                                </div>

                                                <Link href={route('products.show', product.id)} className="mt-2 min-h-[3.25rem] focus-visible:underline">
                                                    <h2 className="uppercase line-clamp-2 text-lg font-bold leading-snug text-navy-900">{product.title}</h2>
                                                </Link>
                                                <p className="min-h-9 line-clamp-2 text-sm leading-tight text-navy-900/65">{getDescriptionPreview(product.description)}</p>

                                                <div className="mt-auto flex flex-col pt-2">
                                                    <div className="min-h-12">
                                                        <div className="flex flex-wrap items-baseline gap-x-2">
                                                            <span className="text-2xl font-bold text-navy-900">${Number(price).toLocaleString('es-AR')}</span>
                                                            <span className="text-xs font-medium text-navy-700">ARS</span>
                                                            {hasDiscount && <span className="text-sm text-navy-900/70 line-through">${Number(listPrice).toLocaleString('es-AR')}</span>}
                                                        </div>
                                                        {hasDiscount && <p className="text-xs font-medium text-navy-700">Ahorrás ${Number(pricing.savings_amount).toLocaleString('es-AR')}</p>}
                                                    </div>

                                                    <div className="mt-2 flex min-h-12 items-center justify-between gap-2">
                                                        <span className="text-xs font-bold uppercase tracking-wide text-navy-900/70">Cantidad</span>
                                                        <div className="flex items-center overflow-hidden rounded-full border border-navy-900 bg-background/60" role="group" aria-label={`Cantidad de ${product.title}`}>
                                                            <button
                                                                type="button"
                                                                onClick={() => decrementQuantity(product.id)}
                                                                disabled={getQuantity(product.id) <= 1}
                                                                className="flex h-9 w-9 items-center justify-center bg-navy-900/5 text-navy-900 transition-colors hover:bg-navy-900/10 disabled:cursor-not-allowed disabled:opacity-35"
                                                                aria-label={`Restar una unidad de ${product.title}`}
                                                            >−</button>
                                                            <span className="min-w-10 px-2 text-center text-sm font-semibold text-navy-900">{getQuantity(product.id)}</span>
                                                            <button
                                                                type="button"
                                                                onClick={() => incrementQuantity(product.id, product.stock)}
                                                                disabled={getQuantity(product.id) >= Math.min(99, Number(product.stock))}
                                                                className="flex h-9 w-9 items-center justify-center bg-navy-900/5 text-navy-900 transition-colors hover:bg-navy-900/10 disabled:cursor-not-allowed disabled:opacity-35"
                                                                aria-label={`Sumar una unidad de ${product.title}`}
                                                            >+</button>
                                                        </div>
                                                    </div>

                                                    <div className="mt-2 grid grid-cols-2 gap-2">
                                                        <button
                                                            type="button"
                                                            onClick={() => addToCart(product)}
                                                            disabled={addingId === product.id || product.stock <= 0}
                                                            className={`inline-flex min-h-11 items-center justify-center rounded-full px-2 py-2 text-center text-xs font-semibold leading-tight transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-storefront disabled:cursor-not-allowed ${product.stock <= 0 ? 'bg-gray-200 text-graphite/75' : 'bg-storefront text-white hover:brightness-90'}`}
                                                            aria-label={`Agregar ${product.title} al carrito`}
                                                        >{addingId === product.id ? 'Agregando...' : product.stock <= 0 ? 'Sin stock' : 'Agregar al carrito'}</button>
                                                        <Link href={route('products.show', product.id)} className="inline-flex min-h-11 items-center justify-center rounded-full border border-storefront bg-surface px-2 py-2 text-center text-xs font-semibold leading-tight text-storefront transition-colors hover:bg-storefront hover:text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-storefront">Ver producto</Link>
                                                    </div>
                                                </div>
                                            </div>
                                        </motion.article>
                                    );
                                })}
                            </div>

                            {/* Paginación */}
                            {products.links.length > 3 && (
                                <div className="mt-12 flex justify-center">
                                    <nav className="flex flex-wrap justify-center gap-2">
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
                                                        className={`inline-flex min-w-10 items-center justify-center rounded-full border px-4 py-2 text-sm font-semibold transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white focus-visible:ring-offset-2 focus-visible:ring-offset-storefront ${
                                                            link.active 
                                                                ? 'border-white bg-white text-navy-900 shadow-sm'
                                                                : 'border-white/50 bg-transparent text-white hover:border-white hover:bg-navy-900/15'
                                                        }`}
                                                        aria-current={link.active ? 'page' : undefined}
                                                    >{label}</Link>
                                                </motion.div>
                                            ) : (
                                                <span 
                                                    key={index}
                                                    className="cursor-not-allowed px-4 py-2 text-white/65"
                                                    aria-disabled="true"
                                                >{label}</span>
                                            );
                                        })}
                                    </nav>
                                </div>
                            )}
                        </>
                    ) : (
                        <div className="text-center py-16">
                            <svg className="h-16 w-16 text-white mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
                            </svg>
                            <h3 className="uppercase text-xl font-semibold text-white mb-2">
                                No se encontraron productos
                            </h3>
                            <p className="text-white mb-6">
                                {searchTerm || selectedCategory 
                                    ? 'Prueba ajustando tus filtros de búsqueda.' 
                                    : 'Actualmente no hay productos disponibles.'
                                }
                            </p>
                            {(searchTerm || selectedCategory) && (
                                <motion.button
                                    onClick={clearFilters}
                                    className="rounded-full border border-white bg-white px-6 py-3 font-semibold text-navy-900 transition hover:bg-ice-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white focus-visible:ring-offset-2 focus-visible:ring-offset-storefront"
                                    whileHover={!reducedMotion ? { scale: 1.05 } : {}}
                                    whileTap={!reducedMotion ? { scale: 0.95 } : {}}
                                >
                                    Ver todos los productos
                                </motion.button>
                            )}
                        </div>
                    )}
                </div>
            </main>

            <Footer />
            <CartButton />
            <WhatsAppButton />
        </div>
    );
}
