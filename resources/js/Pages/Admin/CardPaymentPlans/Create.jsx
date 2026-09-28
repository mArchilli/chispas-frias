import React from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import toast from 'react-hot-toast';
import AdminLayout from '@/Layouts/AdminLayout';
import { buildPlanPreviewText } from '@/utils/cardPaymentPlans';

const inputClasses =
    'block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900 placeholder:text-slate-400 transition focus:border-navy focus:outline-none focus:ring-2 focus:ring-navy/10';

export default function Create() {
    const { data, setData, post, errors, processing } = useForm({
        name: '',
        installments: 1,
        surcharge_percentage: '',
        sort_order: 0,
        is_active: true,
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('admin.card-payment-plans.store'), {
            onSuccess: () => toast.success('Plan de cuotas creado exitosamente'),
            onError: () => toast.error('Revisá los datos del formulario'),
        });
    };

    return (
        <AdminLayout
            header={
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p className="text-xs font-semibold uppercase tracking-wider text-slate-400">
                            <Link href={route('admin.card-payment-plans.index')} className="hover:text-slate-600">
                                Planes de cuotas
                            </Link>
                        </p>
                        <h1 className="mt-1 text-xl font-semibold text-slate-900 sm:text-2xl">Nuevo plan</h1>
                    </div>
                    <Link
                        href={route('admin.card-payment-plans.index')}
                        className="inline-flex items-center justify-center rounded-lg border border-slate-300 px-3.5 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
                    >
                        Cancelar
                    </Link>
                </div>
            }
        >
            <Head title="Nuevo plan de cuotas - Admin" />

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
                                placeholder="Ej. 3 cuotas"
                                className={inputClasses}
                                maxLength={60}
                            />
                            {errors.name && <p className="mt-1.5 text-xs font-medium text-rose-600">{errors.name}</p>}
                        </div>

                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <label
                                    htmlFor="installments"
                                    className="mb-1.5 block text-sm font-medium text-slate-700"
                                >
                                    Cantidad de cuotas <span className="text-rose-500">*</span>
                                </label>
                                <input
                                    id="installments"
                                    type="number"
                                    step="1"
                                    min="1"
                                    value={data.installments}
                                    onChange={(e) => setData('installments', e.target.value)}
                                    disabled={processing}
                                    className={inputClasses}
                                />
                                {errors.installments && (
                                    <p className="mt-1.5 text-xs font-medium text-rose-600">{errors.installments}</p>
                                )}
                            </div>
                            <div>
                                <label
                                    htmlFor="surcharge_percentage"
                                    className="mb-1.5 block text-sm font-medium text-slate-700"
                                >
                                    Recargo <span className="text-rose-500">*</span>
                                </label>
                                <div className="relative">
                                    <input
                                        id="surcharge_percentage"
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        max="999.99"
                                        value={data.surcharge_percentage}
                                        onChange={(e) => setData('surcharge_percentage', e.target.value)}
                                        disabled={processing}
                                        placeholder="20"
                                        className={`${inputClasses} pr-8`}
                                    />
                                    <span className="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-sm text-slate-400">
                                        %
                                    </span>
                                </div>
                                <p className="mt-1.5 text-xs text-slate-500">
                                    Cargo único sobre el total del pedido, no interés compuesto.
                                </p>
                                {errors.surcharge_percentage && (
                                    <p className="mt-1.5 text-xs font-medium text-rose-600">
                                        {errors.surcharge_percentage}
                                    </p>
                                )}
                            </div>
                        </div>

                        <div>
                            <label htmlFor="sort_order" className="mb-1.5 block text-sm font-medium text-slate-700">
                                Orden
                            </label>
                            <input
                                id="sort_order"
                                type="number"
                                step="1"
                                min="0"
                                value={data.sort_order}
                                onChange={(e) => setData('sort_order', e.target.value)}
                                disabled={processing}
                                className={`${inputClasses} sm:max-w-[200px]`}
                            />
                            <p className="mt-1.5 text-xs text-slate-500">
                                Define el orden en que se listan los planes (menor primero).
                            </p>
                            {errors.sort_order && (
                                <p className="mt-1.5 text-xs font-medium text-rose-600">{errors.sort_order}</p>
                            )}
                        </div>

                        <div className="rounded-lg bg-navy/5 px-4 py-3.5">
                            <p className="text-xs font-semibold uppercase tracking-wider text-navy/60">Vista previa</p>
                            <p className="mt-1 text-sm font-medium text-navy">{buildPlanPreviewText(data)}</p>
                        </div>

                        <div className="flex items-center justify-between rounded-lg bg-slate-50 px-4 py-3.5">
                            <div>
                                <p className="text-sm font-medium text-slate-900">Plan activo</p>
                                <p className="text-xs text-slate-500">
                                    {data.is_active
                                        ? 'Se ofrece en el simulador de recargo.'
                                        : 'Queda guardado pero no se ofrece.'}
                                </p>
                            </div>
                            <label className="relative inline-flex cursor-pointer items-center">
                                <input
                                    type="checkbox"
                                    checked={data.is_active}
                                    onChange={(e) => setData('is_active', e.target.checked)}
                                    disabled={processing}
                                    className="peer sr-only"
                                />
                                <div className="peer h-6 w-11 rounded-full bg-slate-300 transition-colors after:absolute after:left-[2px] after:top-[2px] after:h-5 after:w-5 after:rounded-full after:border after:border-slate-300 after:bg-white after:transition-all after:content-[''] peer-checked:bg-gold peer-checked:after:translate-x-full peer-checked:after:border-white peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-gold/20" />
                            </label>
                        </div>
                    </div>

                    <div className="mt-6 flex justify-end gap-3 border-t border-slate-100 pt-6">
                        <Link
                            href={route('admin.card-payment-plans.index')}
                            className="inline-flex items-center rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
                        >
                            Cancelar
                        </Link>
                        <button
                            type="submit"
                            disabled={processing}
                            className="inline-flex items-center rounded-lg bg-gold px-4 py-2 text-sm font-semibold text-navy transition hover:brightness-95 disabled:opacity-50"
                        >
                            {processing ? 'Guardando...' : 'Crear plan'}
                        </button>
                    </div>
                </form>
            </div>
        </AdminLayout>
    );
}
