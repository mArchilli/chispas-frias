import React from 'react';

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
        <div className="space-y-4">
            {tiers.map((tier, index) => (
                <div
                    key={tier.id ?? `new-${index}`}
                    className="flex flex-wrap items-start gap-3 bg-white rounded-lg p-4 border border-gray-200"
                >
                    <div className="flex-1 min-w-[140px]">
                        <label className="block text-xs font-medium text-gray-600 mb-1">
                            Cantidad mínima
                        </label>
                        <input
                            type="number"
                            min="2"
                            step="1"
                            value={tier.cantidad_minima}
                            onChange={(e) => updateTier(index, 'cantidad_minima', e.target.value)}
                            className={`block w-full px-3 py-2 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500 ${
                                errors[`price_tiers.${index}.cantidad_minima`] ? 'border-red-300 bg-red-50' : 'border-gray-300'
                            }`}
                            placeholder="Ej: 5"
                        />
                        {errors[`price_tiers.${index}.cantidad_minima`] && (
                            <p className="mt-1 text-xs text-red-600">{errors[`price_tiers.${index}.cantidad_minima`]}</p>
                        )}
                    </div>

                    <div className="flex-1 min-w-[140px]">
                        <label className="block text-xs font-medium text-gray-600 mb-1">
                            Precio unitario
                        </label>
                        <div className="relative">
                            <span className="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-500">$</span>
                            <input
                                type="number"
                                min="0.01"
                                step="0.01"
                                value={tier.precio_unitario}
                                onChange={(e) => updateTier(index, 'precio_unitario', e.target.value)}
                                className={`block w-full pl-7 pr-3 py-2 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500 ${
                                    errors[`price_tiers.${index}.precio_unitario`] ? 'border-red-300 bg-red-50' : 'border-gray-300'
                                }`}
                                placeholder="0.00"
                            />
                        </div>
                        {errors[`price_tiers.${index}.precio_unitario`] && (
                            <p className="mt-1 text-xs text-red-600">{errors[`price_tiers.${index}.precio_unitario`]}</p>
                        )}
                    </div>

                    <div className="pt-6 text-sm text-gray-500 whitespace-nowrap min-w-[90px]">
                        {tier.cantidad_minima && tier.precio_unitario
                            ? `${tier.cantidad_minima}+ → $${Number(tier.precio_unitario).toLocaleString('es-AR')}`
                            : '—'}
                    </div>

                    <button
                        type="button"
                        onClick={() => removeTier(index)}
                        className="mt-6 p-1 text-red-500 hover:text-red-700"
                        title="Quitar escala"
                    >
                        <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                        </svg>
                    </button>
                </div>
            ))}

            <button
                type="button"
                onClick={addTier}
                className="inline-flex items-center px-3 py-2 text-sm font-medium text-blue-700 bg-blue-100 rounded-md hover:bg-blue-200 transition-colors"
            >
                <svg className="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 4v16m8-8H4" />
                </svg>
                Agregar escala de precio
            </button>

            {tiers.length === 0 && (
                <p className="text-sm text-gray-500">
                    Sin escalas configuradas: el producto siempre usará el precio de venta.
                </p>
            )}

            {sortedForPreview.length > 0 && (
                <div className="mt-2 p-3 bg-blue-50 rounded-lg text-sm text-blue-900 border border-blue-100">
                    <p className="font-medium mb-1">Vista previa de precios por cantidad:</p>
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
