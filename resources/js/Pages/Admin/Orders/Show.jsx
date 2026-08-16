import React, { useState } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import toast from 'react-hot-toast';
import AdminLayout from '@/Layouts/AdminLayout';
import SecondaryButton from '@/Components/SecondaryButton';
import DeleteConfirmationModal from '@/Components/DeleteConfirmationModal';
import { estadoLabel, estadoBadgeClasses } from '@/utils/orders';

export default function Show({ order }) {
    const { flash } = usePage().props;
    const [showFlash, setShowFlash] = useState(!!(flash?.success || flash?.error));
    const [showMessage, setShowMessage] = useState(false);
    const [showCancelModal, setShowCancelModal] = useState(false);
    const [isUpdating, setIsUpdating] = useState(false);

    React.useEffect(() => {
        if (flash?.success || flash?.error) {
            setShowFlash(true);
            const timer = setTimeout(() => setShowFlash(false), 5000);
            return () => clearTimeout(timer);
        }
    }, [flash]);

    const updateEstado = (estado) => {
        setIsUpdating(true);
        router.patch(route('admin.orders.update-status', order.id), { estado }, {
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
            }
        });
    };

    const transiciones = order.transiciones_disponibles || [];

    return (
        <AdminLayout
            header={
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold text-gray-900">Orden #{order.id}</h1>
                        <p className="text-sm text-gray-600">Realizada el {order.created_at}</p>
                    </div>
                    <Link href={route('admin.orders.index')}>
                        <SecondaryButton>Volver al Listado</SecondaryButton>
                    </Link>
                </div>
            }
        >
            <Head title={`Orden #${order.id} - Admin`} />

            {showFlash && (flash?.success || flash?.error) && (
                <div className={`mb-6 border px-4 py-3 rounded relative ${
                    flash?.success
                        ? 'bg-green-100 border-green-400 text-green-700'
                        : 'bg-red-100 border-red-400 text-red-700'
                }`}>
                    <span className="block sm:inline">{flash?.success || flash?.error}</span>
                </div>
            )}

            <div className="max-w-5xl mx-auto space-y-6">
                {/* Estado y Total */}
                <div className="bg-white rounded-xl shadow-sm border border-gray-100 p-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div className="flex items-center gap-3">
                        <span className="text-sm text-gray-600">Estado actual:</span>
                        <span className={`inline-flex items-center px-3 py-1 rounded-full text-sm font-medium ${estadoBadgeClasses(order.estado)}`}>
                            {estadoLabel(order.estado)}
                        </span>
                    </div>
                    <div className="text-2xl font-bold text-gray-900">{order.formatted_total}</div>
                </div>

                <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    {/* Columna Principal */}
                    <div className="lg:col-span-2 space-y-6">
                        {/* Datos de Contacto */}
                        <div className="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                            <div className="bg-gradient-to-r from-blue-50 to-indigo-50 px-6 py-4 border-b border-gray-100">
                                <h3 className="text-lg font-medium text-gray-900">Datos del Cliente</h3>
                            </div>
                            <div className="p-6 grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                                <div>
                                    <dt className="text-gray-500">Nombre completo</dt>
                                    <dd className="font-medium text-gray-900">{order.name} {order.lastname}</dd>
                                </div>
                                <div>
                                    <dt className="text-gray-500">DNI</dt>
                                    <dd className="font-medium text-gray-900">{order.dni}</dd>
                                </div>
                                <div>
                                    <dt className="text-gray-500">Teléfono</dt>
                                    <dd className="font-medium text-gray-900">{order.phone}</dd>
                                </div>
                                <div>
                                    <dt className="text-gray-500">Email</dt>
                                    <dd className="font-medium text-gray-900">{order.email}</dd>
                                </div>
                                <div className="sm:col-span-2">
                                    <dt className="text-gray-500">Dirección</dt>
                                    <dd className="font-medium text-gray-900">
                                        {order.address} {order.number}
                                        {order.between_streets && ` (entre ${order.between_streets})`}
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-gray-500">Provincia</dt>
                                    <dd className="font-medium text-gray-900">{order.province}</dd>
                                </div>
                                <div>
                                    <dt className="text-gray-500">Ciudad</dt>
                                    <dd className="font-medium text-gray-900">{order.city || 'No especificada'}</dd>
                                </div>
                                <div>
                                    <dt className="text-gray-500">Código Postal</dt>
                                    <dd className="font-medium text-gray-900">{order.postal_code}</dd>
                                </div>
                                {order.observations && (
                                    <div className="sm:col-span-2">
                                        <dt className="text-gray-500">Observaciones</dt>
                                        <dd className="font-medium text-gray-900 whitespace-pre-wrap">{order.observations}</dd>
                                    </div>
                                )}
                            </div>
                        </div>

                        {/* Items */}
                        <div className="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                            <div className="bg-gradient-to-r from-green-50 to-emerald-50 px-6 py-4 border-b border-gray-100">
                                <h3 className="text-lg font-medium text-gray-900">Productos ({order.items.length})</h3>
                            </div>
                            <div className="overflow-x-auto">
                                <table className="min-w-full divide-y divide-gray-200">
                                    <thead className="bg-gray-50">
                                        <tr>
                                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Producto</th>
                                            <th className="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Cantidad</th>
                                            <th className="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Precio Unitario</th>
                                            <th className="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Subtotal</th>
                                        </tr>
                                    </thead>
                                    <tbody className="bg-white divide-y divide-gray-200">
                                        {order.items.map((item) => (
                                            <tr key={item.id}>
                                                <td className="px-6 py-4 text-sm font-medium text-gray-900">{item.product_title}</td>
                                                <td className="px-6 py-4 text-right text-sm text-gray-700">{item.cantidad}</td>
                                                <td className="px-6 py-4 text-right text-sm text-gray-700">
                                                    ${item.precio_unitario.toLocaleString('es-AR', { minimumFractionDigits: 2 })}
                                                </td>
                                                <td className="px-6 py-4 text-right text-sm font-medium text-gray-900">
                                                    ${item.subtotal.toLocaleString('es-AR', { minimumFractionDigits: 2 })}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                    <tfoot className="bg-gray-50">
                                        <tr>
                                            <td colSpan={3} className="px-6 py-3 text-right text-sm font-semibold text-gray-700">Total</td>
                                            <td className="px-6 py-3 text-right text-sm font-bold text-gray-900">{order.formatted_total}</td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>

                        {/* Mensaje de WhatsApp */}
                        <div className="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                            <button
                                type="button"
                                onClick={() => setShowMessage(!showMessage)}
                                className="w-full flex items-center justify-between bg-gradient-to-r from-gray-50 to-slate-50 px-6 py-4 border-b border-gray-100 text-left"
                            >
                                <h3 className="text-lg font-medium text-gray-900">Mensaje de WhatsApp</h3>
                                <svg
                                    className={`w-5 h-5 text-gray-500 transition-transform ${showMessage ? 'rotate-180' : ''}`}
                                    fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                >
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 9l-7 7-7-7" />
                                </svg>
                            </button>
                            {showMessage && (
                                <div className="p-6">
                                    {order.mensaje_whatsapp ? (
                                        <pre className="whitespace-pre-wrap text-sm text-gray-700 font-sans">{order.mensaje_whatsapp}</pre>
                                    ) : (
                                        <p className="text-sm text-gray-500">Esta orden no tiene un mensaje de WhatsApp registrado.</p>
                                    )}
                                </div>
                            )}
                        </div>
                    </div>

                    {/* Columna Lateral - Acciones */}
                    <div className="lg:col-span-1">
                        <div className="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden sticky top-6">
                            <div className="bg-gradient-to-r from-gray-50 to-slate-50 px-6 py-4 border-b border-gray-100">
                                <h3 className="text-lg font-medium text-gray-900">Acciones</h3>
                            </div>
                            <div className="p-6 space-y-3">
                                {transiciones.includes('despachado') && (
                                    <button
                                        onClick={() => updateEstado('despachado')}
                                        disabled={isUpdating}
                                        className="w-full px-4 py-3 text-sm font-medium rounded-lg text-white bg-green-600 hover:bg-green-700 transition-colors disabled:opacity-50"
                                    >
                                        Marcar como despachado
                                    </button>
                                )}
                                {transiciones.includes('pendiente') && (
                                    <button
                                        onClick={() => updateEstado('pendiente')}
                                        disabled={isUpdating}
                                        className="w-full px-4 py-3 text-sm font-medium rounded-lg text-amber-800 bg-amber-100 hover:bg-amber-200 transition-colors disabled:opacity-50"
                                    >
                                        Volver a pendiente
                                    </button>
                                )}
                                {transiciones.includes('cancelado') && (
                                    <button
                                        onClick={() => setShowCancelModal(true)}
                                        disabled={isUpdating}
                                        className="w-full px-4 py-3 text-sm font-medium rounded-lg text-red-700 bg-red-100 hover:bg-red-200 transition-colors disabled:opacity-50"
                                    >
                                        Cancelar orden
                                    </button>
                                )}
                                {transiciones.length === 0 && (
                                    <p className="text-sm text-gray-500 text-center">
                                        Esta orden no admite más cambios de estado.
                                    </p>
                                )}
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <DeleteConfirmationModal
                show={showCancelModal}
                onClose={() => !isUpdating && setShowCancelModal(false)}
                onConfirm={() => updateEstado('cancelado')}
                title="¿Cancelar orden?"
                message={`Estás a punto de cancelar la orden de ${order.name} ${order.lastname}.`}
                warningMessage="Esta acción no se puede deshacer."
                confirmText="Cancelar Orden"
                processingText="Cancelando..."
                processing={isUpdating}
            />
        </AdminLayout>
    );
}
