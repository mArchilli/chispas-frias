import React, { useState } from 'react';
import { Head, Link, useForm, usePage, router } from '@inertiajs/react';
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
        router.patch(route('admin.products.set-primary-image', [product.id, imageId]));
    };

    const existingImages = product.images.filter(img => !data.remove_images.includes(img.id));

    const renderMediaPreview = (media) => {
        if (media.type === 'video') {
            return (
                <video
                    src={media.url}
                    className="w-full h-24 object-cover rounded-lg border"
                    controls={false}
                    muted
                />
            );
        }
        
        return (
            <img
                src={media.url}
                alt={media.alt_text}
                className="w-full h-24 object-cover rounded-lg border"
            />
        );
    };

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
            <Head title="Editar Producto" />
            
            <div className="max-w-7xl mx-auto space-y-6">
                {/* Header del Producto */}
                <div className="bg-gradient-to-r from-blue-50 to-indigo-50 rounded-xl p-6 border border-blue-100">
                    <div className="flex items-center space-x-3">
                        <div className="p-3 bg-blue-100 rounded-lg">
                            <svg className="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                            </svg>
                        </div>
                        <div>
                            <h1 className="text-2xl font-bold text-gray-900">Editando: {product.title}</h1>
                            <p className="text-sm text-gray-600 mt-1">
                                SKU: {product.sku || 'No asignado'} • 
                                Categoría: {product.category?.name} • 
                                Última modificación: {product.updated_at}
                            </p>
                        </div>
                    </div>
                </div>

                <form onSubmit={handleSubmit} className="space-y-8">
                    <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
                        {/* Columna Principal */}
                        <div className="lg:col-span-2 space-y-6">
                            
                            {/* Sección: Información Básica */}
                            <div className="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                                <div className="bg-gradient-to-r from-gray-50 to-slate-50 px-6 py-4 border-b border-gray-100">
                                    <div className="flex items-center space-x-3">
                                        <div className="p-2 bg-gray-100 rounded-lg">
                                            <svg className="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                            </svg>
                                        </div>
                                        <div>
                                            <h3 className="text-lg font-medium text-gray-900">Información Básica</h3>
                                            <p className="text-sm text-gray-600">Nombre y descripción del producto</p>
                                        </div>
                                    </div>
                                </div>
                                
                                <div className="p-6 space-y-6">
                                    <div>
                                        <div className="flex items-center space-x-2 mb-2">
                                            <svg className="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M4 6h16M4 12h16M4 18h7" />
                                            </svg>
                                            <InputLabel htmlFor="title" value="Nombre del Producto" className="text-sm font-medium text-gray-700" />
                                        </div>
                                        <TextInput
                                            id="title"
                                            type="text"
                                            className="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                            value={data.title}
                                            onChange={(e) => setData('title', e.target.value)}
                                            required
                                            placeholder="Ingresa el nombre del producto"
                                        />
                                        <InputError message={errors.title} className="mt-2" />
                                    </div>

                                    <div>
                                        <div className="flex items-center space-x-2 mb-2">
                                            <svg className="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                            </svg>
                                            <InputLabel htmlFor="description" value="Descripción" className="text-sm font-medium text-gray-700" />
                                        </div>
                                        <textarea
                                            id="description"
                                            className="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 min-h-[120px]"
                                            value={data.description}
                                            onChange={(e) => setData('description', e.target.value)}
                                            required
                                            placeholder="Describe las características del producto..."
                                        />
                                        <InputError message={errors.description} className="mt-2" />
                                    </div>
                                </div>
                            </div>

                            {/* Sección: Precios e Inventario */}
                            <div className="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                                <div className="bg-gradient-to-r from-blue-50 to-cyan-50 px-6 py-4 border-b border-gray-100">
                                    <div className="flex items-center space-x-3">
                                        <div className="p-2 bg-blue-100 rounded-lg">
                                            <svg className="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1" />
                                            </svg>
                                        </div>
                                        <div>
                                            <h3 className="text-lg font-medium text-gray-900">Precios e Inventario</h3>
                                            <p className="text-sm text-gray-600">Precio, stock y datos comerciales</p>
                                        </div>
                                    </div>
                                </div>
                                
                                <div className="p-6">
                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                        <div>
                                            <div className="flex items-center space-x-2 mb-2">
                                                <svg className="w-4 h-4 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1" />
                                                </svg>
                                                <InputLabel htmlFor="price" value="Precio (CLP)" className="text-sm font-medium text-gray-700" />
                                            </div>
                                            <TextInput
                                                id="price"
                                                type="number"
                                                step="0.01"
                                                className="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                                value={data.price}
                                                onChange={(e) => setData('price', e.target.value)}
                                                required
                                                placeholder="0.00"
                                            />
                                            <InputError message={errors.price} className="mt-2" />
                                        </div>

                                        <div>
                                            <div className="flex items-center space-x-2 mb-2">
                                                <svg className="w-4 h-4 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                                                </svg>
                                                <InputLabel htmlFor="stock" value="Stock Disponible" className="text-sm font-medium text-gray-700" />
                                            </div>
                                            <TextInput
                                                id="stock"
                                                type="number"
                                                min="0"
                                                className="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                                value={data.stock}
                                                onChange={(e) => setData('stock', e.target.value)}
                                                required
                                                placeholder="0"
                                            />
                                            <InputError message={errors.stock} className="mt-2" />
                                        </div>

                                        <div>
                                            <div className="flex items-center space-x-2 mb-2">
                                                <svg className="w-4 h-4 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M7 20l4-16m2 16l4-16M6 9h14M4 15h14" />
                                                </svg>
                                                <InputLabel htmlFor="sku" value="SKU (Código)" className="text-sm font-medium text-gray-700" />
                                            </div>
                                            <TextInput
                                                id="sku"
                                                type="text"
                                                className="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                                value={data.sku}
                                                onChange={(e) => setData('sku', e.target.value)}
                                                placeholder="Ej: PROD-001"
                                            />
                                            <InputError message={errors.sku} className="mt-2" />
                                        </div>

                                        <div>
                                            <div className="flex items-center space-x-2 mb-2">
                                                <svg className="w-4 h-4 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                                                </svg>
                                                <InputLabel htmlFor="category_id" value="Categoría" className="text-sm font-medium text-gray-700" />
                                            </div>
                                            <SelectInput
                                                id="category_id"
                                                className="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                                value={data.category_id}
                                                onChange={(e) => setData('category_id', e.target.value)}
                                                required
                                            >
                                                <option value="">Selecciona una categoría</option>
                                                {categories.map(category => (
                                                    <option key={category.id} value={category.id}>
                                                        {category.parent ? `${category.parent.name} > ` : ''}{category.name}
                                                    </option>
                                                ))}
                                            </SelectInput>
                                            <InputError message={errors.category_id} className="mt-2" />
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {/* Sección: Configuración del Producto */}
                            <div className="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                                <div className="bg-gradient-to-r from-orange-50 to-amber-50 px-6 py-4 border-b border-gray-100">
                                    <div className="flex items-center space-x-3">
                                        <div className="p-2 bg-orange-100 rounded-lg">
                                            <svg className="w-5 h-5 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                            </svg>
                                        </div>
                                        <div>
                                            <h3 className="text-lg font-medium text-gray-900">Configuración del Producto</h3>
                                            <p className="text-sm text-gray-600">Estado y opciones especiales</p>
                                        </div>
                                    </div>
                                </div>
                                
                                <div className="p-6 space-y-6">
                                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-6">
                                        <div className="bg-orange-50 rounded-lg p-4">
                                            <div className="flex items-center justify-between">
                                                <div>
                                                    <h4 className="text-sm font-medium text-gray-900 flex items-center">
                                                        <svg className="w-4 h-4 text-orange-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                        </svg>
                                                        Estado del Producto
                                                    </h4>
                                                    <p className="text-sm text-gray-600">
                                                        {data.is_active ? 'Visible para los clientes' : 'Oculto en la tienda'}
                                                    </p>
                                                </div>
                                                <label className="relative inline-flex items-center cursor-pointer">
                                                    <input
                                                        type="checkbox"
                                                        checked={data.is_active}
                                                        onChange={(e) => setData('is_active', e.target.checked)}
                                                        className="sr-only peer"
                                                    />
                                                    <div className="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-orange-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-orange-600"></div>
                                                </label>
                                            </div>
                                        </div>

                                        <div className="bg-yellow-50 rounded-lg p-4">
                                            <div className="flex items-center justify-between">
                                                <div>
                                                    <h4 className="text-sm font-medium text-gray-900 flex items-center">
                                                        <svg className="w-4 h-4 text-yellow-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z" />
                                                        </svg>
                                                        Producto Destacado
                                                    </h4>
                                                    <p className="text-sm text-gray-600">
                                                        {data.is_featured ? 'Aparece en secciones destacadas' : 'Producto regular'}
                                                    </p>
                                                </div>
                                                <label className="relative inline-flex items-center cursor-pointer">
                                                    <input
                                                        type="checkbox"
                                                        checked={data.is_featured}
                                                        onChange={(e) => setData('is_featured', e.target.checked)}
                                                        className="sr-only peer"
                                                    />
                                                    <div className="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-yellow-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-yellow-500"></div>
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {/* Columna Lateral - Imágenes */}
                        <div className="lg:col-span-1">
                            <div className="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden sticky top-6">
                                <div className="bg-gradient-to-r from-purple-50 to-indigo-50 px-6 py-4 border-b border-gray-100">
                                    <div className="flex items-center space-x-3">
                                        <div className="p-2 bg-purple-100 rounded-lg">
                                            <svg className="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                            </svg>
                                        </div>
                                        <div>
                                            <h3 className="text-lg font-medium text-gray-900">Gestión de Multimedia</h3>
                                            <p className="text-sm text-gray-600">Agregar, eliminar y organizar imágenes y videos</p>
                                        </div>
                                    </div>
                                </div>
                                
                                <div className="p-6 space-y-6">
                                    {/* Subir nuevas imágenes */}
                                    <div>
                                        <h4 className="text-sm font-medium text-gray-700 mb-3 flex items-center">
                                            <svg className="w-4 h-4 mr-2 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 4v16m8-8H4" />
                                            </svg>
                                            Agregar Nuevas Imágenes y Videos
                                        </h4>
                                        <label htmlFor="new_images" className="flex flex-col items-center justify-center w-full h-32 border-2 border-purple-300 border-dashed rounded-lg cursor-pointer bg-purple-50 hover:bg-purple-100 transition-colors">
                                            <div className="flex flex-col items-center justify-center pt-5 pb-6">
                                                <svg className="w-8 h-8 mb-4 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                                                </svg>
                                                <p className="mb-2 text-sm text-purple-600">
                                                    <span className="font-semibold">Click para subir</span>
                                                </p>
                                                <p className="text-xs text-purple-500">PNG, JPG, JPEG, MP4, MOV, AVI</p>
                                            </div>
                                            <input
                                                id="new_images"
                                                type="file"
                                                multiple
                                                accept="image/*,video/*"
                                                className="hidden"
                                                onChange={handleImageUpload}
                                            />
                                        </label>
                                    </div>

                                    {/* Preview nuevas imágenes */}
                                    {newImages.length > 0 && (
                                        <div>
                                            <h4 className="text-sm font-medium text-gray-700 mb-3">Nuevos archivos a subir:</h4>
                                            <div className="space-y-2">
                                                {newImages.map((file, index) => (
                                                    <div key={index} className="flex items-center justify-between p-2 bg-green-50 rounded-lg">
                                                        <span className="text-sm text-green-700 truncate">{file.name}</span>
                                                        <button
                                                            type="button"
                                                            onClick={() => removeNewImage(index)}
                                                            className="text-red-600 hover:text-red-800"
                                                        >
                                                            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M6 18L18 6M6 6l12 12" />
                                                            </svg>
                                                        </button>
                                                    </div>
                                                ))}
                                            </div>
                                        </div>
                                    )}

                                    {/* Imágenes existentes */}
                                    {existingImages.length > 0 && (
                                        <div>
                                            <h4 className="text-sm font-medium text-gray-700 mb-3">Multimedia actual:</h4>
                                            <div className="space-y-3">
                                                {existingImages.map(image => (
                                                    <div key={image.id} className="relative group">
                                                        {renderMediaPreview(image)}
                                                        <div className="absolute inset-0 bg-black bg-opacity-50 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center space-x-2 rounded-lg">
                                                            {!image.is_primary && (
                                                                <button
                                                                    type="button"
                                                                    onClick={() => setPrimaryImage(image.id)}
                                                                    className="px-2 py-1 bg-blue-600 text-white text-xs rounded hover:bg-blue-700"
                                                                >
                                                                    Principal
                                                                </button>
                                                            )}
                                                            <button
                                                                type="button"
                                                                onClick={() => handleDeleteExistingImage(image)}
                                                                className="px-2 py-1 bg-red-600 text-white text-xs rounded hover:bg-red-700"
                                                            >
                                                                Eliminar
                                                            </button>
                                                        </div>
                                                        {image.is_primary && (
                                                            <div className="absolute top-2 left-2 bg-green-500 text-white text-xs px-2 py-1 rounded">
                                                                Principal
                                                            </div>
                                                        )}
                                                        {image.type === 'video' && (
                                                            <div className="absolute bottom-2 right-2 bg-black bg-opacity-75 text-white text-xs px-2 py-1 rounded">
                                                                📹 Video
                                                            </div>
                                                        )}
                                                    </div>
                                                ))}
                                            </div>
                                        </div>
                                    )}
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* Botones de Acción */}
                    <div className="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                        <div className="flex items-center justify-between">
                            <div className="flex space-x-3">
                                <Link href={route('admin.products.index')}>
                                    <SecondaryButton>Cancelar</SecondaryButton>
                                </Link>
                                <Link href={route('admin.products.show', product.id)}>
                                    <SecondaryButton>Ver Producto</SecondaryButton>
                                </Link>
                            </div>
                            <PrimaryButton
                                disabled={processing}
                                className="inline-flex items-center px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg transition-colors focus:ring-4 focus:ring-blue-300"
                            >
                                {processing ? (
                                    <>
                                        <svg className="w-4 h-4 mr-2 animate-spin" fill="none" viewBox="0 0 24 24">
                                            <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
                                            <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                        </svg>
                                        Actualizando...
                                    </>
                                ) : (
                                    <>
                                        <svg className="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M5 13l4 4L19 7" />
                                        </svg>
                                        Actualizar Producto
                                    </>
                                )}
                            </PrimaryButton>
                        </div>
                    </div>
                </form>
            </div>

            {/* Modal de confirmación para eliminar imagen */}
            <Modal show={showDeleteImageModal} onClose={() => setShowDeleteImageModal(false)}>
                <div className="p-6">
                    <h3 className="text-lg font-medium text-gray-900 mb-4">Eliminar Imagen</h3>
                    <p className="text-gray-600 mb-6">
                        ¿Estás seguro de que quieres eliminar esta imagen? Esta acción no se puede deshacer.
                    </p>
                    <div className="flex justify-end space-x-3">
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