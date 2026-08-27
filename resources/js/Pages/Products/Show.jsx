import { Head, Link } from '@inertiajs/react';
import { useState, useEffect, useMemo } from 'react';
import axios from 'axios';
import toast from 'react-hot-toast';
import Navbar from '@/Components/Navbar';
import Footer from '@/Components/Footer';
import WhatsAppButton from '@/Components/WhatsAppButton';
import CartButton from '@/Components/CartButton';
import PriceTierPills from '@/Components/PriceTierPills';
import PriceTiersTable from '@/Components/PriceTiersTable';
import ProductOptions from '@/Components/ProductOptions';
import { calcularPrecio, precioAddon } from '@/utils/pricing';
import {
    opcionesIniciales,
    addonIdsSeleccionados,
    buildAddToCartPayload,
    galeriaDeVariante,
    stockVariante,
    hayVarianteConStock,
    validarOpciones,
} from '@/utils/productOptions';
import { isOutOfStock, isLowStock } from '@/utils/stock';

export default function ProductShow({ auth, product, relatedProducts }) {
    const [selectedImage, setSelectedImage] = useState(0);
    const [quantity, setQuantity] = useState(1);
    const [showZoom, setShowZoom] = useState(false);
    const [zoomPosition, setZoomPosition] = useState({ x: 0, y: 0 });
    const [showImageModal, setShowImageModal] = useState(false);
    const [isMobile, setIsMobile] = useState(false);

    // --- Opciones del producto (variante de color + add-ons) --------------------
    // Un producto sin variantes ni add-ons deja `options` con variantId null y
    // addons vacío, y todo lo de abajo degrada al comportamiento previo.
    const variants = product.variants || [];
    const addons = product.addons || [];
    const hasOptions = variants.length > 0 || addons.length > 0;

    const [options, setOptions] = useState(() => opcionesIniciales(product));

    const selectedVariant = variants.find((v) => v.id === options.variantId) || null;
    // `valido` gatea el botón "Agregar al carrito" para productos con opciones;
    // `errores` pinta los mensajes inline en ProductOptions.
    const { valido: opcionesValidas, errores: erroresOpciones } = validarOpciones(product, options);

    // Precio para la cantidad + opciones seleccionadas, resuelto en el cliente
    // (preview) con el mismo espejo de PricingService que usa el admin. El precio
    // que realmente se cobra siempre lo resuelve el backend al agregar al carrito.
    const pricing = calcularPrecio(product, quantity, {
        varianteId: options.variantId,
        addonIds: addonIdsSeleccionados(options),
    });

    // Galería reactiva: la media propia de la variante elegida, con las generales
    // como respaldo (ver galeriaDeVariante). Sin variantes => toda la media.
    const gallery = useMemo(
        () => galeriaDeVariante(product.images || [], options.variantId),
        [product.images, options.variantId]
    );
    const currentMedia = gallery[selectedImage] || gallery[0] || null;

    // Stock efectivo: el de la variante elegida (null = ilimitado) o el del
    // producto si no hay variantes.
    const effectiveStock = selectedVariant ? (selectedVariant.stock ?? 99) : product.stock;
    const productOutOfStock = variants.length > 0
        ? !hayVarianteConStock(variants)
        : isOutOfStock(product.stock);
    const selectedVariantUnavailable = !!selectedVariant && stockVariante(selectedVariant) <= 0;

    const [adding, setAdding] = useState(false);

    // Al cambiar de color, volver a la primera imagen de la galería nueva y
    // recapear la cantidad si la variante nueva tiene menos stock.
    useEffect(() => {
        setSelectedImage(0);
        setQuantity((q) => Math.max(1, Math.min(q, effectiveStock)));
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [options.variantId]);

    // Detectar si es dispositivo móvil
    useEffect(() => {
        const checkMobile = () => {
            setIsMobile(window.innerWidth < 768);
        };
        
        checkMobile();
        window.addEventListener('resize', checkMobile);
        
        return () => window.removeEventListener('resize', checkMobile);
    }, []);

    // Bloquear scroll cuando el modal está abierto
    useEffect(() => {
        if (showImageModal) {
            document.body.style.overflow = 'hidden';
        } else {
            document.body.style.overflow = 'unset';
        }
        
        return () => {
            document.body.style.overflow = 'unset';
        };
    }, [showImageModal]);

    // Función para agregar al carrito, con las opciones elegidas (color + add-ons
    // + color libre). El backend revalida todo antes de crear la línea.
    const handleAddToCart = async (e) => {
        e?.preventDefault?.();

        if (hasOptions && !opcionesValidas) {
            toast.error('Revisá el color y las personalizaciones marcadas.');
            return;
        }

        if (selectedVariantUnavailable) {
            toast.error('Este color está sin stock. Elegí otro para continuar.');
            return;
        }

        try {
            setAdding(true);
            await axios.post(route('cart.add'), buildAddToCartPayload(product, quantity, options));

            setQuantity(1);
            window.dispatchEvent(new CustomEvent('cart-updated'));
            toast.success(`${product.title} agregado al carrito (${quantity} ${quantity > 1 ? 'unidades' : 'unidad'})`);
        } catch (error) {
            toast.error(error?.response?.data?.message || 'Error al agregar el producto');
        } finally {
            setAdding(false);
        }
    };

    // Función para manejar cambio de cantidad, capeada al stock real disponible
    // (el de la variante elegida si hay una, si no el del producto).
    const handleQuantityChange = (newQuantity) => {
        if (newQuantity >= 1 && newQuantity <= effectiveStock) {
            setQuantity(newQuantity);
        }
    };

    // Función para obtener la URL de la imagen/video
    const getImageUrl = (image) => {
        if (!image) return null;
        const basePath = import.meta.env.VITE_PRODUCT_IMAGES_PATH || '/images/products/';
        const imagePath = image.url || image.path;
        
        // Si la ruta ya es completa (empieza con http o /), devolverla tal cual
        if (imagePath.startsWith('http') || imagePath.startsWith('/')) {
            return imagePath;
        }
        
        // Si no, construir la ruta con la base path y codificar
        const encodedPath = encodeURIComponent(imagePath);
        return `${basePath}${encodedPath}`;
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
                alt={media.alt_text || product.title}
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
                alt={media.alt_text || `Miniatura de ${product.title}`}
                className={className}
            />
        );
    };

    // Función para obtener la URL de la imagen principal de un producto
    const getPrimaryImageUrl = (product) => {
        const basePath = import.meta.env.VITE_PRODUCT_IMAGES_PATH || '/images/products/';
        
        // Si tiene la imagen principal directamente en product.image
        if (product.image) {
            const encodedImage = encodeURIComponent(product.image);
            return `${basePath}${encodedImage}`;
        }
        
        // Si no, buscar en el array de images
        if (!product.images || product.images.length === 0) {
            return null;
        }
        
        const primaryImage = product.images.find(img => img.is_primary) || product.images[0];
        const imagePath = primaryImage.url || primaryImage.path;
        
        // Si la ruta ya es completa, devolverla tal cual
        if (imagePath.startsWith('http') || imagePath.startsWith('/')) {
            return imagePath;
        }
        
        const encodedUrl = encodeURIComponent(imagePath);
        return `${basePath}${encodedUrl}`;
    };

    // Funciones para el efecto de zoom
    const handleMouseMove = (e) => {
        if (isMobile) return; // No aplicar zoom en mobile
        const rect = e.currentTarget.getBoundingClientRect();
        const x = ((e.clientX - rect.left) / rect.width) * 100;
        const y = ((e.clientY - rect.top) / rect.height) * 100;
        setZoomPosition({ x, y });
    };

    const handleMouseEnter = () => {
        if (isMobile) return; // No aplicar zoom en mobile
        setShowZoom(true);
    };

    const handleMouseLeave = () => {
        if (isMobile) return; // No aplicar zoom en mobile
        setShowZoom(false);
    };

    // Función para manejar click en la imagen (mobile)
    const handleImageClick = () => {
        if (isMobile && currentMedia && !isVideo(currentMedia)) {
            setShowImageModal(true);
        }
    };

    // Función para cerrar el modal
    const closeImageModal = () => {
        setShowImageModal(false);
    };

    // Generar descripción SEO del producto (texto plano)
    const getSeoDescription = () => {
        if (!product.description) return `Comprá ${product.title} - Pirotecnia fría certificada | Chispas Frías`;
        const temp = document.createElement('div');
        temp.innerHTML = product.description;
        const text = temp.textContent || temp.innerText || '';
        return text.substring(0, 155).trim() + (text.length > 155 ? '...' : '');
    };

    // JSON-LD Product schema
    const productSchema = {
        "@context": "https://schema.org",
        "@type": "Product",
        "name": product.title,
        "description": getSeoDescription(),
        "image": product.images?.length > 0 ? getImageUrl(product.images[0]) : undefined,
        "sku": product.sku || undefined,
        "brand": { "@type": "Brand", "name": "Chispas Frías" },
        "offers": {
            "@type": "Offer",
            "price": product.pricing.final_price,
            "priceCurrency": "ARS",
            "availability": productOutOfStock ? "https://schema.org/OutOfStock" : "https://schema.org/InStock"
        }
    };

    // JSON-LD BreadcrumbList
    const breadcrumbSchema = {
        "@context": "https://schema.org",
        "@type": "BreadcrumbList",
        "itemListElement": [
            { "@type": "ListItem", "position": 1, "name": "Inicio", "item": "https://chispasfrias.com.ar" },
            { "@type": "ListItem", "position": 2, "name": "Productos", "item": "https://chispasfrias.com.ar/productos" },
            { "@type": "ListItem", "position": 3, "name": product.title }
        ]
    };

    return (
        <>
            <Head title={`${product.title} - Comprar Pirotecnia Fría | Chispas Frías`}>
                <meta name="description" content={getSeoDescription()} />
                <meta property="og:title" content={`${product.title} | Chispas Frías`} />
                <meta property="og:description" content={getSeoDescription()} />
                <meta property="og:image" content={product.images?.length > 0 ? getImageUrl(product.images[0]) : '/images/chispas-frias-logo.png'} />
                <meta property="og:type" content="product" />
                <meta name="twitter:card" content="summary_large_image" />
                <script type="application/ld+json">{JSON.stringify(productSchema)}</script>
                <script type="application/ld+json">{JSON.stringify(breadcrumbSchema)}</script>
            </Head>
            
            <Navbar auth={auth} />

            {/* Sección superior personalizada */}
            <div
                className="pt-20 pb-10"
                style={{
                    backgroundImage: 'url(/images/fondo-productos.png)',
                    backgroundSize: 'cover',
                    backgroundPosition: 'center',
                    backgroundRepeat: 'no-repeat',
                }}
            >
                <div className="site-shell">
                    {/* Mobile: logo arriba, luego textos */}
                    <div className="flex flex-col items-start text-left md:hidden">
                        <div className="relative mb-6">
                            <div className="absolute inset-0 rounded-full bg-navy/80 filter blur-md" style={{ transform: 'scale(1.06)' }} />
                            <img src="/images/chispas-frias-logo.png" alt="Logo Chispas Frías" className="relative h-32 w-auto z-10" />
                        </div>
                        <h1
                            className="text-3xl font-bold text-chalk mb-3"
                            style={{ textShadow: '0 0 15px rgba(2,18,45,1), 0 0 8px rgba(2,18,45,1), 0 2px 10px rgba(2,18,45,0.9)' }}
                        >
                            {product.title}
                        </h1>
                        <p
                            className="text-lg text-chalk/80 max-w-2xl"
                            style={{ textShadow: '0 0 15px rgba(2,18,45,1), 0 0 8px rgba(2,18,45,1), 0 2px 10px rgba(2,18,45,0.9)' }}
                        >
                            Conocé todas las características, especificaciones y detalles de este producto de pirotecnia fría.
                        </p>
                    </div>
                    {/* Desktop: diseño anterior */}
                    <div className="hidden md:flex items-center">
                        <div className="relative mr-3">
                            <div className="absolute inset-0 rounded-lg bg-navy/80 filter blur-md" style={{ transform: 'scale(1.05)' }} />
                            <img src="/images/chispas-frias-logo.png" alt="Logo Chispas Frías" className="relative h-28 w-auto z-10" />
                        </div>
                        <div className="h-32 w-px bg-white ml-2 mr-1" />
                        <div className="flex flex-col text-left ml-2">
                            <p
                                className="text-4xl lg:text-5xl font-bold text-chalk mb-3"
                                aria-hidden="true"
                                style={{ textShadow: '0 0 15px rgba(2,18,45,1), 0 0 8px rgba(2,18,45,1), 0 2px 10px rgba(2,18,45,0.9)' }}
                            >
                                {product.title}
                            </p>
                            <p
                                className="text-xl text-chalk/80 max-w-2xl"
                                style={{ textShadow: '0 0 15px rgba(2,18,45,1), 0 0 8px rgba(2,18,45,1), 0 2px 10px rgba(2,18,45,0.9)' }}
                            >
                                Conocé todas las características, especificaciones y detalles de este producto de pirotecnia fría.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
            
            {/* Breadcrumbs */}
            <div className="bg-chalk pt-20 pb-8">
                <div className="site-shell">
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
                <div className="site-shell">
                    <div className="grid grid-cols-1 lg:grid-cols-2 gap-12">
                        {/* Galería de imágenes */}
                        <div className="space-y-4">
                            {/* Botón volver */}
                            <div className="mb-6">
                                <button
                                    onClick={() => window.history.back()}
                                    className="inline-flex items-center gap-2 px-6 py-3 bg-navy/10 text-navy rounded-full font-semibold transition-all duration-300 shadow-lg hover:shadow-2xl hover:scale-105"
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
                                    currentMedia && !isVideo(currentMedia)
                                        ? (isMobile ? 'cursor-pointer' : 'cursor-zoom-in')
                                        : ''
                                }`}
                                onMouseMove={handleMouseMove}
                                onMouseEnter={handleMouseEnter}
                                onMouseLeave={handleMouseLeave}
                                onClick={handleImageClick}
                            >
                                {currentMedia ? (
                                    <>
                                        {renderMedia(currentMedia, "w-full h-96 object-contain transition-transform duration-200 ease-out" + (showZoom && !isMobile ? " scale-150" : ""))}

                                        {/* Lupa de zoom (solo desktop) */}
                                        {showZoom && !isMobile && !isVideo(currentMedia) && (
                                            <div
                                                className="absolute inset-0 pointer-events-none"
                                                style={{
                                                    background: `url(${getImageUrl(currentMedia)}) ${zoomPosition.x}% ${zoomPosition.y}% / 200%`,
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
                            {gallery.length > 1 && (
                                <div className="grid grid-cols-4 gap-4">
                                    {gallery.map((image, index) => (
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
                            <h2 className="text-3xl md:text-4xl font-bold text-navy">
                                {product.title}
                            </h2>

                            {/* SKU */}
                            {product.sku && (
                                <p className="text-sm text-navy/60">
                                    SKU: {product.sku}
                                </p>
                            )}

                            {/* Opciones: selector de color + add-ons de personalización.
                                No renderiza nada si el producto no tiene ninguno. */}
                            {hasOptions && (
                                <ProductOptions
                                    product={product}
                                    value={options}
                                    onChange={setOptions}
                                    errores={erroresOpciones}
                                />
                            )}

                            {/* Precio */}
                            <div className="py-6 border-y border-navy/10 space-y-4">
                                <div>
                                    {pricing.ofertaAplicada ? (
                                        <div className="space-y-2">
                                            {/* Badge de oferta */}
                                            <div className="inline-flex items-center px-3 py-1 bg-gold text-white text-sm font-bold rounded-full">
                                                <svg className="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                                    <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                                                </svg>
                                                ¡OFERTA! {pricing.ahorroPorcentaje}% OFF
                                            </div>

                                            {/* Precios */}
                                            <div className="flex items-baseline gap-4">
                                                <span className="text-3xl font-bold text-gold">
                                                    ${pricing.precioUnitarioFinal.toLocaleString('es-AR')}
                                                </span>
                                                <span className="text-sm font-medium text-gold/80">ARS c/u</span>
                                                <span className="text-xl text-navy/60 line-through">
                                                    ${pricing.precioLista.toLocaleString('es-AR')}
                                                </span>
                                            </div>

                                            {/* Ahorro */}
                                            <p className="text-sm text-green-600 font-medium">
                                                Ahorrás ${pricing.ahorroUnitario.toLocaleString('es-AR')} por unidad
                                            </p>
                                        </div>
                                    ) : (
                                        <>
                                            <span className="text-3xl font-bold text-navy">
                                                ${pricing.precioUnitarioFinal.toLocaleString('es-AR')}
                                            </span>
                                            <span className="text-sm font-medium text-navy/60 ml-1">ARS c/u</span>
                                        </>
                                    )}
                                    {quantity > 1 && (
                                        <p className="text-sm text-navy/60 mt-2">
                                            Total por {quantity} unidades:{' '}
                                            <span className="font-semibold text-navy">
                                                ${(pricing.precioFinalConOpciones * quantity).toLocaleString('es-AR')}
                                            </span>
                                        </p>
                                    )}
                                </div>

                                {/* Desglose en vivo cuando hay recargo de variante o add-ons.
                                    El precio de arriba es el base/tier (ya con oferta); acá se
                                    le suman las opciones para llegar al precio unitario real.
                                    La oferta NUNCA descuenta estos recargos (igual que PricingService). */}
                                {(pricing.recargoVariante > 0 || pricing.addonsAplicados.length > 0) && (
                                    <div className="rounded-xl border border-navy/10 bg-navy/[0.02] p-4 space-y-1.5 text-sm">
                                        <div className="flex items-center justify-between text-navy/70">
                                            <span>{pricing.tier ? 'Precio por cantidad' : 'Precio base'}{pricing.ofertaAplicada ? ' (con oferta)' : ''}</span>
                                            <span>${pricing.precioUnitarioFinal.toLocaleString('es-AR')}</span>
                                        </div>
                                        {pricing.recargoVariante > 0 && (
                                            <div className="flex items-center justify-between text-navy/70">
                                                <span>Color{selectedVariant ? `: ${selectedVariant.name}` : ''}</span>
                                                <span>+ ${pricing.recargoVariante.toLocaleString('es-AR')}</span>
                                            </div>
                                        )}
                                        {pricing.addonsAplicados.map((addon) => {
                                            const precio = precioAddon(addon);
                                            return (
                                                <div key={addon.id} className="flex items-center justify-between text-navy/70">
                                                    <span>{addon.name}</span>
                                                    <span>{precio > 0 ? `+ $${precio.toLocaleString('es-AR')}` : 'Sin costo'}</span>
                                                </div>
                                            );
                                        })}
                                        <div className="flex items-center justify-between border-t border-navy/10 pt-1.5 text-base font-semibold text-navy">
                                            <span>Precio unitario</span>
                                            <span>${pricing.precioFinalConOpciones.toLocaleString('es-AR')} <span className="text-xs font-medium text-navy/50">ARS</span></span>
                                        </div>
                                    </div>
                                )}

                                {product.stock > 0 && (
                                    <PriceTierPills product={product} quantity={quantity} onSelect={handleQuantityChange} />
                                )}
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

                            {/* Precios por cantidad */}
                            {product.stock > 0 && product.price_tiers?.length > 0 && (
                                <div className="space-y-4">
                                    <h2 className="text-xl font-semibold text-navy">
                                        Precios por cantidad
                                    </h2>
                                    <PriceTiersTable product={product} />
                                </div>
                            )}

                            {/* Acciones */}
                            <div className="space-y-4 pt-6">
                                {productOutOfStock ? (
                                    <div className="flex items-center gap-3 rounded-xl border-2 border-red-200 bg-red-50 px-6 py-4">
                                        <svg className="w-6 h-6 text-red-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.732-.833-2.464 0L3.34 16.5c-.77.833.192 2.5 1.732 2.5z" />
                                        </svg>
                                        <div>
                                            <p className="text-base font-semibold text-red-700">Sin stock</p>
                                            <p className="text-sm text-red-600">Este producto no está disponible por el momento.</p>
                                        </div>
                                    </div>
                                ) : (
                                    <>
                                        {selectedVariantUnavailable ? (
                                            <p className="text-sm font-semibold text-red-600">
                                                Este color está sin stock. Elegí otro color para continuar.
                                            </p>
                                        ) : isLowStock(effectiveStock) && (
                                            <p className="text-sm font-semibold text-amber-600">
                                                {effectiveStock === 1
                                                    ? '¡Última unidad disponible!'
                                                    : `¡Stock bajo! Quedan ${effectiveStock} unidades`}
                                            </p>
                                        )}

                                        {/* Selector de cantidad */}
                                        <div className="flex items-center space-x-4">
                                            <label className="text-sm font-medium text-navy">
                                                Cantidad:
                                            </label>
                                            <div className="flex items-center border border-navy/20 rounded-lg">
                                                <button
                                                    type="button"
                                                    onClick={() => handleQuantityChange(quantity - 1)}
                                                    disabled={quantity <= 1 || selectedVariantUnavailable}
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
                                                    disabled={quantity >= effectiveStock || selectedVariantUnavailable}
                                                    className="px-3 py-2 text-navy hover:bg-navy/10 disabled:opacity-50 disabled:cursor-not-allowed"
                                                >
                                                    +
                                                </button>
                                            </div>
                                        </div>

                                        {/* Botón agregar al carrito. Para productos con opciones
                                            queda deshabilitado hasta que el color / las
                                            personalizaciones estén completos (validarOpciones). */}
                                        <button
                                            onClick={handleAddToCart}
                                            disabled={adding || selectedVariantUnavailable || (hasOptions && !opcionesValidas)}
                                            className={`w-full py-4 font-semibold rounded-full transition-all duration-300 shadow-lg hover:shadow-2xl ${
                                                adding
                                                    ? 'bg-gold/70 text-navy cursor-wait'
                                                    : (selectedVariantUnavailable || (hasOptions && !opcionesValidas))
                                                        ? 'bg-navy/15 text-navy/50 cursor-not-allowed'
                                                        : 'bg-gold text-navy hover:bg-gold/90 hover:scale-105'
                                            }`}
                                        >
                                            {adding ? (
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

                                        {hasOptions && !opcionesValidas && !selectedVariantUnavailable && (
                                            <p className="text-xs text-navy/60">
                                                Completá el color y las personalizaciones marcadas para agregar el producto.
                                            </p>
                                        )}
                                    </>
                                )}
                            </div>
                        </div>
                    </div>

                    {/* Productos relacionados */}
                    <div className="mt-20 border-t border-navy/10 pt-16">
                        <div className="text-left mb-12">
                            <h2 className="text-3xl font-bold text-navy mb-4">
                                Productos relacionados
                            </h2>
                            <p className="text-navy/70 max-w-2xl">
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
                                                
                                                {/* Badge de oferta */}
                                                {relatedProduct.pricing.has_discount && (
                                                    <div className="absolute top-12 left-3">
                                                        <span className="bg-gold text-white text-xs font-bold px-2 py-1 rounded-full shadow-lg">
                                                            -{relatedProduct.pricing.savings_percentage}%
                                                        </span>
                                                    </div>
                                                )}
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
                                                    <div>
                                                        {relatedProduct.pricing.has_discount ? (
                                                            <div className="space-y-1">
                                                                <div className="flex items-baseline gap-2">
                                                                    <span className="text-xl font-bold text-gold">
                                                                        ${relatedProduct.pricing.final_price.toLocaleString('es-AR')}
                                                                    </span>
                                                                    <span className="text-xs font-medium text-gold/80">ARS</span>
                                                                    <span className="text-sm text-navy/60 line-through">
                                                                        ${relatedProduct.pricing.list_price.toLocaleString('es-AR')}
                                                                    </span>
                                                                </div>
                                                                <div className="text-xs text-green-600 font-medium">
                                                                    -{relatedProduct.pricing.savings_percentage}% OFF
                                                                </div>
                                                            </div>
                                                        ) : (
                                                            <>
                                                                <span className="text-2xl font-bold text-navy">
                                                                    ${relatedProduct.pricing.final_price.toLocaleString('es-AR')}
                                                                </span>
                                                                <span className="text-xs font-medium text-navy/60">ARS</span>
                                                            </>
                                                        )}
                                                        {relatedProduct.pricing.has_tiers && (
                                                            <div className="text-xs text-navy/60 mt-1">
                                                                Precios por cantidad disponibles
                                                            </div>
                                                        )}
                                                    </div>
                                                    
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

            {/* Modal de imagen ampliada (mobile) */}
            {showImageModal && (
                <div 
                    className="fixed inset-0 z-50 flex items-center justify-center bg-black/80 p-4"
                    onClick={closeImageModal}
                >
                    {/* Botón de cerrar */}
                    <button
                        onClick={closeImageModal}
                        className="absolute top-4 right-4 z-10 p-2 bg-white/10 hover:bg-white/20 rounded-full transition-colors"
                        aria-label="Cerrar"
                    >
                        <svg 
                            className="w-8 h-8 text-white" 
                            fill="none" 
                            stroke="currentColor" 
                            viewBox="0 0 24 24"
                        >
                            <path 
                                strokeLinecap="round" 
                                strokeLinejoin="round" 
                                strokeWidth={2} 
                                d="M6 18L18 6M6 6l12 12" 
                            />
                        </svg>
                    </button>

                    {/* Imagen ampliada */}
                    <div 
                        className="relative max-w-full max-h-full"
                        onClick={(e) => e.stopPropagation()}
                    >
                        {currentMedia && (
                            <img
                                src={getImageUrl(currentMedia)}
                                alt={currentMedia.alt_text || "Imagen del producto"}
                                className="max-w-full max-h-[90vh] object-contain rounded-lg"
                            />
                        )}
                    </div>
                </div>
            )}

            <Footer />
            <CartButton />
            <WhatsAppButton />
        </>
    );
}
