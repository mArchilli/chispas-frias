import React from 'react';
import { calcular } from '@/utils/cardSurcharge';

/**
 * Simulador informativo de recargo por pago con tarjeta de crédito para la
 * ficha de producto. Un botón por plan activo (los que llegan en `plans`);
 * comportamiento tipo toggle — volver a clickear el plan activo lo deselecciona.
 *
 * Al seleccionar un plan calcula el recargo 100% en el cliente (espejo de
 * CardSurchargeService, ver utils/cardSurcharge.js — sin ir al servidor en cada
 * click) sobre `total`: el precio final ya mostrado de ESTE producto (con
 * oferta / variante / add-ons si tiene) por la cantidad elegida — el mismo
 * total que el cliente ve arriba en la ficha. Muestra el desglose debajo de los
 * botones.
 *
 * Es SÓLO simulación por producto: no toca el carrito ni el precio que se
 * cobra. El recargo real se aplica una sola vez, sobre el total del pedido
 * completo, más adelante en el checkout.
 */
export default function CardPaymentPlanSimulator({ plans = [], total = 0, selectedPlanId, onSelect }) {
    if (plans.length === 0 || !(Number(total) > 0)) {
        return null;
    }

    const selectedPlan = plans.find((plan) => plan.id === selectedPlanId) || null;
    const sim = selectedPlan ? calcular(total, selectedPlan) : null;

    return (
        <div className="space-y-2">
            <p className="text-sm font-medium text-navy">Pagá con tarjeta de crédito en cuotas:</p>
            <div className="flex flex-wrap gap-2">
                {plans.map((plan) => {
                    const activo = plan.id === selectedPlanId;
                    return (
                        <button
                            key={plan.id}
                            type="button"
                            aria-pressed={activo}
                            onClick={() => onSelect(activo ? null : plan.id)}
                            className={`px-3 py-2 rounded-xl border-2 text-sm font-semibold transition-all duration-200 ${
                                activo
                                    ? 'bg-gold border-gold text-navy'
                                    : 'bg-white border-navy/20 text-navy hover:border-gold/60'
                            }`}
                        >
                            {plan.name} +{formatPercentage(plan.surcharge_percentage)}%
                        </button>
                    );
                })}
            </div>

            {selectedPlan && sim && (
                <div className="rounded-xl border border-navy/10 bg-navy/[0.02] p-4 text-sm text-navy/80">
                    <p>
                        <span className="font-semibold text-navy">
                            Total: ${formatMoney(sim.total_with_surcharge)}
                        </span>
                        {' — '}
                        {selectedPlan.installments === 1
                            ? `1 pago de $${formatMoney(sim.installment_amount)}`
                            : `${selectedPlan.installments} cuotas de $${formatMoney(sim.installment_amount)} c/u`}{' '}
                        <span className="text-navy/60">
                            (recargo ${formatMoney(sim.surcharge_amount)} incluido)
                        </span>
                    </p>
                    <p className="mt-1 text-xs text-navy/50">
                        Valor informativo por este producto. El recargo se calcula una sola vez sobre el total del pedido.
                    </p>
                </div>
            )}
        </div>
    );
}

/** "20.00" → "20"; "20.50" → "20.5"; deja los decimales significativos. */
function formatPercentage(value) {
    return String(Number(value) || 0);
}

function formatMoney(value) {
    return (Number(value) || 0).toLocaleString('es-AR', { maximumFractionDigits: 2 });
}
