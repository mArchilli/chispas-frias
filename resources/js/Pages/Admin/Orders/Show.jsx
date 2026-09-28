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
            <dt className="text-xs font-medium text-gray-500">{label}</dt>
            <dd className="mt-0.5 text-sm font-medium text-graphite">{value || '—'}</dd>
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
                    </div>
                    <div className="flex items-center justify-between gap-3 sm:justify-end">
                        <span className="text-xs text-gray-500">{order.created_at}</span>
                        <span className="text-xl font-bold text-graphite">{order.formatted_total}</span>
                    </div>
                </div>

                <div className="grid grid-cols-1 gap-4 lg:grid-cols-3">
                    {/* Acciones — primero en mobile, para cambiar el estado sin tener que scrollear */}
                    <div className="order-first lg:order-last lg:col-span-1">
                        <div className="sticky top-6 rounded-xl border border-gray-200 bg-surface">
                            <div className="border-b border-gray-200 px-4 py-3.5 sm:px-5">
                                <h3 className="text-sm font-semibold text-graphite">Cambiar estado</h3>
                            </div>
                            <div className="space-y-2 p-4 sm:p-5">
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
                                {transiciones.length === 0 && (
                                    <p className="text-center text-sm text-gray-500">
                                        Este pedido no admite más cambios de estado.
                                    </p>
                                )}
                            </div>
                        </div>
                    </div>

                    {/* Columna principal */}
                    <div className="space-y-4 lg:col-span-2">
                        {/* Datos del cliente */}
                        <div className="rounded-xl border border-gray-200 bg-surface">
                            <div className="border-b border-gray-200 px-4 py-3.5 sm:px-5">
                                <h3 className="text-sm font-semibold text-graphite">Datos del cliente</h3>
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
                                        <dt className="text-xs font-medium text-gray-500">Observaciones</dt>
                                        <dd className="mt-0.5 whitespace-pre-wrap text-sm font-medium text-graphite">
                                            {order.observations}
                                        </dd>
                                    </div>
                                )}
                            </dl>
                        </div>

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
                                        <li key={item.id} className="flex items-center gap-3 px-4 py-3 sm:px-5">
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
