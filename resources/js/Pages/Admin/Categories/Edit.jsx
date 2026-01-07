import React from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import toast from 'react-hot-toast';
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
        patch(route('admin.categories.update', category.id), {
            onSuccess: () => {
                toast.success('Categoría actualizada exitosamente');
            },
            onError: () => {
                toast.error('Error al actualizar la categoría');
            }
        });
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
                            <h1 className="text-2xl font-semibold text-gray-900">
                                Editar Categoría: {category.name}
                            </h1>
                            <p className="text-sm text-gray-600">Modifica la información de la categoría</p>
                        </div>
                    </div>
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

            <div className="max-w-4xl mx-auto space-y-6">
                {/* Header de la Categoría */}
                <div className="bg-gradient-to-r from-blue-50 to-indigo-50 rounded-xl p-6 border border-blue-100">
                    <div className="flex items-center space-x-3">
                        <div className="p-3 bg-blue-100 rounded-lg">
                            <svg className="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                            </svg>
                        </div>
                        <div>
                            <h1 className="text-2xl font-bold text-gray-900">Editando: {category.name}</h1>
                            <p className="text-sm text-gray-600 mt-1">
                                Slug: {category.slug} • 
                                Última modificación: {category.updated_at}
                            </p>
                        </div>
                    </div>
                </div>

                <form onSubmit={submit} className="space-y-6">
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
                                    <p className="text-sm text-gray-600">Nombre y descripción de la categoría</p>
                                </div>
                            </div>
                        </div>
                        
                        <div className="p-6 space-y-6">
                            <div>
                                <div className="flex items-center space-x-2 mb-2">
                                    <svg className="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M4 6h16M4 12h16M4 18h7" />
                                    </svg>
                                    <label htmlFor="name" className="text-sm font-medium text-gray-700">
                                        Nombre *
                                    </label>
                                </div>
                                <input
                                    type="text"
                                    name="name"
                                    id="name"
                                    value={data.name}
                                    onChange={(e) => setData('name', e.target.value)}
                                    className={`mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 ${
                                        errors.name ? 'border-red-300' : ''
                                    }`}
                                    placeholder="Ingresa el nombre de la categoría"
                                    required
                                />
                                {errors.name && <p className="mt-2 text-sm text-red-600">{errors.name}</p>}
                            </div>

                            <div>
                                <div className="flex items-center space-x-2 mb-2">
                                    <svg className="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M7 20l4-16m2 16l4-16M6 9h14M4 15h14" />
                                    </svg>
                                    <label htmlFor="slug" className="text-sm font-medium text-gray-700">
                                        Slug (URL)
                                    </label>
                                </div>
                                <input
                                    type="text"
                                    name="slug"
                                    id="slug"
                                    value={category.slug}
                                    readOnly
                                    className="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 bg-gray-50"
                                />
                                <p className="mt-2 text-sm text-gray-500">
                                    El slug se genera automáticamente basado en el nombre.
                                </p>
                            </div>

                            <div>
                                <div className="flex items-center space-x-2 mb-2">
                                    <svg className="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                    </svg>
                                    <label htmlFor="description" className="text-sm font-medium text-gray-700">
                                        Descripción
                                    </label>
                                </div>
                                <textarea
                                    name="description"
                                    id="description"
                                    rows={4}
                                    value={data.description}
                                    onChange={(e) => setData('description', e.target.value)}
                                    className={`mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 ${
                                        errors.description ? 'border-red-300' : ''
                                    }`}
                                    placeholder="Descripción opcional de la categoría"
                                />
                                {errors.description && <p className="mt-2 text-sm text-red-600">{errors.description}</p>}
                            </div>
                        </div>
                    </div>

                    {/* Sección: Organización y Jerarquía */}
                    <div className="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                        <div className="bg-gradient-to-r from-purple-50 to-indigo-50 px-6 py-4 border-b border-gray-100">
                            <div className="flex items-center space-x-3">
                                <div className="p-2 bg-purple-100 rounded-lg">
                                    <svg className="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                                    </svg>
                                </div>
                                <div>
                                    <h3 className="text-lg font-medium text-gray-900">Organización y Jerarquía</h3>
                                    <p className="text-sm text-gray-600">Ubicación y orden de la categoría</p>
                                </div>
                            </div>
                        </div>
                        
                        <div className="p-6 space-y-6">
                            <div>
                                <div className="flex items-center space-x-2 mb-2">
                                    <svg className="w-4 h-4 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                                    </svg>
                                    <label htmlFor="parent_id" className="text-sm font-medium text-gray-700">
                                        Categoría Padre
                                    </label>
                                </div>
                                <select
                                    name="parent_id"
                                    id="parent_id"
                                    value={data.parent_id}
                                    onChange={(e) => setData('parent_id', e.target.value)}
                                    className={`mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-purple-500 focus:ring-purple-500 ${
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
                                <p className="mt-2 text-sm text-gray-500">
                                    Deja vacío para hacer esta categoría principal, o selecciona una categoría para convertirla en subcategoría.
                                </p>
                            </div>

                            <div>
                                <div className="flex items-center space-x-2 mb-2">
                                    <svg className="w-4 h-4 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4" />
                                    </svg>
                                    <label htmlFor="sort_order" className="text-sm font-medium text-gray-700">
                                        Orden
                                    </label>
                                </div>
                                <input
                                    type="number"
                                    name="sort_order"
                                    id="sort_order"
                                    min="0"
                                    value={data.sort_order}
                                    onChange={(e) => setData('sort_order', parseInt(e.target.value) || 0)}
                                    className={`mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-purple-500 focus:ring-purple-500 ${
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
                    </div>

                    {/* Sección: Configuración */}
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
                                    <h3 className="text-lg font-medium text-gray-900">Configuración</h3>
                                    <p className="text-sm text-gray-600">Estado y opciones de la categoría</p>
                                </div>
                            </div>
                        </div>
                        
                        <div className="p-6">
                            <div className="bg-orange-50 rounded-lg p-4">
                                <div className="flex items-center justify-between">
                                    <div>
                                        <h4 className="text-sm font-medium text-gray-900 flex items-center">
                                            <svg className="w-4 h-4 text-orange-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                            </svg>
                                            Estado de la Categoría
                                        </h4>
                                        <p className="text-sm text-gray-600">
                                            {data.is_active ? 'Visible para los usuarios' : 'Oculta en el sitio'}
                                        </p>
                                    </div>
                                    <label className="relative inline-flex items-center cursor-pointer">
                                        <input
                                            type="checkbox"
                                            name="is_active"
                                            id="is_active"
                                            checked={data.is_active}
                                            onChange={(e) => setData('is_active', e.target.checked)}
                                            className="sr-only peer"
                                        />
                                        <div className="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-orange-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-orange-600"></div>
                                    </label>
                                </div>
                                {errors.is_active && <p className="mt-2 text-sm text-red-600">{errors.is_active}</p>}
                            </div>
                        </div>
                    </div>

                    {/* Botones de Acción */}
                    <div className="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                        <div className="flex justify-end space-x-3">
                            <Link
                                href={route('admin.categories.index')}
                                className="bg-white py-2 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                            >
                                Cancelar
                            </Link>
                            <button
                                type="submit"
                                disabled={processing}
                                className={`inline-flex items-center justify-center py-2 px-6 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 ${
                                    processing ? 'opacity-50 cursor-not-allowed' : ''
                                }`}
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
                                        Actualizar Categoría
                                    </>
                                )}
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </AdminLayout>
    );
}