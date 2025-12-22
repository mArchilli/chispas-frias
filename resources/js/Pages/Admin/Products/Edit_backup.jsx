import React, { useState } from 'react';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import AdminLayout from '../../../Layouts/AdminLayout';
import PrimaryButton from '../../../Components/PrimaryButton';
import SecondaryButton from '../../../Components/SecondaryButton';
import DangerButton from '../../../Components/DangerButton';
import TextInput from '../../../Components/TextInput';
import InputLabel from '../../../Components/InputLabel';
import InputError from '../../../Components/InputError';
import SelectInput from '../../../Components/SelectInput';
import Modal from '../../../Components/Modal';

function Edit() {
    const { product, categories } = usePage().props;
    const [showDeleteImageModal, setShowDeleteImageModal] = useState(false);
    const [imageToDelete, setImageToDelete] = useState(null);
    const [newImages, setNewImages] = useState([]);

    const { data, setData, post, processing, errors } = useForm({
        title: product.title,
        description: product.description,
        price: product.price,
        sku: product.sku || '',
        category_id: product.category_id,
        stock: product.stock,
        is_active: product.is_active,
        is_featured: product.is_featured,
        new_images: [],
        remove_images: [],
    });

    const handleSubmit = (e) => {
        e.preventDefault();
        
        const formData = new FormData();
        formData.append('_method', 'PUT');
        formData.append('title', data.title);
        formData.append('description', data.description);
        formData.append('price', data.price);
        formData.append('sku', data.sku);
        formData.append('category_id', data.category_id);
        formData.append('stock', data.stock);
        formData.append('is_active', data.is_active ? '1' : '0');
        formData.append('is_featured', data.is_featured ? '1' : '0');
        
        // Add remove images IDs
        data.remove_images.forEach((imageId, index) => {
            formData.append(`remove_images[${index}]`, imageId);
        });
        
        // Add new images
        newImages.forEach((file, index) => {
            formData.append(`new_images[${index}]`, file);
        });
        
        post(route('admin.products.update', product.id), {
            data: formData,
            forceFormData: true,
        });
    };

    const handleImageUpload = (e) => {
        const files = Array.from(e.target.files);
        setNewImages(prev => [...prev, ...files]);
    };

    const removeNewImage = (index) => {
        setNewImages(prev => prev.filter((_, i) => i !== index));
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
        post(route('admin.products.set-primary-image', [product.id, imageId]));
    };

    const existingImages = product.images.filter(img => !data.remove_images.includes(img.id));

    return (
        <AdminLayout
            header={
                <div className="flex items-center justify-between">
                    <div className="flex items-center space-x-4">
                        <div className="p-2 bg-blue-100 rounded-lg">
                            <svg className="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                            </svg>
                        </div>
                        <div>
                            <h1 className="text-2xl font-semibold text-gray-900">Editar Producto</h1>
                            <p className="text-sm text-gray-600">Modifica la información del producto y sus imágenes</p>
                        </div>
                    </div>
                    <div className="flex space-x-3">
                        <Link href={route('admin.products.show', product.id)}>
                            <SecondaryButton>Ver Producto</SecondaryButton>
                        </Link>
                        <Link href={route('admin.products.index')}>
                            <SecondaryButton>Volver al Listado</SecondaryButton>
                        </Link>
                    </div>
                </div>
            }
        >

                            <form onSubmit={handleSubmit} className="space-y-6">
                                <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <InputLabel htmlFor="title" value="Título *" />
                                        <TextInput
                                            id="title"
                                            type="text"
                                            className="mt-1 block w-full"
                                            value={data.title}
                                            onChange={(e) => setData('title', e.target.value)}
                                            required
                                        />
                                        <InputError message={errors.title} className="mt-2" />
                                    </div>

                                    <div>
                                        <InputLabel htmlFor="sku" value="SKU" />
                                        <TextInput
                                            id="sku"
                                            type="text"
                                            className="mt-1 block w-full"
                                            value={data.sku}
                                            onChange={(e) => setData('sku', e.target.value)}
                                            placeholder="Código único del producto"
                                        />
                                        <InputError message={errors.sku} className="mt-2" />
                                    </div>

                                    <div>
                                        <InputLabel htmlFor="price" value="Precio *" />
                                        <TextInput
                                            id="price"
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            className="mt-1 block w-full"
                                            value={data.price}
                                            onChange={(e) => setData('price', e.target.value)}
                                            required
                                        />
                                        <InputError message={errors.price} className="mt-2" />
                                    </div>

                                    <div>
                                        <InputLabel htmlFor="stock" value="Stock *" />
                                        <TextInput
                                            id="stock"
                                            type="number"
                                            min="0"
                                            className="mt-1 block w-full"
                                            value={data.stock}
                                            onChange={(e) => setData('stock', e.target.value)}
                                            required
                                        />
                                        <InputError message={errors.stock} className="mt-2" />
                                    </div>

                                    <div>
                                        <InputLabel htmlFor="category_id" value="Categoría *" />
                                        <SelectInput
                                            id="category_id"
                                            className="mt-1 block w-full"
                                            value={data.category_id}
                                            onChange={(e) => setData('category_id', e.target.value)}
                                            required
                                        >
                                            <option value="">Seleccionar categoría</option>
                                            {categories.map(category => (
                                                <option key={category.id} value={category.id}>
                                                    {category.name}
                                                </option>
                                            ))}
                                        </SelectInput>
                                        <InputError message={errors.category_id} className="mt-2" />
                                    </div>

                                    <div className="flex items-center space-x-6">
                                        <label className="flex items-center">
                                            <input
                                                type="checkbox"
                                                checked={data.is_active}
                                                onChange={(e) => setData('is_active', e.target.checked)}
                                                className="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                                            />
                                            <span className="ml-2 text-sm text-gray-600">Activo</span>
                                        </label>
                                        
                                        <label className="flex items-center">
                                            <input
                                                type="checkbox"
                                                checked={data.is_featured}
                                                onChange={(e) => setData('is_featured', e.target.checked)}
                                                className="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                                            />
                                            <span className="ml-2 text-sm text-gray-600">Destacado</span>
                                        </label>
                                    </div>
                                </div>

                                <div>
                                    <InputLabel htmlFor="description" value="Descripción *" />
                                    <textarea
                                        id="description"
                                        className="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
                                        rows={4}
                                        value={data.description}
                                        onChange={(e) => setData('description', e.target.value)}
                                        required
                                    />
                                    <InputError message={errors.description} className="mt-2" />
                                </div>

                                {/* Imágenes Existentes */}
                                {existingImages.length > 0 && (
                                    <div>
                                        <InputLabel value="Imágenes Actuales" />
                                        <div className="mt-2 grid grid-cols-2 md:grid-cols-4 gap-4">
                                            {existingImages.map(image => (
                                                <div key={image.id} className="relative group">
                                                    <img
                                                        src={`/storage/${image.path}`}
                                                        alt={image.alt_text}
                                                        className="w-full h-32 object-cover rounded-lg border"
                                                    />
                                                    
                                                    {image.is_primary && (
                                                        <div className="absolute top-2 left-2 bg-green-500 text-white px-2 py-1 rounded text-xs">
                                                            Principal
                                                        </div>
                                                    )}
                                                    
                                                    <div className="absolute inset-0 bg-black bg-opacity-50 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center space-x-2">
                                                        {!image.is_primary && (
                                                            <button
                                                                type="button"
                                                                onClick={() => setPrimaryImage(image.id)}
                                                                className="bg-blue-500 hover:bg-blue-600 text-white px-2 py-1 rounded text-xs"
                                                            >
                                                                Hacer Principal
                                                            </button>
                                                        )}
                                                        
                                                        <button
                                                            type="button"
                                                            onClick={() => handleDeleteExistingImage(image)}
                                                            className="bg-red-500 hover:bg-red-600 text-white px-2 py-1 rounded text-xs"
                                                        >
                                                            Eliminar
                                                        </button>
                                                    </div>
                                                </div>
                                            ))}
                                        </div>
                                    </div>
                                )}

                                {/* Nuevas Imágenes */}
                                <div>
                                    <InputLabel htmlFor="new_images" value="Agregar Imágenes" />
                                    <input
                                        id="new_images"
                                        type="file"
                                        multiple
                                        accept="image/*"
                                        onChange={handleImageUpload}
                                        className="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100"
                                    />
                                    <p className="mt-1 text-sm text-gray-500">
                                        Máximo 10 imágenes. Formatos: JPG, PNG, GIF, WEBP. Máximo 2MB por imagen.
                                    </p>
                                    <InputError message={errors.new_images} className="mt-2" />
                                    
                                    {newImages.length > 0 && (
                                        <div className="mt-4 grid grid-cols-2 md:grid-cols-4 gap-4">
                                            {newImages.map((file, index) => (
                                                <div key={index} className="relative">
                                                    <img
                                                        src={URL.createObjectURL(file)}
                                                        alt="Preview"
                                                        className="w-full h-32 object-cover rounded-lg border"
                                                    />
                                                    <button
                                                        type="button"
                                                        onClick={() => removeNewImage(index)}
                                                        className="absolute -top-2 -right-2 bg-red-500 text-white rounded-full w-6 h-6 flex items-center justify-center text-xs hover:bg-red-600"
                                                    >
                                                        ×
                                                    </button>
                                                </div>
                                            ))}
                                        </div>
                                    )}
                                </div>

                                <div className="flex items-center justify-end space-x-4">
                                    <Link href={route('admin.products.index')}>
                                        <SecondaryButton>Cancelar</SecondaryButton>
                                    </Link>
                                    <PrimaryButton disabled={processing}>
                                        {processing ? 'Guardando...' : 'Actualizar Producto'}
                                    </PrimaryButton>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            {/* Modal de Confirmación para Eliminar Imagen */}
            <Modal show={showDeleteImageModal} onClose={() => setShowDeleteImageModal(false)}>
                <div className="p-6">
                    <h2 className="text-lg font-medium text-gray-900 mb-6">
                        ¿Confirmar eliminación de imagen?
                    </h2>
                    
                    <p className="text-sm text-gray-600 mb-6">
                        Esta acción no se puede deshacer. ¿Estás seguro de que deseas eliminar esta imagen?
                    </p>
                    
                    <div className="flex justify-end space-x-4">
                        <SecondaryButton onClick={() => setShowDeleteImageModal(false)}>
                            Cancelar
                        </SecondaryButton>
                        <DangerButton onClick={confirmDeleteImage}>
                            Eliminar Imagen
                        </DangerButton>
                    </div>
                </div>
            </Modal>
        </AdminLayout>
    );
}

export default Edit;
