import { calcular } from '@/utils/cardSurcharge';

const money = (value) => (Number(value) || 0).toLocaleString('es-AR', { maximumFractionDigits: 0 });
const percent = (value) => `${parseFloat(Number(value || 0).toFixed(2))}%`;
const textoCuotas = (cuotas) => `${cuotas} ${Number(cuotas) === 1 ? 'cuota' : 'cuotas'}`;

/**
 * Cómo se puede pagar un total, SOLO como información (no se elige nada: el medio de pago se coordina por WhatsApp):
 * "$X en efectivo o transferencia · o hasta N cuotas con tarjeta (con recargo)", con el detalle de cada plan plegado.
 *
 * Dos formas de alimentarlo:
 *  - `cuotas`: el objeto que arma el servidor (CalculadoraPedido::infoCuotas) para el carrito, el checkout y el drawer. El servidor
 *    es la fuente de verdad de los montos.
 *  - `planes` + `total`: los planes activos y un total ya calculado en el navegador. Es el caso de la ficha del producto, donde el
 *    total cambia con la cantidad y las opciones que se van eligiendo; el cálculo es el espejo de CardSurchargeService.
 *
 * Sin planes activos (o sin total) no muestra nada.
 */
export default function InfoCuotas({ cuotas = null, planes = null, total = 0, compacto = false, className = '' }) {
    const info = cuotas ?? desdePlanes(planes, total);

    if (!info || info.planes.length === 0) return null;

    return (
        <div className={`${compacto ? 'text-xs' : 'text-sm'} text-navy-900 ${className}`}>
            <p className="leading-relaxed text-navy-900/80">
                <span className="font-bold text-navy-900">${money(info.efectivo)}</span> en efectivo o transferencia
                {' · '}o hasta <span className="font-bold text-navy-900">{textoCuotas(info.max_cuotas)}</span> con tarjeta{' '}
                <span className="whitespace-nowrap">({info.con_recargo ? 'con recargo' : 'sin recargo'})</span>
            </p>

            <details className="mt-1">
                <summary className="flex min-h-9 w-fit cursor-pointer items-center font-semibold text-storefront focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-storefront">
                    Ver cuotas
                </summary>
                <ul className="mt-1 divide-y divide-navy-900/10 rounded-xl border border-navy-900/10 bg-white">
                    {info.planes.map((plan) => (
                        <li key={plan.id} className="flex items-baseline justify-between gap-3 px-3 py-2">
                            <span className="font-semibold text-navy-900">
                                {textoCuotas(plan.installments)} de ${money(plan.installment_amount)}
                            </span>
                            <span className="text-right text-navy-900/60">
                                {plan.surcharge_amount > 0
                                    ? `total $${money(plan.total_with_surcharge)} (+${percent(plan.surcharge_percentage)})`
                                    : 'sin recargo'}
                            </span>
                        </li>
                    ))}
                </ul>
            </details>
        </div>
    );
}

/** Mismo shape que CalculadoraPedido::infoCuotas, calculado acá con el espejo de CardSurchargeService. */
function desdePlanes(planes, total) {
    const base = Number(total) || 0;

    if (!planes || planes.length === 0 || base <= 0) return null;

    const filas = [...planes]
        .sort((a, b) => Number(a.installments) - Number(b.installments))
        .map((plan) => ({
            id: plan.id,
            name: plan.name,
            installments: Number(plan.installments),
            surcharge_percentage: Number(plan.surcharge_percentage),
            ...calcular(base, plan),
        }));

    return {
        efectivo: base,
        max_cuotas: Math.max(...filas.map((fila) => fila.installments)),
        con_recargo: filas.some((fila) => fila.surcharge_amount > 0),
        planes: filas,
    };
}
