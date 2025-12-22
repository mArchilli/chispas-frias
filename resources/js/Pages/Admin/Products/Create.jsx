import React from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';

export default function Create({ categories = [] }) {
    const { data, setData, post, errors, processing } = useForm({
        title: '',
        description: '',
        price: '',
        sku: '',
        category_id: '',
        stock: 0,
        is_active: true,
        is_featured: false,
        images: []
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('admin.products.store'));
    };

    const handleImageChange = (e) => {
        setData('images', e.target.files);
    };

    return (
        <AdminLayout
            header={
                <div className="flex items-center justify-between">
                    <h1 className="text-2xl font-semibold text-gray-900">Nuevo Producto</h1>
                    <Link
                        href={route('admin.products.index')}
                        className="inline-flex items-center px-4 py-2 bg-gray-500 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-600 active:bg-gray-700 focus:outline-none focus:border-gray-700 focus:ring focus:ring-gray-300 disabled:opacity-25 transition"
                    >
                        Volver
                    </Link>
                </div>
            }
        >
            <Head title="Nuevo Producto - Admin" />

            <div className="bg-white overflow-hidden shadow rounded-lg">
                <form onSubmit={submit} className="space-y-6 px-4 py-5 sm:p-6" encType="multipart/form-data">
                    {/* Title */}
                    <div>
                        <label htmlFor="title" className="block text-sm font-medium text-gray-700">
                            Título *
                        </label>
                        <div className="mt-1">
                            <input
                                type="text"
                                name="title"
                                id="title"
                                value={data.title}
                                onChange={(e) => setData('title', e.target.value)}
                                className={`shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md ${
                                    errors.title ? 'border-red-300' : ''
                                }`}
                                placeholder="Ingresa el título del producto"
                                required
                            />
                            {errors.title && <p className="mt-2 text-sm text-red-600">{errors.title}</p>}
                        </div>
                    </div>

                    {/* Description */}
                    <div>
                        <label htmlFor="description" className="block text-sm font-medium text-gray-700">
                            Descripción *
                        </label>
                        <div className="mt-1">
                            <textarea
                                name="description"
                                id="description"
                                rows={4}
                                value={data.description}
                                onChange={(e) => setData('description', e.target.value)}
                                className={`shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md ${
                                    errors.description ? 'border-red-300' : ''
                                }`}
                                placeholder="Descripción detallada del producto"
                                required
                            />
                            {errors.description && <p className="mt-2 text-sm text-red-600">{errors.description}</p>}
                        </div>
                    </div>

                    <div className="grid grid-cols-1 gap-6 sm:grid-cols-2">
                        {/* Price */}
                        <div>
                            <label htmlFor="price" className="block text-sm font-medium text-gray-700">
                                Precio *
                            </label>
                            <div className="mt-1 relative rounded-md shadow-sm">
                                <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <span className="text-gray-500 sm:text-sm">$</span>
                                </div>
                                <input
                                    type="number"
                                    name="price"
                                    id="price"
                                    min="0"
                                    step="0.01"
                                    value={data.price}
                                    onChange={(e) => setData('price', e.target.value)}
                                    className={`shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full pl-8 sm:text-sm border-gray-300 rounded-md ${
                                        errors.price ? 'border-red-300' : ''
                                    }`}
                                    placeholder="0.00"
                                    required
                                />
                            </div>
                            {errors.price && <p className="mt-2 text-sm text-red-600">{errors.price}</p>}
                        </div>

                        {/* SKU */}
                        <div>
                            <label htmlFor="sku" className="block text-sm font-medium text-gray-700">
                                SKU (Código)
                            </label>
                            <div className="mt-1">
                                <input
                                    type="text"
                                    name="sku"
                                    id="sku"
                                    value={data.sku}
                                    onChange={(e) => setData('sku', e.target.value)}
                                    className={`shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md ${
                                        errors.sku ? 'border-red-300' : ''
                                    }`}
                                    placeholder="Ej: FA-9T-001"
                                />
                                {errors.sku && <p className="mt-2 text-sm text-red-600">{errors.sku}</p>}
                            </div>
                        </div>
                    </div>

                    <div className="grid grid-cols-1 gap-6 sm:grid-cols-2">
                        {/* Category */}
                        <div>
                            <label htmlFor="category_id" className="block text-sm font-medium text-gray-700">
                                Categoría *
                            </label>
                            <div className="mt-1">
                                <select
                                    name="category_id"
                                    id="category_id"
                                    value={data.category_id}
                                    onChange={(e) => setData('category_id', e.target.value)}
                                    className={`shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md ${
                                        errors.category_id ? 'border-red-300' : ''
                                    }`}
                                    required
                                >
                                    <option value="">Selecciona una categoría</option>
                                    {categories.map((category) => (
                                        <option key={category.id} value={category.id}>
                                            {category.name}
                                        </option>
                                    ))}
                                </select>
                                {errors.category_id && <p className="mt-2 text-sm text-red-600">{errors.category_id}</p>}
                            </div>
                        </div>

                        {/* Stock */}
                        <div>
                            <label htmlFor="stock" className="block text-sm font-medium text-gray-700">
                                Stock *
                            </label>
                            <div className="mt-1">
                                <input
                                    type="number"
                                    name="stock"
                                    id="stock"
                                    min="0"
                                    value={data.stock}
                                    onChange={(e) => setData('stock', parseInt(e.target.value) || 0)}
                                    className={`shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md ${
                                        errors.stock ? 'border-red-300' : ''
                                    }`}
                                    placeholder="0"
                                    required
                                />
                                {errors.stock && <p className="mt-2 text-sm text-red-600">{errors.stock}</p>}
                            </div>
                        </div>
                    </div>

                    {/* Images */}
                    <div>
                        <label htmlFor="images" className="block text-sm font-medium text-gray-700">
                            Imágenes
                        </label>
                        <div className="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-gray-300 border-dashed rounded-md">
                            <div className="space-y-1 text-center">
                                <svg
                                    className="mx-auto h-12 w-12 text-gray-400"
                                    stroke="currentColor"
                                    fill="none"
                                    viewBox="0 0 48 48"
                                >
                                    <path
                                        d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02"
                                        strokeWidth={2}
                                        strokeLinecap="round"
                                        strokeLinejoin="round"
                                    />
                                </svg>
                                <div className="flex text-sm text-gray-600">
                                    <label
                                        htmlFor="images"
                                        className="relative cursor-pointer bg-white rounded-md font-medium text-blue-600 hover:text-blue-500 focus-within:outline-none focus-within:ring-2 focus-within:ring-offset-2 focus-within:ring-blue-500"
                                    >
                                        <span>Subir archivos</span>
                                        <input
                                            id="images"
                                            name="images"
                                            type="file"
                                            multiple
                                            accept="image/*"
                                            className="sr-only"
                                            onChange={handleImageChange}
                                        />
                                    </label>
                                    <p className="pl-1">o arrastrar y soltar</p>
                                </div>
                                <p className="text-xs text-gray-500">
                                    PNG, JPG, GIF hasta 2MB (máximo 10 imágenes)
                                </p>
                            </div>
                        </div>
                        {errors.images && <p className="mt-2 text-sm text-red-600">{errors.images}</p>}
                    </div>

                    {/* Status toggles */}
                    <div className="space-y-4">
                        {/* Active Status */}
                        <div className="flex items-center">
                            <input
                                type="checkbox"
                                name="is_active"
                                id="is_active"
                                checked={data.is_active}
                                onChange={(e) => setData('is_active', e.target.checked)}
                                className="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                            />
                            <label htmlFor="is_active" className="ml-2 block text-sm text-gray-900">
                                Producto visible en la tienda
                            </label>
                        </div>
                        <p className="text-sm text-gray-500 ml-6">
                            Los productos inactivos no aparecerán en la tienda para los clientes.
                        </p>

                        {/* Featured Status */}
                        <div className="flex items-center">
                            <input
                                type="checkbox"
                                name="is_featured"
                                id="is_featured"
                                checked={data.is_featured}
                                onChange={(e) => setData('is_featured', e.target.checked)}
                                className="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                            />
                            <label htmlFor="is_featured" className="ml-2 block text-sm text-gray-900">
                                Producto destacado
                            </label>
                        </div>
                        <p className="text-sm text-gray-500 ml-6">
                            Los productos destacados aparecen en secciones especiales de la tienda.
                        </p>
                    </div>

                    {errors.is_active && <p className="mt-2 text-sm text-red-600">{errors.is_active}</p>}
                    {errors.is_featured && <p className="mt-2 text-sm text-red-600">{errors.is_featured}</p>}

                    {/* Submit Buttons */}
                    <div className="flex justify-end space-x-3 pt-5">
                        <Link
                            href={route('admin.products.index')}
                            className="bg-white py-2 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                        >
                            Cancelar
                        </Link>
                        <button
                            type="submit"
                            disabled={processing}
                            className={`inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 ${
                                processing ? 'opacity-50 cursor-not-allowed' : ''
                            }`}
                        >
                            {processing ? 'Creando...' : 'Crear Producto'}
                        </button>
                    </div>
                </form>
            </div>
        </AdminLayout>
    );
}