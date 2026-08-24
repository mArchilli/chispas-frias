import React from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import toast from 'react-hot-toast';
import AdminLayout from '@/Layouts/AdminLayout';

const inputClasses =
    'block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900 placeholder:text-slate-400 transition focus:border-navy focus:outline-none focus:ring-2 focus:ring-navy/10';

export default function Create() {
    const { data, setData, post, errors, processing } = useForm({
        name: '',
        email: '',
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('admin.sellers.store'), {
            onError: () => toast.error('Revisá los datos del formulario'),
        });
    };

    return (
        <AdminLayout
            header={
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p className="text-xs font-semibold uppercase tracking-wider text-slate-400">
                            <Link href={route('admin.sellers.index')} className="hover:text-slate-600">
                                Vendedores
                            </Link>
                        </p>
                        <h1 className="mt-1 text-xl font-semibold text-slate-900 sm:text-2xl">Nuevo vendedor</h1>
                    </div>
                    <Link
                        href={route('admin.sellers.index')}
                        className="inline-flex items-center justify-center rounded-lg border border-slate-300 px-3.5 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
                    >
                        Cancelar
                    </Link>
                </div>
            }
        >
            <Head title="Nuevo vendedor - Admin" />

            <div className="mx-auto max-w-2xl">
                <form onSubmit={submit} className="rounded-xl border border-slate-200 bg-white p-5 sm:p-6">
                    <div className="space-y-6">
                        <div>
                            <label htmlFor="name" className="mb-1.5 block text-sm font-medium text-slate-700">
                                Nombre <span className="text-rose-500">*</span>
                            </label>
                            <input
                                id="name"
                                type="text"
                                value={data.name}
                                onChange={(e) => setData('name', e.target.value)}
                                disabled={processing}
                                placeholder="Nombre y apellido"
                                className={inputClasses}
                                maxLength={255}
                            />
                            {errors.name && <p className="mt-1.5 text-xs font-medium text-rose-600">{errors.name}</p>}
                        </div>

                        <div>
                            <label htmlFor="email" className="mb-1.5 block text-sm font-medium text-slate-700">
                                Email <span className="text-rose-500">*</span>
                            </label>
                            <input
                                id="email"
                                type="email"
                                value={data.email}
                                onChange={(e) => setData('email', e.target.value)}
                                disabled={processing}
                                placeholder="vendedor@ejemplo.com"
                                className={inputClasses}
                                maxLength={255}
                            />
                            {errors.email && <p className="mt-1.5 text-xs font-medium text-rose-600">{errors.email}</p>}
                        </div>

                        <div className="rounded-lg bg-navy/5 px-4 py-3.5 text-xs text-navy/70">
                            Se va a generar una contraseña temporal automáticamente. Vas a poder verla una única vez
                            en la pantalla siguiente para pasársela al vendedor.
                        </div>
                    </div>

                    <div className="mt-6 flex justify-end gap-3 border-t border-slate-100 pt-6">
                        <Link
                            href={route('admin.sellers.index')}
                            className="inline-flex items-center rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
                        >
                            Cancelar
                        </Link>
                        <button
                            type="submit"
                            disabled={processing}
                            className="inline-flex items-center rounded-lg bg-gold px-4 py-2 text-sm font-semibold text-navy transition hover:brightness-95 disabled:opacity-50"
                        >
                            {processing ? 'Creando...' : 'Crear vendedor'}
                        </button>
                    </div>
                </form>
            </div>
        </AdminLayout>
    );
}
