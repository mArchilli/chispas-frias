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
        router.post(route('admin.products.toggle-status', product.id));
    };

    const toggleFeatured = () => {
        router.post(route('admin.products.toggle-featured', product.id));
    };

    const setPrimaryImage = (imageId) => {
        router.post(route('admin.products.set-primary-image', [product.id, imageId]));
    };

    return (
        <AdminLayout>
            <Head title={`Producto: ${product.title}`} />
            
            <div className="py-12">
                <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
                    <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div className="p-6">
                            {/* Header */}
                            <div className="flex justify-between items-start mb-6">
                                <div>
                                    <h1 className="text-2xl font-bold text-gray-900 mb-2">{product.title}</h1>
                                    <div className="flex items-center space-x-4 text-sm text-gray-500">
                                        <span>SKU: {product.sku || 'No asignado'}</span>
                                        <span>•</span>
                                        <span>Creado: {product.created_at}</span>
                                        <span>•</span>
                                        <span>Actualizado: {product.updated_at}</span>
                                    </div>
                                </div>
                                
                                <div className="flex space-x-4">
                                    <Link href={route('admin.products.index')}>
                                        <SecondaryButton>Volver al Listado</SecondaryButton>
                                    </Link>
                                    <Link href={route('admin.products.edit', product.id)}>
                                        <PrimaryButton>Editar Producto</PrimaryButton>
                                    </Link>
                                </div>
                            </div>

                            <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                                {/* Información del Producto */}
                                <div className="space-y-6">
                                    {/* Detalles Básicos */}
                                    <div className="bg-gray-50 p-4 rounded-lg">
                                        <h3 className="text-lg font-medium text-gray-900 mb-4">Información Básica</h3>
                                        
                                        <div className="grid grid-cols-2 gap-4">
                                            <div>
                                                <dt className="text-sm font-medium text-gray-500">Precio</dt>
                                                <dd className="text-xl font-bold text-green-600">{product.formatted_price}</dd>
                                            </div>
                                            
                                            <div>
                                                <dt className="text-sm font-medium text-gray-500">Stock</dt>
                                                <dd className={`text-xl font-bold ${product.in_stock ? 'text-green-600' : 'text-red-600'}`}>
                                                    {product.stock} unidades
                                                </dd>
                                            </div>
                                            
                                            <div>
                                                <dt className="text-sm font-medium text-gray-500">Estado</dt>
                                                <dd className="mt-1">
                                                    <span className={`inline-flex px-2 py-1 text-xs font-semibold rounded-full ${
                                                        product.is_active 
                                                            ? 'bg-green-100 text-green-800' 
                                                            : 'bg-red-100 text-red-800'
                                                    }`}>
                                                        {product.is_active ? 'Activo' : 'Inactivo'}
                                                    </span>
                                                </dd>
                                            </div>
                                            
                                            <div>
                                                <dt className="text-sm font-medium text-gray-500">Destacado</dt>
                                                <dd className="mt-1">
                                                    <span className={`inline-flex px-2 py-1 text-xs font-semibold rounded-full ${
                                                        product.is_featured 
                                                            ? 'bg-yellow-100 text-yellow-800' 
                                                            : 'bg-gray-100 text-gray-800'
                                                    }`}>
                                                        {product.is_featured ? 'Destacado' : 'Normal'}
                                                    </span>
                                                </dd>
                                            </div>
                                        </div>
                                    </div>

                                    {/* Categoría */}
                                    <div className="bg-gray-50 p-4 rounded-lg">
                                        <h3 className="text-lg font-medium text-gray-900 mb-4">Categoría</h3>
                                        <div className="flex items-center space-x-2 text-sm">
                                            {product.category.parent && (
                                                <>
                                                    <span className="bg-blue-100 text-blue-800 px-2 py-1 rounded">
                                                        {product.category.parent.name}
                                                    </span>
                                                    <span className="text-gray-400">→</span>
                                                </>
                                            )}
                                            <span className="bg-indigo-100 text-indigo-800 px-2 py-1 rounded">
                                                {product.category.name}
                                            </span>
                                        </div>
                                    </div>

                                    {/* Descripción */}
                                    <div className="bg-gray-50 p-4 rounded-lg">
                                        <h3 className="text-lg font-medium text-gray-900 mb-4">Descripción</h3>
                                        <p className="text-gray-700 whitespace-pre-wrap">{product.description}</p>
                                    </div>

                                    {/* Acciones */}
                                    <div className="bg-gray-50 p-4 rounded-lg">
                                        <h3 className="text-lg font-medium text-gray-900 mb-4">Acciones</h3>
                                        <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                            <button
                                                onClick={toggleStatus}
                                                className={`px-4 py-2 text-sm font-medium rounded-md ${product.is_active 
                                                    ? 'bg-yellow-100 text-yellow-800 hover:bg-yellow-200' 
                                                    : 'bg-green-100 text-green-800 hover:bg-green-200'
                                                }`}
                                            >
                                                {product.is_active ? 'Desactivar' : 'Activar'}
                                            </button>
                                            
                                            <button
                                                onClick={toggleFeatured}
                                                className={`px-4 py-2 text-sm font-medium rounded-md ${product.is_featured 
                                                    ? 'bg-gray-100 text-gray-800 hover:bg-gray-200' 
                                                    : 'bg-yellow-100 text-yellow-800 hover:bg-yellow-200'
                                                }`}
                                            >
                                                {product.is_featured ? 'Quitar Destacado' : 'Marcar Destacado'}
                                            </button>
                                            
                                            <button
                                                onClick={() => setShowDeleteModal(true)}
                                                className="px-4 py-2 text-sm font-medium text-red-600 bg-red-100 rounded-md hover:bg-red-200"
                                            >
                                                Eliminar Producto
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                {/* Imágenes */}
                                <div>
                                    <div className="bg-gray-50 p-4 rounded-lg">
                                        <h3 className="text-lg font-medium text-gray-900 mb-4">
                                            Imágenes ({product.images.length})
                                        </h3>
                                        
                                        {product.images.length > 0 ? (
                                            <div className="space-y-4">
                                                {/* Imagen Principal */}
                                                {product.images.find(img => img.is_primary) && (
                                                    <div className="relative">
                                                        <div className="absolute top-2 left-2 bg-green-500 text-white px-2 py-1 rounded text-xs font-medium z-10">
                                                            Imagen Principal
                                                        </div>
                                                        <img
                                                            src={product.images.find(img => img.is_primary).url}
                                                            alt={product.images.find(img => img.is_primary).alt_text}
                                                            className="w-full h-64 object-cover rounded-lg border-2 border-green-500"
                                                        />
                                                    </div>
                                                )}
                                                
                                                {/* Otras Imágenes */}
                                                {product.images.filter(img => !img.is_primary).length > 0 && (
                                                    <div>
                                                        <h4 className="text-sm font-medium text-gray-700 mb-2">Otras imágenes:</h4>
                                                        <div className="grid grid-cols-3 gap-2">
                                                            {product.images.filter(img => !img.is_primary).map(image => (
                                                                <div key={image.id} className="relative group">
                                                                    <img
                                                                        src={image.url}
                                                                        alt={image.alt_text}
                                                                        className="w-full h-20 object-cover rounded-lg border"
                                                                    />
                                                                    <div className="absolute inset-0 bg-black bg-opacity-50 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center">
                                                                        <button
                                                                            onClick={() => setPrimaryImage(image.id)}
                                                                            className="bg-white text-gray-800 px-2 py-1 rounded text-xs hover:bg-gray-100"
                                                                        >
                                                                            Hacer Principal
                                                                        </button>
                                                                    </div>
                                                                </div>
                                                            ))}
                                                        </div>
                                                    </div>
                                                )}
                                            </div>
                                        ) : (
                                            <div className="text-center py-8 text-gray-500">
                                                <div className="text-4xl mb-2">📷</div>
                                                <p>No hay imágenes para este producto</p>
                                                <Link 
                                                    href={route('admin.products.edit', product.id)}
                                                    className="text-indigo-600 hover:text-indigo-800 text-sm"
                                                >
                                                    Agregar imágenes
                                                </Link>
                                            </div>
                                        )}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {/* Modal de Confirmación para Eliminar Producto */}
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
        </AdminLayout>
    );
}

export default Show;