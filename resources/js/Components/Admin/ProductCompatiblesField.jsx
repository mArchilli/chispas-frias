import React, { useMemo, useState } from 'react';
import { getProductImageUrl } from '@/utils/images';

const normalizar = (texto) =>
    String(texto ?? '')
        .toLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '');

function Miniatura({ producto }) {
    const url = getProductImageUrl(producto.image);

    return (
        <div className="h-10 w-10 flex-shrink-0 overflow-hidden rounded-lg bg-background">
            {url ? <img src={url} alt="" className="h-full w-full object-contain p-1" /> : null}
        </div>
    );
}

/**
 * «Productos compatibles» del formulario de producto (Create y Edit). `selected` es el array de ids elegidos, en el orden en que
 * se ofrecen; `catalog` son los productos elegibles (sin el propio). La compatibilidad es en los dos sentidos: lo que se marca acá
 * también se ve desde el otro producto (si la pistola es compatible con la chispa, la chispa lo es con la pistola) y el carrito
 * lo usa para sugerir lo que sirve con lo que el cliente ya lleva. Además lleva el check «Sugerir siempre en el carrito».
 */
export default function ProductCompatiblesField({
    catalog = [],
    selected = [],
    onChange,
    sugerirSiempre = false,
    onSugerirSiempreChange,
    error,
}) {
    const [busqueda, setBusqueda] = useState('');

    const porId = useMemo(() => new Map(catalog.map((p) => [Number(p.id), p])), [catalog]);
    // Un id que ya no existe en el catálogo no se muestra ni se reenvía.
    const elegidos = selected.map((id) => porId.get(Number(id))).filter(Boolean);

    const resultados = useMemo(() => {
        const texto = normalizar(busqueda.trim());
        if (texto === '') return [];

        const yaElegidos = new Set(selected.map(Number));

        return catalog
            .filter((p) => !yaElegidos.has(Number(p.id)))
            .filter((p) => normalizar(`${p.title} ${p.sku ?? ''}`).includes(texto))
            .slice(0, 8);
    }, [busqueda, catalog, selected]);

    const agregar = (producto) => {
        onChange([...elegidos.map((p) => Number(p.id)), Number(producto.id)]);
        setBusqueda('');
    };

    const quitar = (id) => onChange(elegidos.map((p) => Number(p.id)).filter((x) => x !== Number(id)));

    const mover = (indice, delta) => {
        const ids = elegidos.map((p) => Number(p.id));
        const destino = indice + delta;
        if (destino < 0 || destino >= ids.length) return;
        [ids[indice], ids[destino]] = [ids[destino], ids[indice]];
        onChange(ids);
    };

    return (
        <div>
            {elegidos.length > 0 && (
                <ul className="mb-3 space-y-2">
                    {elegidos.map((producto, indice) => (
                        <li
                            key={producto.id}
                            className="flex items-center gap-3 rounded-lg border border-gray-200 bg-surface p-2.5"
                        >
                            <Miniatura producto={producto} />
                            <div className="min-w-0 flex-1">
                                <p className="truncate text-sm font-medium text-graphite">{producto.title}</p>
                                {!producto.is_active && (
                                    <p className="text-xs text-gray-500">Oculto: el carrito no lo ofrece mientras lo esté.</p>
                                )}
                            </div>
                            <div className="flex items-center gap-1">
                                <button
                                    type="button"
                                    onClick={() => mover(indice, -1)}
                                    disabled={indice === 0}
                                    className="rounded-lg px-2 py-1 text-gray-500 hover:bg-background disabled:opacity-30"
                                    aria-label={`Subir ${producto.title}`}
                                >
                                    ↑
                                </button>
                                <button
                                    type="button"
                                    onClick={() => mover(indice, 1)}
                                    disabled={indice === elegidos.length - 1}
                                    className="rounded-lg px-2 py-1 text-gray-500 hover:bg-background disabled:opacity-30"
                                    aria-label={`Bajar ${producto.title}`}
                                >
                                    ↓
                                </button>
                                <button
                                    type="button"
                                    onClick={() => quitar(producto.id)}
                                    className="rounded-lg px-2 py-1 text-sm font-medium text-rose-600 hover:bg-rose-50"
                                    aria-label={`Quitar ${producto.title}`}
                                >
                                    Quitar
                                </button>
                            </div>
                        </li>
                    ))}
                </ul>
            )}

            <label htmlFor="compatibles-buscar" className="mb-1 block text-xs font-medium text-gray-500">
                Buscar un producto para marcarlo como compatible
            </label>
            <input
                id="compatibles-buscar"
                type="search"
                value={busqueda}
                onChange={(e) => setBusqueda(e.target.value)}
                placeholder="Nombre o SKU"
                autoComplete="off"
                disabled={catalog.length === 0}
                className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-ice-500 focus:ring-ice-500/20"
            />

            {busqueda.trim() !== '' && (
                <ul className="mt-2 divide-y divide-gray-100 rounded-lg border border-gray-200 bg-surface">
                    {resultados.length === 0 ? (
                        <li className="px-3 py-3 text-sm text-gray-500">No encontramos productos con ese nombre.</li>
                    ) : (
                        resultados.map((producto) => (
                            <li key={producto.id}>
                                <button
                                    type="button"
                                    onClick={() => agregar(producto)}
                                    className="flex w-full items-center gap-3 px-3 py-2 text-left hover:bg-background"
                                >
                                    <Miniatura producto={producto} />
                                    <span className="min-w-0 flex-1 truncate text-sm text-graphite">{producto.title}</span>
                                    <span className="text-xs font-medium text-ice-500">Agregar</span>
                                </button>
                            </li>
                        ))
                    )}
                </ul>
            )}

            {error && <p className="mt-2 text-xs font-medium text-rose-600">{error}</p>}

            <label className="mt-5 flex cursor-pointer items-start gap-3">
                <input
                    type="checkbox"
                    checked={!!sugerirSiempre}
                    onChange={(e) => onSugerirSiempreChange(e.target.checked)}
                    className="mt-0.5 h-4 w-4 rounded border-gray-300 text-navy focus:ring-navy/20"
                />
                <span className="text-sm font-medium text-graphite">
                    Sugerir siempre en el carrito
                    <span className="block text-xs font-normal text-gray-500">
                        Se ofrece en cualquier carrito (útil para las chispas frías genéricas), pero solo cuando el cliente
                        no lleva ningún producto con compatibilidades cargadas: así a quien compra una pistola no se le
                        ofrece una chispa que quizás no le sirva.
                    </span>
                </span>
            </label>
        </div>
    );
}
