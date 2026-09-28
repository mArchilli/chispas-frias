import React from 'react';

function Toggle({ label, hint, checked, onChange }) {
    return (
        <div className="flex items-center justify-between rounded-lg bg-background px-4 py-3.5">
            <div>
                <p className="text-sm font-medium text-graphite">{label}</p>
                <p className="text-xs text-gray-500">{hint}</p>
            </div>
            <label className="relative inline-flex cursor-pointer items-center">
                <input type="checkbox" checked={checked} onChange={onChange} className="peer sr-only" />
                <div className="peer h-6 w-11 rounded-full bg-gray-200 transition-colors after:absolute after:left-[2px] after:top-[2px] after:h-5 after:w-5 after:rounded-full after:border after:border-gray-200 after:bg-surface after:transition-all after:content-[''] peer-checked:bg-ice-500 peer-checked:after:translate-x-full peer-checked:after:border-white peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-ice-500/20" />
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
            {errors.is_active && <p className="text-xs font-medium text-navy-700">{errors.is_active}</p>}
            {errors.is_featured && <p className="text-xs font-medium text-navy-700">{errors.is_featured}</p>}
        </div>
    );
}
