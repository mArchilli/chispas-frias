import { useEffect, useMemo, useState } from 'react';
import { router, usePage } from '@inertiajs/react';

const formatearPrecio = (valor) =>
    new Intl.NumberFormat('es-AR', { style: 'currency', currency: 'ARS', minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(Number(valor) || 0);

const aNumero = (valor) => {
    const n = parseFloat(String(valor ?? '').replace(',', '.'));
    return Number.isFinite(n) ? n : 0;
};

const CAMPO = 'block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-storefront focus:ring focus:ring-storefront focus:ring-opacity-50';

/**
 * 10.4 — «Confirmar venta»: crea la venta en el Sistema Central a partir de un pedido reservado, con o sin pago.
 * Muestra el resumen (con las notas por ítem), el envío cobrado, los pagos (ninguno, uno o varios), el saldo que queda en el
 * Central, la retención del despacho («Despachar solo con
 * pago completo», solo si queda saldo) y el vendedor (el usuario logueado, o el que elija un admin). Los datos del formulario (cuentas, medios, formas de
 * envío) los pide al abrirse, así la ficha del pedido no depende del Central.
 */
export default function ConfirmarVentaModal({ order, onClose }) {
    const { errors } = usePage().props;
    const [datos, setDatos] = useState(null);
    const [falla, setFalla] = useState(null);
    const [enviando, setEnviando] = useState(false);

    const [pagos, setPagos] = useState([]);
    const [formaEnvioId, setFormaEnvioId] = useState('');
    const [transportistaId, setTransportistaId] = useState('');
    const [costoEnvio, setCostoEnvio] = useState('');
    const [esGratis, setEsGratis] = useState(false);
    const [vendedorId, setVendedorId] = useState('');
    const [soloConPagoCompleto, setSoloConPagoCompleto] = useState(false);

    useEffect(() => {
        let activo = true;

        window.axios
            .get(route('admin.orders.confirmation', order.id), { headers: { Accept: 'application/json' } })
            .then(({ data }) => {
                if (!activo) return;
                const formas = data.referencias.formas_envio ?? [];
                const preferida =
                    formas.find((f) => Number(f.id) === Number(data.preseleccion?.forma_envio_id)) ??
                    formas.find((f) => !f.es_retiro) ??
                    formas[0];

                setDatos(data);
                setVendedorId(String(data.vendedor_por_defecto));
                setEsGratis(Boolean(data.resumen.envio_gratis) && !preferida?.es_retiro);

                if (preferida) {
                    setFormaEnvioId(String(preferida.id));
                    setTransportistaId(String(data.preseleccion?.transportista_id ?? preferida.transportista_sugerido_id ?? ''));
                }
            })
            .catch((e) => {
                if (activo) setFalla(e?.response?.data?.message ?? 'No pudimos cargar los datos para confirmar. Probá de nuevo.');
            });

        return () => {
            activo = false;
        };
    }, [order.id]);

    const referencias = datos?.referencias;
    const formas = referencias?.formas_envio ?? [];
    const forma = formas.find((f) => String(f.id) === String(formaEnvioId));
    const esRetiro = Boolean(forma?.es_retiro);

    const cuentaPredeterminada = referencias?.cuentas?.[0]?.codigo ?? '';
    const medioPredeterminado = referencias?.medios_pago?.[0]?.codigo ?? '';

    const costoCobrado = esRetiro || esGratis ? 0 : aNumero(costoEnvio);
    const totalPedido = Number(datos?.resumen.total ?? 0);
    const totalVenta = totalPedido + costoCobrado;
    const pagado = pagos.reduce((suma, p) => suma + aNumero(p.monto), 0);
    const saldo = Math.round((totalVenta - pagado) * 100) / 100;
    const pagaDeMas = saldo < -0.004;
    // La retención solo tiene sentido si queda saldo: con el pago cubierto no hay nada que esperar.
    const quedaSaldo = saldo > 0.004;

    const vendedor = datos?.vendedores?.find((v) => String(v.id) === String(vendedorId));
    const sinMapeo = vendedor ? !vendedor.mapeado : false;

    const sinListas = referencias && referencias.origen === 'ninguno';
    const puedeEnviar =
        !!datos && !enviando && !sinListas && !!forma && !sinMapeo && !pagaDeMas && pagos.every((p) => p.cuenta && aNumero(p.monto) > 0);

    const agregarPago = () => {
        const restante = Math.max(0, saldo);
        setPagos([...pagos, { cuenta: cuentaPredeterminada, medio: medioPredeterminado, monto: restante > 0 ? String(restante) : '' }]);
    };
    const cambiarPago = (indice, campo, valor) =>
        setPagos(
            pagos.map((p, i) => {
                if (i !== indice) return p;
                const cambio = { ...p, [campo]: valor };

                // Una cuenta de efectivo se cobra en efectivo: se sugiere el medio (se puede cambiar).
                if (campo === 'cuenta') {
                    const cuenta = (referencias?.cuentas ?? []).find((c) => c.codigo === valor);
                    if (cuenta?.tipo === 'efectivo' && (referencias?.medios_pago ?? []).some((m) => m.codigo === 'efectivo')) {
                        cambio.medio = 'efectivo';
                    }
                }

                return cambio;
            })
        );
    const quitarPago = (indice) => setPagos(pagos.filter((_, i) => i !== indice));

    const confirmar = () => {
        if (!puedeEnviar) return;
        setEnviando(true);

        router.post(
            route('admin.orders.confirm', order.id),
            {
                vendedor_id: Number(vendedorId),
                pagos: pagos.map((p) => ({ cuenta: p.cuenta, medio: p.medio || null, monto: aNumero(p.monto) })),
                envio: {
                    forma_envio_id: Number(formaEnvioId),
                    costo_cobrado: costoCobrado,
                    es_gratis: esGratis && !esRetiro,
                    transportista_id: !esRetiro && transportistaId ? Number(transportistaId) : null,
                },
                solo_con_pago_completo: quedaSaldo && soloConPagoCompleto,
            },
            {
                preserveScroll: true,
                onSuccess: () => onClose(),
                onFinish: () => setEnviando(false),
            }
        );
    };

    // Errores por campo (`pagos.0.cuenta`, `envio.forma_envio_id`…) y el general (`confirmacion`, `pagos`).
    const erroresDelFormulario = Object.entries(errors ?? {}).filter(([clave]) => clave === 'confirmacion' || clave === 'pagos' || clave.startsWith('pagos.') || clave.startsWith('envio') || clave === 'vendedor_id');

    return (
        <div className="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true" aria-labelledby="confirmar-venta-titulo">
            <div className="flex min-h-screen items-end justify-center px-4 pb-20 pt-4 text-center sm:block sm:p-0">
                <div className="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" onClick={enviando ? undefined : onClose}></div>
                <span className="hidden sm:inline-block sm:h-screen sm:align-middle" aria-hidden="true">&#8203;</span>

                <div className="inline-block transform overflow-hidden rounded-lg bg-white text-left align-bottom shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-2xl sm:align-middle">
                    <div className="px-4 pb-4 pt-5 sm:p-6">
                        <h3 id="confirmar-venta-titulo" className="text-lg font-bold text-gray-900">Confirmar venta</h3>
                        <p className="mt-1 text-sm text-gray-500">
                            Pedido #{order.id} · {order.name} {order.lastname}. Se crea la venta en el Sistema Central; después el pedido es de solo lectura.
                        </p>

                        {falla && <p className="mt-4 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700">{falla}</p>}
                        {!datos && !falla && <p className="mt-6 text-sm text-gray-500">Cargando datos del Sistema Central…</p>}

                        {datos && (
                            <div className="mt-5 space-y-5">
                                {referencias.origen === 'respaldo' && (
                                    <p className="rounded-lg border border-amber-200 bg-amber-50 p-3 text-xs text-amber-900">
                                        {referencias.error} Se usa la lista de respaldo configurada: revisá la cuenta antes de confirmar.
                                    </p>
                                )}
                                {sinListas && (
                                    <p className="rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700">
                                        {referencias.error} Sin las cuentas y formas de envío no se puede confirmar. Probá de nuevo en unos minutos.
                                    </p>
                                )}

                                {/* Resumen */}
                                <section>
                                    <h4 className="mb-2 text-xs font-semibold uppercase tracking-wider text-gray-500">Resumen del pedido</h4>
                                    <ul className="divide-y divide-gray-100 rounded-lg border border-gray-200">
                                        {datos.resumen.items.map((item, i) => (
                                            <li key={i} className="px-3 py-2 text-sm">
                                                <div className="flex justify-between gap-3">
                                                    <span className="text-gray-900">{item.cantidad} × {item.titulo}</span>
                                                    <span className="whitespace-nowrap text-gray-700">{formatearPrecio(item.subtotal)}</span>
                                                </div>
                                                {item.nota && (
                                                    <p className="mt-1 rounded bg-yellow-50 px-2 py-1 text-xs font-semibold text-yellow-900">Nota: {item.nota}</p>
                                                )}
                                            </li>
                                        ))}
                                        {datos.resumen.descuento > 0 && (
                                            <li className="flex justify-between px-3 py-2 text-sm text-green-700">
                                                <span>Cupón {datos.resumen.cupon} (ya está en el total)</span>
                                                <span>− {formatearPrecio(datos.resumen.descuento)}</span>
                                            </li>
                                        )}
                                        <li className="flex justify-between bg-gray-50 px-3 py-2 text-sm font-semibold">
                                            <span>Total del pedido</span>
                                            <span>{formatearPrecio(totalPedido)}</span>
                                        </li>
                                    </ul>
                                </section>

                                {/* Envío */}
                                <section>
                                    <h4 className="mb-2 text-xs font-semibold uppercase tracking-wider text-gray-500">Envío cobrado</h4>
                                    <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                        <label className="text-sm text-gray-700">
                                            Forma de envío
                                            <select className={`${CAMPO} mt-1`} value={formaEnvioId} onChange={(e) => {
                                                const nueva = formas.find((f) => String(f.id) === e.target.value);
                                                setFormaEnvioId(e.target.value);
                                                setTransportistaId(String(nueva?.transportista_sugerido_id ?? ''));
                                                if (nueva?.es_retiro) setEsGratis(false);
                                            }}>
                                                {formas.map((f) => <option key={f.id} value={f.id}>{f.nombre}</option>)}
                                            </select>
                                        </label>
                                        {!esRetiro && (
                                            <label className="text-sm text-gray-700">
                                                Transportista
                                                <select className={`${CAMPO} mt-1`} value={transportistaId} onChange={(e) => setTransportistaId(e.target.value)}>
                                                    <option value="">El que sugiere el Central</option>
                                                    {(referencias.transportistas ?? []).map((t) => <option key={t.id} value={t.id}>{t.nombre}</option>)}
                                                </select>
                                            </label>
                                        )}
                                        <label className="text-sm text-gray-700">
                                            Costo de envío cobrado
                                            <input
                                                type="number" min="0" step="0.01" inputMode="decimal"
                                                className={`${CAMPO} mt-1`}
                                                value={esRetiro || esGratis ? 0 : costoEnvio}
                                                disabled={esRetiro || esGratis}
                                                onChange={(e) => setCostoEnvio(e.target.value)}
                                                placeholder="0"
                                            />
                                        </label>
                                        {!esRetiro && (
                                            <label className="flex items-end gap-2 pb-2 text-sm text-gray-700">
                                                <input type="checkbox" checked={esGratis} onChange={(e) => setEsGratis(e.target.checked)} className="rounded border-gray-300 text-storefront focus:ring-storefront" />
                                                Envío gratis{datos.resumen.envio_gratis ? ' (el pedido califica)' : ''}
                                            </label>
                                        )}
                                    </div>
                                </section>

                                {/* Pagos */}
                                <section>
                                    <div className="mb-2 flex items-center justify-between">
                                        <h4 className="text-xs font-semibold uppercase tracking-wider text-gray-500">Pagos recibidos</h4>
                                        <button type="button" onClick={agregarPago} disabled={pagos.length >= 20} className="text-sm font-semibold text-storefront hover:underline disabled:opacity-40">
                                            + Agregar pago
                                        </button>
                                    </div>
                                    {pagos.length === 0 && (
                                        <p className="rounded-lg border border-dashed border-gray-300 p-3 text-sm text-gray-500">
                                            Sin pagos: la venta queda con todo el saldo pendiente de cobro en el Central.
                                        </p>
                                    )}
                                    <div className="space-y-2">
                                        {pagos.map((p, i) => (
                                            <div key={i} className="grid grid-cols-1 gap-2 rounded-lg border border-gray-200 p-2 sm:grid-cols-[1fr_1fr_8rem_auto]">
                                                <select aria-label="Cuenta" className={CAMPO} value={p.cuenta} onChange={(e) => cambiarPago(i, 'cuenta', e.target.value)}>
                                                    {(referencias.cuentas ?? []).map((c) => <option key={c.codigo} value={c.codigo}>{c.nombre ?? c.codigo}</option>)}
                                                </select>
                                                <select aria-label="Medio de pago" className={CAMPO} value={p.medio} onChange={(e) => cambiarPago(i, 'medio', e.target.value)}>
                                                    <option value="">Sin medio</option>
                                                    {(referencias.medios_pago ?? []).map((m) => <option key={m.codigo} value={m.codigo}>{m.nombre ?? m.codigo}</option>)}
                                                </select>
                                                <input aria-label="Monto" type="number" min="0.01" step="0.01" inputMode="decimal" className={CAMPO} value={p.monto} onChange={(e) => cambiarPago(i, 'monto', e.target.value)} placeholder="Monto" />
                                                <button type="button" onClick={() => quitarPago(i)} className="px-2 text-sm font-semibold text-red-600 hover:underline">Quitar</button>
                                            </div>
                                        ))}
                                    </div>
                                </section>

                                {/* Saldo */}
                                <section className={`rounded-lg border p-3 text-sm ${pagaDeMas ? 'border-red-300 bg-red-50 text-red-800' : saldo > 0.004 ? 'border-amber-300 bg-amber-50 text-amber-900' : 'border-green-300 bg-green-50 text-green-800'}`}>
                                    <div className="flex justify-between"><span>Total de la venta (productos{costoCobrado > 0 ? ' + envío' : ''})</span><strong>{formatearPrecio(totalVenta)}</strong></div>
                                    <div className="flex justify-between"><span>Pagado</span><strong>{formatearPrecio(pagado)}</strong></div>
                                    <p className="mt-1 font-semibold">
                                        {pagaDeMas
                                            ? `Los pagos superan el total por ${formatearPrecio(-saldo)}.`
                                            : saldo > 0.004
                                                ? `Queda con saldo ${formatearPrecio(saldo)} en el central`
                                                : 'Venta pagada por completo'}
                                    </p>
                                    <label className={`mt-3 flex items-start gap-2 border-t border-current/20 pt-3 ${quedaSaldo ? '' : 'opacity-60'}`}>
                                        <input
                                            type="checkbox"
                                            checked={quedaSaldo && soloConPagoCompleto}
                                            disabled={!quedaSaldo}
                                            onChange={(e) => setSoloConPagoCompleto(e.target.checked)}
                                            className="mt-0.5 rounded border-gray-300 text-storefront focus:ring-storefront disabled:cursor-not-allowed"
                                        />
                                        <span>
                                            Despachar solo con pago completo
                                            <span className="block text-xs font-normal">
                                                {quedaSaldo
                                                    ? 'El Central te deja empaquetarla pero no despacharla hasta que se cobre el saldo; cuando se completa el pago, se libera sola.'
                                                    : 'Solo se puede pedir si queda saldo por cobrar.'}
                                            </span>
                                        </span>
                                    </label>
                                </section>

                                {/* Vendedor */}
                                <section>
                                    <h4 className="mb-2 text-xs font-semibold uppercase tracking-wider text-gray-500">Vendedor</h4>
                                    {datos.puede_elegir_vendedor ? (
                                        <select className={CAMPO} value={vendedorId} onChange={(e) => setVendedorId(e.target.value)}>
                                            {datos.vendedores.map((v) => (
                                                <option key={v.id} value={v.id}>{v.nombre}{v.id === datos.vendedor_por_defecto ? ' (vos)' : ''}{v.mapeado ? '' : ' — sin vendedor del Central'}</option>
                                            ))}
                                        </select>
                                    ) : (
                                        <p className="text-sm text-gray-900">{vendedor?.nombre} (vos)</p>
                                    )}
                                    {sinMapeo && <p className="mt-2 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700">{vendedor.mensaje}</p>}
                                    {vendedor?.mapeado && vendedor.habilitado_en_central === false && (
                                        <p className="mt-2 rounded-lg border border-amber-200 bg-amber-50 p-3 text-xs text-amber-900">
                                            Este vendedor todavía no figura entre los asignados del Sistema Central. Si no lo habilitaron, el Central va a pedir
                                            que administración lo asigne y la venta no se crea hasta entonces.
                                        </p>
                                    )}
                                </section>

                                {erroresDelFormulario.length > 0 && (
                                    <div className="space-y-1 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700" role="alert">
                                        {erroresDelFormulario.map(([clave, mensaje]) => <p key={clave}>{mensaje}</p>)}
                                    </div>
                                )}
                            </div>
                        )}
                    </div>

                    <div className="bg-gray-50 px-4 py-3 sm:flex sm:flex-row-reverse sm:px-6">
                        <button
                            type="button"
                            onClick={confirmar}
                            disabled={!puedeEnviar}
                            className="inline-flex w-full justify-center rounded-md border border-transparent bg-storefront px-4 py-2 text-base font-medium text-white shadow-sm hover:brightness-90 disabled:cursor-not-allowed disabled:opacity-50 sm:ml-3 sm:w-auto sm:text-sm"
                        >
                            {enviando ? 'Confirmando…' : 'Confirmar venta'}
                        </button>
                        <button
                            type="button"
                            onClick={onClose}
                            disabled={enviando}
                            className="mt-3 inline-flex w-full justify-center rounded-md border border-gray-300 bg-white px-4 py-2 text-base font-medium text-gray-700 shadow-sm hover:bg-gray-50 disabled:opacity-50 sm:mt-0 sm:w-auto sm:text-sm"
                        >
                            Volver
                        </button>
                    </div>
                </div>
            </div>
        </div>
    );
}
