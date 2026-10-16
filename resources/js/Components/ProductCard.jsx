import { Link } from '@inertiajs/react';
import { useRef, useState } from 'react';
import axios from 'axios';
import toast from 'react-hot-toast';
import { getPrimaryImageUrl } from '@/utils/images';
import { calcularPrecio } from '@/utils/pricing';
import { galeriaDeVariante } from '@/utils/productOptions';
import '../../css/product-card.css';

const formatPrice = (price) => `$ ${Number(price).toLocaleString('es-AR', { maximumFractionDigits: 2 })}`;
const rainbow = 'conic-gradient(#ef4444, #eab308, #22c55e, #3b82f6, #a855f7, #ef4444)';
const stockFor = (product, variant) => Math.max(0, Math.trunc(Number(variant?.stock ?? product.stock) || 0));

function BagIcon() {
    return <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.7" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true"><path d="M5 7h14l1 14H4L5 7Zm3 0V5a4 4 0 0 1 8 0v2" /></svg>;
}

export default function ProductCard({ product }) {
    const [variantId, setVariantId] = useState(null);
    const [adding, setAdding] = useState(false);
    const [imageFailed, setImageFailed] = useState(null);
    const [chooseColor, setChooseColor] = useState(false);
    const colorPicker = useRef(null);
    const variants = product.variants || [];
    const variant = variants.find((item) => item.id === variantId) || null;
    const stock = stockFor(product, variant);
    const unavailable = variant ? stock <= 0 : variants.length > 0
        ? !variants.some((item) => stockFor(product, item) > 0)
        : stock <= 0;
    const needsColor = variants.length > 0 && !variant;
    const needsPersonalization = Boolean(variant?.is_custom_color);
    const pricing = calcularPrecio(product, 1, { varianteId: variant?.id });
    const image = getPrimaryImageUrl(variant ? { images: galeriaDeVariante(product.images || [], variant.id) } : product);
    const href = route('products.show', {
        product: product.id,
        ...(variant ? { variant: variant.id } : {}),
    });

    const selectVariant = (next) => {
        setVariantId(next.id);
        setChooseColor(false);
    };

    const addToCart = async () => {
        if (adding || unavailable) return;
        if (needsColor) {
            setChooseColor(true);
            colorPicker.current?.querySelector('button:not(:disabled)')?.focus();
            return;
        }
        try {
            setAdding(true);
            await axios.post(route('cart.add'), {
                product_id: product.id,
                quantity: 1,
                ...(variant ? { variant_id: variant.id } : {}),
            });
            window.dispatchEvent(new CustomEvent('cart-updated'));
            toast.success(`${product.title}${variant ? ` (${variant.name})` : ''} agregado al carrito`);
        } catch (error) {
            toast.error(error.response?.data?.message || 'No pudimos agregar el producto. Volvé a intentarlo.');
        } finally {
            setAdding(false);
        }
    };

    return (
        <article className="catalog-product-card" data-product-id={product.id} aria-label={product.title}>
            <Link href={href} className="catalog-card-media" aria-label={`Ver ${product.title}${variant ? ` en color ${variant.name}` : ''}`}>
                {image && imageFailed !== image ? (
                    <img key={image} src={image} alt={`${product.title}${variant ? ` · ${variant.name}` : ''}`} loading="lazy" decoding="async" onError={() => setImageFailed(image)} />
                ) : (
                    <span className="catalog-card-no-image">Imagen no disponible{variant ? ` para ${variant.name}` : ''}</span>
                )}
                {product.is_featured && (
                    <span className="catalog-card-featured" aria-label="Producto destacado" title="Producto destacado">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="m12 2 3.1 6.3 7 .9-5.1 5 1.2 7L12 18l-6.2 3.2 1.2-7-5.1-5 7-.9L12 2Z" /></svg>
                    </span>
                )}
                {pricing.ofertaAplicada && <span className="catalog-card-discount">{pricing.ahorroPorcentaje}% OFF</span>}
            </Link>

            <div className="catalog-card-content">
                {product.category && <p className="catalog-card-category">{product.category.parent?.name || product.category.name}</p>}
                <Link href={href} className="catalog-card-title-link"><h2 className="catalog-card-title">{product.title}</h2></Link>

                <div className="catalog-card-colors" aria-hidden={variants.length === 0 ? true : undefined}>
                    {variants.length > 0 && (
                        <fieldset ref={colorPicker} className="catalog-card-color-picker" disabled={adding}>
                            <legend className="sr-only">Elegí un color de {product.title}</legend>
                            <div className="catalog-card-swatches">
                                {variants.map((item) => {
                                    const noStock = stockFor(product, item) <= 0;
                                    return (
                                        <button key={item.id} type="button" onClick={() => selectVariant(item)} disabled={noStock}
                                            className="catalog-card-color" aria-label={`${item.name}${noStock ? ' · sin stock' : ''}`}
                                            aria-pressed={variantId === item.id} title={`${item.name}${noStock ? ' · sin stock' : ''}`}>
                                            <span className="catalog-card-color-dot" style={item.is_custom_color ? { backgroundImage: rainbow } : { backgroundColor: item.color_hex || '#d1d5db' }}>
                                                {variantId === item.id && <svg width="14" height="14" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="11" fill="white" /><path d="m6 12 4 4 8-8" fill="none" stroke="#242424" strokeWidth="2.5" /></svg>}
                                                {item.is_custom_color && variantId !== item.id && <span aria-hidden="true">+</span>}
                                            </span>
                                        </button>
                                    );
                                })}
                            </div>
                            <p className={`catalog-card-color-label ${chooseColor ? 'catalog-card-color-prompt' : ''}`} aria-live="polite">
                                {variant ? `Color: ${variant.name}` : chooseColor ? 'Elegí un color para continuar' : `${variants.length} colores disponibles`}
                            </p>
                        </fieldset>
                    )}
                </div>

                <div className="catalog-card-purchase">
                    <div className="catalog-card-pricing" aria-live="polite" aria-atomic="true">
                        <span className="catalog-card-price">{formatPrice(pricing.precioFinalConOpciones)}</span>
                        {pricing.ofertaAplicada && <span className="catalog-card-list-price">{formatPrice(pricing.precioLista + pricing.recargoVariante)}</span>}
                        <span className="sr-only">por unidad</span>
                    </div>

                    <div className="catalog-card-actions">
                        {needsPersonalization ? (
                            <Link href={href} className="catalog-card-add"><BagIcon />Personalizar</Link>
                        ) : (
                            <button type="button" className="catalog-card-add" onClick={addToCart} disabled={adding || unavailable}
                                aria-label={needsColor ? `Elegir color de ${product.title}` : `Agregar ${product.title}${variant ? ` en color ${variant.name}` : ''} al carrito`}>
                                <BagIcon />{adding ? 'Agregando…' : unavailable ? 'Sin stock' : needsColor ? 'Elegir color' : 'Agregar al carrito'}
                            </button>
                        )}
                    </div>
                    <Link href={href} className="catalog-card-detail-link">Ver producto</Link>
                </div>
            </div>
        </article>
    );
}
