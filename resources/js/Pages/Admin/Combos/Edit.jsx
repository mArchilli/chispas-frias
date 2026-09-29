import React from 'react';
import { Head, Link, useForm, router } from '@inertiajs/react';
import toast from 'react-hot-toast';
import AdminLayout from '@/Layouts/AdminLayout';
import ComboForm from '@/Components/Admin/ComboForm';
import { IconStar, IconTrash, IconVideo } from '@/Components/Admin/Icons';

export default function Edit({ combo, products = [] }) {
    const { data, setData, processing, errors } = useForm({
        title: combo.title,
        description: combo.description || '',
        price: combo.price,
        is_free_shipping: combo.is_free_shipping,
        is_active: combo.is_active,
        items: (combo.items || []).map((i) => ({
            product_id: i.product_id,
            quantity: i.quantity,
            product_title: i.product_title,
        })),
        new_images: null,
        remove_images: [],
    });

    const submit = (e) => {
        e.preventDefault();

        const submitData = { ...data, _method: 'PUT' };
        submitData.is_free_shipping = data.is_free_shipping ? '1' : '0';
        submitData.is_active = data.is_active ? '1' : '0';
        if (!data.new_images || data.new_images.length === 0) {
            delete submitData.new_images;
        }

        router.post(route('admin.combos.update', combo.id), submitData, {
            forceFormData: true,
            onSuccess: () => toast.success('Combo actualizado exitosamente'),
            onError: () => toast.error('Revisá los datos del formulario'),
        });
    };

    const markForRemoval = (imageId) => {
        setData('remove_images', [...data.remove_images, imageId]);
    };

    const setPrimaryImage = (imageId) => {
        router.patch(route('admin.combos.set-primary-image', [combo.id, imageId]), {}, { preserveScroll: true });
    };

    const existingImages = (combo.images || []).filter((img) => !data.remove_images.includes(img.id));
    const existingImagesImg = existingImages.filter((img) => img.type !== 'video');
    const existingVideos = existingImages.filter((img) => img.type === 'video');

    // Card de un medio ya guardado del combo (imagen o video), con acciones de
    // marcar principal / quitar. Se reutiliza en los dos grupos separados.
    const renderExistingMedia = (image) => (
        <div key={image.id} className="group relative aspect-square overflow-hidden rounded-lg border border-gray-200">
            {image.type === 'video' ? (
                <video src={image.url} className="h-full w-full object-cover" muted />
            ) : (
                <img src={image.url} alt="" className="h-full w-full object-cover" />
            )}
            {image.is_primary && (
                <span className="absolute left-1 top-1 rounded bg-storefront px-1.5 py-0.5 text-[10px] font-bold text-white">Principal</span>
            )}
            {image.type === 'video' && (
                <span className="absolute bottom-1 left-1 flex h-5 w-5 items-center justify-center rounded-full bg-graphite/60 text-white">
                    <IconVideo className="h-3 w-3" />
                </span>
            )}
            <div className="absolute inset-x-1 top-1 flex justify-end gap-1 opacity-0 transition group-hover:opacity-100">
                {!image.is_primary && image.type !== 'video' && (
                    <button
                        type="button"
                        onClick={() => setPrimaryImage(image.id)}
                        className="flex h-6 w-6 items-center justify-center rounded-full bg-graphite/70 text-white"
                        title="Marcar como principal"
                    >
                        <IconStar className="h-3.5 w-3.5" />
                    </button>
                )}
                <button
                    type="button"
                    onClick={() => markForRemoval(image.id)}
                    className="flex h-6 w-6 items-center justify-center rounded-full bg-graphite/70 text-white"
                    title="Quitar"
                >
                    <IconTrash className="h-3.5 w-3.5" />
                </button>
            </div>
        </div>
    );

    const existingImagesSlot = existingImages.length > 0 && (
        <div className="mb-4 space-y-4">
            <p className="text-xs font-semibold uppercase tracking-wide text-graphite/70">Multimedia actual</p>

            {existingImagesImg.length > 0 && (
                <div>
                    <p className="mb-2 text-[11px] font-medium text-graphite/60">Imágenes ({existingImagesImg.length})</p>
                    <div className="grid grid-cols-3 gap-2">
                        {existingImagesImg.map(renderExistingMedia)}
                    </div>
                </div>
            )}

            {existingVideos.length > 0 && (
                <div>
                    <p className="mb-2 text-[11px] font-medium text-graphite/60">Videos ({existingVideos.length})</p>
                    <div className="grid grid-cols-3 gap-2">
                        {existingVideos.map(renderExistingMedia)}
                    </div>
                </div>
            )}
        </div>
    );

    return (
        <AdminLayout
            header={
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div className="min-w-0">
                        <p className="text-xs font-semibold uppercase tracking-wider text-gray-500">
                            <Link href={route('admin.combos.index')} className="hover:text-graphite/75">
                                Combos
                            </Link>
                        </p>
                        <h1 className="mt-1 truncate text-xl font-semibold text-graphite sm:text-2xl">{combo.title}</h1>
                    </div>
                    <Link
                        href={route('admin.combos.index')}
                        className="inline-flex items-center justify-center rounded-lg border border-gray-200 px-3.5 py-2 text-sm font-medium text-graphite/85 transition hover:bg-background"
                    >
                        Volver
                    </Link>
                </div>
            }
        >
            <Head title={`Editar ${combo.title} - Admin`} />

            <form onSubmit={submit}>
                <ComboForm
                    data={data}
                    setData={setData}
                    errors={errors}
                    products={products}
                    imagesField="new_images"
                    existingImagesSlot={existingImagesSlot}
                />

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
                        {processing ? 'Guardando...' : 'Guardar cambios'}
                    </button>
                </div>
            </form>
        </AdminLayout>
    );
}
