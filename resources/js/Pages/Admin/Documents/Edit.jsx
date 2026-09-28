import React from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import toast from 'react-hot-toast';
import AdminLayout from '@/Layouts/AdminLayout';
import DocumentForm from '@/Components/Admin/DocumentForm';

export default function Edit({ document: documento, tipos = [] }) {
    const form = useForm({
        title: documento.title || '',
        description: documento.description || '',
        type: documento.type || 'link',
        url: documento.url || '',
        file: null,
        sort_order: documento.sort_order ?? 0,
        is_active: !!documento.is_active,
    });
    const { data, setData, errors, processing } = form;

    // Igual que en Create: FormData con is_active '1'/'0' y sin el campo del
    // tipo que no aplica. `_method: 'PUT'` porque un multipart no viaja por PUT
    // nativo (mismo patrón que Products/Edit).
    form.transform((d) => {
        const payload = { ...d, _method: 'PUT', is_active: d.is_active ? '1' : '0' };
        if (d.type === 'link') {
            delete payload.file;
        } else {
            delete payload.url;
        }
        if (!payload.file) {
            delete payload.file;
        }
        return payload;
    });

    const submit = (e) => {
        e.preventDefault();
        form.post(route('admin.documents.update', documento.id), {
            forceFormData: true,
            onSuccess: () => toast.success('Documento actualizado exitosamente'),
            onError: () => toast.error('Revisá los datos del formulario'),
        });
    };

    return (
        <AdminLayout
            header={
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div className="min-w-0">
                        <p className="text-xs font-semibold uppercase tracking-wider text-slate-400">
                            <Link href={route('admin.documents.index')} className="hover:text-slate-600">
                                Documentos
                            </Link>
                        </p>
                        <h1 className="mt-1 truncate text-xl font-semibold text-slate-900 sm:text-2xl">
                            {documento.title}
                        </h1>
                    </div>
                    <Link
                        href={route('admin.documents.index')}
                        className="inline-flex items-center justify-center rounded-lg border border-slate-300 px-3.5 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
                    >
                        Volver
                    </Link>
                </div>
            }
        >
            <Head title={`Editar ${documento.title} - Admin`} />

            <div className="mx-auto max-w-2xl">
                <form onSubmit={submit} className="rounded-xl border border-slate-200 bg-white p-5 sm:p-6">
                    <DocumentForm
                        data={data}
                        setData={setData}
                        errors={errors}
                        tipos={tipos}
                        currentFileUrl={documento.file_url}
                    />

                    <div className="mt-6 flex justify-end gap-3 border-t border-slate-100 pt-6">
                        <Link
                            href={route('admin.documents.index')}
                            className="inline-flex items-center rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
                        >
                            Cancelar
                        </Link>
                        <button
                            type="submit"
                            disabled={processing}
                            className="inline-flex items-center rounded-lg bg-gold px-4 py-2 text-sm font-semibold text-navy transition hover:brightness-95 disabled:opacity-50"
                        >
                            {processing ? 'Guardando...' : 'Guardar cambios'}
                        </button>
                    </div>
                </form>
            </div>
        </AdminLayout>
    );
}
