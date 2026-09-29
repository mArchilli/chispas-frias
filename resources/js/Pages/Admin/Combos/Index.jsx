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
    IconGift,
    IconTruck,
} from '@/Components/Admin/Icons';

const inputClasses =
    'block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900 placeholder:text-slate-400 transition focus:border-navy focus:outline-none focus:ring-2 focus:ring-navy/10';

const formatMoney = (value) => '$' + Number(value || 0).toLocaleString('es-AR');

export default function Index({ combos, filters = {} }) {
    const { isAdmin } = usePermissions();
    const [showDeleteModal, setShowDeleteModal] = useState(false);
    const [comboToDelete, setComboToDelete] = useState(null);
    const [isDeleting, setIsDeleting] = useState(false);
    const [togglingId, setTogglingId] = useState(null);

    const searchForm = useForm({ search: filters?.search || '' });
    const hasActiveFilters = !!filters?.search;

    const handleSearch = (e) => {
        e.preventDefault();
        searchForm.get(route('admin.combos.index'), { preserveState: true, replace: true });
    };

    const clearFilters = () => {
        searchForm.reset();
        router.get(route('admin.combos.index'), {}, { preserveState: true, replace: true });
    };

    const handleToggleStatus = (combo) => {
        setTogglingId(combo.id);
        router.patch(
            route('admin.combos.toggle-status', combo.id),
            {},
            {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => toast.success(combo.is_active ? 'Combo desactivado' : 'Combo activado'),
                onError: () => toast.error('Error al actualizar el estado'),
                onFinish: () => setTogglingId(null),
            }
        );
    };

    const confirmDelete = () => {
        if (!comboToDelete) return;
        setIsDeleting(true);
        router.delete(route('admin.combos.destroy', comboToDelete.id), {
            onSuccess: () => {
                toast.success('Combo eliminado exitosamente');
                setShowDeleteModal(false);
                setComboToDelete(null);
                setIsDeleting(false);
            },
            onError: (errs) => {
                toast.error(errs?.error || 'Error al eliminar el combo');
                setShowDeleteModal(false);
                setComboToDelete(null);
                setIsDeleting(false);
            },
        });
    };

    const closeDeleteModal = () => {
        if (!isDeleting) {
            setShowDeleteModal(false);
            setComboToDelete(null);
        }
    };

    return (
        <AdminLayout
            header={
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p className="text-xs font-semibold uppercase tracking-wider text-slate-400">
                            {combos?.total ?? 0} {combos?.total === 1 ? 'combo' : 'combos'}
                        </p>
                        <h1 className="mt-1 text-xl font-semibold text-slate-900 sm:text-2xl">Combos</h1>
                    </div>
                    <Link
                        href={route('admin.combos.create')}
                        className="inline-flex items-center gap-1.5 rounded-lg bg-gold px-3.5 py-2 text-sm font-semibold text-navy transition hover:brightness-95"
                    >
                        <IconPlus className="h-4 w-4" />
                        Nuevo combo
                    </Link>
                </div>
            }
        >
            <Head title="Combos - Admin" />

            <div className="space-y-6">
                <form onSubmit={handleSearch} className="space-y-3">
                    <div className="relative">
                        <IconSearch className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                        <input
                            type="text"
                            placeholder="Buscar por nombre..."
                            value={searchForm.data.search}
                            onChange={(e) => searchForm.setData('search', e.target.value)}
                            className={`${inputClasses} pl-9`}
                        />
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

                {combos?.data?.length > 0 ? (
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                        {combos.data.map((combo) => (
                            <div
                                key={combo.id}
                                className="flex flex-col overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm transition hover:shadow-md"
                            >
                                <div className="relative aspect-[4/3] bg-slate-50">
                                    {combo.primary_image ? (
                                        <img src={combo.primary_image} alt={combo.title} className="h-full w-full object-cover" />
                                    ) : (
                                        <span className="flex h-full w-full items-center justify-center text-slate-300">
                                            <IconGift className="h-10 w-10" />
                                        </span>
                                    )}
                                    {combo.is_free_shipping && (
                                        <span className="absolute right-2 top-2 inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2 py-0.5 text-[11px] font-semibold text-emerald-700 shadow-sm">
                                            <IconTruck className="h-3.5 w-3.5" />
                                            Envío gratis
                                        </span>
                                    )}
                                    {!combo.is_active && (
                                        <span className="absolute left-2 top-2 rounded-full bg-slate-900/70 px-2 py-0.5 text-[11px] font-semibold text-white">
                                            Inactivo
                                        </span>
                                    )}
                                </div>

                                <div className="flex flex-1 flex-col p-4">
                                    <h3 className="line-clamp-2 text-sm font-semibold text-slate-900">{combo.title}</h3>
                                    <p className="mt-1 text-lg font-bold text-slate-900">{formatMoney(combo.price)}</p>
                                    <p className="text-xs text-slate-500">
                                        {combo.items_count} {combo.items_count === 1 ? 'producto' : 'productos'}
                                    </p>

                                    <div className="mt-auto flex items-center justify-between gap-2 pt-3">
                                        <span
                                            className={`inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-semibold ${
                                                combo.is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500'
                                            }`}
                                        >
                                            {combo.is_active ? 'Activo' : 'Inactivo'}
                                        </span>
                                        <div className="flex items-center gap-0.5">
                                            <ActionIconButton
                                                onClick={() => handleToggleStatus(combo)}
                                                icon={combo.is_active ? IconEye : IconEyeOff}
                                                label={combo.is_active ? 'Desactivar' : 'Activar'}
                                                tone={combo.is_active ? 'active' : 'default'}
                                                disabled={togglingId === combo.id}
                                            />
                                            <ActionIconButton
                                                href={route('admin.combos.edit', combo.id)}
                                                icon={IconPencil}
                                                label="Editar"
                                            />
                                            {isAdmin && (
                                                <ActionIconButton
                                                    onClick={() => {
                                                        setComboToDelete(combo);
                                                        setShowDeleteModal(true);
                                                    }}
                                                    icon={IconTrash}
                                                    label="Eliminar"
                                                    tone="danger"
                                                />
                                            )}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        ))}
                    </div>
                ) : (
                    <div className="rounded-xl border border-slate-200 bg-white py-16 text-center">
                        <IconInbox className="mx-auto h-8 w-8 text-slate-300" />
                        <h3 className="mt-3 text-sm font-medium text-slate-900">
                            {hasActiveFilters ? 'No se encontraron resultados' : 'No hay combos creados'}
                        </h3>
                        <p className="mt-1 text-sm text-slate-500">
                            {hasActiveFilters ? 'Probá ajustar la búsqueda.' : 'Creá tu primer combo de productos.'}
                        </p>
                        {!hasActiveFilters && (
                            <Link
                                href={route('admin.combos.create')}
                                className="mt-4 inline-flex items-center gap-1.5 rounded-lg bg-gold px-4 py-2 text-sm font-semibold text-navy transition hover:brightness-95"
                            >
                                <IconPlus className="h-4 w-4" />
                                Crear primer combo
                            </Link>
                        )}
                    </div>
                )}

                {combos?.data?.length > 0 && combos?.links?.length > 3 && (
                    <div className="flex flex-col items-center justify-between gap-3 border-t border-slate-200 pt-4 sm:flex-row">
                        <p className="text-sm text-slate-500">
                            Mostrando <span className="font-medium text-slate-700">{combos?.from || 0}</span>–
                            <span className="font-medium text-slate-700">{combos?.to || 0}</span> de{' '}
                            <span className="font-medium text-slate-700">{combos?.total || 0}</span>
                        </p>
                        <nav className="flex flex-wrap items-center gap-1">
                            {combos.links.map((link, index) =>
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
                title="¿Eliminar combo?"
                message="Estás a punto de eliminar el combo:"
                itemName={comboToDelete?.title}
                warningMessage="Esta acción no se puede deshacer. Si preferís, desactivalo en su lugar."
                confirmText="Eliminar combo"
                processing={isDeleting}
            />
        </AdminLayout>
    );
}
