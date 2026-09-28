import React, { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import toast from 'react-hot-toast';
import AdminLayout from '@/Layouts/AdminLayout';
import DeleteConfirmationModal from '@/Components/DeleteConfirmationModal';
import ActionIconButton from '@/Components/Admin/ActionIconButton';
import usePermissions from '@/hooks/usePermissions';
import {
    IconPlus,
    IconPencil,
    IconTrash,
    IconEye,
    IconEyeOff,
    IconInbox,
    IconFileText,
    IconExternalLink,
} from '@/Components/Admin/Icons';

/**
 * Listado de documentos (manuales / instructivos) para vendedores.
 *
 * El vendedor ve sólo los activos y sin acciones de gestión; el admin ve todos
 * (activos e inactivos) y puede crear / editar / activar / borrar. La fuente
 * real de verdad es el backend (Gate 'gestionar-documentos' en las rutas);
 * `canManage` viene del controller y `isAdmin` de usePermissions coinciden —
 * usamos isAdmin para ocultar la UI de gestión, tal como en Categorías/Add-ons.
 */
export default function Index({ documents = [], canManage = false }) {
    const { isAdmin } = usePermissions();
    const puedeGestionar = canManage && isAdmin;

    const [togglingId, setTogglingId] = useState(null);
    const [showDeleteModal, setShowDeleteModal] = useState(false);
    const [documentToDelete, setDocumentToDelete] = useState(null);
    const [isDeleting, setIsDeleting] = useState(false);

    const handleToggleStatus = (doc) => {
        setTogglingId(doc.id);
        router.patch(
            route('admin.documents.toggle-status', doc.id),
            {},
            {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => toast.success(doc.is_active ? 'Documento desactivado' : 'Documento activado'),
                onError: () => toast.error('Error al actualizar el estado'),
                onFinish: () => setTogglingId(null),
            }
        );
    };

    const handleDelete = (doc) => {
        setDocumentToDelete(doc);
        setShowDeleteModal(true);
    };

    const confirmDelete = () => {
        if (!documentToDelete) return;
        setIsDeleting(true);
        router.delete(route('admin.documents.destroy', documentToDelete.id), {
            preserveScroll: true,
            onSuccess: () => {
                toast.success('Documento eliminado exitosamente');
                setShowDeleteModal(false);
                setDocumentToDelete(null);
                setIsDeleting(false);
            },
            onError: () => {
                toast.error('Error al eliminar el documento');
                setIsDeleting(false);
            },
        });
    };

    const closeDeleteModal = () => {
        if (!isDeleting) {
            setShowDeleteModal(false);
            setDocumentToDelete(null);
        }
    };

    const documentHref = (doc) => (doc.type === 'link' ? doc.url : doc.file_url);

    return (
        <AdminLayout
            header={
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p className="text-xs font-semibold uppercase tracking-wider text-slate-400">
                            {documents.length} {documents.length === 1 ? 'documento' : 'documentos'}
                        </p>
                        <h1 className="mt-1 text-xl font-semibold text-slate-900 sm:text-2xl">Documentos</h1>
                    </div>
                    {puedeGestionar && (
                        <Link
                            href={route('admin.documents.create')}
                            className="inline-flex items-center gap-1.5 rounded-lg bg-gold px-3.5 py-2 text-sm font-semibold text-navy transition hover:brightness-95"
                        >
                            <IconPlus className="h-4 w-4" />
                            Nuevo documento
                        </Link>
                    )}
                </div>
            }
        >
            <Head title="Documentos - Admin" />

            <div className="space-y-6">
                {!puedeGestionar && (
                    <p className="text-sm text-slate-500">
                        Manuales e instructivos disponibles. Tocá cada uno para abrirlo.
                    </p>
                )}

                {documents.length > 0 ? (
                    <div className="overflow-hidden rounded-xl border border-slate-200 bg-white">
                        <div className="overflow-x-auto">
                            <table className="w-full text-left text-sm">
                                <thead className="border-b border-slate-200 bg-slate-50 text-xs font-semibold uppercase tracking-wider text-slate-500">
                                    <tr>
                                        <th className="px-4 py-3">Documento</th>
                                        <th className="px-4 py-3">Tipo</th>
                                        <th className="px-4 py-3">Orden</th>
                                        {puedeGestionar && <th className="px-4 py-3">Estado</th>}
                                        <th className="px-4 py-3 text-right">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100">
                                    {documents.map((doc) => {
                                        const href = documentHref(doc);
                                        return (
                                            <tr key={doc.id} className="transition hover:bg-slate-50">
                                                <td className="px-4 py-3">
                                                    <div className="flex items-center gap-2">
                                                        <span className="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-lg bg-navy/5 text-navy">
                                                            <IconFileText className="h-4 w-4" />
                                                        </span>
                                                        <div className="min-w-0">
                                                            <p className="font-medium text-slate-900">{doc.title}</p>
                                                            {doc.description && (
                                                                <p className="max-w-[360px] truncate text-xs text-slate-400">
                                                                    {doc.description}
                                                                </p>
                                                            )}
                                                        </div>
                                                    </div>
                                                </td>
                                                <td className="px-4 py-3">
                                                    <span className="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-semibold text-slate-600">
                                                        {doc.type_label}
                                                    </span>
                                                </td>
                                                <td className="px-4 py-3 text-slate-600">{doc.sort_order}</td>
                                                {puedeGestionar && (
                                                    <td className="px-4 py-3">
                                                        <span
                                                            className={`inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-semibold ${
                                                                doc.is_active
                                                                    ? 'bg-emerald-50 text-emerald-700'
                                                                    : 'bg-slate-100 text-slate-500'
                                                            }`}
                                                        >
                                                            {doc.is_active ? 'Activo' : 'Inactivo'}
                                                        </span>
                                                    </td>
                                                )}
                                                <td className="px-4 py-3">
                                                    <div className="flex items-center justify-end gap-0.5">
                                                        {href ? (
                                                            <a
                                                                href={href}
                                                                target="_blank"
                                                                rel="noopener noreferrer"
                                                                title="Abrir documento"
                                                                className="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-md text-slate-400 transition hover:bg-slate-100 hover:text-slate-700"
                                                            >
                                                                <IconExternalLink className="h-4 w-4" />
                                                            </a>
                                                        ) : (
                                                            <span
                                                                title="Sin archivo o enlace cargado"
                                                                className="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-md text-slate-200"
                                                            >
                                                                <IconExternalLink className="h-4 w-4" />
                                                            </span>
                                                        )}
                                                        {puedeGestionar && (
                                                            <>
                                                                <ActionIconButton
                                                                    onClick={() => handleToggleStatus(doc)}
                                                                    icon={doc.is_active ? IconEye : IconEyeOff}
                                                                    label={doc.is_active ? 'Desactivar' : 'Activar'}
                                                                    tone={doc.is_active ? 'active' : 'default'}
                                                                    disabled={togglingId === doc.id}
                                                                />
                                                                <ActionIconButton
                                                                    href={route('admin.documents.edit', doc.id)}
                                                                    icon={IconPencil}
                                                                    label="Editar"
                                                                />
                                                                <ActionIconButton
                                                                    onClick={() => handleDelete(doc)}
                                                                    icon={IconTrash}
                                                                    label="Eliminar"
                                                                    tone="danger"
                                                                />
                                                            </>
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
                            {puedeGestionar ? 'No hay documentos creados' : 'No hay documentos disponibles'}
                        </h3>
                        <p className="mt-1 text-sm text-slate-500">
                            {puedeGestionar
                                ? 'Cargá el primer manual o instructivo para los vendedores.'
                                : 'Todavía no se publicaron manuales ni instructivos.'}
                        </p>
                        {puedeGestionar && (
                            <Link
                                href={route('admin.documents.create')}
                                className="mt-4 inline-flex items-center gap-1.5 rounded-lg bg-gold px-4 py-2 text-sm font-semibold text-navy transition hover:brightness-95"
                            >
                                <IconPlus className="h-4 w-4" />
                                Crear primer documento
                            </Link>
                        )}
                    </div>
                )}
            </div>

            <DeleteConfirmationModal
                show={showDeleteModal}
                onClose={closeDeleteModal}
                onConfirm={confirmDelete}
                title="¿Eliminar documento?"
                message="Estás a punto de eliminar el documento:"
                itemName={documentToDelete?.title}
                warningMessage="Esta acción no se puede deshacer. Si es un PDF, el archivo también se borra. Si sólo querés dejar de mostrarlo, desactivalo."
                confirmText="Eliminar documento"
                processing={isDeleting}
            />
        </AdminLayout>
    );
}
