import { useState } from 'react';
import { useForm } from '@inertiajs/react';
import {
    DATOS_DE_ENVIO,
    abrirWhatsApp,
    buildPedidoDatosFaltantesMessage,
    datosDeEnvioFaltantes,
    normalizarTelefonoWhatsApp,
} from '@/utils/whatsapp';

const CAMPO = 'block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-storefront focus:ring focus:ring-storefront focus:ring-opacity-50';

function Dato({ label, value }) {
    return (
        <div>
            <dt className="text-xs font-medium text-gray-500">{label}</dt>
            <dd className="mt-0.5 text-sm font-medium text-graphite">{value || <span className="font-normal italic text-gray-400">Sin dato</span>}</dd>
        </div>
    );
}

function Campo({ label, error, children }) {
    return (
        <label className="block text-xs font-medium text-gray-500">
            {label}
            <div className="mt-1">{children}</div>
            {error && <p className="mt-1 text-xs font-medium text-red-600">{error}</p>}
        </label>
    );
}

const IconoWhatsApp = () => (
    <svg className="h-4 w-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
        <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z" />
    </svg>
);

/**
 * Tarjeta «Datos del cliente» de la ficha del pedido (checkout mínimo). El checkout ya no pide DNI, localidad ni código postal: acá se
 * ven con un chip ✔/⚠ (un envío con transportista no se confirma sin DNI, localidad, CP y teléfono; un retiro en oficina no pide nada
 * de eso), se completan en el lugar y se le pueden pedir al cliente por WhatsApp. Guardar hace el PUT al Central con las líneas
 * actuales (no toca el pedido).
 */
export default function DatosClientePedido({ order, provinces, soloLectura }) {
    const [editando, setEditando] = useState(false);
    const esRetiro = order.tipo_entrega === 'retiro';
    const telefonoWhatsApp = normalizarTelefonoWhatsApp(order.phone);

    // Qué datos cuentan: un retiro solo necesita poder avisar al cliente (teléfono); un envío, los cuatro.
    const requeridos = esRetiro ? DATOS_DE_ENVIO.filter(({ campo }) => campo === 'phone') : DATOS_DE_ENVIO;
    const faltantes = datosDeEnvioFaltantes(order).filter(({ campo }) => requeridos.some((r) => r.campo === campo));

    const form = useForm({
        name: order.nombre_completo ?? '',
        phone: order.phone ?? '',
        dni: order.dni ?? '',
        email: order.email ?? '',
        province: order.province ?? '',
        city: order.city ?? '',
        postal_code: order.postal_code ?? '',
    });

    const guardar = (e) => {
        e.preventDefault();
        form.patch(route('admin.orders.update-cliente', order.id), {
            preserveScroll: true,
            onSuccess: () => {
                form.setDefaults();
                setEditando(false);
            },
        });
    };

    const set = (campo) => (e) => form.setData(campo, e.target.value);

    return (
        <div className="rounded-xl border border-gray-200 bg-surface">
            <div className="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 px-4 py-3.5 sm:px-5">
                <div className="flex flex-wrap items-center gap-2">
                    <h3 className="text-sm font-semibold text-graphite">Datos del cliente</h3>
                    <span
                        className={`inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-bold ${
                            esRetiro ? 'border-sky-300 bg-sky-100 text-sky-800' : 'border-gray-300 bg-gray-100 text-gray-700'
                        }`}
                    >
                        {order.tipo_entrega_label}
                    </span>
                </div>
                <div className="flex flex-wrap items-center gap-2">
                    {!soloLectura && !editando && (
                        <button
                            type="button"
                            onClick={() => setEditando(true)}
                            className="inline-flex min-h-9 items-center rounded-lg border border-gray-200 px-3 py-1.5 text-sm font-medium text-graphite/85 transition hover:bg-background"
                        >
                            Editar datos
                        </button>
                    )}
                    {faltantes.length > 0 && telefonoWhatsApp && (
                        <button
                            type="button"
                            onClick={() => abrirWhatsApp(telefonoWhatsApp, buildPedidoDatosFaltantesMessage(order, faltantes))}
                            className="inline-flex min-h-9 items-center gap-2 rounded-lg border border-[#25D366] bg-white px-3 py-1.5 text-sm font-semibold text-[#128c4a] transition hover:bg-green-50"
                            title="Abre WhatsApp con un mensaje que le pide al cliente lo que falta"
                        >
                            <IconoWhatsApp /> Pedir datos por WhatsApp
                        </button>
                    )}
                </div>
            </div>

            <div className="p-4 sm:p-5">
                {/* Chips: lo que el empaquetador necesita para despachar */}
                <ul className="mb-4 flex flex-wrap gap-2" aria-label="Datos necesarios para despachar">
                    {requeridos.map(({ campo, nombre }) => {
                        const completo = String(order[campo] ?? '').trim() !== '';
                        return (
                            <li
                                key={campo}
                                className={`inline-flex items-center gap-1.5 rounded-full border px-3 py-1 text-xs font-bold ${
                                    completo ? 'border-green-300 bg-green-50 text-green-800' : 'border-amber-300 bg-amber-50 text-amber-900'
                                }`}
                            >
                                <span aria-hidden="true">{completo ? '✔' : '⚠'}</span>
                                {nombre}
                                <span className="sr-only">{completo ? ' completo' : ' falta'}</span>
                            </li>
                        );
                    })}
                    {esRetiro && <li className="self-center text-xs text-gray-500">Retira en la oficina: no hace falta más.</li>}
                </ul>

                {soloLectura && <p className="mb-4 rounded-lg border border-gray-200 bg-background px-3 py-2 text-xs text-gray-500">{soloLectura}</p>}

                {editando ? (
                    <form onSubmit={guardar} className="space-y-4">
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <Campo label="Nombre y apellido" error={form.errors.name}>
                                <input className={CAMPO} value={form.data.name} onChange={set('name')} required autoComplete="off" />
                            </Campo>
                            <Campo label="Teléfono" error={form.errors.phone}>
                                <input className={CAMPO} type="tel" inputMode="tel" autoComplete="off" value={form.data.phone} onChange={set('phone')} />
                            </Campo>
                            <Campo label="DNI" error={form.errors.dni}>
                                <input className={CAMPO} inputMode="numeric" autoComplete="off" value={form.data.dni} onChange={set('dni')} />
                            </Campo>
                            <Campo label="Email" error={form.errors.email}>
                                <input className={CAMPO} type="email" inputMode="email" autoComplete="off" value={form.data.email} onChange={set('email')} />
                            </Campo>
                            {!esRetiro && (
                                <>
                                    <Campo label="Provincia" error={form.errors.province}>
                                        <select className={CAMPO} value={form.data.province} onChange={set('province')}>
                                            <option value="">Sin provincia</option>
                                            {Object.entries(provinces ?? {}).map(([slug, provincia]) => (
                                                <option key={slug} value={slug}>{provincia.name}</option>
                                            ))}
                                        </select>
                                    </Campo>
                                    <Campo label="Localidad" error={form.errors.city}>
                                        <input className={CAMPO} autoComplete="off" value={form.data.city} onChange={set('city')} />
                                    </Campo>
                                    <Campo label="Código postal" error={form.errors.postal_code}>
                                        <input className={CAMPO} inputMode="numeric" autoComplete="off" value={form.data.postal_code} onChange={set('postal_code')} />
                                    </Campo>
                                </>
                            )}
                        </div>

                        {order.rukoz_ref && (
                            <p className="text-xs text-gray-500">Al guardar se actualiza el pedido en el Sistema Central (las líneas del pedido no cambian).</p>
                        )}
                        {form.errors.estado && <p className="rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700">{form.errors.estado}</p>}

                        <div className="flex flex-wrap gap-2">
                            <button
                                type="submit"
                                disabled={form.processing}
                                className="inline-flex min-h-10 items-center rounded-lg bg-storefront px-4 py-2 text-sm font-semibold text-white transition hover:brightness-90 disabled:opacity-50"
                            >
                                {form.processing ? 'Guardando…' : 'Guardar datos'}
                            </button>
                            <button
                                type="button"
                                disabled={form.processing}
                                onClick={() => {
                                    form.reset();
                                    form.clearErrors();
                                    setEditando(false);
                                }}
                                className="inline-flex min-h-10 items-center rounded-lg border border-gray-200 px-4 py-2 text-sm font-medium text-graphite/85 transition hover:bg-background disabled:opacity-50"
                            >
                                Cancelar
                            </button>
                        </div>
                    </form>
                ) : (
                    <dl className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <Dato label="Nombre y apellido" value={order.nombre_completo} />
                        <Dato label="Teléfono" value={order.phone} />
                        <Dato label="DNI" value={order.dni} />
                        <Dato label="Email" value={order.email} />
                        {!esRetiro && <Dato label="Provincia" value={order.province_label} />}
                        {!esRetiro && <Dato label="Localidad" value={order.city} />}
                        {!esRetiro && <Dato label="Código postal" value={order.postal_code} />}
                        {order.address && (
                            <div className="sm:col-span-2">
                                <Dato
                                    label="Dirección (pedido antiguo con envío a domicilio)"
                                    value={`${order.address} ${order.number}${order.between_streets ? ` (entre ${order.between_streets})` : ''}`}
                                />
                            </div>
                        )}
                        {order.observations && (
                            <div className="sm:col-span-2">
                                <dt className="text-xs font-medium text-gray-500">Observaciones</dt>
                                <dd className="mt-0.5 whitespace-pre-wrap text-sm font-medium text-graphite">{order.observations}</dd>
                            </div>
                        )}
                    </dl>
                )}
            </div>
        </div>
    );
}
