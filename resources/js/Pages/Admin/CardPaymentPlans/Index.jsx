import React, { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import toast from 'react-hot-toast';
import AdminLayout from '@/Layouts/AdminLayout';
import DeleteConfirmationModal from '@/Components/DeleteConfirmationModal';
import ActionIconButton from '@/Components/Admin/ActionIconButton';
import usePermissions from '@/hooks/usePermissions';
import { IconPlus, IconPencil, IconTrash, IconEye, IconEyeOff, IconInbox, IconPercent } from '@/Components/Admin/Icons';
import { SAMPLE_ORDER_TOTAL, formatMoney, simulateSurcharge } from '@/utils/cardPaymentPlans';

export default function Index({ plans = [] }) {
    const { isAdmin } = usePermissions();
    const [showDeleteModal, setShowDeleteModal] = useState(false);
    const [planToDelete, setPlanToDelete] = useState(null);
    const [isDeleting, setIsDeleting] = useState(false);
    const [togglingId, setTogglingId] = useState(null);

    const handleToggleStatus = (plan) => {
        setTogglingId(plan.id);
        router.patch(
            route('admin.card-payment-plans.toggle-status', plan.id),
            {},
            {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => toast.success(plan.is_active ? 'Plan desactivado' : 'Plan activado'),
                onError: () => toast.error('Error al actualizar el estado'),
                onFinish: () => setTogglingId(null),
            }
        );
    };

    const handleDelete = (plan) => {
        setPlanToDelete(plan);
        setShowDeleteModal(true);
    };

    const confirmDelete = () => {
        if (!planToDelete) return;
        setIsDeleting(true);
        router.delete(route('admin.card-payment-plans.destroy', planToDelete.id), {
            onSuccess: () => {
                toast.success('Plan eliminado exitosamente');
                setShowDeleteModal(false);
                setPlanToDelete(null);
                setIsDeleting(false);
            },
            onError: (errors) => {
                toast.error(errors?.error || 'Error al eliminar el plan');
                setShowDeleteModal(false);
                setPlanToDelete(null);
                setIsDeleting(false);
            },
        });
    };

    const closeDeleteModal = () => {
        if (!isDeleting) {
            setShowDeleteModal(false);
            setPlanToDelete(null);
        }
    };

    return (
        <AdminLayout
            header={
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p className="text-xs font-semibold uppercase tracking-wider text-slate-400">
                            {plans.length} {plans.length === 1 ? 'plan' : 'planes'}
                        </p>
                        <h1 className="mt-1 text-xl font-semibold text-slate-900 sm:text-2xl">Planes de cuotas</h1>
                    </div>
                    <Link
                        href={route('admin.card-payment-plans.create')}
                        className="inline-flex items-center gap-1.5 rounded-lg bg-gold px-3.5 py-2 text-sm font-semibold text-navy transition hover:brightness-95"
                    >
                        <IconPlus className="h-4 w-4" />
                        Nuevo plan
                    </Link>
                </div>
            }
        >
            <Head title="Planes de cuotas - Admin" />

            <div className="space-y-6">
                <div className="rounded-xl border border-slate-200 bg-navy/5 px-4 py-3.5 text-sm text-slate-600">
                    Recargo <strong className="font-semibold text-slate-800">informativo</strong> por pago con tarjeta de
                    crédito. No se cobra online ni se integra Mercado Pago: el vendedor genera el link de pago manual por
                    el total con recargo. El recargo es un cargo único sobre el total del pedido completo.
                </div>

                {plans.length > 0 ? (
                    <div className="overflow-hidden rounded-xl border border-slate-200 bg-white">
                        <div className="overflow-x-auto">
                            <table className="w-full text-left text-sm">
                                <thead className="border-b border-slate-200 bg-slate-50 text-xs font-semibold uppercase tracking-wider text-slate-500">
                                    <tr>
                                        <th className="px-4 py-3">Plan</th>
                                        <th className="px-4 py-3">Cuotas</th>
                                        <th className="px-4 py-3">Recargo</th>
                                        <th className="px-4 py-3">Ejemplo sobre {formatMoney(SAMPLE_ORDER_TOTAL)}</th>
                                        <th className="px-4 py-3">Estado</th>
                                        <th className="px-4 py-3 text-right">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100">
                                    {plans.map((plan) => {
                                        const sim = simulateSurcharge(SAMPLE_ORDER_TOTAL, plan);
                                        return (
                                            <tr key={plan.id} className="transition hover:bg-slate-50">
                                                <td className="px-4 py-3">
                                                    <div className="flex items-center gap-2">
                                                        <span className="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-lg bg-navy/5 text-navy">
                                                            <IconPercent className="h-4 w-4" />
                                                        </span>
                                                        <div className="min-w-0">
                                                            <p className="text-sm font-semibold text-slate-900">
                                                                {plan.name}
                                                            </p>
                                                            {plan.en_uso && (
                                                                <p className="text-xs text-slate-400">
                                                                    Usado en órdenes
                                                                </p>
                                                            )}
                                                        </div>
                                                    </div>
                                                </td>
                                                <td className="px-4 py-3 text-slate-600">{plan.installments}</td>
                                                <td className="px-4 py-3 font-semibold text-slate-900">
                                                    +{Number(plan.surcharge_percentage).toFixed(2)}%
                                                </td>
                                                <td className="px-4 py-3 text-xs text-slate-500">
                                                    {formatMoney(sim.totalWithSurcharge)}
                                                    {' · '}
                                                    {plan.installments === 1
                                                        ? '1 pago'
                                                        : `${plan.installments} de ${formatMoney(sim.installmentAmount)}`}
                                                </td>
                                                <td className="px-4 py-3">
                                                    <span
                                                        className={`inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-semibold ${
                                                            plan.is_active
                                                                ? 'bg-emerald-50 text-emerald-700'
                                                                : 'bg-slate-100 text-slate-500'
                                                        }`}
                                                    >
                                                        {plan.is_active ? 'Activo' : 'Inactivo'}
                                                    </span>
                                                </td>
                                                <td className="px-4 py-3">
                                                    <div className="flex items-center justify-end gap-0.5">
                                                        <ActionIconButton
                                                            onClick={() => handleToggleStatus(plan)}
                                                            icon={plan.is_active ? IconEye : IconEyeOff}
                                                            label={plan.is_active ? 'Desactivar' : 'Activar'}
                                                            tone={plan.is_active ? 'active' : 'default'}
                                                            disabled={togglingId === plan.id}
                                                        />
                                                        <ActionIconButton
                                                            href={route('admin.card-payment-plans.edit', plan.id)}
                                                            icon={IconPencil}
                                                            label="Editar"
                                                        />
                                                        {isAdmin && (
                                                            <ActionIconButton
                                                                onClick={() => handleDelete(plan)}
                                                                icon={IconTrash}
                                                                label={
                                                                    plan.en_uso
                                                                        ? 'No se puede eliminar: ya se usó en órdenes'
                                                                        : 'Eliminar'
                                                                }
                                                                tone="danger"
                                                                disabled={plan.en_uso}
                                                            />
                                                        )}
                                                    </div>
                                                </td>
                                            </tr>
                                        );
                                    })}
                                </tbody>
                            </table>
                        </div>
                    </div>
                ) : (
                    <div className="rounded-xl border border-slate-200 bg-white py-16 text-center">
                        <IconInbox className="mx-auto h-8 w-8 text-slate-300" />
                        <h3 className="mt-3 text-sm font-medium text-slate-900">No hay planes de cuotas creados</h3>
                        <p className="mt-1 text-sm text-slate-500">
                            Comenzá creando tu primer plan de recargo por pago con tarjeta.
                        </p>
                        <Link
                            href={route('admin.card-payment-plans.create')}
                            className="mt-4 inline-flex items-center gap-1.5 rounded-lg bg-gold px-4 py-2 text-sm font-semibold text-navy transition hover:brightness-95"
                        >
                            <IconPlus className="h-4 w-4" />
                            Crear primer plan
                        </Link>
                    </div>
                )}
            </div>

            <DeleteConfirmationModal
                show={showDeleteModal}
                onClose={closeDeleteModal}
                onConfirm={confirmDelete}
                title="¿Eliminar plan de cuotas?"
                message="Estás a punto de eliminar el plan:"
                itemName={planToDelete?.name}
                warningMessage="Esta acción no se puede deshacer. Si ya se usó en órdenes, desactivalo en su lugar."
                confirmText="Eliminar plan"
                processing={isDeleting}
            />
        </AdminLayout>
    );
}
