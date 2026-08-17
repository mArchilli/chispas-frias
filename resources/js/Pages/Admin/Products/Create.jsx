import React from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import toast from 'react-hot-toast';
import AdminLayout from '@/Layouts/AdminLayout';
import ProductBasicFields from '@/Components/Admin/ProductBasicFields';
import ProductStatusFields from '@/Components/Admin/ProductStatusFields';
import MediaDropzone from '@/Components/Admin/MediaDropzone';
import PriceTiersEditor from '@/Components/PriceTiersEditor';

export default function Create({ categories = [] }) {
    const { data, setData, post, errors, processing } = useForm({
        title: '',
        description: '',
        price: '',
        sku: '',
        category_id: '',
        stock: '',
        is_active: true,
        is_featured: false,
        images: null,
        price_tiers: [],
    });

    const submit = (e) => {
        e.preventDefault();

        const formData = { ...data };
        formData.is_active = data.is_active ? '1' : '0';
        formData.is_featured = data.is_featured ? '1' : '0';
        if (!data.images || data.images.length === 0) {
            delete formData.images;
        }

        post(route('admin.products.store'), formData, {
            forceFormData: true,
            onSuccess: () => toast.success('Producto creado exitosamente'),
            onError: () => toast.error('Revisá los datos del formulario'),
        });
    };

    return (
        <AdminLayout
            header={
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p className="text-xs font-semibold uppercase tracking-wider text-slate-400">
                            <Link href={route('admin.products.index')} className="hover:text-slate-600">
                                Productos
                            </Link>
                        </p>
                        <h1 className="mt-1 text-xl font-semibold text-slate-900 sm:text-2xl">Nuevo producto</h1>
                    </div>
                    <Link
                        href={route('admin.products.index')}
                        className="inline-flex items-center justify-center rounded-lg border border-slate-300 px-3.5 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
                    >
                        Cancelar
                    </Link>
                </div>
            }
        >
            <Head title="Nuevo producto - Admin" />

            <form onSubmit={submit}>
                <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
                    <div className="space-y-6 lg:col-span-2">
                        <div className="rounded-xl border border-slate-200 bg-white p-5 sm:p-6">
                            <ProductBasicFields data={data} setData={setData} errors={errors} categories={categories} />
                        </div>

                        <div className="rounded-xl border border-slate-200 bg-white p-5 sm:p-6">
                            <h2 className="mb-4 text-sm font-semibold text-slate-900">Precios por cantidad</h2>
                            <p className="-mt-3 mb-4 text-xs text-slate-500">
                                Opcional: ofrecé un precio unitario más bajo a partir de cierta cantidad.
                            </p>
                            <PriceTiersEditor
                                tiers={data.price_tiers}
                                onChange={(tiers) => setData('price_tiers', tiers)}
                                errors={errors}
                                basePrice={data.price}
                            />
                        </div>

                        <div className="rounded-xl border border-slate-200 bg-white p-5 sm:p-6">
                            <h2 className="mb-4 text-sm font-semibold text-slate-900">Visibilidad</h2>
                            <ProductStatusFields data={data} setData={setData} errors={errors} />
                        </div>
                    </div>

                    <div className="lg:col-span-1">
                        <div className="rounded-xl border border-slate-200 bg-white p-5 sm:sticky sm:top-6 sm:p-6">
                            <h2 className="mb-4 text-sm font-semibold text-slate-900">Multimedia</h2>
                            <MediaDropzone
                                files={data.images}
                                onChange={(files) => setData('images', files)}
                                error={errors.images}
                                inputId="images"
                            />
                        </div>
                    </div>
                </div>

                <div className="mt-6 flex justify-end gap-3 border-t border-slate-200 pt-6">
                    <Link
                        href={route('admin.products.index')}
                        className="inline-flex items-center rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
                    >
                        Cancelar
                    </Link>
                    <button
                        type="submit"
                        disabled={processing}
                        className="inline-flex items-center rounded-lg bg-gold px-4 py-2 text-sm font-semibold text-navy transition hover:brightness-95 disabled:opacity-50"
                    >
                        {processing ? 'Creando...' : 'Crear producto'}
                    </button>
                </div>
            </form>
        </AdminLayout>
    );
}
