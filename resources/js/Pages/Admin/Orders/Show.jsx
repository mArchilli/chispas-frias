import React, { useEffect, useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import toast from 'react-hot-toast';
import AdminLayout from '@/Layouts/AdminLayout';
import DeleteConfirmationModal from '@/Components/DeleteConfirmationModal';
import { estadoLabel, estadoBadgeClasses } from '@/utils/orders';
import { getProductImageUrl } from '@/utils/images';
import { IconPhoto, IconX, IconChevronDown } from '@/Components/Admin/Icons';

function Field({ label, value }) {
    return (
        <div>
            <dt className="text-xs font-medium text-slate-500">{label}</dt>
            <dd className="mt-0.5 text-sm font-medium text-slate-900">{value || '—'}</dd>
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

export default function Show({ order }) {
    const [showMessage, setShowMessage] = useState(false);
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
                onError: () => {
                    toast.error('No se pudo actualizar el estado de la orden');
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
                        <p className="text-xs font-semibold uppercase tracking-wider text-slate-400">
                            <Link href={route('admin.orders.index')} className="hover:text-slate-600">
                                Órdenes
                            </Link>
                        </p>
                        <h1 className="mt-1 text-xl font-semibold text-slate-900 sm:text-2xl">Pedido #{order.id}</h1>
                    </div>
                    <Link
                        href={route('admin.orders.index')}
                        className="inline-flex items-center justify-center rounded-lg border border-slate-300 px-3.5 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
                    >
                        Volver
                    </Link>
                </div>
            }
        >
            <Head title={`Pedido #${order.id} - Admin`} />

            <div className="mx-auto max-w-5xl space-y-4">
                {/* Estado y total */}
                <div className="flex flex-col gap-3 rounded-xl border border-slate-200 bg-white p-4 sm:flex-row sm:items-center sm:justify-between sm:p-5">
                    <div className="flex items-center gap-3">
                        <span className="text-sm text-slate-500">Estado actual:</span>
                        <span
                            className={`inline-flex items-center rounded-full px-3 py-1 text-sm font-medium ${estadoBadgeClasses(order.estado)}`}
                        >
                            {estadoLabel(order.estado)}
                        </span>
                    </div>
                    <div className="flex items-center justify-between gap-3 sm:justify-end">
                        <span className="text-xs text-slate-400">{order.created_at}</span>
                        <span className="text-xl font-bold text-slate-900">{order.formatted_total}</span>
                    </div>
                </div>

                <div className="grid grid-cols-1 gap-4 lg:grid-cols-3">
                    {/* Acciones — primero en mobile, para cambiar el estado sin tener que scrollear */}
                    <div className="order-first lg:order-last lg:col-span-1">
                        <div className="sticky top-6 rounded-xl border border-slate-200 bg-white">
                            <div className="border-b border-slate-100 px-4 py-3.5 sm:px-5">
                                <h3 className="text-sm font-semibold text-slate-900">Cambiar estado</h3>
                            </div>
                            <div className="space-y-2 p-4 sm:p-5">
                                {transiciones.includes('despachado') && (
                                    <button
                                        onClick={() => updateEstado('despachado')}
                                        disabled={isUpdating}
                                        className="w-full rounded-lg bg-gold px-4 py-2.5 text-sm font-semibold text-navy transition hover:brightness-95 disabled:opacity-50"
                                    >
                                        Marcar como despachado
                                    </button>
                                )}
                                {transiciones.includes('pendiente') && (
                                    <button
                                        onClick={() => updateEstado('pendiente')}
                                        disabled={isUpdating}
                                        className="w-full rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-medium text-slate-700 transition hover:bg-slate-50 disabled:opacity-50"
                                    >
                                        Volver a pendiente
                                    </button>
                                )}
                                {transiciones.includes('cancelado') && (
                                    <button
                                        onClick={() => setShowCancelModal(true)}
                                        disabled={isUpdating}
                                        className="w-full rounded-lg border border-rose-200 bg-rose-50 px-4 py-2.5 text-sm font-medium text-rose-700 transition hover:bg-rose-100 disabled:opacity-50"
                                    >
                                        Cancelar pedido
                                    </button>
                                )}
                                {transiciones.length === 0 && (
                                    <p className="text-center text-sm text-slate-500">
                                        Este pedido no admite más cambios de estado.
                                    </p>
                                )}
                            </div>
                        </div>
                    </div>

                    {/* Columna principal */}
                    <div className="space-y-4 lg:col-span-2">
                        {/* Datos del cliente */}
                        <div className="rounded-xl border border-slate-200 bg-white">
                            <div className="border-b border-slate-100 px-4 py-3.5 sm:px-5">
                                <h3 className="text-sm font-semibold text-slate-900">Datos del cliente</h3>
                            </div>
                            <dl className="grid grid-cols-1 gap-4 p-4 sm:grid-cols-2 sm:p-5">
                                <Field label="Nombre completo" value={`${order.name} ${order.lastname}`} />
                                <Field label="DNI" value={order.dni} />
                                <Field label="Teléfono" value={order.phone} />
                                <Field label="Email" value={order.email} />
                                <Field label="Provincia" value={order.province_label} />
                                <Field label="Ciudad" value={order.city || 'No especificada'} />
                                <Field label="Código postal" value={order.postal_code} />
                                {order.address && (
                                    <div className="sm:col-span-2">
                                        <Field
                                            label="Dirección (pedido antiguo con envío a domicilio)"
                                            value={`${order.address} ${order.number}${order.between_streets ? ` (entre ${order.between_streets})` : ''}`}
                                        />
                                    </div>
                                )}
                                {order.observations && (
                                    <div className="sm:col-span-2">
                                        <dt className="text-xs font-medium text-slate-500">Observaciones</dt>
                                        <dd className="mt-0.5 whitespace-pre-wrap text-sm font-medium text-slate-900">
                                            {order.observations}
                                        </dd>
                                    </div>
                                )}
                            </dl>
                        </div>

                        {/* Productos */}
                        <div className="rounded-xl border border-slate-200 bg-white">
                            <div className="border-b border-slate-100 px-4 py-3.5 sm:px-5">
                                <h3 className="text-sm font-semibold text-slate-900">
                                    Productos ({order.items.length})
                                </h3>
                            </div>
                            <ul className="divide-y divide-slate-100">
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
                                                className={`flex h-14 w-14 flex-shrink-0 items-center justify-center overflow-hidden rounded-lg bg-slate-100 ${imageUrl ? 'transition hover:opacity-80' : ''}`}
                                                title={imageUrl ? 'Ver imagen' : undefined}
                                            >
                                                {imageUrl ? (
                                                    <img
                                                        src={imageUrl}
                                                        alt={item.product_title}
                                                        className="h-full w-full object-cover"
                                                    />
                                                ) : (
                                                    <IconPhoto className="h-5 w-5 text-slate-300" />
                                                )}
                                            </button>
                                            <div className="min-w-0 flex-1">
                                                <p className="truncate text-sm font-medium text-slate-900">
                                                    {item.product_title}
                                                </p>
                                                <p className="text-xs text-slate-500">
                                                    {item.cantidad} ×{' '}
                                                    {item.precio_unitario.toLocaleString('es-AR', {
                                                        style: 'currency',
                                                        currency: 'ARS',
                                                        minimumFractionDigits: 0,
                                                    })}
                                                </p>
                                                <ItemOptions item={item} />
                                            </div>
                                            <p className="flex-shrink-0 text-sm font-semibold text-slate-900">
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
                            <div className="space-y-1 border-t border-slate-100 px-4 py-3 sm:px-5">
                                {order.discount_code && (
                                    <>
                                        <div className="flex items-center justify-between text-sm">
                                            <span className="text-slate-500">Subtotal</span>
                                            <span className="font-medium text-slate-700">{order.formatted_subtotal}</span>
                                        </div>
                                        <div className="flex items-center justify-between text-sm">
                                            <span className="text-slate-500">
                                                Descuento (código <span className="font-semibold">{order.discount_code}</span>)
                                            </span>
                                            <span className="font-medium text-green-600">
                                                −{order.formatted_discount_amount}
                                            </span>
                                        </div>
                                    </>
                                )}
                                <div className="flex items-center justify-between pt-1">
                                    <span className="text-sm font-semibold text-slate-700">Total</span>
                                    <span className="text-base font-bold text-slate-900">{order.formatted_total}</span>
                                </div>
                            </div>
                        </div>

                        {/* Forma de pago con tarjeta (si el cliente eligió una) */}
                        {order.payment_plan && <PaymentPlanCard plan={order.payment_plan} />}

                        {/* Mensaje de WhatsApp */}
                        <div className="rounded-xl border border-slate-200 bg-white">
                            <button
                                type="button"
                                onClick={() => setShowMessage(!showMessage)}
                                className="flex w-full items-center justify-between px-4 py-3.5 text-left sm:px-5"
                            >
                                <h3 className="text-sm font-semibold text-slate-900">Mensaje de WhatsApp</h3>
                                <IconChevronDown
                                    className={`h-4 w-4 text-slate-400 transition-transform ${showMessage ? 'rotate-180' : ''}`}
                                />
                            </button>
                            {showMessage && (
                                <div className="border-t border-slate-100 px-4 py-4 sm:px-5">
                                    {order.mensaje_whatsapp ? (
                                        <pre className="whitespace-pre-wrap font-sans text-sm text-slate-700">
                                            {order.mensaje_whatsapp}
                                        </pre>
                                    ) : (
                                        <p className="text-sm text-slate-500">
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
                    className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/80 p-4"
                    onClick={() => setPreviewImage(null)}
                >
                    <button
                        type="button"
                        onClick={() => setPreviewImage(null)}
                        className="absolute right-4 top-4 flex h-9 w-9 items-center justify-center rounded-full bg-white/10 text-white transition hover:bg-white/20"
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

            <DeleteConfirmationModal
                show={showCancelModal}
                onClose={() => !isUpdating && setShowCancelModal(false)}
                onConfirm={() => updateEstado('cancelado')}
                title="¿Cancelar pedido?"
                message={`Estás a punto de cancelar el pedido de ${order.name} ${order.lastname}.`}
                warningMessage="Esta acción no se puede deshacer."
                confirmText="Cancelar pedido"
                processingText="Cancelando..."
                processing={isUpdating}
            />
        </AdminLayout>
    );
}
