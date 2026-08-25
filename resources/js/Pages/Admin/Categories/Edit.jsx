import React from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import toast from 'react-hot-toast';
import AdminLayout from '@/Layouts/AdminLayout';
import CategoryForm from '@/Components/Admin/CategoryForm';
import usePermissions from '@/hooks/usePermissions';
import { IconArrowRight } from '@/Components/Admin/Icons';

export default function Edit({ category, mainCategories = [] }) {
    const { isAdmin } = usePermissions();
    const catalogRoute = isAdmin ? 'admin.products.index' : 'admin.prices.index';
    const { data, setData, patch, errors, processing } = useForm({
        name: category?.name || '',
        description: category?.description || '',
        parent_id: category?.parent_id ? String(category.parent_id) : '',
        sort_order: category?.sort_order || 0,
        is_active: category?.is_active || false,
    });

    const submit = (e) => {
        e.preventDefault();
        patch(route('admin.categories.update', category.id), {
            onSuccess: () => {
                toast.success('Categoría actualizada exitosamente');
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
                    <div className="min-w-0">
                        <p className="text-xs font-semibold uppercase tracking-wider text-slate-400">
                            <Link href={route('admin.categories.index')} className="hover:text-slate-600">
                                Categorías
                            </Link>
                            {category.parent && (
                                <>
                                    {' '}
                                    / <span className="normal-case text-slate-500">{category.parent.name}</span>
                                </>
                            )}
                        </p>
                        <h1 className="mt-1 truncate text-xl font-semibold text-slate-900 sm:text-2xl">
                            {category.name}
                        </h1>
                    </div>
                    <Link
                        href={route('admin.categories.index')}
                        className="inline-flex items-center justify-center rounded-lg border border-slate-300 px-3.5 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
                    >
                        Volver
                    </Link>
                </div>
            }
        >
            <Head title={`Editar ${category.name} - Admin`} />

            <div className="mx-auto max-w-2xl space-y-4">
                {category.products_count > 0 && (
                    <Link
                        href={`${route(catalogRoute)}?category=${category.id}`}
                        className="group flex items-center justify-between rounded-xl border border-slate-200 bg-white px-4 py-3.5 text-sm transition hover:border-slate-300 hover:shadow-sm"
                    >
                        <span className="text-slate-600">
                            <span className="font-semibold text-slate-900">{category.products_count}</span>{' '}
                            {category.products_count === 1 ? 'producto' : 'productos'} en esta categoría
                        </span>
                        <IconArrowRight className="h-4 w-4 flex-shrink-0 text-slate-300 transition group-hover:translate-x-0.5 group-hover:text-slate-500" />
                    </Link>
                )}

                <form onSubmit={submit} className="rounded-xl border border-slate-200 bg-white p-5 sm:p-6">
                    <CategoryForm
                        data={data}
                        setData={setData}
                        errors={errors}
                        mainCategories={mainCategories}
                        slug={category.slug}
                    />

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
                            {processing ? 'Guardando...' : 'Guardar cambios'}
                        </button>
                    </div>
                </form>
            </div>
        </AdminLayout>
    );
}
