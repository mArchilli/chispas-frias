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
    IconSparkles,
} from '@/Components/Admin/Icons';

const inputClasses =
    'block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900 placeholder:text-slate-400 transition focus:border-navy focus:outline-none focus:ring-2 focus:ring-navy/10';

function formatMoney(value) {
    if (value === null || value === undefined) return '—';
    return '$' + Number(value).toLocaleString('es-AR');
}

export default function Index({ addons, filters = {} }) {
    const { isAdmin } = usePermissions();
    const [showDeleteModal, setShowDeleteModal] = useState(false);
    const [addonToDelete, setAddonToDelete] = useState(null);
    const [isDeleting, setIsDeleting] = useState(false);
    const [togglingId, setTogglingId] = useState(null);

    const searchForm = useForm({ search: filters?.search || '', status: filters?.status || '' });

    const hasActiveFilters = !!(filters?.search || filters?.status);

    const handleSearch = (e) => {
        e.preventDefault();
        searchForm.get(route('admin.addons.index'), { preserveState: true, replace: true });
    };

    const clearFilters = () => {
        searchForm.reset();
        router.get(route('admin.addons.index'), {}, { preserveState: true, replace: true });
    };

    const handleToggleStatus = (addon) => {
        setTogglingId(addon.id);
        router.patch(
            route('admin.addons.toggle-status', addon.id),
            {},
            {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => toast.success(addon.is_active ? 'Add-on desactivado' : 'Add-on activado'),
                onError: () => toast.error('Error al actualizar el estado'),
                onFinish: () => setTogglingId(null),
            }
        );
    };

    const handleDelete = (addon) => {
        setAddonToDelete(addon);
        setShowDeleteModal(true);
    };

    const confirmDelete = () => {
        if (!addonToDelete) return;
        setIsDeleting(true);
        router.delete(route('admin.addons.destroy', addonToDelete.id), {
            onSuccess: () => {
                toast.success('Add-on eliminado exitosamente');
                setShowDeleteModal(false);
                setAddonToDelete(null);
                setIsDeleting(false);
            },
            onError: (errors) => {
                toast.error(errors?.error || 'Error al eliminar el add-on');
                setShowDeleteModal(false);
                setAddonToDelete(null);
                setIsDeleting(false);
            },
        });
    };

    const closeDeleteModal = () => {
        if (!isDeleting) {
            setShowDeleteModal(false);
            setAddonToDelete(null);
        }
    };

    return (
        <AdminLayout
            header={
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p className="text-xs font-semibold uppercase tracking-wider text-slate-400">
                            {addons?.total ?? 0} {addons?.total === 1 ? 'add-on' : 'add-ons'}
                        </p>
                        <h1 className="mt-1 text-xl font-semibold text-slate-900 sm:text-2xl">Add-ons</h1>
                    </div>
                    <Link
                        href={route('admin.addons.create')}
                        className="inline-flex items-center gap-1.5 rounded-lg bg-gold px-3.5 py-2 text-sm font-semibold text-navy transition hover:brightness-95"
                    >
                        <IconPlus className="h-4 w-4" />
                        Nuevo add-on
                    </Link>
                </div>
            }
        >
            <Head title="Add-ons - Admin" />

            <div className="space-y-6">
                {/* Filtros */}
                <form onSubmit={handleSearch} className="space-y-3">
                    <div className="flex flex-col gap-2 sm:flex-row">
                        <div className="relative flex-1">
                            <IconSearch className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                            <input
                                type="text"
                                placeholder="Buscar por nombre..."
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
                            <option value="inactivo">Inactivos</option>
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
                {addons?.data?.length > 0 ? (
                    <div className="overflow-hidden rounded-xl border border-slate-200 bg-white">
                        <div className="overflow-x-auto">
                            <table className="w-full text-left text-sm">
                                <thead className="border-b border-slate-200 bg-slate-50 text-xs font-semibold uppercase tracking-wider text-slate-500">
                                    <tr>
                                        <th className="px-4 py-3">Add-on</th>
                                        <th className="px-4 py-3">Precio</th>
                                        <th className="px-4 py-3">Texto</th>
                                        <th className="px-4 py-3">Productos</th>
                                        <th className="px-4 py-3">Estado</th>
                                        <th className="px-4 py-3 text-right">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100">
                                    {addons.data.map((addon) => (
                                        <tr key={addon.id} className="transition hover:bg-slate-50">
                                            <td className="px-4 py-3">
                                                <div className="flex items-center gap-2">
                                                    <span className="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-lg bg-navy/5 text-navy">
                                                        <IconSparkles className="h-4 w-4" />
                                                    </span>
                                                    <div className="min-w-0">
                                                        <p className="text-sm font-semibold text-slate-900">{addon.name}</p>
                                                        {addon.description && (
                                                            <p className="max-w-[260px] truncate text-xs text-slate-400">
                                                                {addon.description}
                                                            </p>
                                                        )}
                                                    </div>
                                                </div>
                                            </td>
                                            <td className="px-4 py-3 font-semibold text-slate-900">
                                                {formatMoney(addon.price)}
                                            </td>
                                            <td className="px-4 py-3 text-slate-600">
                                                {addon.requires_text
                                                    ? `Sí${addon.max_characters ? ` · máx. ${addon.max_characters}` : ''}`
                                                    : 'No'}
                                            </td>
                                            <td className="px-4 py-3 text-slate-600">{addon.products_count}</td>
                                            <td className="px-4 py-3">
                                                <span
                                                    className={`inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-semibold ${
                                                        addon.is_active
                                                            ? 'bg-emerald-50 text-emerald-700'
                                                            : 'bg-slate-100 text-slate-500'
                                                    }`}
                                                >
                                                    {addon.is_active ? 'Activo' : 'Inactivo'}
                                                </span>
                                            </td>
                                            <td className="px-4 py-3">
                                                <div className="flex items-center justify-end gap-0.5">
                                                    <ActionIconButton
                                                        onClick={() => handleToggleStatus(addon)}
                                                        icon={addon.is_active ? IconEye : IconEyeOff}
                                                        label={addon.is_active ? 'Desactivar' : 'Activar'}
                                                        tone={addon.is_active ? 'active' : 'default'}
                                                        disabled={togglingId === addon.id}
                                                    />
                                                    <ActionIconButton
                                                        href={route('admin.addons.edit', addon.id)}
                                                        icon={IconPencil}
                                                        label="Editar"
                                                    />
                                                    {isAdmin && (
                                                        <ActionIconButton
                                                            onClick={() => handleDelete(addon)}
                                                            icon={IconTrash}
                                                            label="Eliminar"
                                                            tone="danger"
                                                        />
                                                    )}
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>
                ) : (
                    <div className="rounded-xl border border-slate-200 bg-white py-16 text-center">
                        <IconInbox className="mx-auto h-8 w-8 text-slate-300" />
                        <h3 className="mt-3 text-sm font-medium text-slate-900">
                            {hasActiveFilters ? 'No se encontraron resultados' : 'No hay add-ons creados'}
                        </h3>
                        <p className="mt-1 text-sm text-slate-500">
                            {hasActiveFilters
                                ? 'Probá ajustar los filtros aplicados.'
                                : 'Comenzá creando tu primer add-on de personalización.'}
                        </p>
                        {!hasActiveFilters && (
                            <Link
                                href={route('admin.addons.create')}
                                className="mt-4 inline-flex items-center gap-1.5 rounded-lg bg-gold px-4 py-2 text-sm font-semibold text-navy transition hover:brightness-95"
                            >
                                <IconPlus className="h-4 w-4" />
                                Crear primer add-on
                            </Link>
                        )}
                    </div>
                )}

                {/* Paginación */}
                {addons?.data?.length > 0 && addons?.links?.length > 3 && (
                    <div className="flex flex-col items-center justify-between gap-3 border-t border-slate-200 pt-4 sm:flex-row">
                        <p className="text-sm text-slate-500">
                            Mostrando <span className="font-medium text-slate-700">{addons?.from || 0}</span>–
                            <span className="font-medium text-slate-700">{addons?.to || 0}</span> de{' '}
                            <span className="font-medium text-slate-700">{addons?.total || 0}</span>
                        </p>
                        <nav className="flex flex-wrap items-center gap-1">
                            {addons.links.map((link, index) =>
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
                title="¿Eliminar add-on?"
                message="Estás a punto de eliminar el add-on:"
                itemName={addonToDelete?.name}
                warningMessage="Esta acción no se puede deshacer. Si ya se usó en órdenes, desactivalo en su lugar."
                confirmText="Eliminar add-on"
                processing={isDeleting}
            />
        </AdminLayout>
    );
}
