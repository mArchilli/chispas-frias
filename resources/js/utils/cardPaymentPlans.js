/**
 * Helpers del admin de planes de pago con tarjeta (formularios Create/Edit e
 * Index). El cálculo del recargo en sí es el espejo de CardSurchargeService que
 * vive en utils/cardSurcharge.js — acá sólo se adapta a la forma camelCase que
 * ya consumían estas vistas. El recargo es 100% informativo: no hay integración
 * con Mercado Pago.
 */
import { calcular } from '@/utils/cardSurcharge';

/** Monto de referencia para los ejemplos de recargo en el formulario del admin. */
export const SAMPLE_ORDER_TOTAL = 10000;

export function simulateSurcharge(total, plan) {
    const { surcharge_amount, total_with_surcharge, installment_amount } = calcular(total, plan);

    return {
        surchargeAmount: surcharge_amount,
        totalWithSurcharge: total_with_surcharge,
        installmentAmount: installment_amount,
    };
}

export function formatMoney(value) {
    if (value === null || value === undefined || value === '') return '—';
    return (
        '$' +
        Number(value).toLocaleString('es-AR', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        })
    );
}

/**
 * Texto de vista previa para los formularios de Create/Edit, armado en memoria
 * a partir del form. Espejo de utils/discountCodes.js y utils/addons.js.
 */
export function buildPlanPreviewText(data) {
    const installments = parseInt(data.installments, 10);
    const percentage = parseFloat(data.surcharge_percentage);

    if (!installments || installments <= 0 || isNaN(percentage)) {
        return 'Completá las cuotas y el recargo para ver la vista previa.';
    }

    const { totalWithSurcharge, installmentAmount } = simulateSurcharge(SAMPLE_ORDER_TOTAL, {
        installments,
        surcharge_percentage: percentage,
    });

    const cuotaLabel = installments === 1 ? '1 pago' : `${installments} cuotas`;

    return `Sobre ${formatMoney(SAMPLE_ORDER_TOTAL)}: +${percentage}% → ${formatMoney(
        totalWithSurcharge
    )} (${cuotaLabel} de ${formatMoney(installmentAmount)})`;
}
