import React, { useEffect, useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import toast from 'react-hot-toast';
import AdminLayout from '@/Layouts/AdminLayout';
import DeleteConfirmationModal from '@/Components/DeleteConfirmationModal';
import ConfirmarVentaModal from '@/Components/Admin/ConfirmarVentaModal';
import DatosClientePedido from '@/Components/Admin/DatosClientePedido';
import { estadoLabel, estadoBadgeClasses } from '@/utils/orders';
import { getProductImageUrl } from '@/utils/images';
import { IconPhoto, IconX, IconChevronDown } from '@/Components/Admin/Icons';

function Field({ label, value }) {
    return (
        <div>
            <dt className="text-xs font-medium text-gray-500">{label}</dt>
            <dd className="mt-0.5 text-sm font-medium text-graphite">{value || '—'}</dd>
        </div>
    );
}

// Color y add-ons elegidos de un ítem (snapshot en el order_item), para que quien
// despacha sepa qué preparar. Con `custom_color_text` la variante era "a elección
// del cliente"; si no, `variant_name` es el color fijo. No renderiza nada para un
// ítem sin opciones, así un pedido de productos simples se ve igual que antes.
function ItemOptions({ item }) {
    const hasColor = item.custom_color_text || item.variant_name;
    const addons = item.addons_selected || [];
    const comboSelections = item.combo_selections || [];

    // Combo: listar los productos incluidos con su color elegido.
    if (item.is_combo && comboSelections.length > 0) {
        return (
            <div className="mt-1.5 space-y-1 border-l-2 border-slate-200 pl-2.5 text-xs">
                {comboSelections.map((comp, i) => {
                    const color = comp.custom_color_text || comp.variant_name;
                    return (
                        <div key={i} className="flex items-center gap-1.5 text-slate-600">
                            <span className="font-medium text-slate-500">{comp.quantity}×</span>
                            <span>{comp.product_title}</span>
                            {color && (
                                <>
                                    <span className="text-slate-400">·</span>
                                    {comp.variant_color_hex && !comp.custom_color_text && (
                                        <span
                                            className="inline-block h-3 w-3 flex-shrink-0 rounded-full border border-slate-300"
                                            style={{ backgroundColor: comp.variant_color_hex }}
                                        />
                                    )}
                                    <span>{color}</span>
                                </>
                            )}
                        </div>
                    );
                })}
            </div>
        );
    }

    if (!hasColor && addons.length === 0) {
        return null;
    }

    return (
        <div className="mt-1.5 space-y-1 border-l-2 border-slate-200 pl-2.5 text-xs">
            {hasColor && (
                <div className="flex items-center gap-1.5 text-slate-600">
                    {item.custom_color_text ? (
                        <>
                            <span className="font-medium text-slate-500">Color solicitado:</span>
                            <span>{item.custom_color_text}</span>
                        </>
                    ) : (
                        <>
                            <span className="font-medium text-slate-500">Color:</span>
                            {item.variant_color_hex && (
                                <span
                                    className="inline-block h-3 w-3 flex-shrink-0 rounded-full border border-slate-300"
                                    style={{ backgroundColor: item.variant_color_hex }}
                                />
                            )}
                            <span>{item.variant_name}</span>
                        </>
                    )}
                </div>
            )}
            {addons.map((addon, i) => (
                <div key={i} className="text-slate-600">
                    <span className="font-medium text-slate-500">{addon.name}</span>
                    {addon.custom_text && (
                        <span className="italic text-slate-700"> “{addon.custom_text}”</span>
                    )}
                </div>
            ))}
        </div>
    );
}

// "20.00" → "20"; "20.50" → "20.5". Deja sólo los decimales significativos.
function formatPercentage(value) {
    return String(Number(value) || 0);
}

// Forma de pago con tarjeta de crédito elegida por el cliente en el checkout
// (snapshot en la orden). Bloque accionable: el monto EXACTO por el que el
// vendedor genera el link de pago en Mercado Pago a mano — la app no integra
// ninguna API de MP. No renderiza nada si el pedido fue en efectivo /
// transferencia (order.payment_plan == null), así un pedido común se ve igual.
function PaymentPlanCard({ plan }) {
    const cuotasLabel =
        plan.installments === 1
            ? 'Pago único con tarjeta de crédito'
            : `${plan.installments} cuotas sin interés mensual`;

    return (
        <div className="rounded-xl border border-amber-300 bg-amber-50">
            <div className="flex items-center gap-2 border-b border-amber-200 px-4 py-3.5 sm:px-5">
                <span className="text-base">💳</span>
                <h3 className="text-sm font-semibold text-amber-900">Forma de pago: Tarjeta de crédito</h3>
            </div>
            <div className="space-y-2 p-4 sm:p-5">
                <div className="flex items-center justify-between text-sm">
                    <span className="text-amber-800">{cuotasLabel}</span>
                    <span className="font-medium text-amber-900">
                        Recargo {formatPercentage(plan.surcharge_percentage)}%
                    </span>
                </div>
                <div className="flex items-center justify-between text-sm">
                    <span className="text-amber-800">Recargo por tarjeta</span>
                    <span className="font-medium text-amber-900">+{plan.formatted_surcharge_amount}</span>
                </div>
                <div className="flex items-center justify-between border-t border-amber-200 pt-2">
                    <span className="text-sm font-semibold text-amber-900">Total a cobrar</span>
                    <span className="text-lg font-bold text-amber-900">{plan.formatted_total_with_surcharge}</span>
                </div>
                {plan.installments > 1 && (
                    <p className="text-xs text-amber-700">
                        {plan.installments} cuotas de {plan.formatted_installment_amount} c/u
                    </p>
                )}
                <p className="mt-3 rounded-lg bg-amber-100 px-3 py-2 text-sm font-medium text-amber-900">
                    👉 Generá el link de pago en Mercado Pago por {plan.formatted_total_with_surcharge}
                </p>
                <p className="text-[11px] text-amber-700">
                    El total del pedido no cambia; este es el monto a cobrar si el cliente paga con tarjeta de crédito.
                </p>
            </div>
        </div>
    );
}

// Seguimiento del envío que carga el Sistema Central (transportista + número + enlace). El enlace solo llega si es http(s).
function Seguimiento({ seguimiento }) {
    if (!seguimiento) return null;

    return (
        <div className="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2.5 text-sm text-emerald-900">
            <p className="text-xs font-semibold uppercase tracking-wider text-emerald-700">Seguimiento del envío</p>
            <p className="mt-0.5">
                {seguimiento.transportista || 'Transportista sin informar'}
                {seguimiento.numero && (
                    <>
                        {' '}· N° <span className="font-semibold">{seguimiento.numero}</span>
                    </>
                )}
            </p>
            {seguimiento.url && (
                <a
                    href={seguimiento.url}
                    target="_blank"
                    rel="noopener noreferrer"
                    className="mt-1 inline-block font-semibold text-emerald-800 underline hover:text-emerald-950"
                >
                    Seguir el envío
                </a>
            )}
        </div>
    );
}

// La venta tal como la tiene el Sistema Central: número, pagado y saldo, retención del despacho y seguimiento. Solo lectura.
function VentaDelCentral({ order }) {
    const venta = order.venta;
    if (!venta) return null;

    const anulado = order.estado === 'anulado';
    const conSaldo = venta.saldo !== null && venta.saldo > 0.004;
    const fechas = [
        ['Confirmado', venta.confirmado_at],
        ['Empaquetado', venta.empaquetado_at],
        ['Despachado', venta.despachado_at],
        ['Entregado', venta.entregado_at],
        ['Anulado', venta.anulado_at],
    ].filter(([, fecha]) => fecha);

    return (
        <div className="rounded-xl border border-gray-200 bg-surface">
            <div className="flex items-center justify-between gap-2 border-b border-gray-200 px-4 py-3.5 sm:px-5">
                <h3 className="text-sm font-semibold text-graphite">Venta en el Sistema Central</h3>
                {venta.numero && <span className="text-sm font-semibold text-graphite">N° {venta.numero}</span>}
            </div>
            <div className="space-y-4 p-4 sm:p-5">
                {venta.retenido_por_pago && !anulado && (
                    <p className="rounded-lg border border-amber-300 bg-amber-50 px-3 py-2 text-sm font-semibold text-amber-900">
                        Retenido hasta completar el pago: el Central deja empaquetarlo pero no despacharlo hasta que se cobre el saldo.
                    </p>
                )}
                <dl className="grid grid-cols-2 gap-4 sm:grid-cols-4">
                    <Field label="Total de la venta" value={venta.formatted_total} />
                    <Field label="Pagado" value={venta.formatted_pagado} />
                    <div>
                        <dt className="text-xs font-medium text-gray-500">Saldo</dt>
                        <dd className={`mt-0.5 text-sm font-semibold ${conSaldo && !anulado ? 'text-amber-700' : 'text-graphite'}`}>
                            {venta.formatted_saldo || '—'}
                        </dd>
                    </div>
                    <Field label="Envío cobrado" value={venta.formatted_envio_cobrado} />
                </dl>
                <Seguimiento seguimiento={venta.seguimiento} />
                {fechas.length > 0 && (
                    <ul className="space-y-0.5 text-xs text-gray-500">
                        {fechas.map(([etiqueta, fecha]) => (
                            <li key={etiqueta}>
                                {etiqueta}: <span className="font-medium text-graphite/85">{fecha}</span>
                            </li>
                        ))}
                    </ul>
                )}
                <p className="text-xs text-gray-500">
                    El empaquetado, el despacho y las correcciones se hacen en el Sistema Central; esta pantalla muestra lo que informa.
                </p>
            </div>
        </div>
    );
}

export default function Show({ order, provinces }) {
    const [showMessage, setShowMessage] = useState(false);
    const [showConfirmModal, setShowConfirmModal] = useState(false);
    const [showCancelModal, setShowCancelModal] = useState(false);
    const [isUpdating, setIsUpdating] = useState(false);
    const [previewImage, setPreviewImage] = useState(null);

    useEffect(() => {
        if (!previewImage) return;
        const onKeyDown = (e) => e.key === 'Escape' && setPreviewImage(null);
        window.addEventListener('keydown', onKeyDown);
        return () => window.removeEventListener('keydown', onKeyDown);
    }, [previewImage]);

    const updateEstado = (estado) => {
        setIsUpdating(true);
        router.patch(
            route('admin.orders.update-status', order.id),
            { estado },
            {
                preserveScroll: true,
                onSuccess: () => {
                    toast.success('Estado de la orden actualizado');
                    setIsUpdating(false);
                    setShowCancelModal(false);
                },
                onError: (errors) => {
                    // Con el Sistema Central puede haber un motivo concreto (no responde, ya es una venta confirmada…).
                    toast.error(errors?.estado || 'No se pudo actualizar el estado de la orden');
                    setIsUpdating(false);
                    setShowCancelModal(false);
                },
            }
        );
    };

    const transiciones = order.transiciones_disponibles || [];

    return (
        <AdminLayout
            header={
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p className="text-xs font-semibold uppercase tracking-wider text-gray-500">
                            <Link href={route('admin.orders.index')} className="hover:text-graphite/75">
                                Órdenes
                            </Link>
                        </p>
                        <h1 className="mt-1 text-xl font-semibold text-graphite sm:text-2xl">Pedido #{order.id}</h1>
                    </div>
                    <Link
                        href={route('admin.orders.index')}
                        className="inline-flex items-center justify-center rounded-lg border border-gray-200 px-3.5 py-2 text-sm font-medium text-graphite/85 transition hover:bg-background"
                    >
                        Volver
                    </Link>
                </div>
            }
        >
            <Head title={`Pedido #${order.id} - Admin`} />

            <div className="mx-auto max-w-5xl space-y-4">
                {/* Estado y total */}
                <div className="flex flex-col gap-3 rounded-xl border border-gray-200 bg-surface p-4 sm:flex-row sm:items-center sm:justify-between sm:p-5">
                    <div className="flex items-center gap-3">
                        <span className="text-sm text-gray-500">Estado actual:</span>
                        <span
                            className={`inline-flex items-center rounded-full px-3 py-1 text-sm font-medium ${estadoBadgeClasses(order.estado)}`}
                        >
                            {estadoLabel(order.estado)}
                        </span>
                        {order.rukoz_ref && (
                            <span
                                className="inline-flex items-center rounded-full border border-indigo-300 bg-indigo-100 px-3 py-1 text-xs font-bold text-indigo-800"
                                title={`Referencia en el Sistema Central: ${order.rukoz_ref}`}
                            >
                                {order.esta_confirmado
                                    ? 'Venta confirmada en el Central'
                                    : order.rukoz_estado === 'cancelado'
                                        ? 'Reserva liberada en el Central'
                                        : order.rukoz_estado === 'cancelacion_pendiente'
                                            ? 'Liberando reserva en el Central…'
                                            : 'Stock reservado en el Central'}
                            </span>
                        )}
                    </div>
                    <div className="flex items-center justify-between gap-3 sm:justify-end">
                        <span className="text-xs text-gray-500">{order.created_at}</span>
                        <span className="text-xl font-bold text-graphite">{order.formatted_total}</span>
                    </div>
                </div>

                {(order.estado === 'cancelado' || order.estado === 'anulado') && order.motivo_cancelacion && (
                    <p className="rounded-xl border border-gray-200 bg-surface px-4 py-3 text-sm text-gray-500 sm:px-5">
                        Motivo de {order.estado === 'anulado' ? 'la anulación' : 'la cancelación'}: {order.motivo_cancelacion}
                    </p>
                )}
                {order.rukoz_estado === 'cancelacion_pendiente' && (
                    <p className="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                        El Sistema Central no respondió: la liberación de la reserva quedó pendiente y se reintenta sola cada pocos minutos.
                    </p>
                )}

                <div className="grid grid-cols-1 gap-4 lg:grid-cols-3">
                    {/* Acciones — primero en mobile, para cambiar el estado sin tener que scrollear */}
                    <div className="order-first lg:order-last lg:col-span-1">
                        <div className="sticky top-6 rounded-xl border border-gray-200 bg-surface">
                            <div className="border-b border-gray-200 px-4 py-3.5 sm:px-5">
                                <h3 className="text-sm font-semibold text-graphite">Cambiar estado</h3>
                            </div>
                            <div className="space-y-2 p-4 sm:p-5">
                                {order.puede_confirmar && (
                                    <button
                                        onClick={() => setShowConfirmModal(true)}
                                        disabled={isUpdating}
                                        className="w-full rounded-lg bg-storefront px-4 py-2.5 text-sm font-semibold text-white transition hover:brightness-90 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-storefront disabled:bg-gray-200 disabled:text-gray-500"
                                    >
                                        Confirmar venta
                                    </button>
                                )}
                                {transiciones.includes('despachado') && (
                                    <button
                                        onClick={() => updateEstado('despachado')}
                                        disabled={isUpdating}
                                        className="w-full rounded-lg bg-storefront px-4 py-2.5 text-sm font-semibold text-white transition hover:brightness-90 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-storefront disabled:bg-gray-200 disabled:text-gray-500"
                                    >
                                        Marcar como despachado
                                    </button>
                                )}
                                {transiciones.includes('pendiente') && (
                                    <button
                                        onClick={() => updateEstado('pendiente')}
                                        disabled={isUpdating}
                                        className="w-full rounded-lg border border-gray-200 px-4 py-2.5 text-sm font-medium text-graphite/85 transition hover:bg-background disabled:opacity-50"
                                    >
                                        Volver a pendiente
                                    </button>
                                )}
                                {transiciones.includes('cancelado') && (
                                    <button
                                        onClick={() => setShowCancelModal(true)}
                                        disabled={isUpdating}
                                        className="w-full rounded-lg border border-gray-200 bg-ice-50 px-4 py-2.5 text-sm font-medium text-navy-700 transition hover:bg-ice-100 disabled:opacity-50"
                                    >
                                        Cancelar pedido
                                    </button>
                                )}
                                {transiciones.length === 0 && !order.puede_confirmar && (
                                    <p className="text-center text-sm text-gray-500">
                                        {order.esta_confirmado || order.estado === 'anulado'
                                            ? 'Para anular, hacelo en el Sistema Central.'
                                            : 'Este pedido no admite más cambios de estado.'}
                                    </p>
                                )}
                            </div>
                        </div>
                    </div>

                    {/* Columna principal */}
                    <div className="space-y-4 lg:col-span-2">
                        {/* Datos del cliente (checkout mínimo: chips de lo que falta para despachar, edición en el lugar) */}
                        <DatosClientePedido order={order} provinces={provinces} soloLectura={order.datos_cliente_solo_lectura} />

                        {/* Productos */}
                        <div className="rounded-xl border border-gray-200 bg-surface">
                            <div className="border-b border-gray-200 px-4 py-3.5 sm:px-5">
                                <h3 className="text-sm font-semibold text-graphite">
                                    Productos ({order.items.length})
                                </h3>
                            </div>
                            <ul className="divide-y divide-gray-200">
                                {order.items.map((item) => {
                                    const imageUrl = getProductImageUrl(item.primary_image);
                                    return (
                                        <li key={item.id} className="flex items-start gap-3 px-4 py-3 sm:px-5">
                                            <button
                                                type="button"
                                                onClick={() =>
                                                    imageUrl &&
                                                    setPreviewImage({ url: imageUrl, alt: item.product_title })
                                                }
                                                disabled={!imageUrl}
                                                className={`flex h-14 w-14 flex-shrink-0 items-center justify-center overflow-hidden rounded-lg bg-ice-100 ${imageUrl ? 'transition hover:opacity-80' : ''}`}
                                                title={imageUrl ? 'Ver imagen' : undefined}
                                            >
                                                {imageUrl ? (
                                                    <img
                                                        src={imageUrl}
                                                        alt={item.product_title}
                                                        className="h-full w-full object-cover"
                                                    />
                                                ) : (
                                                    <IconPhoto className="h-5 w-5 text-gray-500/70" />
                                                )}
                                            </button>
                                            <div className="min-w-0 flex-1">
                                                <p className="truncate text-sm font-medium text-graphite">
                                                    {item.is_combo && (
                                                        <span className="mr-1 rounded bg-promo px-1 py-0.5 text-[10px] font-bold uppercase text-navy-900">Combo</span>
                                                    )}
                                                    {item.product_title}
                                                </p>
                                                <p className="text-xs text-gray-500">
                                                    {item.cantidad} ×{' '}
                                                    {item.precio_unitario.toLocaleString('es-AR', {
                                                        style: 'currency',
                                                        currency: 'ARS',
                                                        minimumFractionDigits: 0,
                                                    })}
                                                </p>
                                                <ItemOptions item={item} />
                                            </div>
                                            <p className="flex-shrink-0 text-sm font-semibold text-graphite">
                                                {item.subtotal.toLocaleString('es-AR', {
                                                    style: 'currency',
                                                    currency: 'ARS',
                                                    minimumFractionDigits: 0,
                                                })}
                                            </p>
                                        </li>
                                    );
                                })}
                            </ul>
                            <div className="space-y-1 border-t border-gray-200 px-4 py-3 sm:px-5">
                                {order.discount_code && (
                                    <>
                                        <div className="flex items-center justify-between text-sm">
                                            <span className="text-gray-500">Subtotal</span>
                                            <span className="font-medium text-graphite/85">{order.formatted_subtotal}</span>
                                        </div>
                                        <div className="flex items-center justify-between text-sm">
                                            <span className="text-gray-500">
                                                Descuento (código <span className="font-semibold">{order.discount_code}</span>)
                                            </span>
                                            <span className="font-medium text-navy-700">
                                                −{order.formatted_discount_amount}
                                            </span>
                                        </div>
                                    </>
                                )}
                                <div className="flex items-center justify-between pt-1">
                                    <span className="text-sm font-semibold text-graphite/85">Total</span>
                                    <span className="text-base font-bold text-graphite">{order.formatted_total}</span>
                                </div>
                            </div>
                        </div>

                        {/* Venta en el Sistema Central (solo lectura, 10.4) */}
                        <VentaDelCentral order={order} />

                        {/* Forma de pago: los pedidos anteriores a la forma de pago informativa pueden traer un plan de tarjeta elegido (snapshot);
                            los nuevos se coordinan por WhatsApp. */}
                        {order.payment_plan ? (
                            <PaymentPlanCard plan={order.payment_plan} />
                        ) : (
                            <div className="rounded-xl border border-gray-200 bg-surface px-4 py-3.5 sm:px-5">
                                <h3 className="text-sm font-semibold text-graphite">Forma de pago</h3>
                                <p className="mt-1 text-sm text-gray-500">A coordinar por WhatsApp.</p>
                            </div>
                        )}

                        {/* Mensaje de WhatsApp */}
                        <div className="rounded-xl border border-gray-200 bg-surface">
                            <button
                                type="button"
                                onClick={() => setShowMessage(!showMessage)}
                                className="flex w-full items-center justify-between px-4 py-3.5 text-left sm:px-5"
                            >
                                <h3 className="text-sm font-semibold text-graphite">Mensaje de WhatsApp</h3>
                                <IconChevronDown
                                    className={`h-4 w-4 text-gray-500 transition-transform ${showMessage ? 'rotate-180' : ''}`}
                                />
                            </button>
                            {showMessage && (
                                <div className="border-t border-gray-200 px-4 py-4 sm:px-5">
                                    {order.mensaje_whatsapp ? (
                                        <pre className="whitespace-pre-wrap font-sans text-sm text-graphite/85">
                                            {order.mensaje_whatsapp}
                                        </pre>
                                    ) : (
                                        <p className="text-sm text-gray-500">
                                            Este pedido no tiene un mensaje de WhatsApp registrado.
                                        </p>
                                    )}
                                </div>
                            )}
                        </div>
                    </div>
                </div>
            </div>

            {/* Lightbox de imagen */}
            {previewImage && (
                <div
                    className="fixed inset-0 z-50 flex items-center justify-center bg-navy-900/80 p-4"
                    onClick={() => setPreviewImage(null)}
                >
                    <button
                        type="button"
                        onClick={() => setPreviewImage(null)}
                        className="absolute right-4 top-4 flex h-9 w-9 items-center justify-center rounded-full bg-surface/10 text-white transition hover:bg-surface/20"
                    >
                        <IconX className="h-5 w-5" />
                    </button>
                    <img
                        src={previewImage.url}
                        alt={previewImage.alt}
                        onClick={(e) => e.stopPropagation()}
                        className="max-h-[85vh] max-w-full rounded-lg object-contain shadow-2xl"
                    />
                </div>
            )}

            {showConfirmModal && <ConfirmarVentaModal order={order} onClose={() => setShowConfirmModal(false)} />}

            <DeleteConfirmationModal
                show={showCancelModal}
                onClose={() => !isUpdating && setShowCancelModal(false)}
                onConfirm={() => updateEstado('cancelado')}
                title="¿Cancelar pedido?"
                message={`Estás a punto de cancelar el pedido de ${order.nombre_completo}.`}
                warningMessage={order.rukoz_ref
                    ? 'Se va a liberar el stock que este pedido tiene reservado en el Sistema Central. Esta acción no se puede deshacer.'
                    : 'Esta acción no se puede deshacer.'}
                confirmText="Cancelar pedido"
                processingText="Cancelando..."
                processing={isUpdating}
            />
        </AdminLayout>
    );
}
