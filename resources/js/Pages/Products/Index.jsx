import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import Navbar from '@/Components/Navbar';
import Footer from '@/Components/Footer';

export default function ProductsIndex({ auth, products, categories, selectedMainCategory, selectedSubcategories, filters }) {
    const [searchTerm, setSearchTerm] = useState(filters.search || '');
    const [selectedCategory, setSelectedCategory] = useState(filters.category || '');

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
        <>
            <Head title="Productos - Chispas Frías" />
            
            <Navbar auth={auth} />
            
            {/* Hero Section */}
            <div className="bg-navy pt-20 pb-16">
                <div className="max-w-7xl mx-auto px-6 lg:px-8">
                    <div className="text-center">
                        <h1 className="text-4xl md:text-5xl font-bold text-chalk mb-6">
                            Nuestros Productos
                        </h1>
                        <p className="text-xl text-chalk/80 max-w-3xl mx-auto">
                            Descubre nuestra amplia gama de productos de pirotecnia fría, 
                            diseñados para crear momentos únicos y experiencias inolvidables.
                        </p>
                    </div>
                </div>
            </div>

            {/* Filtros y Búsqueda */}
            <div className="bg-chalk py-8">
                <div className="max-w-7xl mx-auto px-6 lg:px-8">
                    <div className="flex flex-col lg:flex-row gap-6 items-center justify-between">
                        {/* Barra de búsqueda */}
                        <form onSubmit={handleSearch} className="flex-1 max-w-md">
                            <div className="relative">
                                <input
                                    type="text"
                                    placeholder="Buscar productos..."
                                    value={searchTerm}
                                    onChange={(e) => setSearchTerm(e.target.value)}
                                    className="w-full px-4 py-3 pl-10 border border-navy/20 rounded-lg focus:outline-none focus:ring-2 focus:ring-gold focus:border-transparent"
                                />
                                <svg className="absolute left-3 top-3.5 h-5 w-5 text-navy/60" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                </svg>
                            </div>
                        </form>

                        {/* Filtro por categorías */}
                        <div className="flex flex-wrap gap-3">
                            {/* Si hay subcategorías seleccionadas, mostrar botón para volver */}
                            {selectedSubcategories?.length > 0 ? (
                                <>
                                    <button
                                        onClick={goBackToMainCategories}
                                        className="px-3 py-2 bg-navy/10 text-navy rounded-lg font-medium transition hover:bg-navy/20 flex items-center gap-2"
                                    >
                                        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 19l-7-7 7-7" />
                                        </svg>
                                        Categorías
                                    </button>
                                    
                                    {/* Mostrar categoría principal seleccionada */}
                                    <div className="px-4 py-2 bg-gold text-navy rounded-lg font-medium">
                                        {selectedMainCategory?.name}
                                    </div>
                                    
                                    {/* Mostrar subcategorías */}
                                    <button
                                        onClick={() => handleCategoryFilter(selectedMainCategory?.slug)}
                                        className={`px-4 py-2 rounded-lg font-medium transition ${
                                            selectedCategory === selectedMainCategory?.slug
                                                ? 'bg-gold text-navy'
                                                : 'bg-white text-navy hover:bg-gold/10'
                                        }`}
                                    >
                                        Todas las {selectedMainCategory?.name}
                                    </button>
                                    
                                    {selectedSubcategories.map((subcategory) => (
                                        <button
                                            key={subcategory.id}
                                            onClick={() => handleCategoryFilter(subcategory.slug)}
                                            className={`px-4 py-2 rounded-lg font-medium transition ${
                                                selectedCategory === subcategory.slug
                                                    ? 'bg-gold text-navy'
                                                    : 'bg-white text-navy hover:bg-gold/10'
                                            }`}
                                        >
                                            {subcategory.name}
                                        </button>
                                    ))}
                                </>
                            ) : (
                                <>
                                    {/* Mostrar categorías principales */}
                                    <button
                                        onClick={() => handleCategoryFilter('')}
                                        className={`px-4 py-2 rounded-lg font-medium transition ${
                                            !selectedCategory 
                                                ? 'bg-gold text-navy' 
                                                : 'bg-white text-navy hover:bg-gold/10'
                                        }`}
                                    >
                                        Todas
                                    </button>
                                    {categories.map((category) => (
                                        <button
                                            key={category.id}
                                            onClick={() => handleCategoryFilter(category.slug)}
                                            className={`px-4 py-2 rounded-lg font-medium transition ${
                                                selectedCategory === category.slug
                                                    ? 'bg-gold text-navy'
                                                    : 'bg-white text-navy hover:bg-gold/10'
                                            }`}
                                        >
                                            {category.name}
                                            {category.children?.length > 0 && (
                                                <span className="ml-1 text-xs opacity-70">({category.children.length})</span>
                                            )}
                                        </button>
                                    ))}
                                </>
                            )}
                        </div>

                        {/* Limpiar filtros */}
                        {(searchTerm || selectedCategory) && (
                            <button
                                onClick={clearFilters}
                                className="text-navy/70 hover:text-navy font-medium underline"
                            >
                                Limpiar filtros
                            </button>
                        )}
                    </div>
                </div>
            </div>

            {/* Lista de productos */}
            <main className="bg-chalk py-12">
                <div className="max-w-7xl mx-auto px-6 lg:px-8">
                    {products.data.length > 0 ? (
                        <>
                            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-8">
                                {products.data.map((product) => (
                                    <div key={product.id} className="bg-white rounded-lg shadow-lg overflow-hidden hover:shadow-xl transition-shadow duration-300">
                                        {/* Imagen del producto */}
                                        <div className="aspect-w-4 aspect-h-3 bg-gray-100">
                                            {product.images?.length > 0 ? (
                                                <img
                                                    src={getPrimaryImageUrl(product)}
                                                    alt={product.title}
                                                    className="w-full h-48 object-cover"
                                                />
                                            ) : (
                                                <div className="w-full h-48 bg-gray-200 flex items-center justify-center">
                                                    <svg className="h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                                    </svg>
                                                </div>
                                            )}
                                        </div>

                                        {/* Información del producto */}
                                        <div className="p-6">
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
                                            <p className="text-navy/70 text-sm mb-4 line-clamp-3">
                                                {product.description}
                                            </p>

                                            {/* Precio y acciones */}
                                            <div className="flex items-center justify-between">
                                                <div className="flex flex-col">
                                                    <span className="text-2xl font-bold text-navy">
                                                        ${Number(product.price).toLocaleString('es-CL')}
                                                    </span>
                                                    {product.stock > 0 ? (
                                                        <span className="text-sm text-green-600">
                                                            En stock ({product.stock})
                                                        </span>
                                                    ) : (
                                                        <span className="text-sm text-red-600">
                                                            Sin stock
                                                        </span>
                                                    )}
                                                </div>

                                                <Link
                                                    href={route('products.show', product.id)}
                                                    className="px-4 py-2 bg-gold text-navy font-semibold rounded-lg hover:bg-gold/90 transition-colors"
                                                >
                                                    Ver detalles
                                                </Link>
                                            </div>
                                        </div>
                                    </div>
                                ))}
                            </div>

                            {/* Paginación */}
                            {products.links.length > 3 && (
                                <div className="mt-12 flex justify-center">
                                    <nav className="flex space-x-2">
                                        {products.links.map((link, index) => (
                                            link.url ? (
                                                <Link
                                                    key={index}
                                                    href={link.url}
                                                    className={`px-4 py-2 rounded-lg font-medium transition ${
                                                        link.active 
                                                            ? 'bg-gold text-navy' 
                                                            : 'bg-white text-navy hover:bg-gold/10'
                                                    }`}
                                                    dangerouslySetInnerHTML={{ __html: link.label }}
                                                />
                                            ) : (
                                                <span 
                                                    key={index}
                                                    className="px-4 py-2 text-navy/40"
                                                    dangerouslySetInnerHTML={{ __html: link.label }}
                                                />
                                            )
                                        ))}
                                    </nav>
                                </div>
                            )}
                        </>
                    ) : (
                        <div className="text-center py-16">
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
                                <button
                                    onClick={clearFilters}
                                    className="px-6 py-3 bg-gold text-navy font-semibold rounded-lg hover:bg-gold/90 transition-colors"
                                >
                                    Ver todos los productos
                                </button>
                            )}
                        </div>
                    )}
                </div>
            </main>

            <Footer />
        </>
    );
}