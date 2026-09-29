import React from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import toast from 'react-hot-toast';
import AdminLayout from '@/Layouts/AdminLayout';
import ComboForm from '@/Components/Admin/ComboForm';

export default function Create({ products = [] }) {
    const { data, setData, post, errors, processing } = useForm({
        title: '',
        description: '',
        price: '',
        is_free_shipping: false,
        is_active: true,
        items: [],
        images: null,
    });

    const submit = (e) => {
        e.preventDefault();

        const formData = { ...data };
        formData.is_free_shipping = data.is_free_shipping ? '1' : '0';
        formData.is_active = data.is_active ? '1' : '0';
        if (!data.images || data.images.length === 0) {
            delete formData.images;
        }

        post(route('admin.combos.store'), formData, {
            forceFormData: true,
            onSuccess: () => toast.success('Combo creado exitosamente'),
            onError: () => toast.error('Revisá los datos del formulario'),
        });
    };

    return (
        <AdminLayout
            header={
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p className="text-xs font-semibold uppercase tracking-wider text-gray-500">
                            <Link href={route('admin.combos.index')} className="hover:text-graphite/75">
                                Combos
                            </Link>
                        </p>
                        <h1 className="mt-1 text-xl font-semibold text-graphite sm:text-2xl">Nuevo combo</h1>
                    </div>
                    <Link
                        href={route('admin.combos.index')}
                        className="inline-flex items-center justify-center rounded-lg border border-gray-200 px-3.5 py-2 text-sm font-medium text-graphite/85 transition hover:bg-background"
                    >
                        Cancelar
                    </Link>
                </div>
            }
        >
            <Head title="Nuevo combo - Admin" />

            <form onSubmit={submit}>
                <ComboForm data={data} setData={setData} errors={errors} products={products} imagesField="images" />

                <div className="mt-6 flex justify-end gap-3 border-t border-gray-200 pt-6">
                    <Link
                        href={route('admin.combos.index')}
                        className="inline-flex items-center rounded-lg border border-gray-200 px-4 py-2 text-sm font-medium text-graphite/85 transition hover:bg-background"
                    >
                        Cancelar
                    </Link>
                    <button
                        type="submit"
                        disabled={processing}
                        className="inline-flex items-center rounded-lg bg-storefront px-4 py-2 text-sm font-semibold text-white transition hover:brightness-90 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-storefront disabled:bg-gray-200 disabled:text-gray-500"
                    >
                        {processing ? 'Creando...' : 'Crear combo'}
                    </button>
                </div>
            </form>
        </AdminLayout>
    );
}
