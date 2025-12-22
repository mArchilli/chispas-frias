import React from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';

export default function Edit({ category, mainCategories = [] }) {
    const { data, setData, patch, errors, processing } = useForm({
        name: category?.name || '',
        description: category?.description || '',
        parent_id: category?.parent_id || '',
        sort_order: category?.sort_order || 0,
        is_active: category?.is_active || false,
    });

    const submit = (e) => {
        e.preventDefault();
        patch(route('admin.categories.update', category.id));
    };

    return (
        <AdminLayout
            header={
                <div className="flex items-center justify-between">
                    <h1 className="text-2xl font-semibold text-gray-900">
                        Editar Categoría: {category.name}
                    </h1>
                    <div className="flex space-x-3">
                        <Link
                            href={route('admin.categories.show', category.id)}
                            className="inline-flex items-center px-4 py-2 bg-blue-500 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-600 active:bg-blue-700 focus:outline-none focus:border-blue-700 focus:ring focus:ring-blue-300 disabled:opacity-25 transition"
                        >
                            Ver
                        </Link>
                        <Link
                            href={route('admin.categories.index')}
                            className="inline-flex items-center px-4 py-2 bg-gray-500 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-600 active:bg-gray-700 focus:outline-none focus:border-gray-700 focus:ring focus:ring-gray-300 disabled:opacity-25 transition"
                        >
                            Volver
                        </Link>
                    </div>
                </div>
            }
        >
            <Head title={`Editar ${category.name} - Admin`} />

            <div className="bg-white overflow-hidden shadow rounded-lg">
                <form onSubmit={submit} className="space-y-6 px-4 py-5 sm:p-6">
                    {/* Name */}
                    <div>
                        <label htmlFor="name" className="block text-sm font-medium text-gray-700">
                            Nombre *
                        </label>
                        <div className="mt-1">
                            <input
                                type="text"
                                name="name"
                                id="name"
                                value={data.name}
                                onChange={(e) => setData('name', e.target.value)}
                                className={`shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md ${
                                    errors.name ? 'border-red-300' : ''
                                }`}
                                placeholder="Ingresa el nombre de la categoría"
                                required
                            />
                            {errors.name && <p className="mt-2 text-sm text-red-600">{errors.name}</p>}
                        </div>
                    </div>

                    {/* Slug (Read Only) */}
                    <div>
                        <label htmlFor="slug" className="block text-sm font-medium text-gray-700">
                            Slug (URL)
                        </label>
                        <div className="mt-1">
                            <input
                                type="text"
                                name="slug"
                                id="slug"
                                value={category.slug}
                                readOnly
                                className="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md bg-gray-50"
                            />
                            <p className="mt-2 text-sm text-gray-500">
                                El slug se genera automáticamente basado en el nombre.
                            </p>
                        </div>
                    </div>

                    {/* Description */}
                    <div>
                        <label htmlFor="description" className="block text-sm font-medium text-gray-700">
                            Descripción
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
                                placeholder="Descripción opcional de la categoría"
                            />
                            {errors.description && <p className="mt-2 text-sm text-red-600">{errors.description}</p>}
                        </div>
                    </div>

                    {/* Parent Category */}
                    <div>
                        <label htmlFor="parent_id" className="block text-sm font-medium text-gray-700">
                            Categoría Padre
                        </label>
                        <div className="mt-1">
                            <select
                                name="parent_id"
                                id="parent_id"
                                value={data.parent_id}
                                onChange={(e) => setData('parent_id', e.target.value)}
                                className={`shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md ${
                                    errors.parent_id ? 'border-red-300' : ''
                                }`}
                            >
                                <option value="">Sin categoría padre (Categoría principal)</option>
                                {mainCategories.map((mainCategory) => (
                                    <option key={mainCategory.id} value={mainCategory.id}>
                                        {mainCategory.name}
                                    </option>
                                ))}
                            </select>
                            {errors.parent_id && <p className="mt-2 text-sm text-red-600">{errors.parent_id}</p>}
                        </div>
                        <p className="mt-2 text-sm text-gray-500">
                            Deja vacío para hacer esta categoría principal, o selecciona una categoría para convertirla en subcategoría.
                        </p>
                    </div>

                    {/* Sort Order */}
                    <div>
                        <label htmlFor="sort_order" className="block text-sm font-medium text-gray-700">
                            Orden
                        </label>
                        <div className="mt-1">
                            <input
                                type="number"
                                name="sort_order"
                                id="sort_order"
                                min="0"
                                value={data.sort_order}
                                onChange={(e) => setData('sort_order', parseInt(e.target.value) || 0)}
                                className={`shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md ${
                                    errors.sort_order ? 'border-red-300' : ''
                                }`}
                                placeholder="0"
                            />
                            {errors.sort_order && <p className="mt-2 text-sm text-red-600">{errors.sort_order}</p>}
                            <p className="mt-2 text-sm text-gray-500">
                                Número menor aparece primero en la lista.
                            </p>
                        </div>
                    </div>

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
                            Categoría activa
                        </label>
                    </div>
                    {errors.is_active && <p className="mt-2 text-sm text-red-600">{errors.is_active}</p>}

                    {/* Submit Buttons */}
                    <div className="flex justify-end space-x-3 pt-5">
                        <Link
                            href={route('admin.categories.index')}
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
                            {processing ? 'Actualizando...' : 'Actualizar Categoría'}
                        </button>
                    </div>
                </form>
            </div>
        </AdminLayout>
    );
}