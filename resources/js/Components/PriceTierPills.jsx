import React from 'react';
import { calcularPrecio, tierAplicable } from '@/utils/pricing';

/**
 * Un pill por nivel de precio del producto (precio base + cada price_tier).
 * Click selecciona esa cantidad. El pill activo es el que resuelve tierAplicable()
 * para la cantidad seleccionada actualmente (no una comparación de igualdad directa,
 * así el pill "5+" sigue marcado como activo si el usuario tipea cantidad=7). Los
 * niveles cuya cantidad_minima supera el stock real del producto quedan
 * deshabilitados y tachados: existen como información de precio, pero no se
 * pueden seleccionar porque no hay unidades suficientes.
 */
export default function PriceTierPills({ product, quantity, onSelect, disabled = false, maxQuantity = Infinity }) {
    const priceTiers = [...(product.price_tiers || [])].sort((a, b) => a.cantidad_minima - b.cantidad_minima);

    if (priceTiers.length === 0) {
        return null;
    }

    const stock = product.stock ?? Infinity;

    const niveles = [
        { key: 'base', cantidad: 1, label: '1+ unidad', tierId: null },
        ...priceTiers.map((tier) => ({
            key: `tier-${tier.id}`,
            cantidad: tier.cantidad_minima,
            label: `${tier.cantidad_minima}+ unidades`,
            tierId: tier.id,
        })),
    ];

    const precioBase = calcularPrecio(product, 1).precioUnitarioFinal;
    const tierActivo = tierAplicable(priceTiers, quantity);
    const tierActivoId = tierActivo?.id ?? null;

    return (
        <fieldset className="min-w-0">
            <legend className="mb-3 text-sm font-semibold text-navy-900">Elegí tu precio por cantidad</legend>
            <div className="grid grid-cols-[repeat(auto-fit,minmax(8rem,1fr))] gap-2">
                {niveles.map((nivel) => {
                    const resultado = calcularPrecio(product, nivel.cantidad);
                    const activo = tierActivoId === nivel.tierId;
                    const sinStockSuficiente = nivel.cantidad > stock;
                    const superaLimite = nivel.cantidad > maxQuantity;
                    const ahorro = precioBase > 0 && resultado.precioUnitarioFinal < precioBase
                        ? Math.round((1 - resultado.precioUnitarioFinal / precioBase) * 100)
                        : 0;

                    return (
                        <button
                            key={nivel.key}
                            type="button"
                            disabled={disabled || sinStockSuficiente || superaLimite}
                            onClick={() => onSelect(nivel.cantidad)}
                            aria-pressed={activo}
                            title={sinStockSuficiente ? `Solo quedan ${stock} unidades disponibles` : superaLimite ? `Podés agregar hasta ${maxQuantity} unidades por vez` : undefined}
                            className={`flex min-w-0 flex-col items-start rounded-2xl border px-3 py-3 text-left transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-storefront focus-visible:ring-offset-2 disabled:cursor-not-allowed ${
                                sinStockSuficiente || superaLimite
                                    ? 'border-gray-200 bg-background text-gray-500'
                                    : activo
                                        ? 'border-storefront bg-storefront text-white'
                                        : 'border-storefront/30 bg-surface text-navy-900 hover:border-storefront hover:bg-ice-50'
                            }`}
                        >
                            <span className={`text-xs font-semibold ${sinStockSuficiente ? 'line-through' : ''}`}>{nivel.label}</span>
                            <span className={`mt-1 break-all text-base font-bold ${sinStockSuficiente ? 'line-through' : ''}`}>
                                ${resultado.precioUnitarioFinal.toLocaleString('es-AR')}
                            </span>
                            {sinStockSuficiente ? (
                                <span className="mt-1 text-xs font-medium text-navy-700">
                                    Sin stock suficiente
                                </span>
                            ) : superaLimite ? (
                                <span className="mt-1 text-xs text-navy-700">Hasta {maxQuantity} por vez</span>
                            ) : (
                                <>
                                    {resultado.ofertaAplicada && (
                                        <span className="mt-1 text-xs font-normal line-through opacity-75">
                                            ${resultado.precioLista.toLocaleString('es-AR')}
                                        </span>
                                    )}
                                    {ahorro > 0 && (
                                        <span className={`mt-1 text-xs font-medium ${activo ? 'text-white' : 'text-navy-700'}`}>
                                            Ahorrás {ahorro}%
                                        </span>
                                    )}
                                </>
                            )}
                        </button>
                    );
                })}
            </div>
        </fieldset>
    );
}
