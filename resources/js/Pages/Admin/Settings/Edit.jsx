import React from 'react';
import { Head, useForm } from '@inertiajs/react';
import toast from 'react-hot-toast';
import AdminLayout from '@/Layouts/AdminLayout';
import FreeShippingProgress from '@/Components/FreeShippingProgress';
import { IconTruck } from '@/Components/Admin/Icons';

function inputClasses(hasError) {
    return `block w-full rounded-lg border px-4 py-3 text-slate-900 placeholder:text-slate-400 transition focus:outline-none focus:ring-2 ${
        hasError
            ? 'border-rose-300 focus:border-rose-400 focus:ring-rose-100'
            : 'border-slate-300 focus:border-navy focus:ring-navy/10'
    }`;
}

export default function Edit({ freeShippingThreshold }) {
    const { data, setData, patch, errors, processing } = useForm({
        free_shipping_threshold: freeShippingThreshold ?? '',
    });

    const submit = (e) => {
        e.preventDefault();
        patch(route('admin.settings.update'), {
            preserveScroll: true,
            onSuccess: () => {
                toast.success('Configuración actualizada exitosamente');
            },
            onError: () => {
                toast.error('Revisá los datos del formulario');
            },
        });
    };

    const thresholdNumber = Number(data.free_shipping_threshold) || 0;
    const hasThreshold = thresholdNumber > 0;

    return (
        <AdminLayout
            header={
                <div className="flex items-center gap-3">
                    <span className="flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-xl bg-gold/15 text-gold">
                        <IconTruck className="h-5 w-5" />
                    </span>
                    <div>
                        <p className="text-xs font-semibold uppercase tracking-wider text-slate-400">
                            Envío gratis
                        </p>
                        <h1 className="mt-0.5 text-xl font-semibold text-slate-900 sm:text-2xl">
                            Configurá el monto mínimo
                        </h1>
                    </div>
                </div>
            }
        >
            <Head title="Envío gratis - Admin" />

            <div className="mx-auto max-w-5xl">
                <div className="grid grid-cols-1 gap-6 lg:grid-cols-5">
                    {/* Formulario */}
                    <form
                        onSubmit={submit}
                        className="rounded-xl border border-slate-200 bg-white p-6 sm:p-8 lg:col-span-3"
                    >
                        <label htmlFor="free_shipping_threshold" className="block text-sm font-medium text-slate-700">
                            Monto para envío gratis
                        </label>
                        <p className="mt-1.5 text-sm text-slate-500">
                            A partir de este monto de compra, el carrito y el checkout muestran el envío como
                            gratuito.
                        </p>

                        <div className="mt-5 max-w-sm">
                            <div className="relative">
                                <span className="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-4 text-lg font-medium text-slate-400">
                                    $
                                </span>
                                <input
                                    id="free_shipping_threshold"
                                    type="number"
                                    min="0"
                                    step="1"
                                    value={data.free_shipping_threshold}
                                    onChange={(e) => setData('free_shipping_threshold', e.target.value)}
                                    className={`${inputClasses(errors.free_shipping_threshold)} pl-8 text-lg font-semibold`}
                                    placeholder="Ej. 50000"
                                />
                            </div>
                            {errors.free_shipping_threshold && (
                                <p className="mt-1.5 text-xs font-medium text-rose-600">
                                    {errors.free_shipping_threshold}
                                </p>
                            )}
                        </div>

                        <div className="mt-6 flex items-start gap-3 rounded-lg bg-slate-50 px-4 py-3.5">
                            <svg
                                className="h-5 w-5 flex-shrink-0 text-slate-400"
                                fill="none"
                                stroke="currentColor"
                                strokeWidth={1.75}
                                viewBox="0 0 24 24"
                            >
                                <circle cx="12" cy="12" r="9" />
                                <path strokeLinecap="round" d="M12 11v5.5M12 8h.01" />
                            </svg>
                            <p className="text-xs text-slate-500">
                                Dejá el campo vacío para desactivar la barra de envío gratis: no se va a mostrar
                                ni en el carrito ni en el checkout.
                            </p>
                        </div>

                        <div className="mt-6 flex justify-end gap-3 border-t border-slate-100 pt-6">
                            <button
                                type="submit"
                                disabled={processing}
                                className="inline-flex items-center rounded-lg bg-gold px-5 py-2.5 text-sm font-semibold text-navy transition hover:brightness-95 disabled:opacity-50"
                            >
                                {processing ? 'Guardando...' : 'Guardar cambios'}
                            </button>
                        </div>
                    </form>

                    {/* Vista previa */}
                    <div className="lg:col-span-2">
                        <div className="rounded-xl border border-slate-200 bg-white p-6">
                            <p className="text-xs font-semibold uppercase tracking-wider text-slate-400">
                                Vista previa
                            </p>
                            <p className="mt-1 mb-5 text-sm text-slate-500">
                                Así se ve la barra que verán tus clientes.
                            </p>

                            {hasThreshold ? (
                                <div className="space-y-5">
                                    <div>
                                        <p className="mb-2 text-xs font-medium text-slate-400">
                                            Carrito a mitad de camino
                                        </p>
                                        <FreeShippingProgress
                                            total={thresholdNumber * 0.6}
                                            threshold={thresholdNumber}
                                        />
                                    </div>
                                    <div>
                                        <p className="mb-2 text-xs font-medium text-slate-400">
                                            Envío gratis alcanzado
                                        </p>
                                        <FreeShippingProgress total={thresholdNumber} threshold={thresholdNumber} />
                                    </div>
                                </div>
                            ) : (
                                <div className="flex flex-col items-center justify-center rounded-lg border-2 border-dashed border-slate-200 px-4 py-12 text-center">
                                    <IconTruck className="h-8 w-8 text-slate-300" />
                                    <p className="mt-3 text-sm text-slate-400">
                                        Ingresá un monto para ver cómo se va a mostrar en el carrito y el checkout.
                                    </p>
                                </div>
                            )}
                        </div>
                    </div>
                </div>
            </div>
        </AdminLayout>
    );
}
