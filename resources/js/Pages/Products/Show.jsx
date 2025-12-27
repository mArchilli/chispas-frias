import { Head, Link, useForm } from '@inertiajs/react';
import { useState } from 'react';
import Navbar from '@/Components/Navbar';
import Footer from '@/Components/Footer';
import WhatsAppButton from '@/Components/WhatsAppButton';

export default function ProductShow({ auth, product, relatedProducts }) {
    const [selectedImage, setSelectedImage] = useState(0);
    const [quantity, setQuantity] = useState(1);
    const [showZoom, setShowZoom] = useState(false);
    const [zoomPosition, setZoomPosition] = useState({ x: 0, y: 0 });

    const { data, setData, post, processing } = useForm({
        product_id: product.id,
        quantity: 1
    });

    // Función para agregar al carrito
    const handleAddToCart = (e) => {
        e.preventDefault();
        
        // Asegurar que tenemos la cantidad correcta antes de enviar
        const formData = {
            product_id: product.id,
            quantity: quantity
        };
        
        post(route('cart.add'), {
            data: formData,
            preserveScroll: true,
            onSuccess: () => {
                // Resetear cantidad a 1 después de agregar
                setQuantity(1);
                setData('quantity', 1);
                
                // Disparar evento para actualizar el contador del navbar
                window.dispatchEvent(new CustomEvent('cart-updated'));
            }
        });
    };

    // Función para manejar cambio de cantidad
    const handleQuantityChange = (newQuantity) => {
        if (newQuantity >= 1) {
            setQuantity(newQuantity);
            setData('quantity', newQuantity); // Sincronizar con el formulario
        }
    };

    // Función para obtener la URL de la imagen/video
    const getImageUrl = (image) => {
        if (!image) return null;
        return image.url || image.path;
    };

    // Función para verificar si un archivo es un video
    const isVideo = (media) => {
        return media.type === 'video' || (media.mime_type && media.mime_type.startsWith('video/'));
    };

    // Función para renderizar media (imagen o video)
    const renderMedia = (media, className = "w-full h-96 object-contain") => {
        if (isVideo(media)) {
            return (
                <video
                    src={getImageUrl(media)}
                    className={className}
                    controls
                    muted
                    playsInline
                >
                    Tu navegador no soporta el elemento de video.
                </video>
            );
        }
        
        return (
            <img
                src={getImageUrl(media)}
                alt={media.alt_text || "Imagen del producto"}
                className={className}
            />
        );
    };

    // Función para renderizar thumbnail de media
    const renderMediaThumbnail = (media, className = "w-full h-24 object-cover") => {
        if (isVideo(media)) {
            return (
                <div className="relative">
                    <video
                        src={getImageUrl(media)}
                        className={className}
                        muted
                        playsInline
                    />
                    <div className="absolute inset-0 flex items-center justify-center bg-black bg-opacity-30">
                        <svg className="w-8 h-8 text-white" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M8 5v14l11-7z"/>
                        </svg>
                    </div>
                </div>
            );
        }
        
        return (
            <img
                src={getImageUrl(media)}
                alt={media.alt_text || "Thumbnail"}
                className={className}
            />
        );
    };

    // Función para obtener la URL de la imagen principal de un producto
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

    // Funciones para el efecto de zoom
    const handleMouseMove = (e) => {
        const rect = e.currentTarget.getBoundingClientRect();
        const x = ((e.clientX - rect.left) / rect.width) * 100;
        const y = ((e.clientY - rect.top) / rect.height) * 100;
        setZoomPosition({ x, y });
    };

    const handleMouseEnter = () => {
        setShowZoom(true);
    };

    const handleMouseLeave = () => {
        setShowZoom(false);
    };

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
            <main className="bg-chalk pb-12">
                <div className="max-w-7xl mx-auto px-6 lg:px-8">
                    <div className="grid grid-cols-1 lg:grid-cols-2 gap-12">
                        {/* Galería de imágenes */}
                        <div className="space-y-4">
                            {/* Botón volver */}
                            <div className="mb-6">
                                <button
                                    onClick={() => window.history.back()}
                                    className="flex items-center gap-2 px-4 py-2 bg-navy/10 text-navy rounded-lg font-medium transition hover:bg-navy/20 hover:scale-105"
                                >
                                    <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 19l-7-7 7-7" />
                                    </svg>
                                    Volver
                                </button>
                            </div>
                            
                            {/* Imagen principal con efecto de zoom */}
                            <div 
                                className={`aspect-w-4 aspect-h-3 bg-gray-100 rounded-lg overflow-hidden relative ${
                                    product.images?.length > 0 && !isVideo(product.images[selectedImage]) 
                                        ? 'cursor-zoom-in' 
                                        : ''
                                }`}
                                onMouseMove={
                                    product.images?.length > 0 && !isVideo(product.images[selectedImage]) 
                                        ? handleMouseMove 
                                        : undefined
                                }
                                onMouseEnter={
                                    product.images?.length > 0 && !isVideo(product.images[selectedImage]) 
                                        ? handleMouseEnter 
                                        : undefined
                                }
                                onMouseLeave={
                                    product.images?.length > 0 && !isVideo(product.images[selectedImage]) 
                                        ? handleMouseLeave 
                                        : undefined
                                }
                            >
                                {product.images?.length > 0 ? (
                                    <>
                                        {renderMedia(product.images[selectedImage], "w-full h-96 object-contain transition-transform duration-200 ease-out" + (showZoom ? " scale-150" : ""))}
                                        
                                        {/* Lupa de zoom */}
                                        {showZoom && !isVideo(product.images[selectedImage]) && (
                                            <div 
                                                className="absolute inset-0 pointer-events-none"
                                                style={{
                                                    background: `url(${getImageUrl(product.images[selectedImage])}) ${zoomPosition.x}% ${zoomPosition.y}% / 200%`,
                                                    backgroundRepeat: 'no-repeat',
                                                    clipPath: `circle(100px at ${zoomPosition.x}% ${zoomPosition.y}%)`,
                                                    border: '3px solid rgba(255, 215, 0, 0.8)',
                                                    borderRadius: '50%'
                                                }}
                                            />
                                        )}
                                    </>
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
                                            {renderMediaThumbnail(image, "w-full h-24 object-cover")}
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

                            {/* Precio */}
                            <div className="py-6 border-y border-navy/10">
                                <span className="text-3xl font-bold text-navy">
                                    ${Number(product.price).toLocaleString('es-CL')}
                                </span>
                            </div>

                            {/* Descripción */}
                            <div className="space-y-4">
                                <h2 className="text-xl font-semibold text-navy">
                                    Descripción
                                </h2>
                                <div 
                                    className="text-navy/80 leading-relaxed prose prose-sm max-w-none prose-headings:text-navy prose-strong:text-navy prose-a:text-gold hover:prose-a:text-gold/80"
                                    dangerouslySetInnerHTML={{ __html: product.description }}
                                />
                            </div>

                            {/* Acciones */}
                            <div className="space-y-4 pt-6">
                                {/* Selector de cantidad */}
                                <div className="flex items-center space-x-4">
                                    <label className="text-sm font-medium text-navy">
                                        Cantidad:
                                    </label>
                                    <div className="flex items-center border border-navy/20 rounded-lg">
                                        <button
                                            type="button"
                                            onClick={() => handleQuantityChange(quantity - 1)}
                                            disabled={quantity <= 1}
                                            className="px-3 py-2 text-navy hover:bg-navy/10 disabled:opacity-50 disabled:cursor-not-allowed"
                                        >
                                            −
                                        </button>
                                        <span className="px-4 py-2 text-navy font-medium min-w-[3rem] text-center">
                                            {quantity}
                                        </span>
                                        <button
                                            type="button"
                                            onClick={() => handleQuantityChange(quantity + 1)}
                                            className="px-3 py-2 text-navy hover:bg-navy/10"
                                        >
                                            +
                                        </button>
                                    </div>
                                </div>

                                {/* Botón agregar al carrito */}
                                <button 
                                    onClick={handleAddToCart}
                                    disabled={processing}
                                    className={`w-full py-4 font-bold rounded-lg transition-colors ${
                                        processing
                                            ? 'bg-gold/70 text-navy cursor-wait'
                                            : 'bg-gold text-navy hover:bg-gold/90'
                                    }`}
                                >
                                    {processing ? (
                                        <span className="flex items-center justify-center">
                                            <svg className="animate-spin -ml-1 mr-2 h-4 w-4 text-navy" fill="none" viewBox="0 0 24 24">
                                                <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                                                <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                                            </svg>
                                            Agregando...
                                        </span>
                                    ) : (
                                        'Agregar al carrito'
                                    )}
                                </button>
                            </div>
                        </div>
                    </div>

                    {/* Productos relacionados */}
                    <div className="mt-20 border-t border-navy/10 pt-16">
                        <div className="text-center mb-12">
                            <h2 className="text-3xl font-bold text-navy mb-4">
                                Productos relacionados
                            </h2>
                            <p className="text-navy/70 max-w-2xl mx-auto">
                                Descubre otros productos que podrían interesarte de la misma categoría
                            </p>
                        </div>

                        <div className="grid grid-cols-1 md:grid-cols-3 gap-8">
                                {relatedProducts.map((relatedProduct) => (
                                    <div key={relatedProduct.id} className="group">
                                        <Link
                                            href={route('products.show', relatedProduct.id)}
                                            className="block bg-white rounded-lg shadow-lg overflow-hidden hover:shadow-xl transition-all duration-300 transform group-hover:-translate-y-1"
                                        >
                                            {/* Imagen */}
                                            <div className="relative aspect-w-4 aspect-h-3 bg-gray-100 overflow-hidden">
                                                {relatedProduct.images?.length > 0 ? (
                                                    <img
                                                        src={getPrimaryImageUrl(relatedProduct)}
                                                        alt={relatedProduct.title}
                                                        className="w-full h-48 object-cover transition-transform duration-300 group-hover:scale-105"
                                                    />
                                                ) : (
                                                    <div className="w-full h-48 bg-gray-200 flex items-center justify-center">
                                                        <svg className="h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                                        </svg>
                                                    </div>
                                                )}
                                                
                                                {/* Badge de categoría */}
                                                <div className="absolute top-3 left-3">
                                                    <span className="bg-gold text-navy text-xs font-medium px-2 py-1 rounded">
                                                        {relatedProduct.category?.parent?.name || relatedProduct.category?.name}
                                                    </span>
                                                </div>
                                            </div>

                                            {/* Información */}
                                            <div className="p-6">
                                                {/* Subcategoría si existe */}
                                                {relatedProduct.category?.parent && (
                                                    <div className="text-sm text-gold font-medium mb-2">
                                                        {relatedProduct.category.name}
                                                    </div>
                                                )}

                                                {/* Título */}
                                                <h3 className="font-bold text-navy mb-3 line-clamp-2 group-hover:text-gold transition-colors">
                                                    {relatedProduct.title}
                                                </h3>

                                                {/* Precio */}
                                                <div className="flex items-center justify-between">
                                                    <span className="text-2xl font-bold text-navy">
                                                        ${Number(relatedProduct.price).toLocaleString('es-CL')}
                                                    </span>
                                                    
                                                    {/* Botón de acción */}
                                                    <div className="opacity-0 group-hover:opacity-100 transition-opacity duration-300">
                                                        <span className="bg-navy text-chalk text-sm font-medium px-3 py-1 rounded-lg">
                                                            Ver detalles
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                        </Link>
                                    </div>
                                ))}
                            </div>

                        {/* Ver más productos */}
                        <div className="text-center mt-12">
                            <Link
                                href={`${route('products.index')}?category=${product.category?.parent?.slug || product.category?.slug}`}
                                className="inline-flex items-center px-8 py-3 border-2 border-navy text-navy font-semibold rounded-lg hover:bg-navy hover:text-chalk transition-colors"
                            >
                                Ver más productos de esta categoría
                                <svg className="ml-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M17 8l4 4m0 0l-4 4m4-4H3" />
                                </svg>
                            </Link>
                        </div>
                    </div>
                </div>
            </main>

            <Footer />
            <WhatsAppButton />
        </>
    );
}