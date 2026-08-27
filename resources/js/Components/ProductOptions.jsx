import React from 'react';
import { precioAddon } from '@/utils/pricing';
import { stockVariante } from '@/utils/productOptions';
import { isLowStock } from '@/utils/stock';

/**
 * Selector de color (variantes) + checklist de add-ons de personalización para la
 * ficha pública de producto. Componente controlado: `value` es el estado de
 * opciones (ver utils/productOptions.js) y `onChange` recibe el estado nuevo
 * completo. `errores` viene de validarOpciones() para pintar los mensajes inline.
 *
 * Si el producto no tiene variantes ni add-ons activos, no renderiza nada (la
 * ficha se ve y se comporta igual que antes de este sistema).
 */

// Rueda de color para la variante "a elección del cliente" (is_custom_color):
// su color_hex es solo referencia en el admin, acá el cliente elige libre.
const RAINBOW =
    'conic-gradient(from 90deg, #ef4444, #f97316, #eab308, #22c55e, #3b82f6, #6366f1, #a855f7, #ef4444)';

export default function ProductOptions({ product, value, onChange, errores }) {
    const variants = product.variants || [];
    const addons = product.addons || [];

    if (variants.length === 0 && addons.length === 0) {
        return null;
    }

    const set = (patch) => onChange({ ...value, ...patch });

    const selectedVariant = variants.find((v) => v.id === value.variantId) || null;

    const toggleAddon = (addon, checked) => {
        const next = { ...value.addons };
        if (checked) {
            next[addon.id] = '';
        } else {
            delete next[addon.id];
        }
        set({ addons: next });
    };

    const setAddonText = (addon, text) => {
        set({ addons: { ...value.addons, [addon.id]: text } });
    };

    return (
        <div className="space-y-6">
            {variants.length > 0 && (
                <div className="space-y-3">
                    <h3 className="text-sm font-semibold text-navy">
                        Color
                        {selectedVariant && (
                            <span className="ml-1 font-normal text-navy/60">— {selectedVariant.name}</span>
                        )}
                    </h3>

                    <div className="flex flex-wrap gap-3">
                        {variants.map((variant) => {
                            const activo = variant.id === value.variantId;
                            const sinStock = stockVariante(variant) <= 0;

                            return (
                                <button
                                    key={variant.id}
                                    type="button"
                                    onClick={() => !sinStock && set({ variantId: variant.id })}
                                    disabled={sinStock}
                                    title={sinStock ? `${variant.name} — sin stock` : variant.name}
                                    aria-pressed={activo}
                                    aria-label={variant.name}
                                    className={`relative flex h-12 w-12 items-center justify-center rounded-full border-2 transition ${
                                        activo
                                            ? 'border-navy ring-2 ring-navy/25'
                                            : 'border-navy/20 hover:border-navy/40'
                                    } ${sinStock ? 'cursor-not-allowed opacity-40' : ''}`}
                                    style={
                                        variant.is_custom_color
                                            ? { backgroundImage: RAINBOW }
                                            : { backgroundColor: variant.color_hex || '#e5e7eb' }
                                    }
                                >
                                    {activo && (
                                        <span className="flex h-5 w-5 items-center justify-center rounded-full bg-white text-navy shadow">
                                            <svg
                                                viewBox="0 0 24 24"
                                                className="h-3.5 w-3.5"
                                                fill="none"
                                                stroke="currentColor"
                                                strokeWidth="3"
                                                strokeLinecap="round"
                                                strokeLinejoin="round"
                                            >
                                                <path d="m4 12 5 5L20 6" />
                                            </svg>
                                        </span>
                                    )}
                                    {sinStock && (
                                        <span className="pointer-events-none absolute h-[2px] w-10 rotate-45 rounded bg-red-500" />
                                    )}
                                </button>
                            );
                        })}
                    </div>

                    {selectedVariant && !selectedVariant.is_custom_color && (
                        <VariantStockHint variant={selectedVariant} />
                    )}

                    {selectedVariant?.is_custom_color && (
                        <div className="space-y-3 rounded-xl border border-navy/15 bg-navy/[0.03] p-4">
                            <p className="text-sm font-medium text-navy">
                                Elegí el color que querés o describilo — con completar al menos uno alcanza.
                            </p>

                            <div className="flex flex-wrap items-center gap-3">
                                <input
                                    type="color"
                                    value={value.customColor || '#000000'}
                                    onChange={(e) => set({ customColor: e.target.value })}
                                    className="h-10 w-14 cursor-pointer rounded border border-navy/20 bg-white p-0.5"
                                    aria-label="Color elegido"
                                />
                                {value.customColor ? (
                                    <span className="inline-flex items-center gap-2 text-xs font-medium text-navy/70">
                                        <span className="font-mono uppercase">{value.customColor}</span>
                                        <button
                                            type="button"
                                            onClick={() => set({ customColor: '' })}
                                            className="underline hover:text-navy"
                                        >
                                            quitar
                                        </button>
                                    </span>
                                ) : (
                                    <span className="text-xs text-navy/50">Sin color elegido</span>
                                )}
                            </div>

                            <textarea
                                value={value.customColorText || ''}
                                onChange={(e) => set({ customColorText: e.target.value })}
                                rows={2}
                                maxLength={255}
                                placeholder="Ej: violeta con destellos plateados, tono Pantone 2685C…"
                                className="block w-full rounded-lg border border-navy/20 px-3 py-2 text-sm transition focus:border-navy focus:outline-none focus:ring-2 focus:ring-navy/10"
                            />

                            {errores?.colorPersonalizado && (
                                <p className="text-xs font-medium text-red-600">
                                    {errores.colorPersonalizado}
                                </p>
                            )}
                        </div>
                    )}
                </div>
            )}

            {addons.length > 0 && (
                <div className="space-y-3">
                    <h3 className="text-sm font-semibold text-navy">Personalización</h3>

                    <div className="space-y-2">
                        {addons.map((addon) => {
                            const checked = value.addons?.[addon.id] !== undefined;
                            const precio = precioAddon(addon);
                            const err = errores?.addons?.[addon.id];
                            const texto = value.addons?.[addon.id] ?? '';

                            return (
                                <div
                                    key={addon.id}
                                    className={`rounded-xl border p-3 transition ${
                                        checked ? 'border-navy/30 bg-navy/[0.03]' : 'border-navy/15 bg-white'
                                    }`}
                                >
                                    <label className="flex cursor-pointer items-start gap-3">
                                        <input
                                            type="checkbox"
                                            checked={checked}
                                            onChange={(e) => toggleAddon(addon, e.target.checked)}
                                            className="mt-0.5 h-4 w-4 rounded border-navy/30 text-navy focus:ring-navy/20"
                                        />
                                        <span className="flex min-w-0 flex-1 flex-wrap items-baseline justify-between gap-x-3 gap-y-0.5">
                                            <span className="text-sm font-medium text-navy">{addon.name}</span>
                                            <span className="text-sm font-semibold text-navy">
                                                {precio > 0
                                                    ? `+ $${precio.toLocaleString('es-AR')}`
                                                    : 'Sin costo'}
                                            </span>
                                        </span>
                                    </label>

                                    {checked && addon.requires_text && (
                                        <div className="mt-2.5 space-y-1 pl-7">
                                            <input
                                                type="text"
                                                value={texto}
                                                onChange={(e) => setAddonText(addon, e.target.value)}
                                                maxLength={addon.max_characters || undefined}
                                                placeholder={addon.text_placeholder || 'Escribí el texto'}
                                                className="block w-full rounded-lg border border-navy/20 px-3 py-2 text-sm transition focus:border-navy focus:outline-none focus:ring-2 focus:ring-navy/10"
                                            />
                                            <div className="flex items-center justify-between gap-2">
                                                {err ? (
                                                    <p className="text-xs font-medium text-red-600">{err}</p>
                                                ) : (
                                                    <span />
                                                )}
                                                {addon.max_characters ? (
                                                    <span className="flex-shrink-0 text-xs text-navy/50">
                                                        {texto.length}/{addon.max_characters}
                                                    </span>
                                                ) : null}
                                            </div>
                                        </div>
                                    )}
                                </div>
                            );
                        })}
                    </div>
                </div>
            )}
        </div>
    );
}

function VariantStockHint({ variant }) {
    if (variant.stock === null || variant.stock === undefined) {
        return null; // stock ilimitado
    }

    if (variant.stock <= 0) {
        return <p className="text-sm font-semibold text-red-600">Este color está sin stock.</p>;
    }

    if (isLowStock(variant.stock)) {
        return (
            <p className="text-sm font-semibold text-amber-600">
                {variant.stock === 1
                    ? '¡Última unidad de este color!'
                    : `¡Pocas unidades de este color! Quedan ${variant.stock}`}
            </p>
        );
    }

    return null;
}
