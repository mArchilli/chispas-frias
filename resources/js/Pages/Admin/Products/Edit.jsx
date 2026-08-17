import React, { useState } from 'react';
import { Head, Link, useForm, usePage, router } from '@inertiajs/react';
import toast from 'react-hot-toast';
import AdminLayout from '@/Layouts/AdminLayout';
import ProductBasicFields from '@/Components/Admin/ProductBasicFields';
import ProductStatusFields from '@/Components/Admin/ProductStatusFields';
import MediaDropzone from '@/Components/Admin/MediaDropzone';
import PriceTiersEditor from '@/Components/PriceTiersEditor';
import { IconStar, IconTrash, IconVideo } from '@/Components/Admin/Icons';

export default function Edit() {
    const { product, categories } = usePage().props;
    const [showDeleteImageModal, setShowDeleteImageModal] = useState(false);
    const [imageToDelete, setImageToDelete] = useState(null);

    const { data, setData, processing, errors } = useForm({
        title: product.title,
        description: product.description,
        price: product.price,
        sku: product.sku || '',
        category_id: product.category_id,
        stock: product.stock === 9999 ? '' : product.stock,
        is_active: product.is_active,
        is_featured: product.is_featured,
        new_images: null,
        remove_images: [],
        price_tiers: product.price_tiers || [],
    });

    const submit = (e) => {
        e.preventDefault();

        const submitData = { ...data, _method: 'PUT' };
        submitData.is_active = data.is_active ? '1' : '0';
        submitData.is_featured = data.is_featured ? '1' : '0';
        if (!data.new_images || data.new_images.length === 0) {
            delete submitData.new_images;
        }

        router.post(route('admin.products.update', product.id), submitData, {
            forceFormData: true,
            onSuccess: () => toast.success('Producto actualizado exitosamente'),
            onError: () => toast.error('Revisá los datos del formulario'),
        });
    };

    const handleDeleteExistingImage = (image) => {
        setImageToDelete(image);
        setShowDeleteImageModal(true);
    };

    const confirmDeleteImage = () => {
        setData('remove_images', [...data.remove_images, imageToDelete.id]);
        setShowDeleteImageModal(false);
        setImageToDelete(null);
    };

    const setPrimaryImage = (imageId) => {
        router.patch(route('admin.products.set-primary-image', [product.id, imageId]), {}, { preserveScroll: true });
    };

    const existingImages = product.images.filter((img) => !data.remove_images.includes(img.id));

    return (
        <AdminLayout
            header={
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div className="min-w-0">
                        <p className="text-xs font-semibold uppercase tracking-wider text-slate-400">
                            <Link href={route('admin.products.index')} className="hover:text-slate-600">
                                Productos
                            </Link>
                        </p>
                        <h1 className="mt-1 truncate text-xl font-semibold text-slate-900 sm:text-2xl">
                            {product.title}
                        </h1>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Link
                            href={route('admin.products.show', product.id)}
                            className="inline-flex items-center justify-center rounded-lg border border-slate-300 px-3.5 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
                        >
                            Ver producto
                        </Link>
                        <Link
                            href={route('admin.products.index')}
                            className="inline-flex items-center justify-center rounded-lg border border-slate-300 px-3.5 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
                        >
                            Volver
                        </Link>
                    </div>
                </div>
            }
        >
            <Head title={`Editar ${product.title} - Admin`} />

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

                    <div className="space-y-6 lg:col-span-1">
                        <div className="rounded-xl border border-slate-200 bg-white p-5 sm:sticky sm:top-6 sm:p-6">
                            <h2 className="mb-4 text-sm font-semibold text-slate-900">Agregar multimedia</h2>
                            <MediaDropzone
                                files={data.new_images}
                                onChange={(files) => setData('new_images', files)}
                                error={errors.new_images}
                                inputId="new_images"
                            />

                            {existingImages.length > 0 && (
                                <div className="mt-6 border-t border-slate-100 pt-5">
                                    <h3 className="mb-3 text-sm font-semibold text-slate-900">
                                        Multimedia actual ({existingImages.length})
                                    </h3>
                                    <div className="grid grid-cols-3 gap-2 sm:grid-cols-4 lg:grid-cols-3">
                                        {existingImages.map((image) => (
                                            <div key={image.id} className="group relative aspect-square overflow-hidden rounded-lg border border-slate-200">
                                                {image.type === 'video' ? (
                                                    <video src={image.url} className="h-full w-full object-cover" muted />
                                                ) : (
                                                    <img src={image.url} alt={image.alt_text} className="h-full w-full object-cover" />
                                                )}

                                                {image.is_primary && (
                                                    <span
                                                        className="absolute left-1 top-1 flex h-5 w-5 items-center justify-center rounded-full bg-gold text-navy shadow-sm"
                                                        title="Imagen principal"
                                                    >
                                                        <IconStar filled className="h-3 w-3" />
                                                    </span>
                                                )}
                                                {image.type === 'video' && (
                                                    <span className="absolute bottom-1 left-1 flex h-5 w-5 items-center justify-center rounded-full bg-black/60 text-white">
                                                        <IconVideo className="h-3 w-3" />
                                                    </span>
                                                )}

                                                <div className="absolute inset-0 flex items-center justify-center gap-1 bg-black/50 opacity-0 transition group-hover:opacity-100">
                                                    {!image.is_primary && (
                                                        <button
                                                            type="button"
                                                            onClick={() => setPrimaryImage(image.id)}
                                                            title="Marcar como principal"
                                                            className="flex h-7 w-7 items-center justify-center rounded-full bg-white/90 text-slate-700 hover:bg-white"
                                                        >
                                                            <IconStar className="h-3.5 w-3.5" />
                                                        </button>
                                                    )}
                                                    <button
                                                        type="button"
                                                        onClick={() => handleDeleteExistingImage(image)}
                                                        title="Eliminar"
                                                        className="flex h-7 w-7 items-center justify-center rounded-full bg-white/90 text-rose-600 hover:bg-white"
                                                    >
                                                        <IconTrash className="h-3.5 w-3.5" />
                                                    </button>
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            )}
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
                        {processing ? 'Guardando...' : 'Guardar cambios'}
                    </button>
                </div>
            </form>

            {showDeleteImageModal && (
                <div
                    className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4"
                    onClick={() => setShowDeleteImageModal(false)}
                >
                    <div
                        className="w-full max-w-sm rounded-xl bg-white p-5 shadow-xl sm:p-6"
                        onClick={(e) => e.stopPropagation()}
                    >
                        <h3 className="text-base font-semibold text-slate-900">Eliminar archivo</h3>
                        <p className="mt-1 text-sm text-slate-500">
                            Se quitará al guardar los cambios. Esta acción no se puede deshacer.
                        </p>
                        <div className="mt-5 flex justify-end gap-3">
                            <button
                                onClick={() => setShowDeleteImageModal(false)}
                                className="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
                            >
                                Cancelar
                            </button>
                            <button
                                onClick={confirmDeleteImage}
                                className="rounded-lg bg-rose-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-rose-700"
                            >
                                Eliminar
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </AdminLayout>
    );
}
