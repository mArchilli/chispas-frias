import React, { useEffect, useState } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import toast from 'react-hot-toast';
import AdminLayout from '@/Layouts/AdminLayout';
import Modal from '@/Components/Modal';
import ActionIconButton from '@/Components/Admin/ActionIconButton';
import {
    IconPlus,
    IconPencil,
    IconEye,
    IconEyeOff,
    IconInbox,
    IconUsers,
    IconCheck,
} from '@/Components/Admin/Icons';

function formatDate(date) {
    if (!date) return null;
    return new Date(date).toLocaleDateString('es-AR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
    });
}

export default function Index({ sellers = [] }) {
    const { flash } = usePage().props;
    const [togglingId, setTogglingId] = useState(null);
    const [temporaryPassword, setTemporaryPassword] = useState(null);
    const [copied, setCopied] = useState(false);

    // La contraseña temporal sólo viaja en el flash de la respuesta de creación
    // (ver SellerController::store): se muestra una única vez acá y desaparece
    // en cuanto se recarga la página, porque el flash de sesión no sobrevive
    // a un segundo request.
    useEffect(() => {
        if (flash?.temporaryPassword) {
            setTemporaryPassword(flash.temporaryPassword);
        }
    }, [flash?.temporaryPassword]);

    const handleToggleStatus = (seller) => {
        setTogglingId(seller.id);
        router.patch(
            route('admin.sellers.toggle-status', seller.id),
            {},
            {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => toast.success(seller.is_active ? 'Vendedor desactivado' : 'Vendedor activado'),
                onError: () => toast.error('Error al actualizar el estado'),
                onFinish: () => setTogglingId(null),
            }
        );
    };

    const copyPassword = async () => {
        try {
            await navigator.clipboard.writeText(temporaryPassword);
            setCopied(true);
            setTimeout(() => setCopied(false), 2000);
        } catch {
            toast.error('No se pudo copiar. Copiala manualmente.');
        }
    };

    return (
        <AdminLayout
            header={
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p className="text-xs font-semibold uppercase tracking-wider text-slate-400">
                            {sellers.length} {sellers.length === 1 ? 'vendedor' : 'vendedores'}
                        </p>
                        <h1 className="mt-1 text-xl font-semibold text-slate-900 sm:text-2xl">Vendedores</h1>
                    </div>
                    <Link
                        href={route('admin.sellers.create')}
                        className="inline-flex items-center gap-1.5 rounded-lg bg-gold px-3.5 py-2 text-sm font-semibold text-navy transition hover:brightness-95"
                    >
                        <IconPlus className="h-4 w-4" />
                        Nuevo vendedor
                    </Link>
                </div>
            }
        >
            <Head title="Vendedores - Admin" />

            <div className="space-y-6">
                {sellers.length > 0 ? (
                    <div className="overflow-hidden rounded-xl border border-slate-200 bg-white">
                        <div className="overflow-x-auto">
                            <table className="w-full text-left text-sm">
                                <thead className="border-b border-slate-200 bg-slate-50 text-xs font-semibold uppercase tracking-wider text-slate-500">
                                    <tr>
                                        <th className="px-4 py-3">Nombre</th>
                                        <th className="px-4 py-3">Email</th>
                                        <th className="px-4 py-3">Estado</th>
                                        <th className="px-4 py-3">Fecha de alta</th>
                                        <th className="px-4 py-3 text-right">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100">
                                    {sellers.map((seller) => (
                                        <tr key={seller.id} className="transition hover:bg-slate-50">
                                            <td className="px-4 py-3">
                                                <div className="flex items-center gap-2">
                                                    <span className="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-lg bg-navy/5 text-navy">
                                                        <IconUsers className="h-4 w-4" />
                                                    </span>
                                                    <p className="font-medium text-slate-900">{seller.name}</p>
                                                </div>
                                            </td>
                                            <td className="px-4 py-3 text-slate-600">{seller.email}</td>
                                            <td className="px-4 py-3">
                                                <span
                                                    className={`inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-semibold ${
                                                        seller.is_active
                                                            ? 'bg-emerald-50 text-emerald-700'
                                                            : 'bg-slate-100 text-slate-500'
                                                    }`}
                                                >
                                                    {seller.is_active ? 'Activo' : 'Inactivo'}
                                                </span>
                                            </td>
                                            <td className="px-4 py-3 text-xs text-slate-500">
                                                {formatDate(seller.created_at)}
                                            </td>
                                            <td className="px-4 py-3">
                                                <div className="flex items-center justify-end gap-0.5">
                                                    <ActionIconButton
                                                        onClick={() => handleToggleStatus(seller)}
                                                        icon={seller.is_active ? IconEyeOff : IconEye}
                                                        label={seller.is_active ? 'Desactivar' : 'Activar'}
                                                        tone={seller.is_active ? 'default' : 'active'}
                                                        disabled={togglingId === seller.id}
                                                    />
                                                    <ActionIconButton
                                                        href={route('admin.sellers.edit', seller.id)}
                                                        icon={IconPencil}
                                                        label="Editar"
                                                    />
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
                        <h3 className="mt-3 text-sm font-medium text-slate-900">No hay vendedores creados</h3>
                        <p className="mt-1 text-sm text-slate-500">Comenzá creando tu primera cuenta de vendedor.</p>
                        <Link
                            href={route('admin.sellers.create')}
                            className="mt-4 inline-flex items-center gap-1.5 rounded-lg bg-gold px-4 py-2 text-sm font-semibold text-navy transition hover:brightness-95"
                        >
                            <IconPlus className="h-4 w-4" />
                            Crear primer vendedor
                        </Link>
                    </div>
                )}
            </div>

            <Modal show={!!temporaryPassword} onClose={() => setTemporaryPassword(null)} maxWidth="md">
                <div className="p-6">
                    <h2 className="text-lg font-semibold text-slate-900">Vendedor creado</h2>
                    <p className="mt-1 text-sm text-slate-500">
                        Copiá esta contraseña temporal ahora y pasásela al vendedor: no se va a volver a mostrar.
                    </p>
                    <div className="mt-4 flex items-center gap-2 rounded-lg border border-slate-200 bg-slate-50 px-4 py-3">
                        <code className="flex-1 select-all font-mono text-base font-semibold text-slate-900">
                            {temporaryPassword}
                        </code>
                        <button
                            type="button"
                            onClick={copyPassword}
                            className="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 transition hover:bg-slate-100"
                        >
                            {copied ? <IconCheck className="h-3.5 w-3.5 text-emerald-600" /> : null}
                            {copied ? 'Copiado' : 'Copiar'}
                        </button>
                    </div>
                    <div className="mt-6 flex justify-end">
                        <button
                            type="button"
                            onClick={() => setTemporaryPassword(null)}
                            className="inline-flex items-center rounded-lg bg-gold px-4 py-2 text-sm font-semibold text-navy transition hover:brightness-95"
                        >
                            Ya la copié
                        </button>
                    </div>
                </div>
            </Modal>
        </AdminLayout>
    );
}
