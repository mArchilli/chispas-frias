import React from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import toast from 'react-hot-toast';
import AdminLayout from '@/Layouts/AdminLayout';
import { buildPreviewText } from '@/utils/discountCodes';

const inputClasses =
    'block w-full rounded-lg border border-gray-200 px-3 py-2 text-sm text-graphite placeholder:text-gray-500 transition focus:border-ice-500 focus:outline-none focus:ring-2 focus:ring-ice-100';

export default function Create() {
    const { data, setData, post, errors, processing } = useForm({
        code: '',
        description: '',
        percentage: '',
        min_purchase_amount: '',
        usage_limit: '',
        start_date: '',
        end_date: '',
        is_active: true,
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('admin.discount-codes.store'), {
            onSuccess: () => toast.success('Código de descuento creado exitosamente'),
            onError: () => toast.error('Revisá los datos del formulario'),
        });
    };

    return (
        <AdminLayout
            header={
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p className="text-xs font-semibold uppercase tracking-wider text-gray-500">
                            <Link href={route('admin.discount-codes.index')} className="hover:text-graphite/75">
                                Códigos de descuento
                            </Link>
                        </p>
                        <h1 className="mt-1 text-xl font-semibold text-graphite sm:text-2xl">Nuevo código</h1>
                    </div>
                    <Link
                        href={route('admin.discount-codes.index')}
                        className="inline-flex items-center justify-center rounded-lg border border-gray-200 px-3.5 py-2 text-sm font-medium text-graphite/85 transition hover:bg-background"
                    >
                        Cancelar
                    </Link>
                </div>
            }
        >
            <Head title="Nuevo código de descuento - Admin" />

            <div className="mx-auto max-w-2xl">
                <form onSubmit={submit} className="rounded-xl border border-gray-200 bg-surface p-5 sm:p-6">
                    <div className="space-y-6">
                        <div>
                            <label htmlFor="code" className="mb-1.5 block text-sm font-medium text-graphite/85">
                                Código <span className="text-navy-700">*</span>
                            </label>
                            <input
                                id="code"
                                type="text"
                                value={data.code}
                                onChange={(e) => setData('code', e.target.value.toUpperCase())}
                                disabled={processing}
                                placeholder="VERANO2026"
                                className={`${inputClasses} font-mono uppercase`}
                                maxLength={50}
                            />
                            <p className="mt-1.5 text-xs text-gray-500">
                                No distingue mayúsculas/minúsculas: se guarda siempre en mayúsculas.
                            </p>
                            {errors.code && <p className="mt-1.5 text-xs font-medium text-navy-700">{errors.code}</p>}
                        </div>

                        <div>
                            <label htmlFor="description" className="mb-1.5 block text-sm font-medium text-graphite/85">
                                Descripción interna
                            </label>
                            <textarea
                                id="description"
                                rows={2}
                                value={data.description}
                                onChange={(e) => setData('description', e.target.value)}
                                disabled={processing}
                                placeholder="Nota interna para el admin (no se muestra al cliente)"
                                className={inputClasses}
                            />
                            {errors.description && (
                                <p className="mt-1.5 text-xs font-medium text-navy-700">{errors.description}</p>
                            )}
                        </div>

                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <label
                                    htmlFor="percentage"
                                    className="mb-1.5 block text-sm font-medium text-graphite/85"
                                >
                                    Porcentaje de descuento <span className="text-navy-700">*</span>
                                </label>
                                <div className="relative">
                                    <input
                                        id="percentage"
                                        type="number"
                                        step="0.01"
                                        min="0.01"
                                        max="100"
                                        value={data.percentage}
                                        onChange={(e) => setData('percentage', e.target.value)}
                                        disabled={processing}
                                        placeholder="10"
                                        className={`${inputClasses} pr-8`}
                                    />
                                    <span className="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-sm text-gray-500">
                                        %
                                    </span>
                                </div>
                                {errors.percentage && (
                                    <p className="mt-1.5 text-xs font-medium text-navy-700">{errors.percentage}</p>
                                )}
                            </div>
                            <div>
                                <label
                                    htmlFor="min_purchase_amount"
                                    className="mb-1.5 block text-sm font-medium text-graphite/85"
                                >
                                    Compra mínima
                                </label>
                                <div className="relative">
                                    <span className="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-sm text-gray-500">
                                        $
                                    </span>
                                    <input
                                        id="min_purchase_amount"
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        value={data.min_purchase_amount}
                                        onChange={(e) => setData('min_purchase_amount', e.target.value)}
                                        disabled={processing}
                                        placeholder="Sin mínimo"
                                        className={`${inputClasses} pl-7`}
                                    />
                                </div>
                                {errors.min_purchase_amount && (
                                    <p className="mt-1.5 text-xs font-medium text-navy-700">
                                        {errors.min_purchase_amount}
                                    </p>
                                )}
                            </div>
                        </div>

                        <div>
                            <label htmlFor="usage_limit" className="mb-1.5 block text-sm font-medium text-graphite/85">
                                Límite de usos
                            </label>
                            <input
                                id="usage_limit"
                                type="number"
                                step="1"
                                min="1"
                                value={data.usage_limit}
                                onChange={(e) => setData('usage_limit', e.target.value)}
                                disabled={processing}
                                placeholder="Ilimitado"
                                className={`${inputClasses} sm:max-w-[200px]`}
                            />
                            {errors.usage_limit && (
                                <p className="mt-1.5 text-xs font-medium text-navy-700">{errors.usage_limit}</p>
                            )}
                        </div>

                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <label
                                    htmlFor="start_date"
                                    className="mb-1.5 block text-sm font-medium text-graphite/85"
                                >
                                    Fecha de inicio
                                </label>
                                <input
                                    id="start_date"
                                    type="datetime-local"
                                    value={data.start_date}
                                    onChange={(e) => setData('start_date', e.target.value)}
                                    disabled={processing}
                                    className={inputClasses}
                                />
                                {errors.start_date && (
                                    <p className="mt-1.5 text-xs font-medium text-navy-700">{errors.start_date}</p>
                                )}
                            </div>
                            <div>
                                <label htmlFor="end_date" className="mb-1.5 block text-sm font-medium text-graphite/85">
                                    Fecha de fin
                                </label>
                                <input
                                    id="end_date"
                                    type="datetime-local"
                                    value={data.end_date}
                                    onChange={(e) => setData('end_date', e.target.value)}
                                    disabled={processing}
                                    className={inputClasses}
                                />
                                {errors.end_date && (
                                    <p className="mt-1.5 text-xs font-medium text-navy-700">{errors.end_date}</p>
                                )}
                            </div>
                        </div>
                        <p className="-mt-3 text-xs text-gray-500">
                            Opcional. Sin fechas, el código queda disponible hasta que lo desactives.
                        </p>

                        <div className="rounded-lg bg-navy-900/5 px-4 py-3.5">
                            <p className="text-xs font-semibold uppercase tracking-wider text-navy-900/60">Vista previa</p>
                            <p className="mt-1 text-sm font-medium text-navy-900">{buildPreviewText(data)}</p>
                        </div>

                        <div className="flex items-center justify-between rounded-lg bg-background px-4 py-3.5">
                            <div>
                                <p className="text-sm font-medium text-graphite">Código activo</p>
                                <p className="text-xs text-gray-500">
                                    {data.is_active
                                        ? 'Se puede usar de inmediato (según fechas y límite de usos).'
                                        : 'Queda guardado pero sin poder usarse.'}
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
                                <div className="peer h-6 w-11 rounded-full bg-gray-200 transition-colors after:absolute after:left-[2px] after:top-[2px] after:h-5 after:w-5 after:rounded-full after:border after:border-gray-200 after:bg-surface after:transition-all after:content-[''] peer-checked:bg-ice-500 peer-checked:after:translate-x-full peer-checked:after:border-white peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-ice-500/20" />
                            </label>
                        </div>
                    </div>

                    <div className="mt-6 flex justify-end gap-3 border-t border-gray-200 pt-6">
                        <Link
                            href={route('admin.discount-codes.index')}
                            className="inline-flex items-center rounded-lg border border-gray-200 px-4 py-2 text-sm font-medium text-graphite/85 transition hover:bg-background"
                        >
                            Cancelar
                        </Link>
                        <button
                            type="submit"
                            disabled={processing}
                            className="inline-flex items-center rounded-lg bg-storefront px-4 py-2 text-sm font-semibold text-white transition hover:brightness-90 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-storefront disabled:bg-gray-200 disabled:text-gray-500"
                        >
                            {processing ? 'Guardando...' : 'Crear código'}
                        </button>
                    </div>
                </form>
            </div>
        </AdminLayout>
    );
}
