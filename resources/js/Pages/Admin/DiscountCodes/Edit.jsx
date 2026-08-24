import React from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import toast from 'react-hot-toast';
import AdminLayout from '@/Layouts/AdminLayout';
import { buildPreviewText } from '@/utils/discountCodes';

const inputClasses =
    'block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900 placeholder:text-slate-400 transition focus:border-navy focus:outline-none focus:ring-2 focus:ring-navy/10';

function formatDateForInput(dateString) {
    if (!dateString) return '';
    return new Date(dateString).toISOString().slice(0, 16);
}

export default function Edit({ discountCode }) {
    const { data, setData, put, errors, processing } = useForm({
        code: discountCode.code || '',
        description: discountCode.description || '',
        percentage: discountCode.percentage ?? '',
        min_purchase_amount: discountCode.min_purchase_amount ?? '',
        usage_limit: discountCode.usage_limit ?? '',
        start_date: formatDateForInput(discountCode.start_date),
        end_date: formatDateForInput(discountCode.end_date),
        is_active: discountCode.is_active,
    });

    const codeLocked = discountCode.usage_count > 0;

    const submit = (e) => {
        e.preventDefault();
        put(route('admin.discount-codes.update', discountCode.id), {
            onSuccess: () => toast.success('Código de descuento actualizado exitosamente'),
            onError: () => toast.error('Revisá los datos del formulario'),
        });
    };

    return (
        <AdminLayout
            header={
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div className="min-w-0">
                        <p className="text-xs font-semibold uppercase tracking-wider text-slate-400">
                            <Link href={route('admin.discount-codes.index')} className="hover:text-slate-600">
                                Códigos de descuento
                            </Link>
                        </p>
                        <h1 className="mt-1 truncate text-xl font-semibold text-slate-900 sm:text-2xl">
                            {discountCode.code}
                        </h1>
                    </div>
                    <Link
                        href={route('admin.discount-codes.index')}
                        className="inline-flex items-center justify-center rounded-lg border border-slate-300 px-3.5 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
                    >
                        Volver
                    </Link>
                </div>
            }
        >
            <Head title={`Editar ${discountCode.code} - Admin`} />

            <div className="mx-auto max-w-2xl space-y-4">
                {codeLocked && (
                    <div className="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                        Este código ya se usó en {discountCode.usage_count}{' '}
                        {discountCode.usage_count === 1 ? 'orden' : 'órdenes'}, así que su texto no se puede
                        modificar. Desactivalo y creá uno nuevo si necesitás otro código.
                    </div>
                )}

                <form onSubmit={submit} className="rounded-xl border border-slate-200 bg-white p-5 sm:p-6">
                    <div className="space-y-6">
                        <div>
                            <label htmlFor="code" className="mb-1.5 block text-sm font-medium text-slate-700">
                                Código <span className="text-rose-500">*</span>
                            </label>
                            <input
                                id="code"
                                type="text"
                                value={data.code}
                                onChange={(e) => setData('code', e.target.value.toUpperCase())}
                                disabled={processing || codeLocked}
                                className={`${inputClasses} font-mono uppercase disabled:bg-slate-50 disabled:text-slate-500`}
                                maxLength={50}
                            />
                            {errors.code && <p className="mt-1.5 text-xs font-medium text-rose-600">{errors.code}</p>}
                        </div>

                        <div>
                            <label htmlFor="description" className="mb-1.5 block text-sm font-medium text-slate-700">
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
                                <p className="mt-1.5 text-xs font-medium text-rose-600">{errors.description}</p>
                            )}
                        </div>

                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <label
                                    htmlFor="percentage"
                                    className="mb-1.5 block text-sm font-medium text-slate-700"
                                >
                                    Porcentaje de descuento <span className="text-rose-500">*</span>
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
                                        className={`${inputClasses} pr-8`}
                                    />
                                    <span className="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-sm text-slate-400">
                                        %
                                    </span>
                                </div>
                                {errors.percentage && (
                                    <p className="mt-1.5 text-xs font-medium text-rose-600">{errors.percentage}</p>
                                )}
                            </div>
                            <div>
                                <label
                                    htmlFor="min_purchase_amount"
                                    className="mb-1.5 block text-sm font-medium text-slate-700"
                                >
                                    Compra mínima
                                </label>
                                <div className="relative">
                                    <span className="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-sm text-slate-400">
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
                                    <p className="mt-1.5 text-xs font-medium text-rose-600">
                                        {errors.min_purchase_amount}
                                    </p>
                                )}
                            </div>
                        </div>

                        <div>
                            <label htmlFor="usage_limit" className="mb-1.5 block text-sm font-medium text-slate-700">
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
                            {discountCode.usage_count > 0 && (
                                <p className="mt-1.5 text-xs text-slate-500">
                                    Ya lleva {discountCode.usage_count} {discountCode.usage_count === 1 ? 'uso' : 'usos'}{' '}
                                    registrados.
                                </p>
                            )}
                            {errors.usage_limit && (
                                <p className="mt-1.5 text-xs font-medium text-rose-600">{errors.usage_limit}</p>
                            )}
                        </div>

                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <label
                                    htmlFor="start_date"
                                    className="mb-1.5 block text-sm font-medium text-slate-700"
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
                                    <p className="mt-1.5 text-xs font-medium text-rose-600">{errors.start_date}</p>
                                )}
                            </div>
                            <div>
                                <label htmlFor="end_date" className="mb-1.5 block text-sm font-medium text-slate-700">
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
                                    <p className="mt-1.5 text-xs font-medium text-rose-600">{errors.end_date}</p>
                                )}
                            </div>
                        </div>
                        <p className="-mt-3 text-xs text-slate-500">
                            Opcional. Sin fechas, el código queda disponible hasta que lo desactives.
                        </p>

                        <div className="rounded-lg bg-navy/5 px-4 py-3.5">
                            <p className="text-xs font-semibold uppercase tracking-wider text-navy/60">Vista previa</p>
                            <p className="mt-1 text-sm font-medium text-navy">{buildPreviewText(data)}</p>
                        </div>

                        <div className="flex items-center justify-between rounded-lg bg-slate-50 px-4 py-3.5">
                            <div>
                                <p className="text-sm font-medium text-slate-900">Código activo</p>
                                <p className="text-xs text-slate-500">
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
                                <div className="peer h-6 w-11 rounded-full bg-slate-300 transition-colors after:absolute after:left-[2px] after:top-[2px] after:h-5 after:w-5 after:rounded-full after:border after:border-slate-300 after:bg-white after:transition-all after:content-[''] peer-checked:bg-gold peer-checked:after:translate-x-full peer-checked:after:border-white peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-gold/20" />
                            </label>
                        </div>
                    </div>

                    <div className="mt-6 flex justify-end gap-3 border-t border-slate-100 pt-6">
                        <Link
                            href={route('admin.discount-codes.index')}
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
