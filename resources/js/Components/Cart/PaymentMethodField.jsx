import { useEffect, useState } from 'react';
import { router } from '@inertiajs/react';
import axios from 'axios';
import toast from 'react-hot-toast';
import { calcular } from '@/utils/cardSurcharge';

/**
 * Selector "Forma de pago" del resumen del carrito / checkout, compartido entre
 * Cart/Index y Cart/Checkout. Mismo patrón de interacción que DiscountCodeField
 * (aplicar / quitar contra la sesión del backend + router.reload):
 *
 *  - "Efectivo / Transferencia (sin recargo)" — opción por defecto. Elegirla
 *    equivale a "quitar": DELETE cart.payment-plan.remove.
 *  - un plan de cuotas activo — POST cart.payment-plan.set { plan_id }.
 *
 * El plan queda en session('cart_payment_plan'); el recargo lo recalcula siempre
 * el backend (resolvePaymentPlan → CardSurchargeService) y llega ya resuelto en
 * `paymentPlan`. La previsualización por opción usa el mirror JS
 * (utils/cardSurcharge.js) sólo para no ir al servidor en cada render.
 *
 * Es 100% informativo: el total que se cobra y el mensaje de WhatsApp del pedido
 * NO cambian. Sin elección previa se ve "Efectivo / Transferencia" seleccionado
 * y ningún desglose extra.
 */
export default function PaymentMethodField({
    plans = [],
    paymentPlan = null,
    removedReason = null,
    subtotal = 0,
    total = 0,
    discountCode = null,
    reloadOnly = ['paymentPlan', 'paymentPlanRemovedReason'],
}) {
    // id del request en vuelo: 'cash' para la opción sin recargo, el id del plan
    // para un plan de cuotas, null si no hay nada viajando.
    const [pendingId, setPendingId] = useState(null);

    // Avisar si el backend quitó el plan automáticamente (se desactivó o se
    // borró desde el panel desde la última vez que se cargó el carrito).
    useEffect(() => {
        if (removedReason) {
            toast.error(removedReason);
        }
    }, [removedReason]);

    if (plans.length === 0) {
        return null;
    }

    const selectedId = paymentPlan?.id ?? null;
    const busy = pendingId !== null;

    // planId === null → "Efectivo / Transferencia" (quita el plan de la sesión).
    const choose = async (planId) => {
        if (busy || planId === selectedId) return;

        setPendingId(planId ?? 'cash');

        try {
            const response = planId === null
                ? await axios.delete(route('cart.payment-plan.remove'))
                : await axios.post(route('cart.payment-plan.set'), { plan_id: planId });

            toast.success(response.data?.message || 'Forma de pago actualizada.');
            router.reload({ only: reloadOnly });
        } catch (error) {
            toast.error(error.response?.data?.message || 'No pudimos actualizar la forma de pago.');
        } finally {
            setPendingId(null);
        }
    };

    return (
        <div className="space-y-2">
            <p className="text-sm font-medium text-navy">Forma de pago</p>

            <div className="space-y-2" role="radiogroup" aria-label="Forma de pago">
                <PaymentOption
                    label="Efectivo / Transferencia"
                    hint="Sin recargo"
                    selected={selectedId === null}
                    loading={pendingId === 'cash'}
                    disabled={busy}
                    onSelect={() => choose(null)}
                />

                {plans.map((plan) => {
                    const sim = calcular(total, plan);
                    const cuotas = Number(plan.installments) === 1
                        ? `1 pago de $${formatMoney(sim.installment_amount)}`
                        : `${plan.installments} cuotas de $${formatMoney(sim.installment_amount)}`;

                    return (
                        <PaymentOption
                            key={plan.id}
                            label={`Tarjeta de crédito · ${plan.name}`}
                            hint={`+${formatPercentage(plan.surcharge_percentage)}% · ${cuotas}`}
                            selected={selectedId === plan.id}
                            loading={pendingId === plan.id}
                            disabled={busy}
                            onSelect={() => choose(plan.id)}
                        />
                    );
                })}
            </div>

            {paymentPlan && (
                <div className="rounded-lg border border-navy/10 bg-navy/[0.02] p-3 text-sm">
                    <div className="flex justify-between">
                        <span className="text-navy/70">Subtotal:</span>
                        <span className="text-navy">${formatMoney(subtotal)}</span>
                    </div>
                    {discountCode && (
                        <div className="flex justify-between">
                            <span className="text-navy/70">Descuento ({discountCode.code}):</span>
                            <span className="text-green-600">
                                −${formatMoney(discountCode.amount)}
                            </span>
                        </div>
                    )}
                    <div className="flex justify-between">
                        <span className="text-navy/70">
                            Recargo tarjeta ({formatPercentage(paymentPlan.surcharge_percentage)}%):
                        </span>
                        <span className="text-navy">+${formatMoney(paymentPlan.surcharge_amount)}</span>
                    </div>
                    <div className="mt-1 flex justify-between border-t border-navy/10 pt-1 font-semibold">
                        <span className="text-navy">Total con tarjeta:</span>
                        <span className="text-navy">${formatMoney(paymentPlan.total_with_surcharge)}</span>
                    </div>
                    <p className="mt-1 text-xs text-navy/60">
                        {Number(paymentPlan.installments) === 1
                            ? `1 pago de $${formatMoney(paymentPlan.installment_amount)}`
                            : `${paymentPlan.installments} cuotas de $${formatMoney(paymentPlan.installment_amount)} c/u`}
                    </p>
                    <p className="mt-1 text-[11px] text-navy/50">
                        Valor informativo. El vendedor genera el link de pago por este monto; el
                        total del pedido no cambia.
                    </p>
                </div>
            )}
        </div>
    );
}

function PaymentOption({ label, hint, selected, loading, disabled, onSelect }) {
    return (
        <button
            type="button"
            role="radio"
            aria-checked={selected}
            onClick={onSelect}
            disabled={disabled}
            className={`flex w-full items-start gap-3 rounded-lg border-2 px-3 py-2 text-left transition-all duration-200 disabled:cursor-not-allowed ${
                selected
                    ? 'border-gold bg-gold/10'
                    : 'border-navy/15 bg-white hover:border-gold/60'
            } ${disabled && !loading ? 'opacity-50' : ''}`}
        >
            <span
                className={`mt-0.5 flex h-4 w-4 flex-shrink-0 items-center justify-center rounded-full border-2 ${
                    selected ? 'border-gold' : 'border-navy/30'
                }`}
            >
                {loading ? (
                    <svg className="h-3 w-3 animate-spin text-navy" fill="none" viewBox="0 0 24 24">
                        <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                        <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                    </svg>
                ) : (
                    selected && <span className="h-2 w-2 rounded-full bg-gold" />
                )}
            </span>
            <span className="min-w-0 flex-1">
                <span className="block text-sm font-medium text-navy">{label}</span>
                {hint && <span className="block text-xs text-navy/60">{hint}</span>}
            </span>
        </button>
    );
}

/** "20.00" → "20"; "20.50" → "20.5"; deja los decimales significativos. */
function formatPercentage(value) {
    return String(Number(value) || 0);
}

function formatMoney(value) {
    return (Number(value) || 0).toLocaleString('es-AR', { maximumFractionDigits: 2 });
}
