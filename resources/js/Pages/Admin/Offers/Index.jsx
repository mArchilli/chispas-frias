import React, { useState } from 'react';
import { Head } from '@inertiajs/react';
import { Link, useForm } from '@inertiajs/react';
import toast from 'react-hot-toast';
import AdminLayout from '../../../Layouts/AdminLayout';

export default function OffersIndex({ offers, products }) {
    const [isModalOpen, setIsModalOpen] = useState(false);
    const [isDeleteModalOpen, setIsDeleteModalOpen] = useState(false);
    const [editingOffer, setEditingOffer] = useState(null);
    const [deletingOffer, setDeletingOffer] = useState(null);
    
    const { data, setData, post, put, delete: destroy, processing, errors, reset } = useForm({
        product_id: '',
        offer_price: '',
        percentage_discount: '',
        start_date: '',
        end_date: '',
        is_active: true
    });

    const openModal = (offer = null) => {
        if (offer) {
            setEditingOffer(offer);
            setData({
                product_id: offer.product_id,
                offer_price: offer.offer_price,
                percentage_discount: offer.percentage_discount || '',
                start_date: offer.start_date || '',
                end_date: offer.end_date || '',
                is_active: offer.is_active
            });
        } else {
            setEditingOffer(null);
            reset();
        }
        setIsModalOpen(true);
    };

    const closeModal = () => {
        setIsModalOpen(false);
        setEditingOffer(null);
        reset();
    };

    const handleSubmit = (e) => {
        e.preventDefault();
        
        if (editingOffer) {
            put(`/admin/offers/${editingOffer.id}`, {
                onSuccess: () => {
                    toast.success('Oferta actualizada exitosamente');
                    closeModal();
                },
                onError: () => {
                    toast.error('Error al actualizar la oferta');
                }
            });
        } else {
            post('/admin/offers', {
                onSuccess: () => {
                    toast.success('Oferta creada exitosamente');
                    closeModal();
                },
                onError: () => {
                    toast.error('Error al crear la oferta');
                }
            });
        }
    };

    const handleDelete = (offer) => {
        setDeletingOffer(offer);
        setIsDeleteModalOpen(true);
    };

    const confirmDelete = () => {
        if (deletingOffer) {
            destroy(`/admin/offers/${deletingOffer.id}`, {
                onSuccess: () => {
                    toast.success('Oferta eliminada exitosamente');
                    setIsDeleteModalOpen(false);
                    setDeletingOffer(null);
                },
                onError: () => {
                    toast.error('Error al eliminar la oferta');
                    setIsDeleteModalOpen(false);
                    setDeletingOffer(null);
                }
            });
        }
    };

    const cancelDelete = () => {
        setIsDeleteModalOpen(false);
        setDeletingOffer(null);
    };

    const handleToggleStatus = (offer) => {
        post(`/admin/offers/${offer.id}/toggle-status`, {}, {
            onSuccess: () => {
                toast.success('Estado de la oferta actualizado');
            },
            onError: () => {
                toast.error('Error al actualizar el estado');
            }
        });
    };

    const formatDate = (date) => {
        if (!date) return 'Sin fecha';
        return new Date(date).toLocaleDateString();
    };

    const getOfferStatus = (offer) => {
        if (!offer.is_active) return 'Inactiva';
        
        const now = new Date();
        const startDate = offer.start_date ? new Date(offer.start_date) : null;
        const endDate = offer.end_date ? new Date(offer.end_date) : null;
        
        if (startDate && now < startDate) return 'Programada';
        if (endDate && now > endDate) return 'Expirada';
        return 'Activa';
    };

    const getStatusColor = (offer) => {
        const status = getOfferStatus(offer);
        switch (status) {
            case 'Activa': return 'bg-green-100 text-green-800';
            case 'Programada': return 'bg-yellow-100 text-yellow-800';
            case 'Expirada': return 'bg-red-100 text-red-800';
            case 'Inactiva': return 'bg-gray-100 text-gray-800';
            default: return 'bg-gray-100 text-gray-800';
        }
    };

    return (
        <AdminLayout>
            <Head title="Gestión de Ofertas" />
            
            <div className="p-6">
                <div className="sm:flex sm:items-center">
                    <div className="sm:flex-auto">
                        <h1 className="text-xl font-semibold text-gray-900">Gestión de Ofertas</h1>
                        <p className="mt-2 text-sm text-gray-700">
                            Administra las ofertas de productos de tu tienda.
                        </p>
                    </div>
                    <div className="mt-4 sm:mt-0 sm:ml-16 sm:flex-none">
                        <button
                            type="button"
                            onClick={() => openModal()}
                            className="inline-flex items-center justify-center rounded-md border border-transparent bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 sm:w-auto"
                        >
                            Nueva Oferta
                        </button>
                    </div>
                </div>

                {/* Ofertas Cards */}
                <div className="mt-8">
                    <div className="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                        {offers.data.map((offer) => (
                            <div key={offer.id} className="bg-white overflow-hidden shadow rounded-lg">
                                <div className="px-4 py-5 sm:p-6">
                                    {/* Header with product name and status */}
                                    <div className="flex items-center justify-between mb-4">
                                        <h3 className="text-lg font-medium text-gray-900 truncate">
                                            {offer.product.title}
                                        </h3>
                                        <span className={`inline-flex px-2 py-1 text-xs font-semibold rounded-full ${getStatusColor(offer)}`}>
                                            {getOfferStatus(offer)}
                                        </span>
                                    </div>

                                    {/* Price information */}
                                    <div className="space-y-3 mb-4">
                                        <div className="flex justify-between items-center">
                                            <span className="text-sm font-medium text-gray-500">Precio Regular:</span>
                                            <span className="text-sm text-gray-900 line-through">${offer.product.price}</span>
                                        </div>
                                        <div className="flex justify-between items-center">
                                            <span className="text-sm font-medium text-gray-500">Precio Oferta:</span>
                                            <span className="text-lg font-bold text-green-600">${offer.offer_price}</span>
                                        </div>
                                        <div className="flex justify-between items-center">
                                            <span className="text-sm font-medium text-gray-500">Descuento:</span>
                                            <span className="text-lg font-bold text-red-600">
                                                {offer.percentage_discount ? `${Math.round(offer.percentage_discount)}%` : 'N/A'}
                                            </span>
                                        </div>
                                    </div>

                                    {/* Date information */}
                                    <div className="space-y-2 mb-4 text-sm text-gray-600">
                                        <div className="flex justify-between">
                                            <span>Inicio:</span>
                                            <span>{formatDate(offer.start_date)}</span>
                                        </div>
                                        <div className="flex justify-between">
                                            <span>Fin:</span>
                                            <span>{formatDate(offer.end_date)}</span>
                                        </div>
                                    </div>

                                    {/* Actions */}
                                    <div className="flex flex-wrap gap-2">
                                        <button
                                            onClick={() => handleToggleStatus(offer)}
                                            className={`flex-1 inline-flex justify-center items-center px-3 py-2 border border-transparent text-sm font-medium rounded-md ${
                                                offer.is_active 
                                                    ? 'text-red-700 bg-red-100 hover:bg-red-200' 
                                                    : 'text-green-700 bg-green-100 hover:bg-green-200'
                                            }`}
                                        >
                                            {offer.is_active ? 'Desactivar' : 'Activar'}
                                        </button>
                                        <button
                                            onClick={() => openModal(offer)}
                                            className="flex-1 inline-flex justify-center items-center px-3 py-2 border border-transparent text-sm font-medium rounded-md text-indigo-700 bg-indigo-100 hover:bg-indigo-200"
                                        >
                                            Editar
                                        </button>
                                        <button
                                            onClick={() => handleDelete(offer)}
                                            className="flex-1 inline-flex justify-center items-center px-3 py-2 border border-transparent text-sm font-medium rounded-md text-red-700 bg-red-100 hover:bg-red-200"
                                        >
                                            Eliminar
                                        </button>
                                    </div>
                                </div>
                            </div>
                        ))}
                    </div>

                    {/* Empty state */}
                    {offers.data.length === 0 && (
                        <div className="text-center py-12">
                            <svg className="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M7 7h10l4 12H5l2-12z" />
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 9v1a3 3 0 0 0 6 0V9" />
                            </svg>
                            <h3 className="mt-2 text-sm font-medium text-gray-900">No hay ofertas</h3>
                            <p className="mt-1 text-sm text-gray-500">
                                Comienza creando tu primera oferta para los productos.
                            </p>
                            <div className="mt-6">
                                <button
                                    type="button"
                                    onClick={() => openModal()}
                                    className="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700"
                                >
                                    Nueva Oferta
                                </button>
                            </div>
                        </div>
                    )}
                </div>

                {/* Pagination */}
                {offers.links && (
                    <div className="mt-6 flex justify-between items-center">
                        <div className="text-sm text-gray-700">
                            Mostrando {offers.from} a {offers.to} de {offers.total} ofertas
                        </div>
                        <div className="flex space-x-2">
                            {offers.links.map((link, index) => {
                                if (link.url === null) {
                                    return (
                                        <span
                                            key={index}
                                            className="px-3 py-2 text-sm text-gray-500 bg-gray-100 rounded-md cursor-not-allowed"
                                            dangerouslySetInnerHTML={{ __html: link.label }}
                                        />
                                    );
                                }
                                
                                return (
                                    <Link
                                        key={index}
                                        href={link.url}
                                        className={`px-3 py-2 text-sm rounded-md ${
                                            link.active
                                                ? 'bg-indigo-600 text-white'
                                                : 'text-gray-700 bg-white border border-gray-300 hover:bg-gray-50'
                                        }`}
                                        dangerouslySetInnerHTML={{ __html: link.label }}
                                    />
                                );
                            })}
                        </div>
                    </div>
                )}
            </div>

            {/* Modal */}
            {isModalOpen && (
                <div className="fixed inset-0 z-10 overflow-y-auto">
                    <div className="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                        <div className="fixed inset-0 transition-opacity" aria-hidden="true">
                            <div className="absolute inset-0 bg-gray-500 opacity-75" onClick={closeModal}></div>
                        </div>

                        <div className="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                            <form onSubmit={handleSubmit}>
                                <div className="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                                    <h3 className="text-lg font-medium text-gray-900 mb-4">
                                        {editingOffer ? 'Editar Oferta' : 'Nueva Oferta'}
                                    </h3>

                                    {/* Product Selection */}
                                    {!editingOffer && (
                                        <div className="mb-4">
                                            <label className="block text-sm font-medium text-gray-700 mb-2">
                                                Producto
                                            </label>
                                            <select
                                                value={data.product_id}
                                                onChange={(e) => setData('product_id', e.target.value)}
                                                className="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"
                                                required
                                            >
                                                <option value="">Seleccionar producto</option>
                                                {products.map((product) => (
                                                    <option key={product.id} value={product.id}>
                                                        {product.title}
                                                    </option>
                                                ))}
                                            </select>
                                            {errors.product_id && <p className="mt-1 text-sm text-red-600">{errors.product_id}</p>}
                                        </div>
                                    )}

                                    {/* Offer Price */}
                                    <div className="mb-4">
                                        <label className="block text-sm font-medium text-gray-700 mb-2">
                                            Precio de Oferta
                                        </label>
                                        <input
                                            type="number"
                                            step="0.01"
                                            value={data.offer_price}
                                            onChange={(e) => setData('offer_price', e.target.value)}
                                            className="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"
                                            required
                                        />
                                        {errors.offer_price && <p className="mt-1 text-sm text-red-600">{errors.offer_price}</p>}
                                    </div>

                                    {/* Percentage Discount */}
                                    <div className="mb-4">
                                        <label className="block text-sm font-medium text-gray-700 mb-2">
                                            Porcentaje de Descuento (opcional)
                                        </label>
                                        <input
                                            type="number"
                                            step="0.01"
                                            value={data.percentage_discount}
                                            onChange={(e) => setData('percentage_discount', e.target.value)}
                                            className="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"
                                            placeholder="Se calculará automáticamente si se deja vacío"
                                        />
                                        {errors.percentage_discount && <p className="mt-1 text-sm text-red-600">{errors.percentage_discount}</p>}
                                    </div>

                                    {/* Start Date */}
                                    <div className="mb-4">
                                        <label className="block text-sm font-medium text-gray-700 mb-2">
                                            Fecha de Inicio (opcional)
                                        </label>
                                        <input
                                            type="datetime-local"
                                            value={data.start_date}
                                            onChange={(e) => setData('start_date', e.target.value)}
                                            className="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"
                                        />
                                        {errors.start_date && <p className="mt-1 text-sm text-red-600">{errors.start_date}</p>}
                                    </div>

                                    {/* End Date */}
                                    <div className="mb-4">
                                        <label className="block text-sm font-medium text-gray-700 mb-2">
                                            Fecha de Fin (opcional)
                                        </label>
                                        <input
                                            type="datetime-local"
                                            value={data.end_date}
                                            onChange={(e) => setData('end_date', e.target.value)}
                                            className="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"
                                        />
                                        {errors.end_date && <p className="mt-1 text-sm text-red-600">{errors.end_date}</p>}
                                    </div>

                                    {/* Is Active */}
                                    <div className="mb-4">
                                        <label className="flex items-center">
                                            <input
                                                type="checkbox"
                                                checked={data.is_active}
                                                onChange={(e) => setData('is_active', e.target.checked)}
                                                className="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded"
                                            />
                                            <span className="ml-2 text-sm text-gray-700">Oferta activa</span>
                                        </label>
                                        {errors.is_active && <p className="mt-1 text-sm text-red-600">{errors.is_active}</p>}
                                    </div>
                                </div>

                                <div className="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                                    <button
                                        type="submit"
                                        disabled={processing}
                                        className="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-indigo-600 text-base font-medium text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:ml-3 sm:w-auto sm:text-sm disabled:opacity-50"
                                    >
                                        {processing ? 'Guardando...' : (editingOffer ? 'Actualizar' : 'Crear')}
                                    </button>
                                    <button
                                        type="button"
                                        onClick={closeModal}
                                        className="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm"
                                    >
                                        Cancelar
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            )}
            
            {/* Delete Confirmation Modal */}
            {isDeleteModalOpen && (
                <div className="fixed inset-0 z-10 overflow-y-auto">
                    <div className="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                        <div className="fixed inset-0 transition-opacity" aria-hidden="true">
                            <div className="absolute inset-0 bg-gray-500 opacity-75" onClick={cancelDelete}></div>
                        </div>

                        <div className="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                            <div className="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                                <div className="sm:flex sm:items-start">
                                    <div className="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-red-100 sm:mx-0 sm:h-10 sm:w-10">
                                        <svg className="h-6 w-6 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.732-.833-2.464 0L3.34 16.5c-.77.833.192 2.5 1.732 2.5z" />
                                        </svg>
                                    </div>
                                    <div className="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                                        <h3 className="text-lg leading-6 font-medium text-gray-900">
                                            Eliminar Oferta
                                        </h3>
                                        <div className="mt-2">
                                            <p className="text-sm text-gray-500">
                                                ¿Estás seguro de que quieres eliminar la oferta para "{deletingOffer?.product.title}"? 
                                                Esta acción no se puede deshacer.
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div className="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                                <button
                                    type="button"
                                    onClick={confirmDelete}
                                    disabled={processing}
                                    className="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-red-600 text-base font-medium text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 sm:ml-3 sm:w-auto sm:text-sm disabled:opacity-50"
                                >
                                    {processing ? 'Eliminando...' : 'Eliminar'}
                                </button>
                                <button
                                    type="button"
                                    onClick={cancelDelete}
                                    className="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm"
                                >
                                    Cancelar
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            )}
        </AdminLayout>
    );
}