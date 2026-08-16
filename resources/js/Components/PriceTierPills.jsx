import React from 'react';
import { calcularPrecio, tierAplicable } from '@/utils/pricing';

/**
 * Un pill por nivel de precio del producto (precio base + cada price_tier).
 * Click selecciona esa cantidad. El pill activo es el que resuelve tierAplicable()
 * para la cantidad seleccionada actualmente (no una comparación de igualdad directa,
 * así el pill "5+" sigue marcado como activo si el usuario tipea cantidad=7).
 */
export default function PriceTierPills({ product, quantity, onSelect }) {
    const priceTiers = product.price_tiers || [];

    if (priceTiers.length === 0) {
        return null;
    }

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
        <div className="space-y-2">
            <p className="text-sm font-medium text-navy">Precios por cantidad:</p>
            <div className="flex flex-wrap gap-2">
                {niveles.map((nivel) => {
                    const resultado = calcularPrecio(product, nivel.cantidad);
                    const activo = tierActivoId === nivel.tierId;
                    const ahorro = precioBase > 0 && resultado.precioUnitarioFinal < precioBase
                        ? Math.round((1 - resultado.precioUnitarioFinal / precioBase) * 100)
                        : 0;

                    return (
                        <button
                            key={nivel.key}
                            type="button"
                            onClick={() => onSelect(nivel.cantidad)}
                            className={`flex flex-col items-start px-3 py-2 rounded-xl border-2 transition-all duration-200 text-left min-w-[6.5rem] ${
                                activo
                                    ? 'bg-gold border-gold text-navy'
                                    : 'bg-white border-navy/20 text-navy hover:border-gold/60'
                            }`}
                        >
                            <span className="text-xs font-semibold">{nivel.label}</span>
                            <span className="text-sm font-bold">
                                ${resultado.precioUnitarioFinal.toLocaleString('es-AR')}
                            </span>
                            {resultado.ofertaAplicada && (
                                <span className="text-[10px] font-normal line-through opacity-70">
                                    ${resultado.precioLista.toLocaleString('es-AR')}
                                </span>
                            )}
                            {ahorro > 0 && (
                                <span className={`text-[10px] font-medium ${activo ? 'text-navy/80' : 'text-green-600'}`}>
                                    Ahorrás {ahorro}%
                                </span>
                            )}
                        </button>
                    );
                })}
            </div>
        </div>
    );
}
