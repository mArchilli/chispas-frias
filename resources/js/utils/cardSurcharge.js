/**
 * Espejo 1:1 en JS de App\Services\CardSurchargeService::calcular().
 *
 * El recargo por pago con tarjeta de crédito es un cargo único sobre el total
 * (no compuesto cuota a cuota): "cuotas sin interés" según el plan. Es 100%
 * informativo — este proyecto no integra ningún SDK/API de Mercado Pago ni
 * cobra online.
 *
 * Se usa para simular el recargo en el cliente sin ir al servidor en cada
 * interacción (hoy: los botones de plan de la ficha de producto). El monto que
 * efectivamente se cobre, si alguna vez entra al circuito de pago, lo resuelve
 * siempre el backend con CardSurchargeService.
 *
 * @param {number} total  Total sobre el que se aplica el recargo.
 * @param {{ installments: number|string, surcharge_percentage: number|string }} plan
 * @returns {{ surcharge_amount: number, total_with_surcharge: number, installment_amount: number }}
 */
export function calcular(total, plan) {
    const base = Number(total) || 0;
    const percentage = Number(plan?.surcharge_percentage) || 0;
    const installments = Math.max(1, Number(plan?.installments) || 1);

    const surchargeAmount = round2(base * (percentage / 100));
    const totalWithSurcharge = round2(base + surchargeAmount);
    const installmentAmount = round2(totalWithSurcharge / installments);

    return {
        surcharge_amount: surchargeAmount,
        total_with_surcharge: totalWithSurcharge,
        installment_amount: installmentAmount,
    };
}

function round2(value) {
    return Math.round((value + Number.EPSILON) * 100) / 100;
}
