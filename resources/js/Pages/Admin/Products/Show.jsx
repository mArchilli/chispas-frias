import React, { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import AdminLayout from '../../../Layouts/AdminLayout';
import PrimaryButton from '../../../Components/PrimaryButton';
import SecondaryButton from '../../../Components/SecondaryButton';
import DeleteConfirmationModal from '../../../Components/DeleteConfirmationModal';

function Show({ product }) {
    const [showDeleteModal, setShowDeleteModal] = useState(false);
    const [isDeleting, setIsDeleting] = useState(false);

    const handleDelete = () => {
        setIsDeleting(true);
        router.delete(route('admin.products.destroy', product.id), {
            onSuccess: () => {
                setShowDeleteModal(false);
                setIsDeleting(false);
            },
            onError: () => {
                setIsDeleting(false);
            }
        });
    };

    const closeDeleteModal = () => {
        if (!isDeleting) {
            setShowDeleteModal(false);
        }
    };

    const toggleStatus = () => {
        router.patch(route('admin.products.toggle-status', product.id));
    };

    const toggleFeatured = () => {
        router.patch(route('admin.products.toggle-featured', product.id));
    };

    const setPrimaryImage = (imageId) => {
        router.patch(route('admin.products.set-primary-image', [product.id, imageId]));
    };

    const renderMediaContent = (media, isPrimary = false) => {
        const baseClasses = isPrimary 
            ? "w-full h-64 object-cover rounded-lg border-2 border-green-200 shadow-md group-hover:shadow-lg transition-shadow"
            : "w-full h-20 object-cover rounded-lg border border-gray-200 group-hover:border-blue-300 transition-colors";
            
        if (media.type === 'video') {
            return (
                <>
                    <video
                        src={media.url}
                        className={baseClasses}
                        controls={isPrimary}
                        muted
                    />
                    {!isPrimary && (
                        <div className="absolute bottom-1 right-1 bg-black bg-opacity-75 text-white text-xs px-1 py-0.5 rounded">
                            📹
                        </div>
                    )}
                </>
            );
        }
        
        return (
            <img
                src={media.url}
                alt={media.alt_text}
                className={baseClasses}
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
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                            </svg>
                        </div>
                        <div>
                            <h1 className="text-2xl font-semibold text-gray-900">Detalle del Producto</h1>
                            <p className="text-sm text-gray-600">Visualiza y gestiona la información del producto</p>
                        </div>
                    </div>
                    <div className="flex space-x-3">
                        <Link href={route('admin.products.index')}>
                            <SecondaryButton>Volver al Listado</SecondaryButton>
                        </Link>
                        <Link href={route('admin.products.edit', product.id)}>
                            <PrimaryButton>Editar Producto</PrimaryButton>
                        </Link>
                    </div>
                </div>
            }
        >
            <Head title={`Producto: ${product.title}`} />
            
            <div className="max-w-7xl mx-auto space-y-6">
                {/* Header del Producto */}
                <div className="bg-gradient-to-r from-blue-50 to-indigo-50 rounded-xl p-6 border border-blue-100">
                    <div className="flex flex-col md:flex-row md:items-start md:justify-between space-y-4 md:space-y-0">
                        <div className="flex-1">
                            <h1 className="text-3xl font-bold text-gray-900 mb-3">{product.title}</h1>
                            <div className="flex flex-wrap items-center gap-4 text-sm text-gray-600">
                                <div className="flex items-center">
                                    <svg className="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M7 20l4-16m2 16l4-16M6 9h14M4 15h14" />
                                    </svg>
                                    SKU: {product.sku || 'No asignado'}
                                </div>
                                <div className="flex items-center">
                                    <svg className="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M8 7V3a2 2 0 012-2h4a2 2 0 012 2v4m0 0V7a2 2 0 012 2v6a2 2 0 01-2 2H6a2 2 0 01-2-2V9a2 2 0 012-2m8 0V7a2 2 0 00-2-2H10a2 2 0 00-2 2v2m0 4h4" />
                                    </svg>
                                    Creado: {product.created_at}
                                </div>
                                <div className="flex items-center">
                                    <svg className="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                    </svg>
                                    Actualizado: {product.updated_at}
                                </div>
                            </div>
                        </div>
                        <div className="flex items-center space-x-3">
                            {product.is_featured && (
                                <span className="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-yellow-100 text-yellow-800">
                                    <svg className="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z" />
                                    </svg>
                                    Destacado
                                </span>
                            )}
                            <span className={`inline-flex items-center px-3 py-1 rounded-full text-sm font-medium ${
                                product.is_active 
                                    ? 'bg-green-100 text-green-800' 
                                    : 'bg-red-100 text-red-800'
                            }`}>
                                <div className={`w-2 h-2 rounded-full mr-2 ${
                                    product.is_active ? 'bg-green-500' : 'bg-red-500'
                                }`}></div>
                                {product.is_active ? 'Activo' : 'Inactivo'}
                            </span>
                        </div>
                    </div>
                </div>

                <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    {/* Columna Principal - Información */}
                    <div className="lg:col-span-2 space-y-6">
                        {/* Información Básica */}
                        <div className="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                            <div className="bg-gradient-to-r from-green-50 to-emerald-50 px-6 py-4 border-b border-gray-100">
                                <div className="flex items-center space-x-3">
                                    <div className="p-2 bg-green-100 rounded-lg">
                                        <svg className="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                    </div>
                                    <h3 className="text-lg font-medium text-gray-900">Información Básica</h3>
                                </div>
                            </div>
                            
                            <div className="p-6">
                                <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div className="bg-green-50 rounded-lg p-4">
                                        <div className="flex items-center justify-between">
                                            <div>
                                                <dt className="text-sm font-medium text-gray-600">Precio de Venta</dt>
                                                <dd className="text-2xl font-bold text-green-600">{product.formatted_price}</dd>
                                            </div>
                                            <div className="p-3 bg-green-100 rounded-full">
                                                <svg className="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1" />
                                                </svg>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div className={`rounded-lg p-4 ${
                                        product.in_stock ? 'bg-blue-50' : 'bg-red-50'
                                    }`}>
                                        <div className="flex items-center justify-between">
                                            <div>
                                                <dt className="text-sm font-medium text-gray-600">Stock Disponible</dt>
                                                <dd className={`text-2xl font-bold ${
                                                    product.in_stock ? 'text-blue-600' : 'text-red-600'
                                                }`}>
                                                    {product.stock} unidades
                                                </dd>
                                            </div>
                                            <div className={`p-3 rounded-full ${
                                                product.in_stock ? 'bg-blue-100' : 'bg-red-100'
                                            }`}>
                                                <svg className={`w-6 h-6 ${
                                                    product.in_stock ? 'text-blue-600' : 'text-red-600'
                                                }`} fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                                                </svg>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {/* Categoría */}
                        <div className="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                            <div className="bg-gradient-to-r from-purple-50 to-indigo-50 px-6 py-4 border-b border-gray-100">
                                <div className="flex items-center space-x-3">
                                    <div className="p-2 bg-purple-100 rounded-lg">
                                        <svg className="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                                        </svg>
                                    </div>
                                    <h3 className="text-lg font-medium text-gray-900">Categorización</h3>
                                </div>
                            </div>
                            <div className="p-6">
                                <div className="flex items-center space-x-2">
                                    {product.category.parent && (
                                        <>
                                            <span className="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-blue-100 text-blue-800">
                                                {product.category.parent.name}
                                            </span>
                                            <svg className="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 5l7 7-7 7" />
                                            </svg>
                                        </>
                                    )}
                                    <span className="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-indigo-100 text-indigo-800">
                                        {product.category.name}
                                    </span>
                                </div>
                            </div>
                        </div>

                        {/* Descripción */}
                        <div className="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                            <div className="bg-gradient-to-r from-orange-50 to-amber-50 px-6 py-4 border-b border-gray-100">
                                <div className="flex items-center space-x-3">
                                    <div className="p-2 bg-orange-100 rounded-lg">
                                        <svg className="w-5 h-5 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M4 6h16M4 12h16M4 18h7" />
                                        </svg>
                                    </div>
                                    <h3 className="text-lg font-medium text-gray-900">Descripción del Producto</h3>
                                </div>
                            </div>
                            <div className="p-6">
                                <p className="text-gray-700 leading-relaxed whitespace-pre-wrap">{product.description}</p>
                            </div>
                        </div>

                        {/* Panel de Acciones */}
                        <div className="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                            <div className="bg-gradient-to-r from-gray-50 to-slate-50 px-6 py-4 border-b border-gray-100">
                                <div className="flex items-center space-x-3">
                                    <div className="p-2 bg-gray-100 rounded-lg">
                                        <svg className="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 100 4m0-4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 100 4m0-4v2m0-6V4" />
                                        </svg>
                                    </div>
                                    <h3 className="text-lg font-medium text-gray-900">Acciones Rápidas</h3>
                                </div>
                            </div>
                            <div className="p-6">
                                <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                                    <button
                                        onClick={toggleStatus}
                                        className={`flex items-center justify-center px-4 py-3 text-sm font-medium rounded-lg transition-colors ${
                                            product.is_active 
                                                ? 'bg-yellow-100 text-yellow-800 hover:bg-yellow-200' 
                                                : 'bg-green-100 text-green-800 hover:bg-green-200'
                                        }`}
                                    >
                                        <svg className="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d={product.is_active ? "M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.878 9.878L3 3m6.878 6.878L21 21" : "M15 12a3 3 0 11-6 0 3 3 0 016 0z M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"} />
                                        </svg>
                                        {product.is_active ? 'Desactivar' : 'Activar'}
                                    </button>
                                    
                                    <button
                                        onClick={toggleFeatured}
                                        className={`flex items-center justify-center px-4 py-3 text-sm font-medium rounded-lg transition-colors ${
                                            product.is_featured 
                                                ? 'bg-gray-100 text-gray-800 hover:bg-gray-200' 
                                                : 'bg-yellow-100 text-yellow-800 hover:bg-yellow-200'
                                        }`}
                                    >
                                        <svg className="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z" />
                                        </svg>
                                        {product.is_featured ? 'Quitar Destacado' : 'Destacar'}
                                    </button>
                                    
                                    <button
                                        onClick={() => setShowDeleteModal(true)}
                                        className="flex items-center justify-center px-4 py-3 text-sm font-medium text-red-600 bg-red-100 rounded-lg hover:bg-red-200 transition-colors"
                                    >
                                        <svg className="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                        Eliminar
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* Columna Lateral - Imágenes */}
                    <div className="lg:col-span-1">
                        <div className="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden sticky top-6">
                            <div className="bg-gradient-to-r from-pink-50 to-rose-50 px-6 py-4 border-b border-gray-100">
                                <div className="flex items-center space-x-3">
                                    <div className="p-2 bg-pink-100 rounded-lg">
                                        <svg className="w-5 h-5 text-pink-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                        </svg>
                                    </div>
                                    <h3 className="text-lg font-medium text-gray-900">
                                        Galería ({product.images.length})
                                    </h3>
                                </div>
                            </div>
                            
                            <div className="p-6">
                                {product.images.length > 0 ? (
                                    <div className="space-y-4">
                                        {/* Imagen/Video Principal */}
                                        {product.images.find(img => img.is_primary) && (
                                            <div className="relative group">
                                                <div className="absolute -top-2 -left-2 z-10">
                                                    <span className="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-500 text-white shadow-lg">
                                                        <svg className="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M5 13l4 4L19 7" />
                                                        </svg>
                                                        Principal
                                                    </span>
                                                </div>
                                                {renderMediaContent(product.images.find(img => img.is_primary), true)}
                                            </div>
                                        )}
                                        
                                        {/* Otras Imágenes/Videos */}
                                        {product.images.filter(img => !img.is_primary).length > 0 && (
                                            <div>
                                                <h4 className="text-sm font-medium text-gray-700 mb-3 flex items-center">
                                                    <svg className="w-4 h-4 mr-2 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                                                    </svg>
                                                    Multimedia adicional:
                                                </h4>
                                                <div className="grid grid-cols-2 gap-3">
                                                    {product.images.filter(img => !img.is_primary).map(image => (
                                                        <div key={image.id} className="relative group">
                                                            {renderMediaContent(image)}
                                                            <div className="absolute inset-0 bg-black bg-opacity-50 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center rounded-lg">
                                                                <button
                                                                    onClick={() => setPrimaryImage(image.id)}
                                                                    className="inline-flex items-center px-2 py-1 bg-white text-gray-800 text-xs font-medium rounded hover:bg-gray-100 transition-colors"
                                                                >
                                                                    <svg className="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M5 13l4 4L19 7" />
                                                                    </svg>
                                                                    Principal
                                                                </button>
                                                            </div>
                                                        </div>
                                                    ))}
                                                </div>
                                            </div>
                                        )}
                                    </div>
                                ) : (
                                    <div className="text-center py-8">
                                        <div className="mx-auto w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mb-4">
                                            <svg className="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                            </svg>
                                        </div>
                                        <h3 className="text-sm font-medium text-gray-900 mb-1">Sin multimedia</h3>
                                        <p className="text-xs text-gray-500 mb-4">
                                            Este producto no tiene imágenes ni videos
                                        </p>
                                        <Link 
                                            href={route('admin.products.edit', product.id)}
                                            className="inline-flex items-center px-3 py-2 border border-transparent text-xs font-medium rounded-md text-blue-700 bg-blue-100 hover:bg-blue-200 transition-colors"
                                        >
                                            <svg className="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 4v16m8-8H4" />
                                            </svg>
                                            Agregar imágenes
                                        </Link>
                                    </div>
                                )}
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {/* Modal de Confirmación para Eliminar Producto */}
            {showDeleteModal && (
                <DeleteConfirmationModal
                    show={showDeleteModal}
                    onClose={closeDeleteModal}
                    onConfirm={handleDelete}
                    title="¿Eliminar producto?"
                    message="Estás a punto de eliminar el siguiente producto:"
                    itemName={product.title}
                    warningMessage="Esta acción eliminará el producto y todas sus imágenes asociadas."
                    confirmText="Eliminar Producto"
                    processing={isDeleting}
                />
            )}
        </AdminLayout>
    );
}

export default Show;