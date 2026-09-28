import React from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import toast from 'react-hot-toast';
import AdminLayout from '@/Layouts/AdminLayout';
import OfferDiscountFields from '@/Components/OfferDiscountFields';

const inputClasses =
    'block w-full rounded-lg border border-gray-200 px-3 py-2 text-sm text-graphite placeholder:text-gray-500 transition focus:border-ice-500 focus:outline-none focus:ring-2 focus:ring-ice-100';

function formatDateForInput(dateString) {
    if (!dateString) return '';
    return new Date(dateString).toISOString().slice(0, 16);
}

export default function Edit({ offer, product }) {
    const { data, setData, put, errors, processing } = useForm({
        tipo_descuento: offer.tipo_descuento || 'porcentaje',
        valor_descuento: offer.valor_descuento ?? '',
        alcance: offer.alcance || 'todos',
        product_price_tier_id: offer.product_price_tier_id ?? null,
        start_date: formatDateForInput(offer.start_date),
        end_date: formatDateForInput(offer.end_date),
        is_active: offer.is_active,
    });

    const submit = (e) => {
        e.preventDefault();
        put(route('admin.offers.update', offer.id), {
            onSuccess: () => toast.success('Oferta actualizada exitosamente'),
            onError: () => toast.error('Revisá los datos del formulario'),
        });
    };

    return (
        <AdminLayout
            header={
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div className="min-w-0">
                        <p className="text-xs font-semibold uppercase tracking-wider text-gray-500">
                            <Link href={route('admin.offers.index')} className="hover:text-graphite/75">
                                Ofertas
                            </Link>
                        </p>
                        <h1 className="mt-1 truncate text-xl font-semibold text-graphite sm:text-2xl">
                            {product.title}
                        </h1>
                    </div>
                    <Link
                        href={route('admin.offers.index')}
                        className="inline-flex items-center justify-center rounded-lg border border-gray-200 px-3.5 py-2 text-sm font-medium text-graphite/85 transition hover:bg-background"
                    >
                        Volver
                    </Link>
                </div>
            }
        >
            <Head title={`Editar oferta - ${product.title} - Admin`} />

            <div className="mx-auto max-w-2xl space-y-4">
                <Link
                    href={route('admin.products.edit', product.id)}
                    className="group flex items-center justify-between rounded-xl border border-gray-200 bg-surface px-4 py-3.5 text-sm transition hover:border-gray-200 hover:shadow-sm"
                >
                    <span className="text-graphite/75">
                        Precio de venta: <span className="font-semibold text-graphite">${product.price}</span>
                    </span>
                    <span className="text-xs font-medium text-gray-500 group-hover:text-graphite/75">
                        Ver producto
                    </span>
                </Link>

                <form onSubmit={submit} className="rounded-xl border border-gray-200 bg-surface p-5 sm:p-6">
                    <div className="space-y-5">
                        <OfferDiscountFields
                            data={data}
                            setData={setData}
                            errors={errors}
                            product={product}
                            disabled={processing}
                        />

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
                            Opcional. Sin fechas, la oferta queda activa hasta que la desactives.
                        </p>

                        <div className="flex items-center justify-between rounded-lg bg-background px-4 py-3.5">
                            <div>
                                <p className="text-sm font-medium text-graphite">Oferta activa</p>
                                <p className="text-xs text-gray-500">
                                    {data.is_active
                                        ? 'Se aplica de inmediato (según las fechas configuradas).'
                                        : 'Queda guardada pero sin aplicar.'}
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
                            href={route('admin.offers.index')}
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
