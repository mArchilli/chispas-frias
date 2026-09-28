import React from 'react';
import { calcularPrecio, tierAplicable } from '@/utils/pricing';

/**
 * Tabla de "Precios por cantidad" de la ficha de producto: un rango por precio
 * base + cada price_tier, con el precio final tachado cuando hay una oferta
 * aplicable a ese nivel específico (misma resolución que PricingService, ver
 * resources/js/utils/pricing.js).
 */
export default function PriceTiersTable({ product, quantity = 1 }) {
    const priceTiers = [...(product.price_tiers || [])].sort(
        (a, b) => a.cantidad_minima - b.cantidad_minima
    );

    if (priceTiers.length === 0) {
        return null;
    }

    const activeTierId = tierAplicable(priceTiers, quantity)?.id ?? null;
    const niveles = [
        { key: 'base', tierId: null, rango: `1 a ${priceTiers[0].cantidad_minima - 1}`, cantidad: 1 },
        ...priceTiers.map((tier, index) => {
            const siguiente = priceTiers[index + 1];
            return {
                key: `tier-${tier.id}`,
                tierId: tier.id,
                rango: siguiente
                    ? `${tier.cantidad_minima} a ${siguiente.cantidad_minima - 1}`
                    : `${tier.cantidad_minima}+`,
                cantidad: tier.cantidad_minima,
            };
        }),
    ];

    return (
        <div className="overflow-hidden rounded-2xl border border-gray-200">
            <table className="w-full text-sm">
                <caption className="sr-only">Precios por cantidad en pesos argentinos por unidad</caption>
                <thead className="bg-ice-50">
                    <tr>
                        <th scope="col" className="px-3 py-3 text-left font-semibold text-navy-900 sm:px-4">Cantidad</th>
                        <th scope="col" className="px-3 py-3 text-right font-semibold text-navy-900 sm:px-4">Precio por unidad</th>
                    </tr>
                </thead>
                <tbody className="divide-y divide-gray-200">
                    {niveles.map((nivel) => {
                        const resultado = calcularPrecio(product, nivel.cantidad);
                        const active = nivel.tierId === activeTierId;
                        return (
                            <tr key={nivel.key} className={active ? 'bg-ice-100' : 'bg-surface'}>
                                <th scope="row" className="px-3 py-3 text-left font-medium text-navy-900 sm:px-4">
                                    {nivel.rango} unidades
                                    {active && <span className="mt-1 block text-xs font-semibold text-navy-700">Tu selección</span>}
                                </th>
                                <td className="px-3 py-3 text-right sm:px-4">
                                    <span className="font-semibold text-navy-900">
                                        ${resultado.precioUnitarioFinal.toLocaleString('es-AR')}
                                    </span>
                                    {resultado.ofertaAplicada && (
                                        <span className="mt-1 block text-xs text-navy-900/65 line-through">
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
