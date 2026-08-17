import React from 'react';

function Toggle({ label, hint, checked, onChange }) {
    return (
        <div className="flex items-center justify-between rounded-lg bg-slate-50 px-4 py-3.5">
            <div>
                <p className="text-sm font-medium text-slate-900">{label}</p>
                <p className="text-xs text-slate-500">{hint}</p>
            </div>
            <label className="relative inline-flex cursor-pointer items-center">
                <input type="checkbox" checked={checked} onChange={onChange} className="peer sr-only" />
                <div className="peer h-6 w-11 rounded-full bg-slate-300 transition-colors after:absolute after:left-[2px] after:top-[2px] after:h-5 after:w-5 after:rounded-full after:border after:border-slate-300 after:bg-white after:transition-all after:content-[''] peer-checked:bg-gold peer-checked:after:translate-x-full peer-checked:after:border-white peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-gold/20" />
            </label>
        </div>
    );
}

/**
 * Toggles de visibilidad y destacado, compartidos entre Create y Edit de productos.
 */
export default function ProductStatusFields({ data, setData, errors = {} }) {
    return (
        <div className="space-y-3">
            <Toggle
                label="Producto visible"
                hint={data.is_active ? 'Aparece en la tienda.' : 'Oculto para los clientes.'}
                checked={data.is_active}
                onChange={(e) => setData('is_active', e.target.checked)}
            />
            <Toggle
                label="Producto destacado"
                hint={data.is_featured ? 'Aparece en secciones destacadas.' : 'Producto regular.'}
                checked={data.is_featured}
                onChange={(e) => setData('is_featured', e.target.checked)}
            />
            {errors.is_active && <p className="text-xs font-medium text-rose-600">{errors.is_active}</p>}
            {errors.is_featured && <p className="text-xs font-medium text-rose-600">{errors.is_featured}</p>}
        </div>
    );
}
