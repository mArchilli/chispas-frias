import React from 'react';
import { aplicarDescuento } from '@/utils/pricing';

const TIPOS_DESCUENTO = [
    { value: 'porcentaje', label: 'Porcentaje (%)' },
    { value: 'fijo', label: 'Monto fijo ($)' },
];

function precioConDescuento(precioBase, tipoDescuento, valorDescuento) {
    // Guarda explícita para '': aplicarDescuento haría Number('') = 0, que no es
    // NaN, y devolvería un precio con descuento "0" en vez de "sin dato todavía".
    if (precioBase == null || valorDescuento === '') return null;
    return aplicarDescuento(precioBase, tipoDescuento, valorDescuento);
}

/**
 * Tipo de descuento + valor + alcance, compartido entre el modal rápido de
 * Admin/Products/Index y la vista dedicada Admin/Offers/Index — los dos
 * puntos de entrada de ofertas del admin. `product` necesita `price` y
 * `price_tiers` (id, cantidad_minima, precio_unitario) para poblar el
 * selector de "alcance específico" y la vista previa por nivel de precio.
 */
export default function OfferDiscountFields({ data, setData, errors = {}, product, disabled = false }) {
    const priceTiers = product?.price_tiers || [];
    const basePrice = product?.price != null ? Number(product.price) : null;

    const setAlcance = (value) => {
        // OJO: setData(objeto) en Inertia REEMPLAZA todo el form data (no lo mergea
        // como setData(key, value)) — hay que setear cada campo por separado.
        setData('alcance', value);
        if (value === 'todos') {
            setData('product_price_tier_id', null);
        }
    };

    const nivelesParaPreview = [
        { key: 'base', label: `Precio base ($${basePrice ?? 0})`, tierId: null, precio: basePrice },
        ...priceTiers.map((tier) => ({
            key: `tier-${tier.id}`,
            label: `${tier.cantidad_minima}+ unidades ($${tier.precio_unitario})`,
            tierId: tier.id,
            precio: Number(tier.precio_unitario),
        })),
    ];

    const afecta = (tierId) => {
        if (data.alcance === 'todos') return true;
        const seleccionado = data.product_price_tier_id ? Number(data.product_price_tier_id) : null;
        return seleccionado === tierId;
    };

    return (
        <div className="space-y-4">
            <div className="grid grid-cols-2 gap-3">
                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">
                        Tipo de descuento <span className="text-red-500">*</span>
                    </label>
                    <select
                        value={data.tipo_descuento}
                        onChange={(e) => setData('tipo_descuento', e.target.value)}
                        disabled={disabled}
                        className="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                    >
                        {TIPOS_DESCUENTO.map((tipo) => (
                            <option key={tipo.value} value={tipo.value}>{tipo.label}</option>
                        ))}
                    </select>
                    {errors.tipo_descuento && <p className="mt-1 text-sm text-red-600">{errors.tipo_descuento}</p>}
                </div>

                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">
                        Valor del descuento <span className="text-red-500">*</span>
                    </label>
                    <input
                        type="number"
                        step="0.01"
                        min="0.01"
                        max={data.tipo_descuento === 'porcentaje' ? 100 : undefined}
                        value={data.valor_descuento}
                        onChange={(e) => setData('valor_descuento', e.target.value)}
                        disabled={disabled}
                        placeholder={data.tipo_descuento === 'porcentaje' ? 'Ej: 25' : 'Ej: 500'}
                        className="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                    />
                    {errors.valor_descuento && <p className="mt-1 text-sm text-red-600">{errors.valor_descuento}</p>}
                </div>
            </div>

            <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">
                    Alcance de la oferta <span className="text-red-500">*</span>
                </label>
                <select
                    value={data.alcance}
                    onChange={(e) => setAlcance(e.target.value)}
                    disabled={disabled}
                    className="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                >
                    <option value="todos">Todos los precios (base y escalas por cantidad)</option>
                    <option value="especifico">Un precio específico</option>
                </select>
                {errors.alcance && <p className="mt-1 text-sm text-red-600">{errors.alcance}</p>}
            </div>

            {data.alcance === 'especifico' && (
                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">
                        Precio al que aplica
                    </label>
                    <select
                        value={data.product_price_tier_id ?? ''}
                        onChange={(e) => setData('product_price_tier_id', e.target.value === '' ? null : e.target.value)}
                        disabled={disabled}
                        className="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                    >
                        <option value="">{`Precio base ($${basePrice ?? 0})`}</option>
                        {priceTiers.map((tier) => (
                            <option key={tier.id} value={tier.id}>
                                {`${tier.cantidad_minima}+ unidades ($${tier.precio_unitario})`}
                            </option>
                        ))}
                    </select>
                    {errors.product_price_tier_id && <p className="mt-1 text-sm text-red-600">{errors.product_price_tier_id}</p>}
                    {priceTiers.length === 0 && (
                        <p className="mt-1 text-xs text-gray-500">Este producto no tiene escalas de precio: solo se puede apuntar al precio base.</p>
                    )}
                </div>
            )}

            {basePrice != null && data.tipo_descuento && data.valor_descuento !== '' && (
                <div className="p-3 bg-blue-50 rounded-md text-xs text-blue-900 border border-blue-100">
                    <p className="font-medium mb-1">Vista previa por nivel de precio:</p>
                    <ul className="space-y-0.5">
                        {nivelesParaPreview.map((nivel) => {
                            const aplica = afecta(nivel.tierId);
                            const final = aplica ? precioConDescuento(nivel.precio, data.tipo_descuento, data.valor_descuento) : nivel.precio;
                            return (
                                <li key={nivel.key} className="flex justify-between">
                                    <span>{nivel.label}</span>
                                    <span className={aplica ? 'font-semibold text-green-700' : 'text-gray-500'}>
                                        {aplica && final !== null
                                            ? `$${final.toLocaleString('es-AR')} (con descuento)`
                                            : `$${nivel.precio?.toLocaleString('es-AR') ?? '—'} (sin cambios)`}
                                    </span>
                                </li>
                            );
                        })}
                    </ul>
                </div>
            )}
        </div>
    );
}
