import React, { useState, useEffect } from 'react';
import { Head, Link, useForm, router } from '@inertiajs/react';
import toast from 'react-hot-toast';
import AdminLayout from '@/Layouts/AdminLayout';
import DeleteConfirmationModal from '@/Components/DeleteConfirmationModal';
import ActionIconButton from '@/Components/Admin/ActionIconButton';
import StatusDot from '@/Components/Admin/StatusDot';
import {
    IconLayers,
    IconChevronDown,
    IconPencil,
    IconTrash,
    IconEye,
    IconEyeOff,
    IconPlus,
    IconSearch,
    IconInbox,
} from '@/Components/Admin/Icons';

export default function Index({ categories = [], filters = {} }) {
    // Colapsadas por defecto; una búsqueda nueva expande los grupos con
    // coincidencias, y limpiarla vuelve a colapsar. Fuera de eso (borrar,
    // activar/desactivar) se conserva lo que el usuario tenía abierto.
    const [expandedIds, setExpandedIds] = useState(() =>
        filters?.search ? new Set(categories.map((c) => c.id)) : new Set()
    );
    const previousSearch = React.useRef(filters?.search || '');
    const [showDeleteModal, setShowDeleteModal] = useState(false);
    const [categoryToDelete, setCategoryToDelete] = useState(null);
    const [isDeleting, setIsDeleting] = useState(false);
    const [togglingId, setTogglingId] = useState(null);

    useEffect(() => {
        const currentSearch = filters?.search || '';
        const searchChanged = currentSearch !== previousSearch.current;
        previousSearch.current = currentSearch;

        setExpandedIds((prev) => {
            const validIds = new Set(categories.map((c) => c.id));
            if (searchChanged) {
                return currentSearch ? validIds : new Set();
            }
            return new Set([...prev].filter((id) => validIds.has(id)));
        });
    }, [categories, filters?.search]);

    const searchForm = useForm({ search: filters?.search || '' });

    const handleSearch = (e) => {
        e.preventDefault();
        searchForm.get(route('admin.categories.index'), { preserveState: true, replace: true });
    };

    const clearSearch = () => {
        // No usar searchForm.get() acá: setData() programa la actualización de
        // forma asíncrona, así que un get() disparado en el mismo tick todavía
        // ve el valor de búsqueda viejo (closure de React desactualizado).
        searchForm.setData('search', '');
        router.get(route('admin.categories.index'), {}, { preserveState: true, replace: true });
    };

    const toggleExpand = (id) => {
        setExpandedIds((prev) => {
            const next = new Set(prev);
            if (next.has(id)) {
                next.delete(id);
            } else {
                next.add(id);
            }
            return next;
        });
    };

    const handleToggleStatus = (category) => {
        setTogglingId(category.id);
        router.patch(
            route('admin.categories.toggle-status', category.id),
            {},
            {
                preserveScroll: true,
                onSuccess: () => toast.success(category.is_active ? 'Categoría desactivada' : 'Categoría activada'),
                onError: () => toast.error('Error al actualizar el estado'),
                onFinish: () => setTogglingId(null),
            }
        );
    };

    const handleDeleteCategory = (category) => {
        setCategoryToDelete(category);
        setShowDeleteModal(true);
    };

    const canDeleteCategory = (category) => (category?.children_count || 0) === 0 && (category?.products_count || 0) === 0;

    const getDeleteWarningMessage = (category) => {
        if (category?.children_count > 0) {
            return `No se puede eliminar porque tiene ${category.children_count} subcategorías. Eliminá primero las subcategorías.`;
        }
        if (category?.products_count > 0) {
            return `No se puede eliminar porque tiene ${category.products_count} productos asociados.`;
        }
        return 'Esta acción no se puede deshacer.';
    };

    const confirmDelete = () => {
        if (!categoryToDelete) return;
        setIsDeleting(true);
        router.delete(route('admin.categories.destroy', categoryToDelete.id), {
            onSuccess: () => {
                toast.success('Categoría eliminada exitosamente');
                setShowDeleteModal(false);
                setCategoryToDelete(null);
                setIsDeleting(false);
            },
            onError: () => {
                toast.error('Error al eliminar la categoría');
                setIsDeleting(false);
            },
        });
    };

    const closeDeleteModal = () => {
        if (!isDeleting) {
            setShowDeleteModal(false);
            setCategoryToDelete(null);
        }
    };

    const subCount = categories.reduce((sum, c) => sum + (c.children?.length || 0), 0);
    const allExpanded = categories.length > 0 && categories.every((c) => expandedIds.has(c.id));

    return (
        <AdminLayout
            header={
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p className="text-xs font-semibold uppercase tracking-wider text-slate-400">
                            {categories.length} {categories.length === 1 ? 'principal' : 'principales'} · {subCount}{' '}
                            {subCount === 1 ? 'subcategoría' : 'subcategorías'}
                        </p>
                        <h1 className="mt-1 text-xl font-semibold text-slate-900 sm:text-2xl">Categorías</h1>
                    </div>
                    <Link
                        href={route('admin.categories.create')}
                        className="inline-flex items-center gap-1.5 rounded-lg bg-gold px-3.5 py-2 text-sm font-semibold text-navy transition hover:brightness-95"
                    >
                        <IconPlus className="h-4 w-4" />
                        Nueva categoría
                    </Link>
                </div>
            }
        >
            <Head title="Categorías - Admin" />

            <div className="space-y-4">
                {/* Búsqueda */}
                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <form onSubmit={handleSearch} className="flex flex-1 gap-2">
                        <div className="relative flex-1 sm:max-w-xs">
                            <IconSearch className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                            <input
                                type="text"
                                placeholder="Buscar categoría o subcategoría..."
                                value={searchForm.data.search}
                                onChange={(e) => searchForm.setData('search', e.target.value)}
                                className="block w-full rounded-lg border border-slate-300 py-2 pl-9 pr-3 text-sm text-slate-900 placeholder:text-slate-400 transition focus:border-navy focus:outline-none focus:ring-2 focus:ring-navy/10"
                            />
                        </div>
                        <button
                            type="submit"
                            className="rounded-lg border border-slate-300 px-3.5 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
                        >
                            Buscar
                        </button>
                        {filters?.search && (
                            <button
                                type="button"
                                onClick={clearSearch}
                                className="rounded-lg px-3.5 py-2 text-sm font-medium text-slate-500 transition hover:bg-slate-100"
                            >
                                Limpiar
                            </button>
                        )}
                    </form>

                    {categories.length > 0 && (
                        <button
                            type="button"
                            onClick={() =>
                                setExpandedIds(allExpanded ? new Set() : new Set(categories.map((c) => c.id)))
                            }
                            className="inline-flex items-center justify-center whitespace-nowrap rounded-lg px-3.5 py-2 text-xs font-medium text-slate-500 transition hover:bg-slate-100 hover:text-slate-700"
                        >
                            {allExpanded ? 'Colapsar todo' : 'Expandir todo'}
                        </button>
                    )}
                </div>

                {/* Árbol de categorías */}
                {categories.length > 0 ? (
                    <div className="space-y-3">
                        {categories.map((category) => {
                            const expanded = expandedIds.has(category.id);
                            return (
                                <div key={category.id} className="rounded-xl border border-slate-200 bg-white">
                                    <div className="flex items-center gap-2 px-3 py-3 sm:px-4">
                                        <button
                                            type="button"
                                            onClick={() => toggleExpand(category.id)}
                                            className="flex min-w-0 flex-1 items-center gap-3 text-left"
                                        >
                                            <IconChevronDown
                                                className={`h-4 w-4 flex-shrink-0 text-slate-400 transition-transform ${
                                                    expanded ? '' : '-rotate-90'
                                                }`}
                                            />
                                            <span className="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-lg bg-navy/5 text-navy">
                                                <IconLayers className="h-5 w-5" />
                                            </span>
                                            <span className="min-w-0 flex-1">
                                                <span className="flex items-center gap-2">
                                                    <span className="truncate text-sm font-semibold text-slate-900">
                                                        {category.name}
                                                    </span>
                                                    <StatusDot active={category.is_active} />
                                                </span>
                                                <span className="block text-xs text-slate-500">
                                                    {category.children_count}{' '}
                                                    {category.children_count === 1 ? 'subcategoría' : 'subcategorías'} ·{' '}
                                                    {category.products_count}{' '}
                                                    {category.products_count === 1 ? 'producto' : 'productos'}
                                                </span>
                                            </span>
                                        </button>
                                        <div className="flex flex-shrink-0 items-center gap-0.5">
                                            <ActionIconButton
                                                onClick={() => handleToggleStatus(category)}
                                                icon={category.is_active ? IconEye : IconEyeOff}
                                                label={category.is_active ? 'Desactivar' : 'Activar'}
                                                disabled={togglingId === category.id}
                                            />
                                            <ActionIconButton
                                                href={route('admin.categories.edit', category.id)}
                                                icon={IconPencil}
                                                label="Editar"
                                            />
                                            <ActionIconButton
                                                onClick={() => handleDeleteCategory(category)}
                                                icon={IconTrash}
                                                label="Eliminar"
                                                tone="danger"
                                            />
                                        </div>
                                    </div>

                                    {expanded && (
                                        <div className="border-t border-slate-100 px-3 pb-3 sm:px-4">
                                            {category.description && (
                                                <p className="pt-3 text-sm text-slate-500">{category.description}</p>
                                            )}

                                            {category.children.length > 0 && (
                                                <ul className="mt-2 divide-y divide-slate-100 pl-4">
                                                    {category.children.map((child) => (
                                                        <li key={child.id} className="flex items-center gap-2 py-2">
                                                            <span className="h-1 w-1 flex-shrink-0 rounded-full bg-slate-300" />
                                                            <div className="min-w-0 flex-1">
                                                                <div className="flex items-center gap-2">
                                                                    <span className="truncate text-sm font-medium text-slate-800">
                                                                        {child.name}
                                                                    </span>
                                                                    <StatusDot active={child.is_active} />
                                                                </div>
                                                                <p className="text-xs text-slate-400">
                                                                    {child.products_count}{' '}
                                                                    {child.products_count === 1 ? 'producto' : 'productos'}
                                                                </p>
                                                            </div>
                                                            <div className="flex flex-shrink-0 items-center gap-0.5">
                                                                <ActionIconButton
                                                                    onClick={() => handleToggleStatus(child)}
                                                                    icon={child.is_active ? IconEye : IconEyeOff}
                                                                    label={child.is_active ? 'Desactivar' : 'Activar'}
                                                                    disabled={togglingId === child.id}
                                                                />
                                                                <ActionIconButton
                                                                    href={route('admin.categories.edit', child.id)}
                                                                    icon={IconPencil}
                                                                    label="Editar"
                                                                />
                                                                <ActionIconButton
                                                                    onClick={() => handleDeleteCategory(child)}
                                                                    icon={IconTrash}
                                                                    label="Eliminar"
                                                                    tone="danger"
                                                                />
                                                            </div>
                                                        </li>
                                                    ))}
                                                </ul>
                                            )}

                                            <Link
                                                href={`${route('admin.categories.create')}?parent_id=${category.id}`}
                                                className="mt-3 flex items-center justify-center gap-1.5 rounded-lg border border-dashed border-slate-300 py-2 text-xs font-medium text-slate-500 transition hover:border-navy/30 hover:text-navy"
                                            >
                                                <IconPlus className="h-3.5 w-3.5" />
                                                Subcategoría
                                            </Link>
                                        </div>
                                    )}
                                </div>
                            );
                        })}
                    </div>
                ) : (
                    <div className="rounded-xl border border-slate-200 bg-white py-16 text-center">
                        <IconInbox className="mx-auto h-8 w-8 text-slate-300" />
                        <h3 className="mt-3 text-sm font-medium text-slate-900">
                            {filters?.search ? 'No se encontraron resultados' : 'No hay categorías creadas'}
                        </h3>
                        <p className="mt-1 text-sm text-slate-500">
                            {filters?.search
                                ? 'Probá con otro término de búsqueda.'
                                : 'Comenzá creando tu primera categoría para organizar el catálogo.'}
                        </p>
                        {!filters?.search && (
                            <Link
                                href={route('admin.categories.create')}
                                className="mt-4 inline-flex items-center gap-1.5 rounded-lg bg-gold px-4 py-2 text-sm font-semibold text-navy transition hover:brightness-95"
                            >
                                <IconPlus className="h-4 w-4" />
                                Crear primera categoría
                            </Link>
                        )}
                    </div>
                )}
            </div>

            <DeleteConfirmationModal
                show={showDeleteModal}
                onClose={closeDeleteModal}
                onConfirm={canDeleteCategory(categoryToDelete) ? confirmDelete : closeDeleteModal}
                title={
                    categoryToDelete?.children_count > 0 || categoryToDelete?.products_count > 0
                        ? 'No se puede eliminar'
                        : '¿Eliminar categoría?'
                }
                message={
                    categoryToDelete?.children_count > 0 || categoryToDelete?.products_count > 0
                        ? 'Esta categoría no se puede eliminar:'
                        : 'Estás a punto de eliminar la siguiente categoría:'
                }
                itemName={categoryToDelete?.name}
                warningMessage={getDeleteWarningMessage(categoryToDelete)}
                confirmText={canDeleteCategory(categoryToDelete) ? 'Eliminar categoría' : 'Entendido'}
                processing={isDeleting}
            />
        </AdminLayout>
    );
}
