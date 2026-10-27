import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import toast from 'react-hot-toast';
import Navbar from '@/Components/Navbar';
import Footer from '@/Components/Footer';
import WhatsAppButton from '@/Components/WhatsAppButton';
import CartButton from '@/Components/CartButton';
import ProductGallery from '@/Components/ProductGallery';
import PriceTierPills from '@/Components/PriceTierPills';
import PriceTiersTable from '@/Components/PriceTiersTable';
import ProductOptions from '@/Components/ProductOptions';
import InfoCuotas from '@/Components/InfoCuotas';
import { calcularPrecio, precioAddon } from '@/utils/pricing';
import { opcionesIniciales, addonIdsSeleccionados, buildAddToCartPayload, validarOpciones } from '@/utils/productOptions';
import { isOutOfStock, isLowStock } from '@/utils/stock';
import { getPrimaryImageUrl } from '@/utils/images';
import { useUrlTienda, useWhatsappNumero, whatsappLink } from '@/utils/whatsapp';
import '../../../css/product-detail.css';

const money = new Intl.NumberFormat('es-AR', {
    style: 'currency',
    currency: 'ARS',
    minimumFractionDigits: 0,
    maximumFractionDigits: 2,
});
const formatPrice = (value) => money.format(Number(value) || 0);

function RelatedProductCard({ product }) {
    const image = getPrimaryImageUrl(product);
    const pricing = product.pricing;

    return (
        <Link
            href={route('products.show', product.id)}
            className="product-related-card group"
        >
            <div className="product-related-image">
                {image ? (
                    <img src={image} alt={product.title} className="h-full w-full object-contain transition-transform duration-500 group-hover:scale-105 motion-reduce:transform-none" loading="lazy" />
                ) : (
                    <div className="flex h-full items-center justify-center bg-gray-50 text-sm text-gray-600">Imagen no disponible</div>
                )}
                {pricing.has_discount && (
                    <span className="product-discount product-related-discount">
                        -{pricing.savings_percentage}%
                    </span>
                )}
            </div>
            <div className="product-related-content">
                {product.category && (
                    <span className="product-related-category">
                        {product.category.name}
                    </span>
                )}
                <h3 className="product-related-title">{product.title}</h3>
                <div className="mt-auto pt-3">
                    <div className="flex flex-wrap items-baseline gap-x-2 gap-y-1">
                        <span className="product-related-price">{formatPrice(pricing.final_price)}</span>
                        <span className="text-xs text-gray-600">ARS c/u</span>
                        {pricing.has_discount && <span className="text-xs text-gray-600 line-through">{formatPrice(pricing.list_price)}</span>}
                    </div>
                    {pricing.has_tiers && <p className="mt-2 text-xs text-gray-600">Precios por cantidad disponibles</p>}
                    <span className="product-related-link">
                        Ver producto
                    </span>
                </div>
            </div>
        </Link>
    );
}

function ProductDetail({ auth, product, relatedProducts = [], cardPaymentPlans = [] }) {
    const { url } = usePage();
    const whatsappNumero = useWhatsappNumero();
    const urlTienda = useUrlTienda();
    const initialSelection = new URLSearchParams(url.split('?')[1] || '');
    const stock = Math.max(0, Math.trunc(Number(product.stock) || 0));
    const variants = product.variants || [];
    const addons = product.addons || [];
    const hasOptions = variants.length > 0 || addons.length > 0;
    const [options, setOptions] = useState(() => {
        const initial = opcionesIniciales(product);
        const requestedVariant = variants.find((variant) => variant.id === Number(initialSelection.get('variant')));
        return requestedVariant ? { ...initial, variantId: requestedVariant.id } : initial;
    });
    const selectedVariant = variants.find((variant) => variant.id === options.variantId) || null;
    const effectiveStock = selectedVariant?.stock == null ? stock : Math.max(0, Number(selectedVariant.stock));
    const selectedVariantUnavailable = selectedVariant && effectiveStock <= 0;
    const outOfStock = variants.length > 0
        ? !variants.some((variant) => Number(variant.stock ?? stock) > 0)
        : isOutOfStock(stock);
    const maxQuantity = effectiveStock;
    const { valido: optionsValid, errores: optionErrors } = validarOpciones(product, options);
    const { data, setData, transform, post, processing, errors, setError, clearErrors } = useForm({
        product_id: product.id,
        quantity: Math.min(maxQuantity || 1, Math.max(1, Math.trunc(Number(initialSelection.get('quantity'))) || 1)),
    });
    const quantity = Math.min(maxQuantity || 1, Math.max(1, Math.trunc(Number(data.quantity)) || 1));
    const pricing = calcularPrecio(product, quantity, {
        varianteId: options.variantId,
        addonIds: addonIdsSeleccionados(options),
    });
    const total = Math.round(pricing.precioFinalConOpciones * quantity * 100) / 100;
    const category = product.category;
    const categorySlug = category?.parent?.slug || category?.slug;
    const categoryUrl = categorySlug ? route('products.index', { category: categorySlug }) : route('products.index');
    const primaryImage = getPrimaryImageUrl(product);
    const whatsappUrl = whatsappLink(whatsappNumero, `Hola! Quisiera consultar por ${product.title}.`);
    const hasDescription = Boolean(product.description?.trim());
    const hasTiers = product.price_tiers?.length > 0;

    const seoDescription = useMemo(() => {
        if (!product.description) return `Comprá ${product.title} - Pirotecnia fría certificada | Chispas Frías`;
        let text = product.description.replace(/<[^>]*>/g, ' ');
        if (typeof document !== 'undefined') {
            const element = document.createElement('div');
            element.innerHTML = product.description;
            text = element.textContent || '';
        }
        text = text.replace(/\s+/g, ' ').trim();
        return text.slice(0, 155) + (text.length > 155 ? '...' : '');
    }, [product.description, product.title]);

    const setQuantity = (value) => {
        clearErrors('quantity');
        if (value === '') {
            setData('quantity', '');
            return;
        }
        const parsed = Number(value);
        if (!Number.isFinite(parsed)) return;
        setData('quantity', Math.min(maxQuantity || 1, Math.max(1, Math.trunc(parsed))));
    };

    const handleAddToCart = (event) => {
        event.preventDefault();
        if (outOfStock || selectedVariantUnavailable || processing) return;
        if (hasOptions && !optionsValid) {
            toast.error('Revisá el color y las personalizaciones marcadas.');
            return;
        }
        const addedQuantity = quantity;
        // El precio definitivo y la validez de las opciones los resuelve el backend.
        transform(() => buildAddToCartPayload(product, addedQuantity, options));
        post(route('cart.add'), {
            preserveScroll: true,
            onSuccess: ({ props }) => {
                if (props.flash?.error) {
                    setError('quantity', props.flash.error);
                    toast.error(props.flash.error);
                    return;
                }
                setData('quantity', 1);
                clearErrors();
                window.dispatchEvent(new CustomEvent('cart-updated'));
                toast.success(`${product.title} agregado al carrito (${addedQuantity} ${addedQuantity === 1 ? 'unidad' : 'unidades'})`);
            },
            onError: (formErrors) => {
                const message = formErrors.quantity || formErrors.product_id || 'No pudimos agregar el producto. Volvé a intentarlo.';
                toast.error(Array.isArray(message) ? message[0] : message);
            },
        });
    };

    const productSchema = {
        '@context': 'https://schema.org',
        '@type': 'Product',
        name: product.title,
        description: seoDescription,
        image: primaryImage || undefined,
        brand: { '@type': 'Brand', name: 'Chispas Frías' },
        offers: {
            '@type': 'Offer',
            price: product.pricing.final_price,
            priceCurrency: 'ARS',
            availability: outOfStock ? 'https://schema.org/OutOfStock' : 'https://schema.org/InStock',
        },
    };
    const breadcrumbSchema = {
        '@context': 'https://schema.org',
        '@type': 'BreadcrumbList',
        itemListElement: [
            { '@type': 'ListItem', position: 1, name: 'Inicio', item: urlTienda },
            { '@type': 'ListItem', position: 2, name: 'Catálogo', item: `${urlTienda}/productos` },
            { '@type': 'ListItem', position: 3, name: product.title },
        ],
    };

    return (
        <div className="product-detail-page min-h-screen">
            <Head title={`${product.title} - Comprar Pirotecnia Fría | Chispas Frías`}>
                <meta name="description" content={seoDescription} />
                <meta property="og:title" content={`${product.title} | Chispas Frías`} />
                <meta property="og:description" content={seoDescription} />
                <meta property="og:image" content={primaryImage || '/images/chispas-frias-logo.png'} />
                <meta property="og:type" content="product" />
                <meta name="twitter:card" content="summary_large_image" />
                <script type="application/ld+json">{JSON.stringify(productSchema).replace(/</g, '\\u003c')}</script>
                <script type="application/ld+json">{JSON.stringify(breadcrumbSchema).replace(/</g, '\\u003c')}</script>
            </Head>

            <Navbar auth={auth} />

            <main className="product-detail-main">
                <div className="product-detail-shell">
                    <nav className="product-breadcrumb" aria-label="Navegación del producto">
                        <Link href={route('products.index')} className="product-back-link">
                            <span aria-hidden="true">←</span>
                            Volver al catálogo
                        </Link>
                        {category && (
                            <>
                                <span className="product-breadcrumb-divider" aria-hidden="true">/</span>
                                <Link href={route('products.index', { category: category.slug })}>{category.name}</Link>
                            </>
                        )}
                    </nav>

                    <article className="product-sheet" aria-labelledby="product-title">
                        <div className="product-overview">
                            <header className="product-heading">
                                <div className="product-meta">
                                    {category && (
                                        <Link href={route('products.index', { category: category.slug })}>
                                            {category.name}
                                        </Link>
                                    )}
                                    {product.sku && <span>SKU: {product.sku}</span>}
                                </div>

                                <h1 id="product-title">{product.title}</h1>
                                {hasDescription && (
                                    <a href="#descripcion" className="product-description-link">Ver descripción</a>
                                )}
                            </header>

                            <div className="product-media-column">
                                <ProductGallery product={product} variantId={options.variantId} />
                            </div>

                            <section className="product-price-section" aria-label="Precio del producto">
                                <div className="product-price-block">
                                    <p className="product-price-label">Precio por unidad</p>
                                    {pricing.ofertaAplicada && (
                                        <span className="product-discount">
                                            {pricing.ahorroPorcentaje}% de descuento
                                        </span>
                                    )}
                                    <div className="product-price" aria-live="polite" aria-atomic="true">
                                        <span className="product-price-value">{formatPrice(pricing.precioUnitarioFinal)}</span>
                                        <span className="product-price-currency">ARS c/u</span>
                                        {pricing.ofertaAplicada && <span className="product-price-original">{formatPrice(pricing.precioLista)}</span>}
                                    </div>
                                    {pricing.ofertaAplicada && <p className="product-savings">Ahorrás {formatPrice(pricing.ahorroUnitario)} por unidad</p>}
                                    {(pricing.recargoVariante > 0 || pricing.addonsAplicados.length > 0) && (
                                        <div className="product-price-breakdown">
                                            {pricing.recargoVariante > 0 && (
                                                <p className="flex justify-between gap-3"><span>Color: {selectedVariant?.name}</span><span>+ {formatPrice(pricing.recargoVariante)}</span></p>
                                            )}
                                            {pricing.addonsAplicados.map((addon) => (
                                                <p key={addon.id} className="flex justify-between gap-3"><span>{addon.name}</span><span>{precioAddon(addon) > 0 ? `+ ${formatPrice(precioAddon(addon))}` : 'Sin costo'}</span></p>
                                            ))}
                                            <p className="flex justify-between gap-3 border-t border-navy-900/10 pt-2 font-semibold text-navy-900"><span>Precio unitario con opciones</span><span>{formatPrice(pricing.precioFinalConOpciones)}</span></p>
                                        </div>
                                    )}
                                </div>
                                {/* Cuotas con tarjeta: solo información sobre el total de ESTE producto (cantidad y opciones elegidas). */}
                                <InfoCuotas planes={cardPaymentPlans} total={total} />
                            </section>

                            <section className="product-purchase" aria-label="Opciones de compra">
                                <p className={`product-stock ${outOfStock || selectedVariantUnavailable ? 'product-stock-unavailable' : ''}`} aria-live="polite">
                                    {outOfStock || selectedVariantUnavailable ? 'Sin stock' : isLowStock(effectiveStock) ? (effectiveStock === 1 ? '¡Última unidad disponible!' : `¡Últimas ${effectiveStock} unidades!`) : 'Stock disponible'}
                                </p>
                                {hasOptions && (
                                    <div className="product-options">
                                        <ProductOptions product={product} value={options} onChange={setOptions} errores={optionErrors} />
                                    </div>
                                )}
                                {outOfStock ? (
                                    <div className="product-unavailable">
                                        <h2>Este producto no está disponible por el momento.</h2>
                                        <p>
                                            {relatedProducts.length > 0 ? 'Consultanos por su disponibilidad o mirá los productos relacionados.' : 'Consultanos por su disponibilidad.'}
                                        </p>
                                    </div>
                                ) : (
                                    <form onSubmit={handleAddToCart} className="product-purchase-form" aria-busy={processing}>
                                        {selectedVariantUnavailable && <p className="text-sm font-semibold text-navy-700">Este color está sin stock. Elegí otro para continuar.</p>}
                                        <PriceTierPills product={product} quantity={quantity} onSelect={setQuantity} disabled={processing || selectedVariantUnavailable} maxQuantity={maxQuantity} />
                                        <div className="flex flex-wrap items-center justify-between gap-3">
                                            <label htmlFor="product-quantity" className="text-sm font-semibold text-navy-900">Cantidad</label>
                                            <div data-oculta-flotantes className="product-quantity-control">
                                                <button type="button" onClick={() => setQuantity(quantity - 1)} disabled={quantity <= 1 || processing} aria-label="Restar una unidad" className="flex h-full w-11 items-center justify-center text-xl text-navy-900 transition hover:bg-ice-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-navy-900 disabled:cursor-not-allowed disabled:opacity-35">−</button>
                                                <input
                                                    id="product-quantity"
                                                    name="quantity"
                                                    type="number"
                                                    inputMode="numeric"
                                                    min="1"
                                                    max={maxQuantity}
                                                    step="1"
                                                    required
                                                    value={data.quantity}
                                                    onChange={(event) => setQuantity(event.target.value)}
                                                    onBlur={() => setQuantity(quantity)}
                                                    disabled={processing}
                                                    aria-invalid={Boolean(errors.quantity)}
                                                    aria-describedby={errors.quantity ? 'quantity-error' : undefined}
                                                    className="h-full w-16 appearance-none border-0 bg-transparent px-1 text-center text-base font-semibold tabular-nums text-navy-900 focus:ring-0 [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none [appearance:textfield]"
                                                />
                                                <button type="button" onClick={() => setQuantity(quantity + 1)} disabled={quantity >= maxQuantity || processing} aria-label="Sumar una unidad" className="flex h-full w-11 items-center justify-center text-xl text-navy-900 transition hover:bg-ice-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-navy-900 disabled:cursor-not-allowed disabled:opacity-35">+</button>
                                            </div>
                                        </div>
                                        {errors.quantity && <p id="quantity-error" role="alert" className="text-sm font-medium text-navy-700">{Array.isArray(errors.quantity) ? errors.quantity[0] : errors.quantity}</p>}
                                        {errors.product_id && <p role="alert" className="text-sm font-medium text-navy-700">{Array.isArray(errors.product_id) ? errors.product_id[0] : errors.product_id}</p>}
                                        <div className="product-total">
                                            <p className="text-sm text-navy-900/75">Total por {quantity} {quantity === 1 ? 'unidad' : 'unidades'}</p>
                                            <output className="text-xl font-bold tabular-nums text-navy-900">{formatPrice(total)}</output>
                                        </div>
                                        <button type="submit" disabled={processing || selectedVariantUnavailable || (hasOptions && !optionsValid)} className="product-primary-button">
                                            {processing && <span className="h-4 w-4 animate-spin rounded-full border-2 border-white/30 border-t-white" aria-hidden="true" />}
                                            {!processing && <svg className="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.7" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true"><path d="M3 3h2l2.4 12h11.2L21 7H6M9 21h.01M18 21h.01" /></svg>}
                                            {processing ? 'Agregando...' : 'Agregar al carrito'}
                                        </button>
                                        {hasOptions && !optionsValid && !selectedVariantUnavailable && <p className="text-xs text-navy-900/70">Completá el color y las personalizaciones marcadas para agregar el producto.</p>}
                                    </form>
                                )}

                                <a href={whatsappUrl} target="_blank" rel="noopener noreferrer" className="product-secondary-button">
                                    {outOfStock ? 'Consultar disponibilidad' : 'Consultar por WhatsApp'}
                                </a>
                            </section>

                            {hasTiers && (
                                <section aria-labelledby="price-tiers-title" className="product-content-section product-tiers-section">
                                    <h2 id="price-tiers-title">Precios por cantidad</h2>
                                    <p className="product-section-intro">El precio por unidad se ajusta a la cantidad elegida.</p>
                                    <PriceTiersTable product={product} quantity={quantity} />
                                </section>
                            )}
                            {hasDescription && (
                                <section id="descripcion" aria-labelledby="description-title" className="product-content-section product-description-section">
                                    <h2 id="description-title">Descripción del producto</h2>
                                    <div className="product-description prose break-words [&_img]:h-auto [&_img]:max-w-full [&_table]:block [&_table]:overflow-x-auto" dangerouslySetInnerHTML={{ __html: product.description }} />
                                </section>
                            )}
                        </div>

                        {relatedProducts.length > 0 && (
                            <section aria-labelledby="related-title" className="product-content-section product-related-section">
                                <div className="product-section-heading">
                                    <div>
                                        <h2 id="related-title">También te puede interesar</h2>
                                        <p className="product-section-intro">Más opciones para completar tu evento.</p>
                                    </div>
                                    <Link href={categoryUrl} className="product-more-link">
                                        Ver más productos
                                    </Link>
                                </div>
                                <div className="product-related-grid">
                                    {relatedProducts.map((related) => <RelatedProductCard key={related.id} product={related} />)}
                                </div>
                            </section>
                        )}
                    </article>
                </div>
            </main>

            <Footer />
            <div className="product-contact-tools">
                <CartButton />
                <WhatsAppButton />
            </div>
        </div>
    );
}

export default function ProductShow(props) {
    // Inertia reutiliza la página al navegar entre productos: reiniciar galería y formulario.
    return <ProductDetail key={props.product.id} {...props} />;
}
