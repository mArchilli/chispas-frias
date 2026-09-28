import React from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import toast from 'react-hot-toast';
import AdminLayout from '@/Layouts/AdminLayout';
import { getProductImageUrl } from '@/utils/images';

export default function Show({ category }) {
    const { patch, processing } = useForm();

    const toggleStatus = () => {
        patch(route('admin.categories.toggle-status', category.id), {}, {
            onSuccess: () => {
                toast.success('Estado de la categoría actualizado');
            },
            onError: () => {
                toast.error('Error al actualizar el estado');
            }
        });
    };

    return (
        <AdminLayout
            header={
                <div className="flex items-center justify-between">
                    <div className="flex items-center space-x-4">
                        <div className="p-2 bg-ice-100 rounded-lg">
                            <svg className="w-6 h-6 text-navy-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                            </svg>
                        </div>
                        <div>
                            <h1 className="text-2xl font-semibold text-graphite">
                                Categoría: {category.name}
                            </h1>
                            <p className="text-sm text-graphite/75">Visualiza y gestiona la información de la categoría</p>
                        </div>
                    </div>
                    <div className="flex space-x-3">
                        <button
                            onClick={toggleStatus}
                            disabled={processing}
                            className={`inline-flex items-center px-4 py-2 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest focus:outline-none focus:ring focus:ring-offset-2 disabled:opacity-25 transition ${
                                category.is_active
                                    ? 'bg-storefront hover:brightness-90 active:brightness-90 focus:border-storefront focus:ring-storefront'
                                    : 'bg-storefront hover:brightness-90 active:brightness-90 focus:border-storefront focus:ring-storefront'
                            }`}
                        >
                            {processing 
                                ? 'Procesando...' 
                                : category.is_active 
                                    ? 'Desactivar' 
                                    : 'Activar'
                            }
                        </button>
                        <Link
                            href={route('admin.categories.edit', category.id)}
                            className="inline-flex items-center px-4 py-2 bg-storefront border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:brightness-90 active:brightness-90 focus:outline-none focus:border-storefront focus:ring focus:ring-storefront disabled:bg-gray-200 disabled:text-gray-500 transition"
                        >
                            Editar
                        </Link>
                        <Link
                            href={route('admin.categories.index')}
                            className="inline-flex items-center px-4 py-2 bg-surface border border-gray-200 rounded-md font-semibold text-xs text-navy-900 uppercase tracking-widest hover:bg-ice-50 hover:border-ice-500 active:bg-ice-100 focus:outline-none focus:ring focus:ring-ice-500 disabled:opacity-25 transition"
                        >
                            Volver
                        </Link>
                    </div>
                </div>
            }
        >
            <Head title={`Categoría: ${category.name} - Admin`} />

            <div className="max-w-6xl mx-auto space-y-6">
                {/* Header de la Categoría */}
                <div className="rounded-xl border border-ice-100 bg-ice-50 p-6">
                    <div className="flex flex-col md:flex-row md:items-start md:justify-between space-y-4 md:space-y-0">
                        <div className="flex-1">
                            <h1 className="text-3xl font-bold text-graphite mb-3">{category.name}</h1>
                            <div className="flex flex-wrap items-center gap-4 text-sm text-graphite/75">
                                <div className="flex items-center">
                                    <svg className="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M7 20l4-16m2 16l4-16M6 9h14M4 15h14" />
                                    </svg>
                                    Slug: {category.slug}
                                </div>
                                <div className="flex items-center">
                                    <svg className="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M8 7V3a2 2 0 012-2h4a2 2 0 012 2v4m0 0V7a2 2 0 012 2v6a2 2 0 01-2 2H6a2 2 0 01-2-2V9a2 2 0 012-2m8 0V7a2 2 0 00-2-2H10a2 2 0 00-2 2v2m0 4h4" />
                                    </svg>
                                    Creado: {category.created_at}
                                </div>
                                <div className="flex items-center">
                                    <svg className="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                    </svg>
                                    Actualizado: {category.updated_at}
                                </div>
                            </div>
                        </div>
                        <div className="flex items-center space-x-3">
                            <span className={`inline-flex items-center px-3 py-1 rounded-full text-sm font-medium ${
                                category.is_active
                                    ? 'bg-ice-100 text-navy-700'
                                    : 'bg-gray-200 text-gray-500'
                            }`}>
                                <div className={`w-2 h-2 rounded-full mr-2 ${
                                    category.is_active ? 'bg-ice-500' : 'bg-gray-500'
                                }`}></div>
                                {category.is_active ? 'Activa' : 'Inactiva'}
                            </span>
                        </div>
                    </div>
                </div>

                <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    {/* Columna Principal - Información */}
                    <div className="lg:col-span-2 space-y-6">
                        {/* Información Básica */}
                        <div className="bg-surface rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                            <div className="border-b border-gray-200 bg-ice-50 px-6 py-4">
                                <div className="flex items-center space-x-3">
                                    <div className="p-2 bg-ice-100 rounded-lg">
                                        <svg className="w-5 h-5 text-navy-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                    </div>
                                    <h3 className="text-lg font-medium text-graphite">Información Básica</h3>
                                </div>
                            </div>
                            
                            <div className="p-6">
                                <div className="space-y-4">
                                    <div>
                                        <dt className="text-sm font-medium text-gray-500">Nombre</dt>
                                        <dd className="mt-1 text-lg font-semibold text-graphite">{category.name}</dd>
                                    </div>
                                    
                                    <div>
                                        <dt className="text-sm font-medium text-gray-500">Descripción</dt>
                                        <dd className="mt-1 text-graphite/85">
                                            {category.description || (
                                                <span className="text-gray-500 italic">Sin descripción</span>
                                            )}
                                        </dd>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {/* Jerarquía y Organización */}
                        <div className="bg-surface rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                            <div className="border-b border-gray-200 bg-ice-50 px-6 py-4">
                                <div className="flex items-center space-x-3">
                                    <div className="p-2 bg-ice-100 rounded-lg">
                                        <svg className="w-5 h-5 text-navy-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                                        </svg>
                                    </div>
                                    <h3 className="text-lg font-medium text-graphite">Jerarquía y Organización</h3>
                                </div>
                            </div>
                            
                            <div className="p-6">
                                <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div className="bg-ice-50 rounded-lg p-4">
                                        <div className="flex items-center justify-between">
                                            <div>
                                                <dt className="text-sm font-medium text-graphite/75">Tipo de Categoría</dt>
                                                <dd className="text-lg font-bold text-navy-700">
                                                    {category.parent ? 'Subcategoría' : 'Categoría Principal'}
                                                </dd>
                                            </div>
                                            <div className="p-3 bg-ice-100 rounded-full">
                                                <svg className="w-6 h-6 text-navy-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d={category.parent ? "M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" : "M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 100 4m0-4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 100 4m0-4v2m0-6V4"} />
                                                </svg>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div className="bg-ice-50 rounded-lg p-4">
                                        <div className="flex items-center justify-between">
                                            <div>
                                                <dt className="text-sm font-medium text-graphite/75">Orden de Visualización</dt>
                                                <dd className="text-lg font-bold text-navy-700">
                                                    #{category.sort_order}
                                                </dd>
                                            </div>
                                            <div className="p-3 bg-ice-100 rounded-full">
                                                <svg className="w-6 h-6 text-navy-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4" />
                                                </svg>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                {category.parent && (
                                    <div className="mt-6">
                                        <dt className="text-sm font-medium text-gray-500 mb-2">Categoría Padre</dt>
                                        <div className="flex items-center space-x-2">
                                            <span className="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-ice-100 text-navy-900">
                                                {category.parent.name}
                                            </span>
                                            <svg className="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 5l7 7-7 7" />
                                            </svg>
                                            <span className="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-ice-100 text-navy-900">
                                                {category.name}
                                            </span>
                                        </div>
                                    </div>
                                )}
                            </div>
                        </div>

                        {/* Subcategorías */}
                        {category.children && category.children.length > 0 && (
                            <div className="bg-surface rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                                <div className="border-b border-gray-200 bg-ice-50 px-6 py-4">
                                    <div className="flex items-center justify-between">
                                        <div className="flex items-center space-x-3">
                                            <div className="p-2 bg-ice-100 rounded-lg">
                                                <svg className="w-5 h-5 text-navy-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                                                </svg>
                                            </div>
                                            <h3 className="text-lg font-medium text-graphite">Subcategorías ({category.children.length})</h3>
                                        </div>
                                        <Link
                                            href={route('admin.categories.create')}
                                            className="inline-flex items-center px-3 py-1 text-xs font-medium text-navy-700 bg-ice-100 rounded-full hover:bg-ice-100 transition-colors"
                                        >
                                            Nueva
                                        </Link>
                                    </div>
                                </div>
                                
                                <div className="p-6">
                                    <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                                        {category.children.map(child => (
                                            <Link 
                                                key={child.id} 
                                                href={route('admin.categories.show', child.id)}
                                                className="flex items-center p-3 bg-ice-50 rounded-lg hover:bg-ice-100 transition-colors group"
                                            >
                                                <div className="flex-1">
                                                    <span className="text-sm font-medium text-navy-900 group-hover:text-navy-700">
                                                        {child.name}
                                                    </span>
                                                    <span className={`ml-2 inline-flex items-center px-2 py-1 rounded-full text-xs font-medium ${
                                                        child.is_active 
                                                            ? 'bg-ice-100 text-navy-900'
                                                            : 'bg-ice-100 text-navy-900'
                                                    }`}>
                                                        {child.is_active ? 'Activa' : 'Inactiva'}
                                                    </span>
                                                </div>
                                                <svg className="w-4 h-4 text-navy-700 group-hover:text-navy-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 5l7 7-7 7" />
                                                </svg>
                                            </Link>
                                        ))}
                                    </div>
                                </div>
                            </div>
                        )}

                        {/* Productos */}
                        {category.products && category.products.length > 0 && (
                            <div className="bg-surface rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                                <div className="border-b border-gray-200 bg-background px-6 py-4">
                                    <div className="flex items-center justify-between">
                                        <div className="flex items-center space-x-3">
                                            <div className="p-2 bg-ice-100 rounded-lg">
                                                <svg className="w-5 h-5 text-navy-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                                                </svg>
                                            </div>
                                            <h3 className="text-lg font-medium text-graphite">Productos ({category.products.length})</h3>
                                        </div>
                                        <Link
                                            href="#"
                                            className="inline-flex items-center px-3 py-1 text-xs font-medium text-navy-700 bg-ice-100 rounded-full hover:bg-ice-100 transition-colors"
                                        >
                                            Nuevo
                                        </Link>
                                    </div>
                                </div>
                                
                                <div className="p-6">
                                    <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                                        {category.products.map((product) => (
                                            <div key={product.id} className="bg-ice-50 rounded-lg p-4 hover:bg-ice-100 transition-colors">
                                                <div className="flex items-start space-x-3">
                                                    {product.primary_image && (
                                                        <div className="flex-shrink-0">
                                                            <img
                                                                className="h-12 w-12 rounded-lg object-cover"
                                                                src={getProductImageUrl(product.primary_image)}
                                                                alt={product.title}
                                                            />
                                                        </div>
                                                    )}
                                                    <div className="flex-1 min-w-0">
                                                        <p className="text-sm font-medium text-graphite truncate">
                                                            {product.title}
                                                        </p>
                                                        <p className="text-sm text-navy-700 font-semibold">
                                                            ${product.price}
                                                        </p>
                                                        <p className="text-xs text-gray-500">
                                                            Stock: {product.stock}
                                                        </p>
                                                        <div className="mt-1">
                                                            <span className={`inline-flex items-center px-2 py-1 rounded-full text-xs font-medium ${
                                                                product.is_active 
                                                                    ? 'bg-ice-100 text-navy-900'
                                                                    : 'bg-ice-100 text-navy-900'
                                                            }`}>
                                                                {product.is_active ? 'Activo' : 'Inactivo'}
                                                            </span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            </div>
                        )}
                    </div>

                    {/* Columna Lateral - Acciones */}
                    <div className="lg:col-span-1">
                        <div className="bg-surface rounded-xl shadow-sm border border-gray-200 overflow-hidden sticky top-6">
                            <div className="border-b border-gray-200 bg-background px-6 py-4">
                                <div className="flex items-center space-x-3">
                                    <div className="p-2 bg-background rounded-lg">
                                        <svg className="w-5 h-5 text-graphite/75" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 100 4m0-4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 100 4m0-4v2m0-6V4" />
                                        </svg>
                                    </div>
                                    <h3 className="text-lg font-medium text-graphite">Acciones Rápidas</h3>
                                </div>
                            </div>
                            
                            <div className="p-6 space-y-4">
                                <button
                                    onClick={toggleStatus}
                                    disabled={processing}
                                    className={`flex items-center justify-center w-full px-4 py-3 text-sm font-medium rounded-lg transition-colors ${
                                        category.is_active 
                                            ? 'bg-ice-100 text-navy-900 hover:bg-ice-100'
                                            : 'bg-ice-100 text-navy-900 hover:bg-ice-100'
                                    } ${processing ? 'opacity-50 cursor-not-allowed' : ''}`}
                                >
                                    <svg className="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d={category.is_active ? "M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.878 9.878L3 3m6.878 6.878L21 21" : "M15 12a3 3 0 11-6 0 3 3 0 016 0z M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"} />
                                    </svg>
                                    {processing 
                                        ? 'Procesando...' 
                                        : category.is_active 
                                            ? 'Desactivar' 
                                            : 'Activar'
                                    }
                                </button>
                                
                                <Link
                                    href={route('admin.categories.edit', category.id)}
                                    className="flex items-center justify-center w-full px-4 py-3 text-sm font-medium text-navy-700 bg-ice-100 rounded-lg hover:bg-ice-100 transition-colors"
                                >
                                    <svg className="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                    </svg>
                                    Editar Categoría
                                </Link>
                                
                                <Link
                                    href={route('admin.categories.index')}
                                    className="flex items-center justify-center w-full px-4 py-3 text-sm font-medium text-graphite/75 bg-background rounded-lg hover:bg-gray-200 transition-colors"
                                >
                                    <svg className="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M11 17l-5-5m0 0l5-5m-5 5h12" />
                                    </svg>
                                    Volver al Listado
                                </Link>
                            </div>
                        </div>
                    </div>
                </div>

                {/* Estado Vacío */}
                {(!category.children || category.children.length === 0) && (!category.products || category.products.length === 0) && (
                    <div className="rounded-xl border border-gray-200 bg-background p-8">
                        <div className="text-center">
                            <div className="mx-auto h-16 w-16 bg-background rounded-full flex items-center justify-center mb-4">
                                <svg className="h-8 w-8 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                                </svg>
                            </div>
                            <h3 className="text-lg font-medium text-graphite mb-2">Categoría sin contenido</h3>
                            <p className="text-graphite/75 mb-6">
                                Esta categoría aún no tiene subcategorías ni productos asociados.
                            </p>
                            <div className="flex flex-col sm:flex-row gap-3 justify-center">
                                <Link
                                    href={route('admin.categories.create')}
                                    className="inline-flex items-center px-4 py-2 bg-storefront text-white text-sm font-medium rounded-lg hover:brightness-90 transition-colors"
                                >
                                    <svg className="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                                    </svg>
                                    Crear Subcategoría
                                </Link>
                                <Link
                                    href="#"
                                    className="inline-flex items-center px-4 py-2 bg-surface border border-gray-200 text-graphite/85 text-sm font-medium rounded-lg hover:bg-ice-50 transition-colors"
                                >
                                    <svg className="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                                    </svg>
                                    Crear Producto
                                </Link>
                            </div>
                        </div>
                    </div>
                )}
            </div>
        </AdminLayout>
    );
}
