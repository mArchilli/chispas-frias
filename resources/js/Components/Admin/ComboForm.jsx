import React, { useMemo, useState } from 'react';
import MediaDropzone from '@/Components/Admin/MediaDropzone';
import { IconPlus, IconX, IconSearch } from '@/Components/Admin/Icons';

const inputClasses =
    'block w-full rounded-lg border border-gray-200 px-3 py-2 text-sm text-graphite placeholder:text-gray-400 transition focus:border-navy-900 focus:outline-none focus:ring-2 focus:ring-navy-900/10';

const money = (value) => '$' + Number(value || 0).toLocaleString('es-AR');

/**
 * Formulario compartido de alta/edición de combos. Controla:
 *  - datos base (título, descripción, precio),
 *  - toggles de envío gratis y activo,
 *  - selector de productos que integran el combo (con cantidad),
 *  - dropzone de imágenes nuevas (campo `imagesField`).
 *
 * El manejo de imágenes ya existentes (quitar / marcar principal) lo resuelve la
 * página de edición y se inyecta por `existingImagesSlot`.
 */
export default function ComboForm({
    data,
    setData,
    errors = {},
    products = [],
    imagesField = 'images',
    existingImagesSlot = null,
}) {
    const [search, setSearch] = useState('');

    const productsById = useMemo(() => {
        const map = {};
        products.forEach((p) => {
            map[p.id] = p;
        });
        return map;
    }, [products]);

    const selectedIds = useMemo(() => new Set((data.items || []).map((i) => Number(i.product_id))), [data.items]);

    const disponibles = useMemo(() => {
        const term = search.trim().toLowerCase();
        return products.filter(
            (p) => !selectedIds.has(p.id) && (term === '' || p.title.toLowerCase().includes(term))
        );
    }, [products, selectedIds, search]);

    const addProduct = (product) => {
        setData('items', [...(data.items || []), { product_id: product.id, quantity: 1 }]);
    };

    const removeItem = (productId) => {
        setData('items', (data.items || []).filter((i) => Number(i.product_id) !== Number(productId)));
    };

    const setItemQuantity = (productId, value) => {
        const qty = Math.max(1, Math.min(999, Math.trunc(Number(value)) || 1));
        setData(
            'items',
            (data.items || []).map((i) =>
                Number(i.product_id) === Number(productId) ? { ...i, quantity: qty } : i
            )
        );
    };

    // Error genérico de items (ej. "items required") vs. errores por fila.
    const itemsError = errors.items || errors['items.0.product_id'];

    return (
        <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
            <div className="space-y-6 lg:col-span-2">
                {/* Datos base */}
                <div className="space-y-4 rounded-xl border border-gray-200 bg-surface p-5 sm:p-6">
                    <div>
                        <label htmlFor="combo-title" className="mb-1 block text-sm font-medium text-graphite">
                            Nombre del combo
                        </label>
                        <input
                            id="combo-title"
                            type="text"
                            value={data.title}
                            onChange={(e) => setData('title', e.target.value)}
                            className={inputClasses}
                            placeholder="Ej: Combo Fiesta x3"
                        />
                        {errors.title && <p className="mt-1 text-xs font-medium text-navy-700">{errors.title}</p>}
                    </div>

                    <div>
                        <label htmlFor="combo-description" className="mb-1 block text-sm font-medium text-graphite">
                            Descripción <span className="text-gray-400">(opcional)</span>
                        </label>
                        <textarea
                            id="combo-description"
                            rows={4}
                            value={data.description || ''}
                            onChange={(e) => setData('description', e.target.value)}
                            className={inputClasses}
                            placeholder="Qué incluye el combo, para qué evento sirve, etc."
                        />
                        {errors.description && <p className="mt-1 text-xs font-medium text-navy-700">{errors.description}</p>}
                    </div>

                    <div>
                        <label htmlFor="combo-price" className="mb-1 block text-sm font-medium text-graphite">
                            Precio del combo
                        </label>
                        <div className="relative">
                            <span className="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-sm text-gray-400">$</span>
                            <input
                                id="combo-price"
                                type="number"
                                min="0"
                                step="0.01"
                                value={data.price}
                                onChange={(e) => setData('price', e.target.value)}
                                className={`${inputClasses} pl-7`}
                                placeholder="0.00"
                            />
                        </div>
                        <p className="mt-1 text-xs text-gray-500">
                            Precio fijo del combo. No depende del precio de los productos que lo integran.
                        </p>
                        {errors.price && <p className="mt-1 text-xs font-medium text-navy-700">{errors.price}</p>}
                    </div>
                </div>

                {/* Productos del combo */}
                <div className="rounded-xl border border-gray-200 bg-surface p-5 sm:p-6">
                    <h2 className="mb-1 text-sm font-semibold text-graphite">Productos del combo</h2>
                    <p className="mb-4 text-xs text-gray-500">
                        Elegí qué productos incluye y en qué cantidad. Si un producto tiene colores, el cliente
                        elegirá el color al comprar el combo.
                    </p>

                    {itemsError && <p className="mb-3 text-xs font-medium text-navy-700">{itemsError}</p>}

                    {/* Seleccionados */}
                    {(data.items || []).length > 0 ? (
                        <ul className="mb-4 space-y-2">
                            {(data.items || []).map((item) => {
                                const product = productsById[item.product_id];
                                const title = product?.title || item.product_title || `Producto #${item.product_id}`;
                                return (
                                    <li
                                        key={item.product_id}
                                        className="flex items-center gap-3 rounded-lg border border-gray-200 bg-background/60 p-2.5"
                                    >
                                        <div className="h-10 w-10 flex-shrink-0 overflow-hidden rounded-md border border-gray-200 bg-surface">
                                            {product?.primary_image ? (
                                                <img src={product.primary_image} alt={title} className="h-full w-full object-cover" />
                                            ) : (
                                                <div className="flex h-full w-full items-center justify-center text-[10px] text-gray-400">—</div>
                                            )}
                                        </div>
                                        <div className="min-w-0 flex-1">
                                            <p className="truncate text-sm font-medium text-graphite">{title}</p>
                                            {product?.has_variants && (
                                                <p className="text-[11px] text-navy-700">El cliente elige color</p>
                                            )}
                                        </div>
                                        <div className="flex items-center gap-1">
                                            <label className="sr-only" htmlFor={`qty-${item.product_id}`}>Cantidad</label>
                                            <input
                                                id={`qty-${item.product_id}`}
                                                type="number"
                                                min="1"
                                                max="999"
                                                value={item.quantity}
                                                onChange={(e) => setItemQuantity(item.product_id, e.target.value)}
                                                className="h-9 w-16 rounded-md border border-gray-200 px-2 text-center text-sm text-graphite focus:border-navy-900 focus:outline-none focus:ring-1 focus:ring-navy-900/10"
                                            />
                                        </div>
                                        <button
                                            type="button"
                                            onClick={() => removeItem(item.product_id)}
                                            className="flex h-8 w-8 items-center justify-center rounded-md text-gray-400 transition hover:bg-navy-900/5 hover:text-navy-900"
                                            title="Quitar del combo"
                                        >
                                            <IconX className="h-4 w-4" />
                                        </button>
                                    </li>
                                );
                            })}
                        </ul>
                    ) : (
                        <p className="mb-4 rounded-lg border border-dashed border-gray-200 bg-background/60 px-3 py-4 text-center text-xs text-gray-500">
                            Todavía no agregaste productos al combo.
                        </p>
                    )}

                    {/* Buscador / disponibles */}
                    <div className="relative mb-2">
                        <IconSearch className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
                        <input
                            type="text"
                            placeholder="Buscar producto para agregar..."
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            className={`${inputClasses} pl-9`}
                        />
                    </div>
                    <div className="max-h-56 space-y-1 overflow-y-auto rounded-lg border border-gray-200 p-1">
                        {disponibles.length > 0 ? (
                            disponibles.slice(0, 50).map((product) => (
                                <button
                                    key={product.id}
                                    type="button"
                                    onClick={() => addProduct(product)}
                                    className="flex w-full items-center gap-3 rounded-md px-2 py-1.5 text-left transition hover:bg-navy-900/5"
                                >
                                    <div className="h-8 w-8 flex-shrink-0 overflow-hidden rounded-md border border-gray-200 bg-surface">
                                        {product.primary_image ? (
                                            <img src={product.primary_image} alt={product.title} className="h-full w-full object-cover" />
                                        ) : (
                                            <div className="flex h-full w-full items-center justify-center text-[10px] text-gray-400">—</div>
                                        )}
                                    </div>
                                    <span className="min-w-0 flex-1 truncate text-sm text-graphite">{product.title}</span>
                                    <span className="text-xs text-gray-400">{money(product.price)}</span>
                                    <IconPlus className="h-4 w-4 text-navy-900" />
                                </button>
                            ))
                        ) : (
                            <p className="px-2 py-3 text-center text-xs text-gray-400">
                                {search ? 'Sin resultados.' : 'No hay más productos para agregar.'}
                            </p>
                        )}
                    </div>
                </div>

                {/* Visibilidad / envío gratis */}
                <div className="space-y-3 rounded-xl border border-gray-200 bg-surface p-5 sm:p-6">
                    <h2 className="text-sm font-semibold text-graphite">Opciones</h2>
                    <label className="flex items-start gap-3">
                        <input
                            type="checkbox"
                            checked={!!data.is_free_shipping}
                            onChange={(e) => setData('is_free_shipping', e.target.checked)}
                            className="mt-0.5 h-4 w-4 rounded border-gray-300 text-storefront focus:ring-storefront/20"
                        />
                        <span>
                            <span className="block text-sm font-medium text-graphite">Envío gratis</span>
                            <span className="block text-xs text-gray-500">
                                Se muestra en el combo y se aclara en el mensaje de WhatsApp al finalizar la compra.
                            </span>
                        </span>
                    </label>
                    <label className="flex items-start gap-3">
                        <input
                            type="checkbox"
                            checked={!!data.is_active}
                            onChange={(e) => setData('is_active', e.target.checked)}
                            className="mt-0.5 h-4 w-4 rounded border-gray-300 text-storefront focus:ring-storefront/20"
                        />
                        <span>
                            <span className="block text-sm font-medium text-graphite">Activo</span>
                            <span className="block text-xs text-gray-500">Un combo inactivo no se muestra en el catálogo.</span>
                        </span>
                    </label>
                </div>
            </div>

            {/* Multimedia */}
            <div className="lg:col-span-1">
                <div className="rounded-xl border border-gray-200 bg-surface p-5 sm:sticky sm:top-6 sm:p-6">
                    <h2 className="mb-4 text-sm font-semibold text-graphite">Multimedia</h2>
                    {existingImagesSlot}
                    <MediaDropzone
                        files={data[imagesField]}
                        onChange={(files) => setData(imagesField, files)}
                        error={errors[imagesField]}
                        inputId={imagesField}
                    />
                </div>
            </div>
        </div>
    );
}
