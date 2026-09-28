import { Head, Link, useForm } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import axios from 'axios';
import toast from 'react-hot-toast';
import Navbar from '@/Components/Navbar';
import Footer from '@/Components/Footer';
import WhatsAppButton from '@/Components/WhatsAppButton';
import CartButton from '@/Components/CartButton';
import ProductGallery from '@/Components/ProductGallery';
import PriceTierPills from '@/Components/PriceTierPills';
import PriceTiersTable from '@/Components/PriceTiersTable';
import ProductOptions from '@/Components/ProductOptions';
import CardPaymentPlanSimulator from '@/Components/CardPaymentPlanSimulator';
import { calcularPrecio, precioAddon } from '@/utils/pricing';
import { opcionesIniciales, addonIdsSeleccionados, buildAddToCartPayload, validarOpciones } from '@/utils/productOptions';
import { isOutOfStock, isLowStock } from '@/utils/stock';
import { getPrimaryImageUrl } from '@/utils/images';

const money = new Intl.NumberFormat('es-AR', {
    style: 'currency',
    currency: 'ARS',
    minimumFractionDigits: 0,
    maximumFractionDigits: 2,
});
const formatPrice = (value) => money.format(Number(value) || 0);

// Coincide con el máximo por agregado que valida CartController.
const MAX_QUANTITY_PER_ADD = 99;

function RelatedProductCard({ product }) {
    const image = getPrimaryImageUrl(product);
    const pricing = product.pricing;

    return (
        <Link
            href={route('products.show', product.id)}
            className="group flex h-full min-w-0 flex-col overflow-hidden rounded-[1.75rem] border border-gray-200 bg-surface p-2 text-navy-900 shadow-card transition hover:-translate-y-1 hover:border-ice-500 hover:shadow-card-hover focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white focus-visible:ring-offset-2 focus-visible:ring-offset-storefront motion-reduce:transform-none"
        >
            <div className="relative aspect-[5/4] overflow-hidden rounded-[1.35rem] bg-surface">
                {image ? (
                    <img src={image} alt={product.title} className="h-full w-full object-contain transition-transform duration-500 group-hover:scale-105 motion-reduce:transform-none" loading="lazy" />
                ) : (
                    <div className="flex h-full items-center justify-center bg-ice-50 text-sm text-navy-700">Imagen no disponible</div>
                )}
                {pricing.has_discount && (
                    <span className="absolute right-3 top-3 rounded-full bg-promo px-3 py-1 text-xs font-bold text-navy-900">
                        -{pricing.savings_percentage}%
                    </span>
                )}
            </div>
            <div className="flex flex-1 flex-col px-4 pb-4 pt-3">
                {product.category && (
                    <span className="mb-3 w-fit rounded-full bg-ice-100 px-2.5 py-1 text-xs font-semibold text-navy-700">
                        {product.category.name}
                    </span>
                )}
                <h3 className="uppercase line-clamp-2 min-h-12 text-lg font-bold leading-6 text-navy-900">{product.title}</h3>
                <div className="mt-auto pt-4">
                    <div className="flex flex-wrap items-baseline gap-x-2 gap-y-1">
                        <span className="text-2xl font-bold text-navy-900">{formatPrice(pricing.final_price)}</span>
                        <span className="text-xs text-navy-900/70">ARS c/u</span>
                        {pricing.has_discount && <span className="text-sm text-navy-900/65 line-through">{formatPrice(pricing.list_price)}</span>}
                    </div>
                    {pricing.has_tiers && <p className="mt-2 text-xs text-navy-700">Precios por cantidad disponibles</p>}
                    <span className="mt-4 inline-flex min-h-11 w-full items-center justify-center rounded-full border border-storefront px-4 py-2 text-sm font-semibold transition group-hover:bg-storefront group-hover:text-white">
                        Ver producto
                    </span>
                </div>
            </div>
        </Link>
    );
}

function ProductDetail({ auth, product, relatedProducts = [], cardPaymentPlans = [], selectedCardPaymentPlanId = null }) {
    const stock = Math.max(0, Math.trunc(Number(product.stock) || 0));
    const variants = product.variants || [];
    const addons = product.addons || [];
    const hasOptions = variants.length > 0 || addons.length > 0;
    const [options, setOptions] = useState(() => opcionesIniciales(product));
    const [selectedPlanId, setSelectedPlanId] = useState(selectedCardPaymentPlanId);
    const selectedVariant = variants.find((variant) => variant.id === options.variantId) || null;
    const effectiveStock = selectedVariant?.stock == null ? stock : Math.max(0, Number(selectedVariant.stock));
    const selectedVariantUnavailable = selectedVariant && effectiveStock <= 0;
    const outOfStock = variants.length > 0
        ? !variants.some((variant) => Number(variant.stock ?? stock) > 0)
        : isOutOfStock(stock);
    const maxQuantity = Math.min(effectiveStock, MAX_QUANTITY_PER_ADD);
    const { valido: optionsValid, errores: optionErrors } = validarOpciones(product, options);
    const { data, setData, transform, post, processing, errors, setError, clearErrors } = useForm({
        product_id: product.id,
        quantity: 1,
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
    const whatsappUrl = `https://wa.me/5491178886833?text=${encodeURIComponent(`Hola! Quisiera consultar por ${product.title}.`)}`;
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

    const handleSelectPlan = async (planId) => {
        const previousPlanId = selectedPlanId;
        setSelectedPlanId(planId);
        try {
            if (planId === null) {
                await axios.delete(route('cart.payment-plan.remove'));
            } else {
                await axios.post(route('cart.payment-plan.set'), { plan_id: planId });
            }
        } catch {
            setSelectedPlanId(previousPlanId);
            toast.error('No pudimos guardar la forma de pago. Intentá nuevamente.');
        }
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
            { '@type': 'ListItem', position: 1, name: 'Inicio', item: 'https://chispasfrias.com.ar' },
            { '@type': 'ListItem', position: 2, name: 'Catálogo', item: 'https://chispasfrias.com.ar/productos' },
            { '@type': 'ListItem', position: 3, name: product.title },
        ],
    };

    return (
        <div className="storefront-background min-h-screen overflow-x-clip text-white">
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

            <main className="pb-16 pt-36 sm:pb-20 sm:pt-40">
                <div className="site-shell">
                    <Link href={route('products.index')} className="mb-6 inline-flex min-h-11 items-center gap-2 rounded-full border border-white/70 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-white hover:text-navy-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white focus-visible:ring-offset-2 focus-visible:ring-offset-storefront sm:mb-8">
                        <span aria-hidden="true">←</span>
                        Volver al catálogo
                    </Link>

                    <div className="grid items-start gap-6 lg:grid-cols-[minmax(0,1.05fr)_minmax(0,1fr)]">
                        <div className="min-w-0 lg:sticky lg:top-36">
                            <ProductGallery product={product} variantId={options.variantId} />
                        </div>

                        <section aria-labelledby="product-title" className="min-w-0 rounded-[1.75rem] border border-gray-200 bg-surface p-6 text-navy-900 shadow-card sm:p-8 lg:p-9">
                            <div className="flex flex-wrap items-center gap-2">
                                {category && (
                                    <Link href={route('products.index', { category: category.slug })} className="rounded-full bg-ice-100 px-3 py-1.5 text-xs font-bold uppercase tracking-wide text-navy-700 hover:bg-ice-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-navy-900">
                                        {category.name}
                                    </Link>
                                )}
                                <span className="inline-flex items-center gap-1.5 text-xs font-semibold text-navy-700">
                                    <span className={`h-1.5 w-1.5 rounded-full ${outOfStock || selectedVariantUnavailable ? 'bg-gray-500' : 'bg-navy-700'}`} aria-hidden="true" />
                                    {outOfStock || selectedVariantUnavailable ? 'Sin stock' : isLowStock(effectiveStock) ? (effectiveStock === 1 ? 'Última unidad' : `Últimas ${effectiveStock} unidades`) : 'En stock'}
                                </span>
                            </div>

                            <h1 id="product-title" className="uppercase mt-4 break-words text-3xl font-bold leading-tight tracking-[-0.035em] text-navy-900 sm:text-4xl">{product.title}</h1>
                            {hasDescription && (
                                <a href="#descripcion" className="mt-3 inline-block text-xs font-semibold text-navy-700 underline underline-offset-4 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-navy-900">Ver descripción</a>
                            )}

                            {hasOptions && (
                                <div className="mt-6">
                                    <ProductOptions product={product} value={options} onChange={setOptions} errores={optionErrors} />
                                </div>
                            )}

                            <div className="mt-6">
                                <CardPaymentPlanSimulator plans={cardPaymentPlans} total={total} selectedPlanId={selectedPlanId} onSelect={handleSelectPlan} />
                            </div>

                            <div className="mt-6 rounded-2xl bg-ice-50 p-4 sm:p-5">
                                <p className="mb-2 text-xs font-semibold uppercase tracking-wide text-navy-700">Precio por unidad</p>
                                {pricing.ofertaAplicada && (
                                    <span className="mb-3 inline-flex rounded-full bg-promo px-3 py-1 text-xs font-bold text-navy-900">
                                        {pricing.ahorroPorcentaje}% de descuento
                                    </span>
                                )}
                                <div className="flex flex-wrap items-baseline gap-x-2 gap-y-1" aria-live="polite" aria-atomic="true">
                                    <span className="break-all text-4xl font-bold tracking-tight text-navy-900">{formatPrice(pricing.precioUnitarioFinal)}</span>
                                    <span className="text-xs font-medium text-navy-700">ARS c/u</span>
                                    {pricing.ofertaAplicada && <span className="text-base text-navy-900/65 line-through">{formatPrice(pricing.precioLista)}</span>}
                                </div>
                                {pricing.ofertaAplicada && <p className="mt-2 text-sm font-medium text-navy-700">Ahorrás {formatPrice(pricing.ahorroUnitario)} por unidad</p>}
                                {(pricing.recargoVariante > 0 || pricing.addonsAplicados.length > 0) && (
                                    <div className="mt-4 space-y-2 border-t border-navy-900/10 pt-4 text-sm text-navy-900/75">
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

                            {outOfStock ? (
                                <div className="mt-6 rounded-2xl border border-gray-200 bg-background p-4">
                                    <h2 className="uppercase text-base font-semibold text-navy-900">Este producto no está disponible por el momento.</h2>
                                    <p className="mt-2 text-sm leading-relaxed text-navy-900/75">
                                        {relatedProducts.length > 0 ? 'Consultanos por su disponibilidad o mirá los productos relacionados.' : 'Consultanos por su disponibilidad.'}
                                    </p>
                                </div>
                            ) : (
                                <form onSubmit={handleAddToCart} className="mt-6 space-y-5" aria-busy={processing}>
                                    {selectedVariantUnavailable && <p className="text-sm font-semibold text-navy-700">Este color está sin stock. Elegí otro para continuar.</p>}
                                    <PriceTierPills product={product} quantity={quantity} onSelect={setQuantity} disabled={processing || selectedVariantUnavailable} maxQuantity={maxQuantity} />
                                    <div className="flex flex-wrap items-center justify-between gap-3">
                                        <label htmlFor="product-quantity" className="text-sm font-semibold text-navy-900">Cantidad</label>
                                        <div className="flex h-12 items-center overflow-hidden rounded-full border border-navy-900 bg-surface focus-within:ring-2 focus-within:ring-navy-900/20">
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
                                                aria-describedby={errors.quantity ? 'quantity-error' : stock > MAX_QUANTITY_PER_ADD ? 'quantity-limit' : undefined}
                                                className="h-full w-16 appearance-none border-0 bg-transparent px-1 text-center text-base font-semibold tabular-nums text-navy-900 focus:ring-0 [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none [appearance:textfield]"
                                            />
                                            <button type="button" onClick={() => setQuantity(quantity + 1)} disabled={quantity >= maxQuantity || processing} aria-label="Sumar una unidad" className="flex h-full w-11 items-center justify-center text-xl text-navy-900 transition hover:bg-ice-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-navy-900 disabled:cursor-not-allowed disabled:opacity-35">+</button>
                                        </div>
                                    </div>
                                    {effectiveStock > MAX_QUANTITY_PER_ADD && <p id="quantity-limit" className="text-xs text-navy-900/70">Podés agregar hasta {MAX_QUANTITY_PER_ADD} unidades por vez.</p>}
                                    {errors.quantity && <p id="quantity-error" role="alert" className="text-sm font-medium text-navy-700">{Array.isArray(errors.quantity) ? errors.quantity[0] : errors.quantity}</p>}
                                    {errors.product_id && <p role="alert" className="text-sm font-medium text-navy-700">{Array.isArray(errors.product_id) ? errors.product_id[0] : errors.product_id}</p>}
                                    <div className="flex flex-wrap items-baseline justify-between gap-2">
                                        <p className="text-sm text-navy-900/75">Total por {quantity} {quantity === 1 ? 'unidad' : 'unidades'}</p>
                                        <output className="text-xl font-bold tabular-nums text-navy-900">{formatPrice(total)}</output>
                                    </div>
                                    <button type="submit" disabled={processing || selectedVariantUnavailable || (hasOptions && !optionsValid)} className="inline-flex min-h-12 w-full items-center justify-center gap-2 rounded-full bg-storefront px-5 py-3 text-sm font-semibold text-white shadow-soft transition hover:brightness-90 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-storefront focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60">
                                        {processing && <span className="h-4 w-4 animate-spin rounded-full border-2 border-white/30 border-t-white" aria-hidden="true" />}
                                        {processing ? 'Agregando...' : 'Agregar al carrito'}
                                    </button>
                                    {hasOptions && !optionsValid && !selectedVariantUnavailable && <p className="text-xs text-navy-900/70">Completá el color y las personalizaciones marcadas para agregar el producto.</p>}
                                </form>
                            )}

                            <a href={whatsappUrl} target="_blank" rel="noopener noreferrer" className="mt-3 inline-flex min-h-12 w-full items-center justify-center rounded-full border border-storefront px-5 py-3 text-sm font-semibold text-storefront transition hover:bg-storefront hover:text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-storefront focus-visible:ring-offset-2">
                                {outOfStock ? 'Consultar disponibilidad' : 'Consultar por WhatsApp'}
                            </a>
                        </section>
                    </div>

                    {(hasDescription || hasTiers) && (
                        <div className={`mt-6 grid items-start gap-6 ${hasDescription && hasTiers ? 'lg:grid-cols-[minmax(0,1.2fr)_minmax(0,0.8fr)]' : ''}`}>
                            {hasDescription && (
                                <section id="descripcion" aria-labelledby="description-title" className="min-w-0 scroll-mt-36 rounded-[1.75rem] border border-gray-200 bg-surface p-6 text-navy-900 shadow-card sm:p-8">
                                    <h2 id="description-title" className="uppercase mb-5 text-2xl font-bold text-navy-900">Descripción del producto</h2>
                                    <div className="prose prose-navy break-words text-sm leading-relaxed sm:text-base [&_a]:text-navy-700 [&_img]:h-auto [&_img]:max-w-full [&_table]:block [&_table]:overflow-x-auto" dangerouslySetInnerHTML={{ __html: product.description }} />
                                </section>
                            )}
                            {hasTiers && (
                                <section aria-labelledby="price-tiers-title" className="min-w-0 rounded-[1.75rem] border border-gray-200 bg-surface p-6 text-navy-900 shadow-card sm:p-8">
                                    <h2 id="price-tiers-title" className="uppercase text-2xl font-bold text-navy-900">Precios por cantidad</h2>
                                    <p className="mb-5 mt-3 text-sm leading-relaxed text-navy-900/75">El precio por unidad se ajusta a la cantidad elegida.</p>
                                    <PriceTiersTable product={product} quantity={quantity} />
                                </section>
                            )}
                        </div>
                    )}

                    {relatedProducts.length > 0 && (
                        <section aria-labelledby="related-title" className="mt-12 sm:mt-16">
                            <div className="mb-6 flex flex-col gap-4 sm:mb-8 sm:flex-row sm:items-end sm:justify-between">
                                <div>
                                    <h2 id="related-title" className="uppercase text-3xl font-bold leading-tight text-white sm:text-4xl">También te puede interesar</h2>
                                    <p className="mt-3 text-sm leading-relaxed text-white sm:text-base">Más opciones para completar tu evento.</p>
                                </div>
                                <Link href={categoryUrl} className="inline-flex min-h-11 w-fit shrink-0 items-center justify-center rounded-full border border-white/70 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-white hover:text-navy-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white focus-visible:ring-offset-2 focus-visible:ring-offset-storefront">
                                    Ver más productos
                                </Link>
                            </div>
                            <div className="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                                {relatedProducts.map((related) => <RelatedProductCard key={related.id} product={related} />)}
                            </div>
                        </section>
                    )}
                </div>
            </main>

            <Footer />
            <CartButton />
            <WhatsAppButton />
        </div>
    );
}

export default function ProductShow(props) {
    // Inertia reutiliza la página al navegar entre productos: reiniciar galería y formulario.
    return <ProductDetail key={props.product.id} {...props} />;
}
