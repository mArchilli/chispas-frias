import React, { useState } from 'react';
import { Head, Link, useForm, router } from '@inertiajs/react';
import toast from 'react-hot-toast';
import AdminLayout from '@/Layouts/AdminLayout';
import DeleteConfirmationModal from '@/Components/DeleteConfirmationModal';
import ActionIconButton from '@/Components/Admin/ActionIconButton';
import usePermissions from '@/hooks/usePermissions';
import {
    IconPlus,
    IconSearch,
    IconPencil,
    IconTrash,
    IconEye,
    IconEyeOff,
    IconInbox,
    IconTicket,
} from '@/Components/Admin/Icons';

const inputClasses =
    'block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900 placeholder:text-slate-400 transition focus:border-navy focus:outline-none focus:ring-2 focus:ring-navy/10';

const STATUS_META = {
    activo: { label: 'Activo', badge: 'bg-emerald-50 text-emerald-700' },
    programado: { label: 'Programado', badge: 'bg-amber-50 text-amber-700' },
    expirado: { label: 'Expirado', badge: 'bg-rose-50 text-rose-700' },
    inactivo: { label: 'Inactivo', badge: 'bg-slate-100 text-slate-500' },
    agotado: { label: 'Agotado', badge: 'bg-slate-200 text-slate-600' },
};

function formatDate(date) {
    if (!date) return null;
    // timeZone: 'UTC' es intencional, ver el mismo comentario en Offers/Index:
    // el server guarda el datetime-local tal cual se tipeó, sin conversión de
    // huso horario.
    return new Date(date).toLocaleDateString('es-AR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        timeZone: 'UTC',
    });
}

function formatMoney(value) {
    if (value === null || value === undefined) return null;
    return '$' + Number(value).toLocaleString('es-AR');
}

export default function Index({ discountCodes, filters = {} }) {
    const { isAdmin } = usePermissions();
    const [showDeleteModal, setShowDeleteModal] = useState(false);
    const [codeToDelete, setCodeToDelete] = useState(null);
    const [isDeleting, setIsDeleting] = useState(false);
    const [togglingId, setTogglingId] = useState(null);

    const searchForm = useForm({ search: filters?.search || '', status: filters?.status || '' });

    const hasActiveFilters = !!(filters?.search || filters?.status);

    const handleSearch = (e) => {
        e.preventDefault();
        searchForm.get(route('admin.discount-codes.index'), { preserveState: true, replace: true });
    };

    const clearFilters = () => {
        // No usar searchForm.get() acá: reset() programa la actualización de forma
        // asíncrona, así que un get() disparado en el mismo tick todavía ve los
        // filtros viejos (closure de React desactualizado). Ver Offers/Index.
        searchForm.reset();
        router.get(route('admin.discount-codes.index'), {}, { preserveState: true, replace: true });
    };

    const handleToggleStatus = (discountCode) => {
        setTogglingId(discountCode.id);
        router.patch(
            route('admin.discount-codes.toggle-status', discountCode.id),
            {},
            {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => toast.success(discountCode.is_active ? 'Código desactivado' : 'Código activado'),
                onError: () => toast.error('Error al actualizar el estado'),
                onFinish: () => setTogglingId(null),
            }
        );
    };

    const handleDelete = (discountCode) => {
        setCodeToDelete(discountCode);
        setShowDeleteModal(true);
    };

    const confirmDelete = () => {
        if (!codeToDelete) return;
        setIsDeleting(true);
        router.delete(route('admin.discount-codes.destroy', codeToDelete.id), {
            onSuccess: () => {
                toast.success('Código eliminado exitosamente');
                setShowDeleteModal(false);
                setCodeToDelete(null);
                setIsDeleting(false);
            },
            onError: (errors) => {
                toast.error(errors?.error || 'Error al eliminar el código');
                setShowDeleteModal(false);
                setCodeToDelete(null);
                setIsDeleting(false);
            },
        });
    };

    const closeDeleteModal = () => {
        if (!isDeleting) {
            setShowDeleteModal(false);
            setCodeToDelete(null);
        }
    };

    return (
        <AdminLayout
            header={
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p className="text-xs font-semibold uppercase tracking-wider text-slate-400">
                            {discountCodes?.total ?? 0} {discountCodes?.total === 1 ? 'código' : 'códigos'}
                        </p>
                        <h1 className="mt-1 text-xl font-semibold text-slate-900 sm:text-2xl">
                            Códigos de descuento
                        </h1>
                    </div>
                    <Link
                        href={route('admin.discount-codes.create')}
                        className="inline-flex items-center gap-1.5 rounded-lg bg-gold px-3.5 py-2 text-sm font-semibold text-navy transition hover:brightness-95"
                    >
                        <IconPlus className="h-4 w-4" />
                        Nuevo código
                    </Link>
                </div>
            }
        >
            <Head title="Códigos de descuento - Admin" />

            <div className="space-y-6">
                {/* Filtros */}
                <form onSubmit={handleSearch} className="space-y-3">
                    <div className="flex flex-col gap-2 sm:flex-row">
                        <div className="relative flex-1">
                            <IconSearch className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                            <input
                                type="text"
                                placeholder="Buscar por código..."
                                value={searchForm.data.search}
                                onChange={(e) => searchForm.setData('search', e.target.value)}
                                className={`${inputClasses} pl-9`}
                            />
                        </div>
                        <select
                            value={searchForm.data.status}
                            onChange={(e) => searchForm.setData('status', e.target.value)}
                            className={`${inputClasses} sm:max-w-[180px]`}
                        >
                            <option value="">Todos los estados</option>
                            <option value="activo">Activos</option>
                            <option value="programado">Programados</option>
                            <option value="expirado">Expirados</option>
                            <option value="inactivo">Inactivos</option>
                            <option value="agotado">Agotados</option>
                        </select>
                    </div>
                    <div className="flex gap-2">
                        <button
                            type="submit"
                            className="rounded-lg border border-slate-300 px-3.5 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
                        >
                            Buscar
                        </button>
                        {hasActiveFilters && (
                            <button
                                type="button"
                                onClick={clearFilters}
                                className="rounded-lg px-3.5 py-2 text-sm font-medium text-slate-500 transition hover:bg-slate-100"
                            >
                                Limpiar
                            </button>
                        )}
                    </div>
                </form>

                {/* Tabla */}
                {discountCodes?.data?.length > 0 ? (
                    <div className="overflow-hidden rounded-xl border border-slate-200 bg-white">
                        <div className="overflow-x-auto">
                            <table className="w-full text-left text-sm">
                                <thead className="border-b border-slate-200 bg-slate-50 text-xs font-semibold uppercase tracking-wider text-slate-500">
                                    <tr>
                                        <th className="px-4 py-3">Código</th>
                                        <th className="px-4 py-3">Descuento</th>
                                        <th className="px-4 py-3">Mínimo de compra</th>
                                        <th className="px-4 py-3">Usos</th>
                                        <th className="px-4 py-3">Vigencia</th>
                                        <th className="px-4 py-3">Estado</th>
                                        <th className="px-4 py-3 text-right">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100">
                                    {discountCodes.data.map((discountCode) => {
                                        const status = STATUS_META[discountCode.status] ?? STATUS_META.inactivo;
                                        return (
                                            <tr key={discountCode.id} className="transition hover:bg-slate-50">
                                                <td className="px-4 py-3">
                                                    <div className="flex items-center gap-2">
                                                        <span className="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-lg bg-navy/5 text-navy">
                                                            <IconTicket className="h-4 w-4" />
                                                        </span>
                                                        <div className="min-w-0">
                                                            <p className="font-mono text-sm font-semibold text-slate-900">
                                                                {discountCode.code}
                                                            </p>
                                                            {discountCode.description && (
                                                                <p className="max-w-[220px] truncate text-xs text-slate-400">
                                                                    {discountCode.description}
                                                                </p>
                                                            )}
                                                        </div>
                                                    </div>
                                                </td>
                                                <td className="px-4 py-3 font-semibold text-slate-900">
                                                    {Math.round(discountCode.percentage)}%
                                                </td>
                                                <td className="px-4 py-3 text-slate-600">
                                                    {discountCode.min_purchase_amount !== null
                                                        ? formatMoney(discountCode.min_purchase_amount)
                                                        : '—'}
                                                </td>
                                                <td className="px-4 py-3 text-slate-600">
                                                    {discountCode.usage_count}
                                                    {discountCode.usage_limit !== null
                                                        ? ` / ${discountCode.usage_limit}`
                                                        : ' / ilimitado'}
                                                </td>
                                                <td className="px-4 py-3 text-xs text-slate-500">
                                                    {discountCode.start_date || discountCode.end_date ? (
                                                        <>
                                                            {discountCode.start_date
                                                                ? formatDate(discountCode.start_date)
                                                                : 'Ahora'}
                                                            {' → '}
                                                            {discountCode.end_date
                                                                ? formatDate(discountCode.end_date)
                                                                : 'Sin fin'}
                                                        </>
                                                    ) : (
                                                        'Sin límite de fechas'
                                                    )}
                                                </td>
                                                <td className="px-4 py-3">
                                                    <span
                                                        className={`inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-semibold ${status.badge}`}
                                                    >
                                                        {status.label}
                                                    </span>
                                                </td>
                                                <td className="px-4 py-3">
                                                    <div className="flex items-center justify-end gap-0.5">
                                                        <ActionIconButton
                                                            onClick={() => handleToggleStatus(discountCode)}
                                                            icon={discountCode.is_active ? IconEye : IconEyeOff}
                                                            label={discountCode.is_active ? 'Desactivar' : 'Activar'}
                                                            tone={discountCode.is_active ? 'active' : 'default'}
                                                            disabled={togglingId === discountCode.id}
                                                        />
                                                        <ActionIconButton
                                                            href={route('admin.discount-codes.edit', discountCode.id)}
                                                            icon={IconPencil}
                                                            label="Editar"
                                                        />
                                                        {isAdmin && (
                                                            <ActionIconButton
                                                                onClick={() => handleDelete(discountCode)}
                                                                icon={IconTrash}
                                                                label="Eliminar"
                                                                tone="danger"
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
                        <h3 className="mt-3 text-sm font-medium text-slate-900">
                            {hasActiveFilters ? 'No se encontraron resultados' : 'No hay códigos de descuento creados'}
                        </h3>
                        <p className="mt-1 text-sm text-slate-500">
                            {hasActiveFilters
                                ? 'Probá ajustar los filtros aplicados.'
                                : 'Comenzá creando tu primer código de descuento.'}
                        </p>
                        {!hasActiveFilters && (
                            <Link
                                href={route('admin.discount-codes.create')}
                                className="mt-4 inline-flex items-center gap-1.5 rounded-lg bg-gold px-4 py-2 text-sm font-semibold text-navy transition hover:brightness-95"
                            >
                                <IconPlus className="h-4 w-4" />
                                Crear primer código
                            </Link>
                        )}
                    </div>
                )}

                {/* Paginación */}
                {discountCodes?.data?.length > 0 && discountCodes?.links?.length > 3 && (
                    <div className="flex flex-col items-center justify-between gap-3 border-t border-slate-200 pt-4 sm:flex-row">
                        <p className="text-sm text-slate-500">
                            Mostrando <span className="font-medium text-slate-700">{discountCodes?.from || 0}</span>–
                            <span className="font-medium text-slate-700">{discountCodes?.to || 0}</span> de{' '}
                            <span className="font-medium text-slate-700">{discountCodes?.total || 0}</span>
                        </p>
                        <nav className="flex flex-wrap items-center gap-1">
                            {discountCodes.links.map((link, index) =>
                                link.url ? (
                                    <Link
                                        key={index}
                                        href={link.url}
                                        preserveScroll
                                        className={`flex h-8 min-w-8 items-center justify-center rounded-md px-2 text-sm font-medium transition ${
                                            link.active ? 'bg-navy text-white' : 'text-slate-600 hover:bg-slate-100'
                                        }`}
                                        dangerouslySetInnerHTML={{ __html: link.label }}
                                    />
                                ) : (
                                    <span
                                        key={index}
                                        className="flex h-8 min-w-8 items-center justify-center rounded-md px-2 text-sm text-slate-300"
                                        dangerouslySetInnerHTML={{ __html: link.label }}
                                    />
                                )
                            )}
                        </nav>
                    </div>
                )}
            </div>

            <DeleteConfirmationModal
                show={showDeleteModal}
                onClose={closeDeleteModal}
                onConfirm={confirmDelete}
                title="¿Eliminar código de descuento?"
                message="Estás a punto de eliminar el código:"
                itemName={codeToDelete?.code}
                warningMessage="Esta acción no se puede deshacer."
                confirmText="Eliminar código"
                processing={isDeleting}
            />
        </AdminLayout>
    );
}
