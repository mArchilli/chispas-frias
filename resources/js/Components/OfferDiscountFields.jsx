import React from 'react';
import { aplicarDescuento } from '@/utils/pricing';
import { IconCheck } from '@/Components/Admin/Icons';

const TIPOS_DESCUENTO = [
    { value: 'porcentaje', symbol: '%', label: 'Porcentaje' },
    { value: 'fijo', symbol: '$', label: 'Monto fijo' },
];

const ALCANCES = [
    { value: 'todos', label: 'Todos los precios' },
    { value: 'especifico', label: 'Un precio específico' },
];

function precioConDescuento(precioBase, tipoDescuento, valorDescuento) {
    // Guarda explícita para '': aplicarDescuento haría Number('') = 0, que no es
    // NaN, y devolvería un precio con descuento "0" en vez de "sin dato todavía".
    if (precioBase == null || valorDescuento === '') return null;
    return aplicarDescuento(precioBase, tipoDescuento, valorDescuento);
}

function ToggleGroup({ options, value, onChange, disabled }) {
    return (
        <div className="grid grid-cols-2 gap-2">
            {options.map((option) => {
                const active = value === option.value;
                return (
                    <button
                        key={option.value}
                        type="button"
                        onClick={() => onChange(option.value)}
                        disabled={disabled}
                        className={`rounded-lg border px-3 py-2.5 text-sm font-medium transition ${
                            active
                                ? 'border-navy bg-navy text-white'
                                : 'border-slate-300 text-slate-700 hover:border-slate-400 hover:bg-slate-50'
                        } ${disabled ? 'pointer-events-none opacity-50' : ''}`}
                    >
                        {option.symbol && (
                            <span className={`mr-1.5 font-semibold ${active ? 'text-gold' : 'text-slate-400'}`}>
                                {option.symbol}
                            </span>
                        )}
                        {option.label}
                    </button>
                );
            })}
        </div>
    );
}

/**
 * Tipo de descuento + valor + alcance, compartido entre el modal rápido de
 * Admin/Products/Index y las vistas dedicadas Admin/Offers/Create y Edit —
 * los tres puntos de entrada de ofertas del admin. `product` necesita
 * `price` y `price_tiers` (id, cantidad_minima, precio_unitario) para poblar
 * las cards de "alcance específico" y la vista previa por nivel de precio.
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

    const selectedTierId = data.product_price_tier_id ? Number(data.product_price_tier_id) : null;

    return (
        <div className="space-y-4">
            <div>
                <label className="block text-sm font-medium text-slate-700 mb-1.5">
                    Tipo de descuento <span className="text-rose-500">*</span>
                </label>
                <ToggleGroup
                    options={TIPOS_DESCUENTO}
                    value={data.tipo_descuento}
                    onChange={(value) => setData('tipo_descuento', value)}
                    disabled={disabled}
                />
                {errors.tipo_descuento && <p className="mt-1.5 text-xs font-medium text-rose-600">{errors.tipo_descuento}</p>}
            </div>

            <div>
                <label className="block text-sm font-medium text-slate-700 mb-1.5">
                    Valor del descuento <span className="text-rose-500">*</span>
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
                    className="block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm transition focus:border-navy focus:outline-none focus:ring-2 focus:ring-navy/10"
                />
                {errors.valor_descuento && <p className="mt-1.5 text-xs font-medium text-rose-600">{errors.valor_descuento}</p>}
            </div>

            <div>
                <label className="block text-sm font-medium text-slate-700 mb-1.5">
                    Alcance de la oferta <span className="text-rose-500">*</span>
                </label>
                <ToggleGroup options={ALCANCES} value={data.alcance} onChange={setAlcance} disabled={disabled} />
                {errors.alcance && <p className="mt-1.5 text-xs font-medium text-rose-600">{errors.alcance}</p>}
            </div>

            {data.alcance === 'especifico' && (
                <div>
                    <label className="block text-sm font-medium text-slate-700 mb-1.5">
                        Precio al que aplica
                    </label>
                    <div className="grid grid-cols-1 gap-2 sm:grid-cols-2">
                        {nivelesParaPreview.map((nivel) => {
                            const selected = selectedTierId === nivel.tierId;
                            return (
                                <button
                                    key={nivel.key}
                                    type="button"
                                    onClick={() => setData('product_price_tier_id', nivel.tierId)}
                                    disabled={disabled}
                                    className={`flex items-center gap-3 rounded-lg border p-3 text-left transition ${
                                        selected
                                            ? 'border-navy bg-navy/5'
                                            : 'border-slate-200 hover:border-slate-300'
                                    } ${disabled ? 'pointer-events-none opacity-50' : ''}`}
                                >
                                    <span
                                        className={`flex h-5 w-5 flex-shrink-0 items-center justify-center rounded border-2 transition ${
                                            selected ? 'border-navy bg-navy' : 'border-slate-300'
                                        }`}
                                    >
                                        {selected && <IconCheck className="h-3.5 w-3.5 text-white" strokeWidth={3} />}
                                    </span>
                                    <span className="text-sm font-medium text-slate-900">{nivel.label}</span>
                                </button>
                            );
                        })}
                    </div>
                    {errors.product_price_tier_id && (
                        <p className="mt-1.5 text-xs font-medium text-rose-600">{errors.product_price_tier_id}</p>
                    )}
                    {priceTiers.length === 0 && (
                        <p className="mt-1.5 text-xs text-slate-500">
                            Este producto no tiene escalas de precio: solo se puede apuntar al precio base.
                        </p>
                    )}
                </div>
            )}

            {basePrice != null && data.tipo_descuento && data.valor_descuento !== '' && (
                <div className="rounded-lg border border-slate-200 bg-slate-50 p-3 text-xs text-slate-600">
                    <p className="mb-1 font-medium text-slate-700">Vista previa por nivel de precio:</p>
                    <ul className="space-y-0.5">
                        {nivelesParaPreview.map((nivel) => {
                            const aplica = afecta(nivel.tierId);
                            const final = aplica ? precioConDescuento(nivel.precio, data.tipo_descuento, data.valor_descuento) : nivel.precio;
                            return (
                                <li key={nivel.key} className="flex justify-between">
                                    <span>{nivel.label}</span>
                                    <span className={aplica ? 'font-semibold text-emerald-700' : 'text-slate-400'}>
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
