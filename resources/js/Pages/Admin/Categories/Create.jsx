import React from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import toast from 'react-hot-toast';
import AdminLayout from '@/Layouts/AdminLayout';
import CategoryForm from '@/Components/Admin/CategoryForm';

export default function Create({ mainCategories = [], selectedParent = null }) {
    const { data, setData, post, errors, processing } = useForm({
        name: '',
        description: '',
        parent_id: selectedParent?.id ? String(selectedParent.id) : '',
        sort_order: 0,
        is_active: true,
    });

    const parentName =
        mainCategories.find((c) => String(c.id) === String(data.parent_id))?.name ?? selectedParent?.name ?? null;

    const submit = (e) => {
        e.preventDefault();
        post(route('admin.categories.store'), {
            onSuccess: () => {
                toast.success(parentName ? 'Subcategoría creada exitosamente' : 'Categoría creada exitosamente');
            },
            onError: () => {
                toast.error('Revisá los datos del formulario');
            },
        });
    };

    return (
        <AdminLayout
            header={
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p className="text-xs font-semibold uppercase tracking-wider text-slate-400">
                            <Link href={route('admin.categories.index')} className="hover:text-slate-600">
                                Categorías
                            </Link>
                            {parentName && (
                                <>
                                    {' '}
                                    / <span className="normal-case text-slate-500">{parentName}</span>
                                </>
                            )}
                        </p>
                        <h1 className="mt-1 text-xl font-semibold text-slate-900 sm:text-2xl">
                            {parentName ? 'Nueva subcategoría' : 'Nueva categoría'}
                        </h1>
                    </div>
                    <Link
                        href={route('admin.categories.index')}
                        className="inline-flex items-center justify-center rounded-lg border border-slate-300 px-3.5 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
                    >
                        Cancelar
                    </Link>
                </div>
            }
        >
            <Head title={parentName ? 'Nueva subcategoría - Admin' : 'Nueva categoría - Admin'} />

            <div className="mx-auto max-w-2xl">
                <form onSubmit={submit} className="rounded-xl border border-slate-200 bg-white p-5 sm:p-6">
                    <CategoryForm data={data} setData={setData} errors={errors} mainCategories={mainCategories} />

                    <div className="mt-6 flex justify-end gap-3 border-t border-slate-100 pt-6">
                        <Link
                            href={route('admin.categories.index')}
                            className="inline-flex items-center rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
                        >
                            Cancelar
                        </Link>
                        <button
                            type="submit"
                            disabled={processing}
                            className="inline-flex items-center rounded-lg bg-gold px-4 py-2 text-sm font-semibold text-navy transition hover:brightness-95 disabled:opacity-50"
                        >
                            {processing ? 'Guardando...' : parentName ? 'Crear subcategoría' : 'Crear categoría'}
                        </button>
                    </div>
                </form>
            </div>
        </AdminLayout>
    );
}
