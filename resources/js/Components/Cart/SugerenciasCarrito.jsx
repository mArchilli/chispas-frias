import { useState } from 'react';
import { Link } from '@inertiajs/react';
import axios from 'axios';
import toast from 'react-hot-toast';
import { useSugerenciasCarrito } from '@/hooks/useSugerenciasCarrito';
import { getProductImageUrl } from '@/utils/images';

const money = (value) => Number(value || 0).toLocaleString('es-AR');

function Imagen({ producto, className }) {
    const url = getProductImageUrl(producto.image);

    return url ? (
        <img src={url} alt="" className={className} loading="lazy" />
    ) : (
        <span aria-hidden="true" className="flex h-full w-full select-none items-center justify-center text-2xl font-bold text-navy-900/20">
            {producto.title?.charAt(0).toUpperCase()}
        </span>
    );
}

/** «Compatible con …»: lo del carrito con lo que sirve esta sugerencia. */
function CompatibleCon({ producto, className = '' }) {
    const con = producto.compatible_con ?? [];
    if (con.length === 0) return null;

    return (
        <p className={`flex items-start gap-1 text-[11px] font-semibold leading-snug text-navy-700 ${className}`}>
            <svg className="mt-px h-3.5 w-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.5} aria-hidden="true">
                <path strokeLinecap="round" strokeLinejoin="round" d="M5 13l4 4L19 7" />
            </svg>
            <span className="line-clamp-2">Compatible con {con.join(', ')}</span>
        </p>
    );
}

function Precio({ producto, className = '' }) {
    const conOferta = Number(producto.price) < Number(producto.list_price);

    return (
        <p className={`flex flex-wrap items-baseline gap-x-1.5 ${className}`}>
            <span className="font-bold text-navy-900">${money(producto.price)}</span>
            {conOferta && <span className="text-[11px] font-medium text-navy-900/50 line-through">${money(producto.list_price)}</span>}
        </p>
    );
}

/**
 * Acción de una sugerencia: un producto con colores activos no se puede agregar sin elegir uno (el carrito lo rechaza), así que
 * lleva a su ficha; el resto se agrega directo.
 */
function Accion({ producto, agregando, onAgregar, compacto, onNavegar }) {
    const base = 'inline-flex shrink-0 items-center justify-center rounded-full bg-storefront font-bold text-white transition hover:brightness-90 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-storefront focus-visible:ring-offset-2 disabled:opacity-50';
    const medida = compacto ? 'min-h-9 px-3 text-xs' : 'min-h-11 w-full px-4 text-sm';

    if (producto.has_variants) {
        return (
            <Link href={route('products.show', producto.id)} onClick={onNavegar} className={`${base} ${medida}`} aria-label={`Elegir color de ${producto.title}`}>
                Elegir color
            </Link>
        );
    }

    return (
        <button type="button" onClick={() => onAgregar(producto)} disabled={agregando} className={`${base} ${medida}`} aria-label={`Agregar ${producto.title} al carrito`}>
            {agregando ? 'Agregando…' : 'Agregar'}
        </button>
    );
}

/**
 * «Compatible con tu pedido»: productos sugeridos según lo que hay en el carrito. `variante` «pagina» es la grilla de Cart/Index;
 * «drawer» es la lista compacta del carrito flotante. Agregar usa el mismo endpoint que la ficha (cart.add), así que la línea
 * entra con sus avisos de stock y sus escalas de precio; después `onAgregado` refresca el carrito de quien lo muestra.
 */
export default function SugerenciasCarrito({ items, variante = 'pagina', activo = true, onAgregado, onNavegar }) {
    const sugerencias = useSugerenciasCarrito(items, activo);
    const [agregando, setAgregando] = useState(null);

    if (sugerencias.length === 0) return null;

    // Si todas llegan por compatibilidad el título lo dice; con alguna genérica queda el neutro.
    const titulo = sugerencias.every((p) => p.compatible_con?.length > 0) ? 'Compatible con tu pedido' : 'Sumá a tu pedido';

    const agregar = async (producto) => {
        if (agregando !== null) return;
        setAgregando(producto.id);
        try {
            const { data } = await axios.post(route('cart.add'), { product_id: producto.id, quantity: 1 }, { headers: { Accept: 'application/json' } });
            toast.success(data.message || `${producto.title} agregado al carrito`);
            await onAgregado?.();
        } catch (error) {
            toast.error(error.response?.data?.message || 'No pudimos agregar el producto.');
        } finally {
            setAgregando(null);
        }
    };

    if (variante === 'drawer') {
        return (
            <section aria-labelledby="sugerencias-drawer" className="mt-5 border-t border-gray-200 pt-4">
                <h3 id="sugerencias-drawer" className="mb-2 text-xs font-bold uppercase tracking-wider text-navy-700">{titulo}</h3>
                <ul className="space-y-1">
                    {sugerencias.map((producto) => (
                        <li key={producto.id} className="flex items-center gap-3 rounded-xl p-2 hover:bg-ice-50">
                            <Link href={route('products.show', producto.id)} onClick={onNavegar} className="h-14 w-14 shrink-0 overflow-hidden rounded-lg bg-ice-50" tabIndex={-1} aria-hidden="true">
                                <Imagen producto={producto} className="h-full w-full object-contain p-1.5" />
                            </Link>
                            <div className="min-w-0 flex-1">
                                <Link href={route('products.show', producto.id)} onClick={onNavegar} className="line-clamp-2 text-sm font-semibold leading-snug hover:text-navy-700">
                                    {producto.title}
                                </Link>
                                <CompatibleCon producto={producto} className="mt-0.5" />
                                <Precio producto={producto} className="mt-0.5 text-sm" />
                            </div>
                            <Accion producto={producto} agregando={agregando === producto.id} onAgregar={agregar} onNavegar={onNavegar} compacto />
                        </li>
                    ))}
                </ul>
            </section>
        );
    }

    return (
        <section aria-labelledby="sugerencias-carrito" className="mt-10">
            <h2 id="sugerencias-carrito" className="mb-4 uppercase text-xl font-bold tracking-tight text-white sm:text-2xl">{titulo}</h2>
            <ul className="grid grid-cols-2 gap-3 sm:gap-4 xl:grid-cols-4">
                {sugerencias.map((producto) => (
                    <li key={producto.id} className="flex min-w-0 flex-col overflow-hidden rounded-[1.5rem] border border-gray-200 bg-surface text-navy-900 shadow-card">
                        <Link href={route('products.show', producto.id)} className="block aspect-square overflow-hidden bg-surface focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-storefront" aria-label={`Ver ${producto.title}`}>
                            <Imagen producto={producto} className="h-full w-full object-contain p-4" />
                        </Link>
                        <div className="flex flex-1 flex-col p-3 sm:p-4">
                            <Link href={route('products.show', producto.id)} className="line-clamp-2 min-h-10 text-sm font-semibold leading-snug hover:text-navy-700">
                                {producto.title}
                            </Link>
                            <CompatibleCon producto={producto} className="mt-1.5" />
                            <Precio producto={producto} className="mb-3 mt-2 text-base" />
                            <div className="mt-auto">
                                <Accion producto={producto} agregando={agregando === producto.id} onAgregar={agregar} />
                            </div>
                        </div>
                    </li>
                ))}
            </ul>
        </section>
    );
}
