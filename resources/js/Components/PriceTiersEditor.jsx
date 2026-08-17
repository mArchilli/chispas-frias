import React from 'react';
import { IconPlus, IconTrash } from '@/Components/Admin/Icons';

/**
 * Repeater de "Precios por cantidad" compartido entre Create.jsx y Edit.jsx de
 * productos. `tiers` es un array de { id?, cantidad_minima, precio_unitario };
 * `id` solo está presente en filas que ya existen en la base (Edit).
 */
export default function PriceTiersEditor({ tiers, onChange, errors = {}, basePrice = 0 }) {
    const addTier = () => {
        onChange([...tiers, { cantidad_minima: '', precio_unitario: '' }]);
    };

    const updateTier = (index, field, value) => {
        onChange(tiers.map((tier, i) => (i === index ? { ...tier, [field]: value } : tier)));
    };

    const removeTier = (index) => {
        onChange(tiers.filter((_, i) => i !== index));
    };

    const sortedForPreview = [...tiers]
        .filter((t) => t.cantidad_minima !== '' && t.precio_unitario !== '' && !isNaN(Number(t.cantidad_minima)))
        .sort((a, b) => Number(a.cantidad_minima) - Number(b.cantidad_minima));

    return (
        <div className="space-y-3">
            {tiers.map((tier, index) => (
                <div
                    key={tier.id ?? `new-${index}`}
                    className="flex flex-wrap items-start gap-3 rounded-lg border border-slate-200 bg-white p-3"
                >
                    <div className="min-w-[130px] flex-1">
                        <label className="mb-1 block text-xs font-medium text-slate-500">Cantidad mínima</label>
                        <input
                            type="number"
                            min="2"
                            step="1"
                            value={tier.cantidad_minima}
                            onChange={(e) => updateTier(index, 'cantidad_minima', e.target.value)}
                            className={`block w-full rounded-lg border px-3 py-2 text-sm transition focus:outline-none focus:ring-2 ${
                                errors[`price_tiers.${index}.cantidad_minima`]
                                    ? 'border-rose-300 focus:border-rose-400 focus:ring-rose-100'
                                    : 'border-slate-300 focus:border-navy focus:ring-navy/10'
                            }`}
                            placeholder="Ej. 5"
                        />
                        {errors[`price_tiers.${index}.cantidad_minima`] && (
                            <p className="mt-1 text-xs text-rose-600">{errors[`price_tiers.${index}.cantidad_minima`]}</p>
                        )}
                    </div>

                    <div className="min-w-[130px] flex-1">
                        <label className="mb-1 block text-xs font-medium text-slate-500">Precio unitario</label>
                        <div className="relative">
                            <span className="pointer-events-none absolute inset-y-0 left-3 flex items-center text-slate-400">$</span>
                            <input
                                type="number"
                                min="0.01"
                                step="0.01"
                                value={tier.precio_unitario}
                                onChange={(e) => updateTier(index, 'precio_unitario', e.target.value)}
                                className={`block w-full rounded-lg border py-2 pl-7 pr-3 text-sm transition focus:outline-none focus:ring-2 ${
                                    errors[`price_tiers.${index}.precio_unitario`]
                                        ? 'border-rose-300 focus:border-rose-400 focus:ring-rose-100'
                                        : 'border-slate-300 focus:border-navy focus:ring-navy/10'
                                }`}
                                placeholder="0.00"
                            />
                        </div>
                        {errors[`price_tiers.${index}.precio_unitario`] && (
                            <p className="mt-1 text-xs text-rose-600">{errors[`price_tiers.${index}.precio_unitario`]}</p>
                        )}
                    </div>

                    <div className="min-w-[90px] whitespace-nowrap pt-6 text-sm text-slate-500">
                        {tier.cantidad_minima && tier.precio_unitario
                            ? `${tier.cantidad_minima}+ → $${Number(tier.precio_unitario).toLocaleString('es-AR')}`
                            : '—'}
                    </div>

                    <button
                        type="button"
                        onClick={() => removeTier(index)}
                        className="mt-6 flex h-8 w-8 items-center justify-center rounded-md text-slate-400 transition hover:bg-rose-50 hover:text-rose-600"
                        title="Quitar escala"
                    >
                        <IconTrash className="h-4 w-4" />
                    </button>
                </div>
            ))}

            <button
                type="button"
                onClick={addTier}
                className="inline-flex items-center gap-1.5 rounded-lg border border-dashed border-slate-300 px-3 py-2 text-sm font-medium text-slate-500 transition hover:border-navy/30 hover:text-navy"
            >
                <IconPlus className="h-4 w-4" />
                Agregar escala de precio
            </button>

            {tiers.length === 0 && (
                <p className="text-sm text-slate-500">
                    Sin escalas configuradas: el producto siempre usará el precio de venta.
                </p>
            )}

            {sortedForPreview.length > 0 && (
                <div className="rounded-lg border border-slate-200 bg-slate-50 p-3 text-sm text-slate-600">
                    <p className="mb-1 font-medium text-slate-700">Vista previa de precios por cantidad:</p>
                    <ul className="space-y-0.5">
                        <li>
                            1 a {Number(sortedForPreview[0].cantidad_minima) - 1} unidades → ${Number(basePrice || 0).toLocaleString('es-AR')} c/u
                        </li>
                        {sortedForPreview.map((tier, index) => {
                            const next = sortedForPreview[index + 1];
                            const rango = next
                                ? `${tier.cantidad_minima} a ${Number(next.cantidad_minima) - 1}`
                                : `${tier.cantidad_minima}+`;
                            return (
                                <li key={tier.id ?? `preview-${index}`}>
                                    {rango} unidades → ${Number(tier.precio_unitario).toLocaleString('es-AR')} c/u
                                </li>
                            );
                        })}
                    </ul>
                </div>
            )}
        </div>
    );
}
