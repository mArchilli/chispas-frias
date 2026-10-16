import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { motion } from 'framer-motion';
import Navbar from '@/Components/Navbar';
import Footer from '@/Components/Footer';
import WhatsAppButton from '@/Components/WhatsAppButton';
import CartButton from '@/Components/CartButton';
import ProductCard from '@/Components/ProductCard';
import { useReducedMotion } from '@/hooks/useAnimations';
import { getProductImageUrl } from '@/utils/images';

export default function ProductsIndex({ auth, products, combos = [], categories, selectedMainCategory, selectedSubcategories, filters }) {
    const [searchTerm, setSearchTerm] = useState(filters.search || '');
    const [selectedCategory, setSelectedCategory] = useState(filters.category || '');
    const reducedMotion = useReducedMotion();

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
                    {/* Combos: sección diferenciada, sólo en la vista sin filtros */}
                    {combos.length > 0 && (
                        <section aria-labelledby="combos-title" className="mb-10 sm:mb-12">
                            <div className="mb-5 flex items-center gap-3">
                                <span className="rounded-full bg-promo px-3 py-1 text-xs font-bold uppercase tracking-wide text-navy-900">Combos</span>
                                <h2 id="combos-title" className="uppercase text-2xl font-bold text-white sm:text-3xl">Combos armados</h2>
                            </div>
                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-5 lg:gap-5">
                                {combos.map((combo) => {
                                    const image = getProductImageUrl(combo.image);

                                    return (
                                        <motion.article
                                            key={`combo-${combo.id}`}
                                            whileHover={!reducedMotion ? { y: -4 } : {}}
                                            className="group flex h-full min-w-0 flex-col overflow-hidden rounded-[1.75rem] border-2 border-promo bg-surface text-navy-900 shadow-card transition-shadow hover:shadow-card-hover"
                                        >
                                            <Link
                                                href={route('combos.show', combo.id)}
                                                className="relative m-2 block aspect-[5/4] overflow-hidden rounded-[1.35rem] bg-background/70 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-navy-900"
                                                aria-label={`Ver ${combo.title}`}
                                            >
                                                {image ? (
                                                    <img src={image} alt={combo.title} loading="lazy" className="h-full w-full object-contain transition-transform duration-500 group-hover:scale-105" />
                                                ) : (
                                                    <div className="flex h-full w-full items-center justify-center bg-navy-900/5 text-sm text-navy-700">Combo</div>
                                                )}
                                                <span className="absolute left-3 top-3 rounded-full bg-promo px-2.5 py-1 text-xs font-bold uppercase tracking-wide text-navy-900 shadow-lg">Combo</span>
                                                {combo.is_free_shipping && (
                                                    <span className="absolute right-3 top-3 rounded-full bg-storefront px-2.5 py-1 text-xs font-bold text-white shadow-lg">Envío gratis</span>
                                                )}
                                            </Link>

                                            <div className="flex min-w-0 flex-1 flex-col px-5 pb-3 pt-2">
                                                <Link href={route('combos.show', combo.id)} className="mt-2 min-h-[3.25rem] focus-visible:underline">
                                                    <h3 className="uppercase line-clamp-2 text-lg font-bold leading-snug text-navy-900">{combo.title}</h3>
                                                </Link>

                                                <div className="mt-auto flex flex-col pt-2">
                                                    <div className="min-h-12">
                                                        <div className="flex flex-wrap items-baseline gap-x-2">
                                                            <span className="text-2xl font-bold text-navy-900">${Number(combo.price).toLocaleString('es-AR')}</span>
                                                            <span className="text-xs font-medium text-navy-700">ARS</span>
                                                        </div>
                                                        <p className="text-xs font-medium text-navy-700">Precio del combo completo</p>
                                                    </div>

                                                    <Link
                                                        href={route('combos.show', combo.id)}
                                                        className="mt-2 inline-flex min-h-11 items-center justify-center rounded-full bg-storefront px-4 py-2 text-center text-xs font-semibold leading-tight text-white transition-colors hover:brightness-90 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-storefront"
                                                    >
                                                        Ver combo
                                                    </Link>
                                                </div>
                                            </div>
                                        </motion.article>
                                    );
                                })}
                            </div>
                        </section>
                    )}

                    {products.data.length > 0 ? (
                        <>
                            <div
                                className="grid auto-rows-fr grid-cols-1 gap-4 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-5 lg:gap-5"
                                key={selectedCategory}
                            >
                                {products.data.map((product) => <ProductCard key={product.id} product={product} />)}
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
