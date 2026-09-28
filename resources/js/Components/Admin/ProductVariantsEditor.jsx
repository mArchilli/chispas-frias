import React from 'react';
import { IconPlus, IconTrash, IconAlertTriangle } from '@/Components/Admin/Icons';
import { genUid } from '@/utils/uid';

/**
 * Repeater de "Variantes de color" para el form de producto (Create/Edit). Cada
 * variante es un color REAL con su propio stock (vacío = ilimitado) y recargo
 * (price_addon). `variants` es un array de
 * { _uid?, id?, name, color_hex, is_custom_color, price_addon, stock, is_active }.
 * `_uid` (clave temporal de cliente) sólo lo llevan las filas nuevas: el backend
 * la traduce al id real al guardar, para poder asociarles imágenes en el mismo
 * submit.
 */
export default function ProductVariantsEditor({ variants, onChange, errors = {} }) {
    const addVariant = () => {
        onChange([
            ...variants,
            {
                _uid: genUid(),
                id: null,
                name: '',
                color_hex: '#3366ff',
                is_custom_color: false,
                price_addon: '',
                stock: '',
                is_active: true,
            },
        ]);
    };

    const updateVariant = (index, field, value) => {
        onChange(variants.map((v, i) => (i === index ? { ...v, [field]: value } : v)));
    };

    const removeVariant = (index) => {
        onChange(variants.filter((_, i) => i !== index));
    };

    const customColorCount = variants.filter((v) => v.is_custom_color).length;

    const error = (index, field) => errors[`variants.${index}.${field}`];

    return (
        <div className="space-y-3">
            {customColorCount > 1 && (
                <div className="flex items-start gap-2 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800">
                    <IconAlertTriangle className="mt-0.5 h-4 w-4 flex-shrink-0" />
                    <span>
                        Sólo una variante puede ser "Color a elección del cliente". Destildá las demás antes de
                        guardar.
                    </span>
                </div>
            )}
            {errors.variants && typeof errors.variants === 'string' && (
                <p className="text-xs font-medium text-rose-600">{errors.variants}</p>
            )}

            {variants.map((variant, index) => (
                <div
                    key={variant.id ?? variant._uid ?? `new-${index}`}
                    className="space-y-3 rounded-lg border border-slate-200 bg-white p-3"
                >
                    <div className="flex flex-wrap items-start gap-3">
                        <div className="min-w-[150px] flex-1">
                            <label className="mb-1 block text-xs font-medium text-slate-500">Nombre</label>
                            <input
                                type="text"
                                value={variant.name}
                                onChange={(e) => updateVariant(index, 'name', e.target.value)}
                                placeholder="Ej. Rojo"
                                maxLength={100}
                                className={`block w-full rounded-lg border px-3 py-2 text-sm transition focus:outline-none focus:ring-2 ${
                                    error(index, 'name')
                                        ? 'border-rose-300 focus:border-rose-400 focus:ring-rose-100'
                                        : 'border-slate-300 focus:border-navy focus:ring-navy/10'
                                }`}
                            />
                            {error(index, 'name') && (
                                <p className="mt-1 text-xs text-rose-600">{error(index, 'name')}</p>
                            )}
                        </div>

                        <div className="w-[130px]">
                            <label className="mb-1 block text-xs font-medium text-slate-500">Color</label>
                            <div className="flex items-center gap-1.5">
                                <input
                                    type="color"
                                    value={variant.color_hex || '#000000'}
                                    onChange={(e) => updateVariant(index, 'color_hex', e.target.value)}
                                    className="h-9 w-9 flex-shrink-0 cursor-pointer rounded border border-slate-300 bg-white p-0.5"
                                    title="Color de referencia"
                                />
                                <input
                                    type="text"
                                    value={variant.color_hex || ''}
                                    onChange={(e) => updateVariant(index, 'color_hex', e.target.value)}
                                    placeholder="#RRGGBB"
                                    className={`block w-full rounded-lg border px-2 py-2 font-mono text-xs uppercase transition focus:outline-none focus:ring-2 ${
                                        error(index, 'color_hex')
                                            ? 'border-rose-300 focus:border-rose-400 focus:ring-rose-100'
                                            : 'border-slate-300 focus:border-navy focus:ring-navy/10'
                                    }`}
                                />
                            </div>
                            {error(index, 'color_hex') && (
                                <p className="mt-1 text-xs text-rose-600">{error(index, 'color_hex')}</p>
                            )}
                        </div>

                        <div className="w-[120px]">
                            <label className="mb-1 block text-xs font-medium text-slate-500">Recargo</label>
                            <div className="relative">
                                <span className="pointer-events-none absolute inset-y-0 left-2.5 flex items-center text-slate-400">
                                    $
                                </span>
                                <input
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    value={variant.price_addon}
                                    onChange={(e) => updateVariant(index, 'price_addon', e.target.value)}
                                    placeholder="0"
                                    className={`block w-full rounded-lg border py-2 pl-6 pr-2 text-sm transition focus:outline-none focus:ring-2 ${
                                        error(index, 'price_addon')
                                            ? 'border-rose-300 focus:border-rose-400 focus:ring-rose-100'
                                            : 'border-slate-300 focus:border-navy focus:ring-navy/10'
                                    }`}
                                />
                            </div>
                            {error(index, 'price_addon') && (
                                <p className="mt-1 text-xs text-rose-600">{error(index, 'price_addon')}</p>
                            )}
                        </div>

                        <div className="w-[110px]">
                            <label className="mb-1 block text-xs font-medium text-slate-500">Stock</label>
                            <input
                                type="number"
                                min="0"
                                step="1"
                                value={variant.stock}
                                onChange={(e) => updateVariant(index, 'stock', e.target.value)}
                                placeholder="Ilimitado"
                                className={`block w-full rounded-lg border px-3 py-2 text-sm transition focus:outline-none focus:ring-2 ${
                                    error(index, 'stock')
                                        ? 'border-rose-300 focus:border-rose-400 focus:ring-rose-100'
                                        : 'border-slate-300 focus:border-navy focus:ring-navy/10'
                                }`}
                            />
                            {error(index, 'stock') && (
                                <p className="mt-1 text-xs text-rose-600">{error(index, 'stock')}</p>
                            )}
                        </div>

                        <button
                            type="button"
                            onClick={() => removeVariant(index)}
                            className="mt-6 flex h-8 w-8 items-center justify-center rounded-md text-slate-400 transition hover:bg-rose-50 hover:text-rose-600"
                            title="Quitar variante"
                        >
                            <IconTrash className="h-4 w-4" />
                        </button>
                    </div>

                    <div className="flex flex-wrap gap-x-5 gap-y-2 border-t border-slate-100 pt-2.5">
                        <label className="flex cursor-pointer items-center gap-2 text-xs font-medium text-slate-600">
                            <input
                                type="checkbox"
                                checked={!!variant.is_active}
                                onChange={(e) => updateVariant(index, 'is_active', e.target.checked)}
                                className="h-4 w-4 rounded border-slate-300 text-navy focus:ring-navy/20"
                            />
                            Activa
                        </label>
                        <label className="flex cursor-pointer items-center gap-2 text-xs font-medium text-slate-600">
                            <input
                                type="checkbox"
                                checked={!!variant.is_custom_color}
                                onChange={(e) => updateVariant(index, 'is_custom_color', e.target.checked)}
                                className="h-4 w-4 rounded border-slate-300 text-navy focus:ring-navy/20"
                            />
                            Color a elección del cliente
                        </label>
                    </div>
                </div>
            ))}

            <button
                type="button"
                onClick={addVariant}
                className="inline-flex items-center gap-1.5 rounded-lg border border-dashed border-slate-300 px-3 py-2 text-sm font-medium text-slate-500 transition hover:border-navy/30 hover:text-navy"
            >
                <IconPlus className="h-4 w-4" />
                Agregar variante
            </button>

            {variants.length === 0 && (
                <p className="text-sm text-slate-500">
                    Sin variantes: el producto se vende sin elección de color.
                </p>
            )}
        </div>
    );
}
