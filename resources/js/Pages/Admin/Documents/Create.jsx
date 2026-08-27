import React from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import toast from 'react-hot-toast';
import AdminLayout from '@/Layouts/AdminLayout';
import DocumentForm from '@/Components/Admin/DocumentForm';

export default function Create({ tipos = [] }) {
    const form = useForm({
        title: '',
        description: '',
        type: 'link',
        url: '',
        file: null,
        sort_order: 0,
        is_active: true,
    });
    const { data, setData, errors, processing } = form;

    // FormData: is_active viaja como '1'/'0' (la regla `boolean` no acepta
    // "true"/"false") y se descarta el campo del tipo que no aplica para no
    // disparar validaciones cruzadas.
    form.transform((d) => {
        const payload = { ...d, is_active: d.is_active ? '1' : '0' };
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
        form.post(route('admin.documents.store'), {
            forceFormData: true,
            onSuccess: () => toast.success('Documento creado exitosamente'),
            onError: () => toast.error('Revisá los datos del formulario'),
        });
    };

    return (
        <AdminLayout
            header={
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p className="text-xs font-semibold uppercase tracking-wider text-slate-400">
                            <Link href={route('admin.documents.index')} className="hover:text-slate-600">
                                Documentos
                            </Link>
                        </p>
                        <h1 className="mt-1 text-xl font-semibold text-slate-900 sm:text-2xl">Nuevo documento</h1>
                    </div>
                    <Link
                        href={route('admin.documents.index')}
                        className="inline-flex items-center justify-center rounded-lg border border-slate-300 px-3.5 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
                    >
                        Cancelar
                    </Link>
                </div>
            }
        >
            <Head title="Nuevo documento - Admin" />

            <div className="mx-auto max-w-2xl">
                <form onSubmit={submit} className="rounded-xl border border-slate-200 bg-white p-5 sm:p-6">
                    <DocumentForm data={data} setData={setData} errors={errors} tipos={tipos} />

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
                            {processing ? 'Guardando...' : 'Crear documento'}
                        </button>
                    </div>
                </form>
            </div>
        </AdminLayout>
    );
}
