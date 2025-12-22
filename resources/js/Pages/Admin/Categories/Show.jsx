import React from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';

export default function Show({ category }) {
    const { patch, processing } = useForm();

    const toggleStatus = () => {
        patch(route('admin.categories.toggle-status', category.id));
    };

    return (
        <AdminLayout
            header={
                <div className="flex items-center justify-between">
                    <h1 className="text-2xl font-semibold text-gray-900">
                        Categoría: {category.name}
                    </h1>
                    <div className="flex space-x-3">
                        <button
                            onClick={toggleStatus}
                            disabled={processing}
                            className={`inline-flex items-center px-4 py-2 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest focus:outline-none focus:ring focus:ring-offset-2 disabled:opacity-25 transition ${
                                category.is_active
                                    ? 'bg-red-600 hover:bg-red-700 active:bg-red-900 focus:border-red-900 focus:ring-red-300'
                                    : 'bg-green-600 hover:bg-green-700 active:bg-green-900 focus:border-green-900 focus:ring-green-300'
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
                            className="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 active:bg-blue-900 focus:outline-none focus:border-blue-900 focus:ring focus:ring-blue-300 disabled:opacity-25 transition"
                        >
                            Editar
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
            <Head title={`${category.name} - Admin`} />

            <div className="space-y-6">
                {/* Category Information */}
                <div className="bg-white overflow-hidden shadow rounded-lg">
                    <div className="px-4 py-5 sm:p-6">
                        <h3 className="text-lg leading-6 font-medium text-gray-900 mb-4">
                            Información de la Categoría
                        </h3>
                        <dl className="grid grid-cols-1 gap-x-4 gap-y-6 sm:grid-cols-2">
                            <div>
                                <dt className="text-sm font-medium text-gray-500">Nombre</dt>
                                <dd className="mt-1 text-sm text-gray-900">{category.name}</dd>
                            </div>
                            <div>
                                <dt className="text-sm font-medium text-gray-500">Slug</dt>
                                <dd className="mt-1 text-sm text-gray-900 font-mono">{category.slug}</dd>
                            </div>
                            <div>
                                <dt className="text-sm font-medium text-gray-500">Estado</dt>
                                <dd className="mt-1">
                                    <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${
                                        category.is_active 
                                            ? 'bg-green-100 text-green-800' 
                                            : 'bg-red-100 text-red-800'
                                    }`}>
                                        {category.is_active ? 'Activa' : 'Inactiva'}
                                    </span>
                                </dd>
                            </div>
                            <div>
                                <dt className="text-sm font-medium text-gray-500">Orden</dt>
                                <dd className="mt-1 text-sm text-gray-900">{category.sort_order}</dd>
                            </div>
                            <div>
                                <dt className="text-sm font-medium text-gray-500">Tipo</dt>
                                <dd className="mt-1">
                                    {category.parent ? (
                                        <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                            Subcategoría
                                        </span>
                                    ) : (
                                        <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                            Categoría Principal
                                        </span>
                                    )}
                                </dd>
                            </div>
                            {category.parent && (
                                <div>
                                    <dt className="text-sm font-medium text-gray-500">Categoría Padre</dt>
                                    <dd className="mt-1 text-sm text-gray-900">
                                        <Link
                                            href={route('admin.categories.show', category.parent.id)}
                                            className="text-blue-600 hover:text-blue-900"
                                        >
                                            {category.parent.name}
                                        </Link>
                                    </dd>
                                </div>
                            )}
                            <div>
                                <dt className="text-sm font-medium text-gray-500">Fecha de creación</dt>
                                <dd className="mt-1 text-sm text-gray-900">{category.created_at}</dd>
                            </div>
                            <div>
                                <dt className="text-sm font-medium text-gray-500">Última actualización</dt>
                                <dd className="mt-1 text-sm text-gray-900">{category.updated_at}</dd>
                            </div>
                            {category.description && (
                                <div className="sm:col-span-2">
                                    <dt className="text-sm font-medium text-gray-500">Descripción</dt>
                                    <dd className="mt-1 text-sm text-gray-900">{category.description}</dd>
                                </div>
                            )}
                        </dl>
                    </div>
                </div>

                {/* Subcategories */}
                {category.children && category.children.length > 0 && (
                    <div className="bg-white overflow-hidden shadow rounded-lg">
                        <div className="px-4 py-5 sm:p-6">
                            <div className="flex items-center justify-between mb-4">
                                <h3 className="text-lg leading-6 font-medium text-gray-900">
                                    Subcategorías ({category.children.length})
                                </h3>
                                <Link
                                    href={route('admin.categories.create')}
                                    className="inline-flex items-center px-3 py-1 border border-transparent text-sm font-medium rounded-md text-blue-700 bg-blue-100 hover:bg-blue-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                                >
                                    Nueva Subcategoría
                                </Link>
                            </div>
                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                {category.children.map((child) => (
                                    <div key={child.id} className="relative rounded-lg border border-gray-300 bg-white px-4 py-3 shadow-sm hover:border-gray-400 focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500">
                                        <div className="flex items-center justify-between">
                                            <div className="flex-1 min-w-0">
                                                <Link
                                                    href={route('admin.categories.show', child.id)}
                                                    className="focus:outline-none"
                                                >
                                                    <p className="text-sm font-medium text-gray-900 truncate">
                                                        {child.name}
                                                    </p>
                                                    <p className="text-sm text-gray-500">
                                                        {child.products_count} {child.products_count === 1 ? 'producto' : 'productos'}
                                                    </p>
                                                </Link>
                                            </div>
                                            <div className="flex-shrink-0">
                                                <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${
                                                    child.is_active 
                                                        ? 'bg-green-100 text-green-800' 
                                                        : 'bg-red-100 text-red-800'
                                                }`}>
                                                    {child.is_active ? 'Activa' : 'Inactiva'}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </div>
                    </div>
                )}

                {/* Products */}
                {category.products && category.products.length > 0 && (
                    <div className="bg-white overflow-hidden shadow rounded-lg">
                        <div className="px-4 py-5 sm:p-6">
                            <div className="flex items-center justify-between mb-4">
                                <h3 className="text-lg leading-6 font-medium text-gray-900">
                                    Productos ({category.products.length})
                                </h3>
                                <Link
                                    href="#"
                                    className="inline-flex items-center px-3 py-1 border border-transparent text-sm font-medium rounded-md text-blue-700 bg-blue-100 hover:bg-blue-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                                >
                                    Nuevo Producto
                                </Link>
                            </div>
                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                {category.products.map((product) => (
                                    <div key={product.id} className="relative rounded-lg border border-gray-300 bg-white px-4 py-3 shadow-sm hover:border-gray-400">
                                        <div className="flex items-start space-x-3">
                                            {product.primary_image && (
                                                <div className="flex-shrink-0">
                                                    <img
                                                        className="h-12 w-12 rounded-md object-cover"
                                                        src={`/storage/${product.primary_image}`}
                                                        alt={product.title}
                                                    />
                                                </div>
                                            )}
                                            <div className="flex-1 min-w-0">
                                                <p className="text-sm font-medium text-gray-900 truncate">
                                                    {product.title}
                                                </p>
                                                <p className="text-sm text-gray-500">
                                                    ${product.price}
                                                </p>
                                                <p className="text-sm text-gray-500">
                                                    Stock: {product.stock}
                                                </p>
                                                <div className="mt-1">
                                                    <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${
                                                        product.is_active 
                                                            ? 'bg-green-100 text-green-800' 
                                                            : 'bg-red-100 text-red-800'
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

                {/* Empty states */}
                {(!category.children || category.children.length === 0) && (!category.products || category.products.length === 0) && (
                    <div className="bg-white overflow-hidden shadow rounded-lg">
                        <div className="px-4 py-5 sm:p-6">
                            <div className="text-center">
                                <svg className="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                                </svg>
                                <h3 className="mt-2 text-sm font-medium text-gray-900">Sin contenido</h3>
                                <p className="mt-1 text-sm text-gray-500">
                                    Esta categoría aún no tiene subcategorías ni productos.
                                </p>
                                <div className="mt-6 flex justify-center space-x-3">
                                    <Link
                                        href={route('admin.categories.create')}
                                        className="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                                    >
                                        Crear Subcategoría
                                    </Link>
                                    <Link
                                        href="#"
                                        className="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                                    >
                                        Crear Producto
                                    </Link>
                                </div>
                            </div>
                        </div>
                    </div>
                )}
            </div>
        </AdminLayout>
    );
}