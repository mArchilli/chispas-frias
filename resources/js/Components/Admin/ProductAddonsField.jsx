import React from 'react';
import { Link } from '@inertiajs/react';

/**
 * Checklist de add-ons (catálogo global) asociados a un producto. `catalog` es la
 * lista completa de add-ons; `selected` es un array de { id, price_override }
 * (sólo los tildados). `price_override` vacío = usa el precio base del add-on.
 */
export default function ProductAddonsField({ catalog = [], selected = [], onChange, error }) {
    const byId = new Map(selected.map((s) => [Number(s.id), s]));

    const toggle = (addonId, checked) => {
        if (checked) {
            onChange([...selected, { id: addonId, price_override: '' }]);
        } else {
            onChange(selected.filter((s) => Number(s.id) !== Number(addonId)));
        }
    };

    const setOverride = (addonId, value) => {
        onChange(
            selected.map((s) => (Number(s.id) === Number(addonId) ? { ...s, price_override: value } : s))
        );
    };

    if (catalog.length === 0) {
        return (
            <p className="text-sm text-slate-500">
                No hay add-ons en el catálogo.{' '}
                <Link href={route('admin.addons.create')} className="font-medium text-navy hover:underline">
                    Creá el primero
                </Link>
                .
            </p>
        );
    }

    return (
        <div className="space-y-2">
            {error && typeof error === 'string' && <p className="text-xs font-medium text-rose-600">{error}</p>}

            {catalog.map((addon) => {
                const entry = byId.get(Number(addon.id));
                const checked = entry !== undefined;

                return (
                    <div
                        key={addon.id}
                        className={`rounded-lg border p-3 transition ${
                            checked ? 'border-navy/30 bg-navy/[0.03]' : 'border-slate-200 bg-white'
                        }`}
                    >
                        <label className="flex cursor-pointer items-start gap-2.5">
                            <input
                                type="checkbox"
                                checked={checked}
                                onChange={(e) => toggle(addon.id, e.target.checked)}
                                className="mt-0.5 h-4 w-4 rounded border-slate-300 text-navy focus:ring-navy/20"
                            />
                            <span className="min-w-0 flex-1">
                                <span className="flex flex-wrap items-center gap-1.5">
                                    <span className="text-sm font-medium text-slate-900">{addon.name}</span>
                                    {!addon.is_active && (
                                        <span className="rounded-full bg-slate-100 px-1.5 py-0.5 text-[10px] font-semibold uppercase text-slate-500">
                                            inactivo
                                        </span>
                                    )}
                                    {addon.requires_text && (
                                        <span className="rounded-full bg-navy/5 px-1.5 py-0.5 text-[10px] font-semibold uppercase text-navy/60">
                                            con texto
                                        </span>
                                    )}
                                </span>
                                <span className="mt-0.5 block text-xs text-slate-500">
                                    Precio base: ${Number(addon.price).toLocaleString('es-AR')}
                                </span>
                            </span>
                        </label>

                        {checked && (
                            <div className="mt-2.5 flex flex-wrap items-center gap-2 pl-7">
                                <label className="text-xs font-medium text-slate-500">Precio propio</label>
                                <div className="relative w-[130px]">
                                    <span className="pointer-events-none absolute inset-y-0 left-2.5 flex items-center text-slate-400">
                                        $
                                    </span>
                                    <input
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        value={entry.price_override ?? ''}
                                        onChange={(e) => setOverride(addon.id, e.target.value)}
                                        placeholder="Base"
                                        className="block w-full rounded-lg border border-slate-300 py-1.5 pl-6 pr-2 text-sm transition focus:border-navy focus:outline-none focus:ring-2 focus:ring-navy/10"
                                    />
                                </div>
                                <span className="text-xs text-slate-400">vacío = precio base</span>
                            </div>
                        )}
                    </div>
                );
            })}
        </div>
    );
}
