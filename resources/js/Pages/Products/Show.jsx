import { Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import Navbar from '@/Components/Navbar';
import Footer from '@/Components/Footer';

export default function ProductShow({ auth, product, relatedProducts }) {
    const [selectedImage, setSelectedImage] = useState(0);

    return (
        <>
            <Head title={`${product.title} - Chispas Frías`} />
            
            <Navbar auth={auth} />
            
            {/* Breadcrumbs */}
            <div className="bg-chalk pt-20 pb-8">
                <div className="max-w-7xl mx-auto px-6 lg:px-8">
                    <nav className="text-sm text-navy/60">
                        <Link href="/" className="hover:text-navy">Inicio</Link>
                        <span className="mx-2">•</span>
                        <Link href={route('products.index')} className="hover:text-navy">Productos</Link>
                        <span className="mx-2">•</span>
                        <span className="text-navy">
                            {product.category?.parent?.name || product.category?.name}
                        </span>
                        {product.category?.parent && (
                            <>
                                <span className="mx-2">•</span>
                                <span className="text-navy">{product.category.name}</span>
                            </>
                        )}
                    </nav>
                </div>
            </div>

            {/* Detalle del producto */}
            <main className="bg-chalk py-12">
                <div className="max-w-7xl mx-auto px-6 lg:px-8">
                    <div className="grid grid-cols-1 lg:grid-cols-2 gap-12">
                        {/* Galería de imágenes */}
                        <div className="space-y-4">
                            {/* Imagen principal */}
                            <div className="aspect-w-4 aspect-h-3 bg-gray-100 rounded-lg overflow-hidden">
                                {product.images?.length > 0 ? (
                                    <img
                                        src={`/storage/${product.images[selectedImage]?.path}`}
                                        alt={product.title}
                                        className="w-full h-96 object-cover"
                                    />
                                ) : (
                                    <div className="w-full h-96 bg-gray-200 flex items-center justify-center">
                                        <svg className="h-24 w-24 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                        </svg>
                                    </div>
                                )}
                            </div>

                            {/* Miniaturas */}
                            {product.images?.length > 1 && (
                                <div className="grid grid-cols-4 gap-4">
                                    {product.images.map((image, index) => (
                                        <button
                                            key={image.id}
                                            onClick={() => setSelectedImage(index)}
                                            className={`aspect-w-1 aspect-h-1 rounded-lg overflow-hidden border-2 transition ${
                                                selectedImage === index 
                                                    ? 'border-gold' 
                                                    : 'border-transparent hover:border-navy/20'
                                            }`}
                                        >
                                            <img
                                                src={`/storage/${image.path}`}
                                                alt={`${product.title} - Imagen ${index + 1}`}
                                                className="w-full h-20 object-cover"
                                            />
                                        </button>
                                    ))}
                                </div>
                            )}
                        </div>

                        {/* Información del producto */}
                        <div className="space-y-6">
                            {/* Categoría */}
                            <div className="flex items-center text-gold font-medium">
                                <span>{product.category?.parent?.name || product.category?.name}</span>
                                {product.category?.parent && (
                                    <>
                                        <span className="mx-2 text-navy/40">•</span>
                                        <span className="text-navy/60">{product.category.name}</span>
                                    </>
                                )}
                            </div>

                            {/* Título */}
                            <h1 className="text-3xl md:text-4xl font-bold text-navy">
                                {product.title}
                            </h1>

                            {/* SKU */}
                            {product.sku && (
                                <p className="text-sm text-navy/60">
                                    SKU: {product.sku}
                                </p>
                            )}

                            {/* Precio y stock */}
                            <div className="flex items-center justify-between py-6 border-y border-navy/10">
                                <div>
                                    <span className="text-3xl font-bold text-navy">
                                        ${Number(product.price).toLocaleString('es-CL')}
                                    </span>
                                </div>
                                <div className="text-right">
                                    {product.stock > 0 ? (
                                        <div>
                                            <span className="block text-green-600 font-medium">
                                                En stock
                                            </span>
                                            <span className="text-sm text-navy/60">
                                                {product.stock} unidades disponibles
                                            </span>
                                        </div>
                                    ) : (
                                        <span className="text-red-600 font-medium">
                                            Sin stock
                                        </span>
                                    )}
                                </div>
                            </div>

                            {/* Descripción */}
                            <div className="space-y-4">
                                <h2 className="text-xl font-semibold text-navy">
                                    Descripción
                                </h2>
                                <div className="text-navy/80 leading-relaxed whitespace-pre-line">
                                    {product.description}
                                </div>
                            </div>

                            {/* Acciones */}
                            <div className="space-y-4 pt-6">
                                <button className="w-full py-4 bg-gold text-navy font-bold rounded-lg hover:bg-gold/90 transition-colors">
                                    Solicitar Cotización
                                </button>
                                <div className="grid grid-cols-2 gap-4">
                                    <button className="py-3 border border-navy text-navy font-medium rounded-lg hover:bg-navy hover:text-chalk transition-colors">
                                        Contactar por WhatsApp
                                    </button>
                                    <button className="py-3 border border-navy text-navy font-medium rounded-lg hover:bg-navy hover:text-chalk transition-colors">
                                        Añadir a favoritos
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* Productos relacionados */}
                    {relatedProducts.length > 0 && (
                        <div className="mt-20">
                            <h2 className="text-2xl font-bold text-navy mb-8">
                                Productos relacionados
                            </h2>
                            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                                {relatedProducts.map((relatedProduct) => (
                                    <Link
                                        key={relatedProduct.id}
                                        href={route('products.show', relatedProduct.id)}
                                        className="bg-white rounded-lg shadow-lg overflow-hidden hover:shadow-xl transition-shadow duration-300"
                                    >
                                        {/* Imagen */}
                                        <div className="aspect-w-4 aspect-h-3 bg-gray-100">
                                            {relatedProduct.images?.length > 0 ? (
                                                <img
                                                    src={`/storage/${relatedProduct.images[0].path}`}
                                                    alt={relatedProduct.title}
                                                    className="w-full h-40 object-cover"
                                                />
                                            ) : (
                                                <div className="w-full h-40 bg-gray-200 flex items-center justify-center">
                                                    <svg className="h-8 w-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                                    </svg>
                                                </div>
                                            )}
                                        </div>

                                        {/* Información */}
                                        <div className="p-4">
                                            <h3 className="font-semibold text-navy mb-2">
                                                {relatedProduct.title}
                                            </h3>
                                            <div className="flex items-center justify-between">
                                                <span className="text-lg font-bold text-navy">
                                                    ${Number(relatedProduct.price).toLocaleString('es-CL')}
                                                </span>
                                                {relatedProduct.stock > 0 ? (
                                                    <span className="text-sm text-green-600">
                                                        En stock
                                                    </span>
                                                ) : (
                                                    <span className="text-sm text-red-600">
                                                        Sin stock
                                                    </span>
                                                )}
                                            </div>
                                        </div>
                                    </Link>
                                ))}
                            </div>
                        </div>
                    )}
                </div>
            </main>

            <Footer />
        </>
    );
}