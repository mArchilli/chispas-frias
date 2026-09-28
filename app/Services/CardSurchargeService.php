<?php

namespace App\Services;

use App\Models\CardPaymentPlan;

class CardSurchargeService
{
    /**
     * Fuente de verdad del cálculo del recargo por pago con tarjeta de crédito.
     *
     * El recargo es un cargo único sobre el total del pedido completo (no por
     * producto, no compuesto cuota a cuota): "cuotas sin interés mensual" según
     * el plan. Es 100% informativo — este proyecto no integra ningún SDK/API de
     * Mercado Pago ni cobra online; el vendedor genera el link de pago a mano
     * por el valor `total_with_surcharge`.
     *
     * @return array{surcharge_amount: float, total_with_surcharge: float, installment_amount: float}
     */
    public function calcular(float $total, CardPaymentPlan $plan): array
    {
        $surchargeAmount = round($total * ((float) $plan->surcharge_percentage / 100), 2);
        $totalWithSurcharge = round($total + $surchargeAmount, 2);
        $installmentAmount = round($totalWithSurcharge / max(1, (int) $plan->installments), 2);

        return [
            'surcharge_amount' => $surchargeAmount,
            'total_with_surcharge' => $totalWithSurcharge,
            'installment_amount' => $installmentAmount,
        ];
    }
}
