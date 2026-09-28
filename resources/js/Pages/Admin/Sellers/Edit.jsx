import React from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import toast from 'react-hot-toast';
import AdminLayout from '@/Layouts/AdminLayout';

const inputClasses =
    'block w-full rounded-lg border border-gray-200 px-3 py-2 text-sm text-graphite placeholder:text-gray-500 transition focus:border-ice-500 focus:outline-none focus:ring-2 focus:ring-ice-100';

export default function Edit({ seller }) {
    const { data, setData, put, errors, processing } = useForm({
        name: seller.name || '',
        email: seller.email || '',
    });

    const submit = (e) => {
        e.preventDefault();
        put(route('admin.sellers.update', seller.id), {
            onSuccess: () => toast.success('Vendedor actualizado exitosamente'),
            onError: () => toast.error('Revisá los datos del formulario'),
        });
    };

    return (
        <AdminLayout
            header={
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div className="min-w-0">
                        <p className="text-xs font-semibold uppercase tracking-wider text-gray-500">
                            <Link href={route('admin.sellers.index')} className="hover:text-graphite/75">
                                Vendedores
                            </Link>
                        </p>
                        <h1 className="mt-1 truncate text-xl font-semibold text-graphite sm:text-2xl">
                            {seller.name}
                        </h1>
                    </div>
                    <Link
                        href={route('admin.sellers.index')}
                        className="inline-flex items-center justify-center rounded-lg border border-gray-200 px-3.5 py-2 text-sm font-medium text-graphite/85 transition hover:bg-background"
                    >
                        Volver
                    </Link>
                </div>
            }
        >
            <Head title={`Editar ${seller.name} - Admin`} />

            <div className="mx-auto max-w-2xl">
                <form onSubmit={submit} className="rounded-xl border border-gray-200 bg-surface p-5 sm:p-6">
                    <div className="space-y-6">
                        <div>
                            <label htmlFor="name" className="mb-1.5 block text-sm font-medium text-graphite/85">
                                Nombre <span className="text-navy-700">*</span>
                            </label>
                            <input
                                id="name"
                                type="text"
                                value={data.name}
                                onChange={(e) => setData('name', e.target.value)}
                                disabled={processing}
                                className={inputClasses}
                                maxLength={255}
                            />
                            {errors.name && <p className="mt-1.5 text-xs font-medium text-navy-700">{errors.name}</p>}
                        </div>

                        <div>
                            <label htmlFor="email" className="mb-1.5 block text-sm font-medium text-graphite/85">
                                Email <span className="text-navy-700">*</span>
                            </label>
                            <input
                                id="email"
                                type="email"
                                value={data.email}
                                onChange={(e) => setData('email', e.target.value)}
                                disabled={processing}
                                className={inputClasses}
                                maxLength={255}
                            />
                            {errors.email && <p className="mt-1.5 text-xs font-medium text-navy-700">{errors.email}</p>}
                        </div>
                    </div>

                    <div className="mt-6 flex justify-end gap-3 border-t border-gray-200 pt-6">
                        <Link
                            href={route('admin.sellers.index')}
                            className="inline-flex items-center rounded-lg border border-gray-200 px-4 py-2 text-sm font-medium text-graphite/85 transition hover:bg-background"
                        >
                            Cancelar
                        </Link>
                        <button
                            type="submit"
                            disabled={processing}
                            className="inline-flex items-center rounded-lg bg-storefront px-4 py-2 text-sm font-semibold text-white transition hover:brightness-90 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-storefront disabled:bg-gray-200 disabled:text-gray-500"
                        >
                            {processing ? 'Guardando...' : 'Guardar cambios'}
                        </button>
                    </div>
                </form>
            </div>
        </AdminLayout>
    );
}
