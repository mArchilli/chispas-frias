import React from 'react';
import { Head, useForm } from '@inertiajs/react';
import toast from 'react-hot-toast';
import AdminLayout from '@/Layouts/AdminLayout';
import FreeShippingProgress from '@/Components/FreeShippingProgress';
import { IconTruck } from '@/Components/Admin/Icons';

function inputClasses(hasError) {
    return `block w-full rounded-lg border px-4 py-3 text-graphite placeholder:text-gray-500 transition focus:outline-none focus:ring-2 ${
        hasError
            ? 'border-navy-700 focus:border-ice-500 focus:ring-ice-100'
            : 'border-gray-200 focus:border-ice-500 focus:ring-ice-100'
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
                    <span className="flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-xl bg-ice-100 text-navy-700">
                        <IconTruck className="h-5 w-5" />
                    </span>
                    <div>
                        <p className="text-xs font-semibold uppercase tracking-wider text-gray-500">
                            Envío gratis
                        </p>
                        <h1 className="mt-0.5 text-xl font-semibold text-graphite sm:text-2xl">
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
                        className="rounded-xl border border-gray-200 bg-surface p-6 sm:p-8 lg:col-span-3"
                    >
                        <label htmlFor="free_shipping_threshold" className="block text-sm font-medium text-graphite/85">
                            Monto para envío gratis
                        </label>
                        <p className="mt-1.5 text-sm text-gray-500">
                            A partir de este monto de compra, el carrito y el checkout muestran el envío como
                            gratuito.
                        </p>

                        <div className="mt-5 max-w-sm">
                            <div className="relative">
                                <span className="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-4 text-lg font-medium text-gray-500">
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
                                <p className="mt-1.5 text-xs font-medium text-navy-700">
                                    {errors.free_shipping_threshold}
                                </p>
                            )}
                        </div>

                        <div className="mt-6 flex items-start gap-3 rounded-lg bg-background px-4 py-3.5">
                            <svg
                                className="h-5 w-5 flex-shrink-0 text-gray-500"
                                fill="none"
                                stroke="currentColor"
                                strokeWidth={1.75}
                                viewBox="0 0 24 24"
                            >
                                <circle cx="12" cy="12" r="9" />
                                <path strokeLinecap="round" d="M12 11v5.5M12 8h.01" />
                            </svg>
                            <p className="text-xs text-gray-500">
                                Dejá el campo vacío para desactivar la barra de envío gratis: no se va a mostrar
                                ni en el carrito ni en el checkout.
                            </p>
                        </div>

                        <div className="mt-6 flex justify-end gap-3 border-t border-gray-200 pt-6">
                            <button
                                type="submit"
                                disabled={processing}
                                className="inline-flex items-center rounded-lg bg-storefront px-5 py-2.5 text-sm font-semibold text-white transition hover:brightness-90 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-storefront disabled:bg-gray-200 disabled:text-gray-500"
                            >
                                {processing ? 'Guardando...' : 'Guardar cambios'}
                            </button>
                        </div>
                    </form>

                    {/* Vista previa */}
                    <div className="lg:col-span-2">
                        <div className="rounded-xl border border-gray-200 bg-surface p-6">
                            <p className="text-xs font-semibold uppercase tracking-wider text-gray-500">
                                Vista previa
                            </p>
                            <p className="mt-1 mb-5 text-sm text-gray-500">
                                Así se ve la barra que verán tus clientes.
                            </p>

                            {hasThreshold ? (
                                <div className="space-y-5">
                                    <div>
                                        <p className="mb-2 text-xs font-medium text-gray-500">
                                            Carrito a mitad de camino
                                        </p>
                                        <FreeShippingProgress
                                            total={thresholdNumber * 0.6}
                                            threshold={thresholdNumber}
                                        />
                                    </div>
                                    <div>
                                        <p className="mb-2 text-xs font-medium text-gray-500">
                                            Envío gratis alcanzado
                                        </p>
                                        <FreeShippingProgress total={thresholdNumber} threshold={thresholdNumber} />
                                    </div>
                                </div>
                            ) : (
                                <div className="flex flex-col items-center justify-center rounded-lg border-2 border-dashed border-gray-200 px-4 py-12 text-center">
                                    <IconTruck className="h-8 w-8 text-gray-500/70" />
                                    <p className="mt-3 text-sm text-gray-500">
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
