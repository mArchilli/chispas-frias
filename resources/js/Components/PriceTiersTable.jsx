import React from 'react';
import { calcularPrecio } from '@/utils/pricing';

/**
 * Tabla de "Precios por cantidad" de la ficha de producto: un rango por precio
 * base + cada price_tier, con el precio final tachado cuando hay una oferta
 * aplicable a ese nivel específico (misma resolución que PricingService, ver
 * resources/js/utils/pricing.js).
 */
export default function PriceTiersTable({ product }) {
    const priceTiers = [...(product.price_tiers || [])].sort(
        (a, b) => a.cantidad_minima - b.cantidad_minima
    );

    if (priceTiers.length === 0) {
        return null;
    }

    const niveles = [
        { key: 'base', rango: `1 a ${priceTiers[0].cantidad_minima - 1}`, cantidad: 1 },
        ...priceTiers.map((tier, index) => {
            const siguiente = priceTiers[index + 1];
            return {
                key: `tier-${tier.id}`,
                rango: siguiente
                    ? `${tier.cantidad_minima} a ${siguiente.cantidad_minima - 1}`
                    : `${tier.cantidad_minima}+`,
                cantidad: tier.cantidad_minima,
            };
        }),
    ];

    return (
        <div className="rounded-xl border border-navy/10 overflow-hidden">
            <table className="w-full text-sm">
                <thead className="bg-navy/5">
                    <tr>
                        <th className="text-left px-4 py-2 font-semibold text-navy">Cantidad</th>
                        <th className="text-right px-4 py-2 font-semibold text-navy">Precio por unidad</th>
                    </tr>
                </thead>
                <tbody className="divide-y divide-navy/10">
                    {niveles.map((nivel) => {
                        const resultado = calcularPrecio(product, nivel.cantidad);
                        return (
                            <tr key={nivel.key}>
                                <td className="px-4 py-2 text-navy/80">{nivel.rango} unidades</td>
                                <td className="px-4 py-2 text-right whitespace-nowrap">
                                    <span className="font-semibold text-navy">
                                        ${resultado.precioUnitarioFinal.toLocaleString('es-AR')}
                                    </span>
                                    {resultado.ofertaAplicada && (
                                        <span className="ml-2 text-xs text-navy/50 line-through">
                                            ${resultado.precioLista.toLocaleString('es-AR')}
                                        </span>
                                    )}
                                </td>
                            </tr>
                        );
                    })}
                </tbody>
            </table>
        </div>
    );
}
