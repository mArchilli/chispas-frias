import React, { useState } from 'react';
import { Head, Link, useForm, usePage, router } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { Transition } from '@headlessui/react';
import DeleteConfirmationModal from '@/Components/DeleteConfirmationModal';
import { getProductImageUrl } from '@/utils/images';

export default function Index({ products, categories, filters = {} }) {
    const { flash } = usePage().props;
    const [showFlash, setShowFlash] = React.useState(!!(flash?.success || flash?.error));
    const [showDeleteModal, setShowDeleteModal] = useState(false);
    const [productToDelete, setProductToDelete] = useState(null);
    const [isDeleting, setIsDeleting] = useState(false);
    
    // Estados para el modal de ofertas
    const [showOfferModal, setShowOfferModal] = useState(false);
    const [selectedProduct, setSelectedProduct] = useState(null);
    const [isProcessingOffer, setIsProcessingOffer] = useState(false);
    const [isEditMode, setIsEditMode] = useState(false);
    const [editingOffer, setEditingOffer] = useState(null);
    
    // Estados para el modal de eliminar oferta
    const [showDeleteOfferModal, setShowDeleteOfferModal] = useState(false);
    const [offerToDelete, setOfferToDelete] = useState(null);
    const [isDeletingOffer, setIsDeletingOffer] = useState(false);

    const searchForm = useForm({
        search: filters?.search || '',
        category: filters?.category || '',
        status: filters?.status || '',
        stock: filters?.stock || '',
    });

    const offerForm = useForm({
        offer_price: '',
        start_date: '',
        end_date: '',
        is_active: true,
    });

    const handleSearch = (e) => {
        e.preventDefault();
        searchForm.get(route('admin.products.index'), {
            preserveState: true,
            replace: true,
        });
    };

    const clearFilters = () => {
        searchForm.reset();
        searchForm.get(route('admin.products.index'));
    };

    const handleDeleteProduct = (product) => {
        setProductToDelete(product);
        setShowDeleteModal(true);
    };

    const confirmDelete = () => {
        if (!productToDelete) return;
        
        setIsDeleting(true);
        router.delete(route('admin.products.destroy', productToDelete.id), {
            onSuccess: () => {
                setShowDeleteModal(false);
                setProductToDelete(null);
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
            setProductToDelete(null);
        }
    };

    const toggleFeatured = (productId) => {
        router.patch(route('admin.products.toggle-featured', productId), {}, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const handleOfferModal = (product) => {
        // Verificar si el producto ya tiene una oferta activa
        if (product.current_offer) {
            alert('Este producto ya tiene una oferta activa. Debes editarla o eliminarla antes de crear una nueva.');
            return;
        }
        
        setSelectedProduct(product);
        setShowOfferModal(true);
        setIsEditMode(false);
        setEditingOffer(null);
        offerForm.reset();
    };

    const handleEditOffer = (product, offer) => {
        setSelectedProduct(product);
        setEditingOffer(offer);
        setShowOfferModal(true);
        setIsEditMode(true);
        
        // Formatear fechas para datetime-local
        const formatDateForInput = (dateString) => {
            if (!dateString) return '';
            const date = new Date(dateString);
            return date.toISOString().slice(0, 16);
        };
        
        offerForm.setData({
            offer_price: offer.offer_price,
            start_date: formatDateForInput(offer.start_date),
            end_date: formatDateForInput(offer.end_date),
            is_active: offer.is_active,
        });
    };

    const closeOfferModal = () => {
        if (!isProcessingOffer) {
            setShowOfferModal(false);
            setSelectedProduct(null);
            setIsEditMode(false);
            setEditingOffer(null);
            offerForm.reset();
        }
    };

    const createQuickOffer = () => {
        if (!selectedProduct || !offerForm.data.offer_price) return;
        
        setIsProcessingOffer(true);
        
        if (isEditMode && editingOffer) {
            // Actualizar oferta existente
            router.put(route('admin.products.offers.update', editingOffer.id), {
                offer_price: offerForm.data.offer_price,
                start_date: offerForm.data.start_date || null,
                end_date: offerForm.data.end_date || null,
                is_active: offerForm.data.is_active,
            }, {
                onSuccess: () => {
                    closeOfferModal();
                    setIsProcessingOffer(false);
                },
                onError: () => {
                    setIsProcessingOffer(false);
                }
            });
        } else {
            // Crear nueva oferta
            router.post(route('admin.products.quick-offer', selectedProduct.id), {
                offer_price: offerForm.data.offer_price,
                start_date: offerForm.data.start_date || null,
                end_date: offerForm.data.end_date || null,
                is_active: offerForm.data.is_active,
            }, {
                onSuccess: () => {
                    closeOfferModal();
                    setIsProcessingOffer(false);
                },
                onError: () => {
                    setIsProcessingOffer(false);
                }
            });
        }
    };

    const removeOffer = (product) => {
        setOfferToDelete(product);
        setShowDeleteOfferModal(true);
    };

    const confirmDeleteOffer = () => {
        if (!offerToDelete || !offerToDelete.current_offer) return;
        
        setIsDeletingOffer(true);
        router.delete(route('admin.offers.destroy', offerToDelete.current_offer.id), {
            preserveState: true,
            preserveScroll: true,
            onSuccess: () => {
                setShowDeleteOfferModal(false);
                setOfferToDelete(null);
                setIsDeletingOffer(false);
            },
            onError: () => {
                setIsDeletingOffer(false);
            }
        });
    };

    const closeDeleteOfferModal = () => {
        if (!isDeletingOffer) {
            setShowDeleteOfferModal(false);
            setOfferToDelete(null);
        }
    };

    React.useEffect(() => {
        if (flash?.success || flash?.error) {
            setShowFlash(true);
            const timer = setTimeout(() => setShowFlash(false), 5000);
            return () => clearTimeout(timer);
        }
    }, [flash]);

    return (
        <AdminLayout
            header={
                <div className="flex items-center justify-between">
                    <h1 className="text-2xl font-semibold text-gray-900">Gestión de Productos</h1>
                    <Link
                        href={route('admin.products.create')}
                        className="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 active:bg-blue-900 focus:outline-none focus:border-blue-900 focus:ring focus:ring-blue-300 disabled:opacity-25 transition"
                    >
                        Nuevo Producto
                    </Link>
                </div>
            }
        >
            <Head title="Productos - Admin" />

            {/* Flash Messages */}
            <Transition
                as="div"
                show={showFlash}
                enter="transition ease-out duration-300"
                enterFrom="opacity-0 transform translate-y-2"
                enterTo="opacity-100 transform translate-y-0"
                leave="transition ease-in duration-200"
                leaveFrom="opacity-100 transform translate-y-0"
                leaveTo="opacity-0 transform translate-y-2"
            >
                {flash?.success && (
                    <div className="mb-6 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative">
                        <span className="block sm:inline">{flash.success}</span>
                        <span
                            className="absolute top-0 bottom-0 right-0 px-4 py-3 cursor-pointer"
                            onClick={() => setShowFlash(false)}
                        >
                            <svg className="fill-current h-6 w-6 text-green-500" role="button" viewBox="0 0 20 20">
                                <path d="M14.348 14.849c-0.469 0.469-1.229 0.469-1.697 0l-2.651-3.030-2.651 3.029c-0.469 0.469-1.229 0.469-1.697 0-0.469-0.469-0.469-1.229 0-1.697l2.758-3.15-2.759-3.152c-0.469-0.469-0.469-1.228 0-1.697s1.228-0.469 1.697 0l2.652 3.031 2.651-3.031c0.469-0.469 1.228-0.469 1.697 0s0.469 1.229 0 1.697l-2.758 3.152 2.758 3.15c0.469 0.469 0.469 1.229 0 1.698z"/>
                            </svg>
                        </span>
                    </div>
                )}
                {flash?.error && (
                    <div className="mb-6 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative">
                        <span className="block sm:inline">{flash.error}</span>
                        <span
                            className="absolute top-0 bottom-0 right-0 px-4 py-3 cursor-pointer"
                            onClick={() => setShowFlash(false)}
                        >
                            <svg className="fill-current h-6 w-6 text-red-500" role="button" viewBox="0 0 20 20">
                                <path d="M14.348 14.849c-0.469 0.469-1.229 0.469-1.697 0l-2.651-3.030-2.651 3.029c-0.469 0.469-1.229 0.469-1.697 0-0.469-0.469-0.469-1.229 0-1.697l2.758-3.15-2.759-3.152c-0.469-0.469-0.469-1.228 0-1.697s1.228-0.469 1.697 0l2.652 3.031 2.651-3.031c0.469-0.469 1.228-0.469 1.697 0s0.469 1.229 0 1.697l-2.758 3.152 2.758 3.15c0.469 0.469 0.469 1.229 0 1.698z"/>
                            </svg>
                        </span>
                    </div>
                )}
            </Transition>

            {/* Search and Filters */}
            <div className="mb-6 bg-white overflow-hidden shadow rounded-lg">
                <div className="px-4 py-5 sm:p-6">
                    <form onSubmit={handleSearch} className="space-y-4">
                        <div className="flex flex-col sm:flex-row gap-4">
                            <div className="flex-1">
                                <input
                                    type="text"
                                    placeholder="Buscar productos..."
                                    className="block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                                    value={searchForm.data.search}
                                    onChange={(e) => searchForm.setData('search', e.target.value)}
                                />
                            </div>
                            <div className="sm:w-48">
                                <select
                                    className="block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                                    value={searchForm.data.category}
                                    onChange={(e) => searchForm.setData('category', e.target.value)}
                                >
                                    <option value="">Todas las categorías</option>
                                    {categories?.map((category) => (
                                        <option key={category.id} value={category.id}>
                                            {category.name}
                                        </option>
                                    ))}
                                </select>
                            </div>
                        </div>
                        <div className="flex flex-col sm:flex-row gap-4">
                            <div className="sm:w-48">
                                <select
                                    className="block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                                    value={searchForm.data.status}
                                    onChange={(e) => searchForm.setData('status', e.target.value)}
                                >
                                    <option value="">Todos los estados</option>
                                    <option value="active">Activos</option>
                                    <option value="inactive">Inactivos</option>
                                    <option value="featured">Destacados</option>
                                </select>
                            </div>
                            <div className="sm:w-48">
                                <select
                                    className="block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                                    value={searchForm.data.stock}
                                    onChange={(e) => searchForm.setData('stock', e.target.value)}
                                >
                                    <option value="">Todo el stock</option>
                                    <option value="in_stock">Con stock</option>
                                    <option value="out_of_stock">Sin stock</option>
                                </select>
                            </div>
                            <div className="flex gap-2">
                                <button
                                    type="submit"
                                    className="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 active:bg-gray-900 focus:outline-none focus:border-gray-900 focus:ring focus:ring-gray-300 disabled:opacity-25 transition"
                                >
                                    Buscar
                                </button>
                                {(filters?.search || filters?.category || filters?.status || filters?.stock) && (
                                    <button
                                        type="button"
                                        onClick={clearFilters}
                                        className="inline-flex items-center px-4 py-2 bg-gray-500 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-600 active:bg-gray-700 focus:outline-none focus:border-gray-700 focus:ring focus:ring-gray-300 disabled:opacity-25 transition"
                                    >
                                        Limpiar
                                    </button>
                                )}
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            {/* Products Grid */}
            <div className="bg-white overflow-hidden shadow rounded-lg">
                {products?.data?.length > 0 ? (
                    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6 p-6">
                        {products.data.map((product) => (
                            <div key={product.id} className="bg-white border border-gray-200 rounded-lg shadow-sm hover:shadow-md transition-shadow duration-200">
                                {/* Product Image */}
                                <div className="aspect-w-1 aspect-h-1 w-full overflow-hidden rounded-t-lg bg-gray-200">
                                    {product.primary_image ? (
                                        <img
                                            src={getProductImageUrl(product.primary_image)}
                                            alt={product.title}
                                            className="h-48 w-full object-cover object-center"
                                        />
                                    ) : (
                                        <div className="h-48 w-full flex items-center justify-center bg-gray-100">
                                            <svg className="h-16 w-16 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1} d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                            </svg>
                                        </div>
                                    )}
                                </div>

                                {/* Product Info */}
                                <div className="p-4">
                                    <div className="flex items-start justify-between mb-2">
                                        <h3 className="text-sm font-medium text-gray-900 line-clamp-2 flex-1">
                                            {product.title}
                                        </h3>
                                        <div className="ml-2 flex gap-1">
                                            {product.is_featured && (
                                                <span className="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                                    ⭐
                                                </span>
                                            )}
                                            {product.current_offer && (
                                                <span className="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                                    🏷️
                                                </span>
                                            )}
                                        </div>
                                    </div>

                                    <p className="text-xs text-gray-500 mb-2">
                                        SKU: {product.sku || 'No definido'}
                                    </p>

                                    <div className="mb-3">
                                        {product.category.parent_name && (
                                            <p className="text-xs text-gray-400">{product.category.parent_name}</p>
                                        )}
                                        <p className="text-sm text-gray-600">{product.category.name}</p>
                                    </div>

                                    {/* Oferta Section */}
                                    {product.current_offer ? (
                                        <div className="mb-3 p-4 bg-gradient-to-br from-green-50 to-emerald-50 border-2 border-green-200 rounded-lg shadow-sm">
                                            <div className="flex items-center justify-between mb-3">
                                                <div className="flex items-center gap-2">
                                                    <span className="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold bg-green-600 text-white shadow-sm">
                                                        ✨ OFERTA ACTIVA
                                                    </span>
                                                    {product.current_offer.percentage_discount && (
                                                        <span className="inline-flex items-center px-2 py-1 rounded-full text-xs font-bold bg-red-500 text-white">
                                                            -{Math.round(product.current_offer.percentage_discount)}%
                                                        </span>
                                                    )}
                                                </div>
                                                <span className={`text-xs px-2 py-1 rounded-full font-medium ${
                                                    product.current_offer.is_active 
                                                        ? 'bg-green-100 text-green-700 border border-green-200' 
                                                        : 'bg-gray-100 text-gray-600 border border-gray-200'
                                                }`}>
                                                    {product.current_offer.is_active ? 'Activa' : 'Inactiva'}
                                                </span>
                                            </div>
                                            
                                            <div className="grid grid-cols-2 gap-3 mb-3">
                                                <div className="text-center p-2 bg-white rounded-md border">
                                                    <span className="text-xs text-gray-500 block font-medium">Precio Regular</span>
                                                    <span className="text-sm text-gray-600 line-through">{product.formatted_price}</span>
                                                </div>
                                                <div className="text-center p-2 bg-white rounded-md border border-green-200">
                                                    <span className="text-xs text-gray-500 block font-medium">Precio Oferta</span>
                                                    <span className="text-lg font-bold text-green-600">{product.current_offer.formatted_offer_price}</span>
                                                </div>
                                            </div>
                                            
                                            {(product.current_offer.start_date || product.current_offer.end_date) && (
                                                <div className="mb-3 p-2 bg-white rounded-md text-xs text-gray-600 border">
                                                    <div className="font-medium text-gray-700 mb-1">Período de la oferta:</div>
                                                    {product.current_offer.start_date && (
                                                        <div>📅 Inicio: {new Date(product.current_offer.start_date).toLocaleDateString()}</div>
                                                    )}
                                                    {product.current_offer.end_date && (
                                                        <div>📅 Fin: {new Date(product.current_offer.end_date).toLocaleDateString()}</div>
                                                    )}
                                                </div>
                                            )}
                                            
                                            <div className="flex gap-2">
                                                <button
                                                    onClick={() => handleEditOffer(product, product.current_offer)}
                                                    className="flex-1 text-sm px-3 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors flex items-center justify-center gap-1 font-medium"
                                                    title="Editar oferta existente"
                                                >
                                                    ✏️ Editar
                                                </button>
                                                <button
                                                    onClick={() => removeOffer(product)}
                                                    className="flex-1 text-sm px-3 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 transition-colors flex items-center justify-center gap-1 font-medium"
                                                    title="Eliminar oferta"
                                                >
                                                    🗑️ Eliminar
                                                </button>
                                            </div>
                                        </div>
                                    ) : (
                                        <div className="mb-3 p-3 bg-gray-50 border border-gray-200 rounded-md">
                                            <div className="flex items-center justify-between mb-2">
                                                <div>
                                                    <span className="text-lg font-semibold text-gray-900">{product.formatted_price}</span>
                                                    <div className="text-xs text-gray-500">Sin ofertas activas</div>
                                                </div>
                                            </div>
                                            <button
                                                onClick={() => handleOfferModal(product)}
                                                className="w-full text-sm px-3 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 transition-colors flex items-center justify-center gap-2 font-medium"
                                                title="Crear nueva oferta para este producto"
                                            >
                                                🏷️ Crear Oferta
                                            </button>
                                        </div>
                                    )}

                                    <div className="flex items-center justify-between mb-4">
                                        <span className={`inline-flex items-center px-2 py-1 rounded-full text-xs font-medium ${
                                            product.is_active
                                                ? 'bg-green-100 text-green-800'
                                                : 'bg-red-100 text-red-800'
                                        }`}>
                                            {product.is_active ? 'Visible' : 'Oculto'}
                                        </span>
                                        <span className={`inline-flex items-center px-2 py-1 rounded-full text-xs font-medium ${
                                            product.in_stock
                                                ? 'bg-green-100 text-green-800'
                                                : 'bg-red-100 text-red-800'
                                        }`}>
                                            {product.stock} {product.stock === 1 ? 'unidad' : 'unidades'}
                                        </span>
                                    </div>

                                    {/* Action Buttons */}
                                    <div className="space-y-2">
                                        <div className="flex space-x-2">
                                            <Link
                                                href={route('admin.products.show', product.id)}
                                                className="flex-1 inline-flex justify-center items-center px-3 py-2 border border-gray-300 shadow-sm text-xs font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                                            >
                                                <svg className="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                                </svg>
                                                Ver
                                            </Link>
                                            <Link
                                                href={route('admin.products.edit', product.id)}
                                                className="flex-1 inline-flex justify-center items-center px-3 py-2 border border-transparent text-xs font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                                            >
                                                <svg className="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                </svg>
                                                Editar
                                            </Link>
                                        </div>
                                        <div className="flex space-x-2">
                                            <button
                                                onClick={() => toggleFeatured(product.id)}
                                                className={`flex-1 inline-flex justify-center items-center px-3 py-2 border border-transparent text-xs font-medium rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 transition-colors ${
                                                    product.is_featured
                                                        ? 'text-white bg-yellow-600 hover:bg-yellow-700 focus:ring-yellow-500'
                                                        : 'text-yellow-700 bg-yellow-100 hover:bg-yellow-200 focus:ring-yellow-500'
                                                }`}
                                                title={product.is_featured ? 'Quitar de destacados' : 'Marcar como destacado'}
                                            >
                                                <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z" />
                                                </svg>
                                            </button>
                                            
                                            <button
                                                onClick={() => handleDeleteProduct(product)}
                                                className="inline-flex justify-center items-center px-3 py-2 border border-transparent text-xs font-medium rounded-md text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500"
                                            >
                                                <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                </svg>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        ))}
                    </div>
                ) : (
                    <div className="text-center py-12">
                        <svg className="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1} d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
                        </svg>
                        <h3 className="mt-2 text-sm font-medium text-gray-900">No hay productos</h3>
                        <p className="mt-1 text-sm text-gray-500">
                            No se encontraron productos con los filtros aplicados.
                        </p>
                        <div className="mt-6">
                            <Link
                                href={route('admin.products.create')}
                                className="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                            >
                                <svg className="-ml-1 mr-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 4v16m8-8H4" />
                                </svg>
                                Crear primer producto
                            </Link>
                        </div>
                    </div>
                )}

                {/* Pagination */}
                {products?.links && (
                    <div className="bg-white px-4 py-3 flex items-center justify-between border-t border-gray-200 sm:px-6">
                        <div className="flex-1 flex justify-between sm:hidden">
                            {products?.prev_page_url && (
                                <Link
                                    href={products.prev_page_url}
                                    className="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50"
                                >
                                    Anterior
                                </Link>
                            )}
                            {products?.next_page_url && (
                                <Link
                                    href={products.next_page_url}
                                    className="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50"
                                >
                                    Siguiente
                                </Link>
                            )}
                        </div>
                        <div className="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                            <div>
                                <p className="text-sm text-gray-700">
                                    Mostrando <span className="font-medium">{products?.from || 0}</span> a{' '}
                                    <span className="font-medium">{products?.to || 0}</span> de{' '}
                                    <span className="font-medium">{products?.total || 0}</span> resultados
                                </p>
                            </div>
                            <div>
                                <nav className="relative z-0 inline-flex rounded-md shadow-sm -space-x-px">
                                    {products?.links?.map((link, index) => (
                                        link.url ? (
                                            <Link
                                                key={index}
                                                href={link.url}
                                                className={`relative inline-flex items-center px-4 py-2 border text-sm font-medium ${
                                                    link.active
                                                        ? 'z-10 bg-blue-50 border-blue-500 text-blue-600'
                                                        : 'bg-white border-gray-300 text-gray-500 hover:bg-gray-50'
                                                } ${index === 0 ? 'rounded-l-md' : ''} ${
                                                    index === products.links.length - 1 ? 'rounded-r-md' : ''
                                                }`}
                                                dangerouslySetInnerHTML={{ __html: link.label }}
                                            />
                                        ) : (
                                            <span
                                                key={index}
                                                className={`relative inline-flex items-center px-4 py-2 border text-sm font-medium bg-gray-100 border-gray-300 text-gray-400 ${
                                                    index === 0 ? 'rounded-l-md' : ''
                                                } ${index === products.links.length - 1 ? 'rounded-r-md' : ''}`}
                                                dangerouslySetInnerHTML={{ __html: link.label }}
                                            />
                                        )
                                    ))}
                                </nav>
                            </div>
                        </div>
                    </div>
                )}
            </div>
            
            {/* Modal de Confirmación para Eliminar Producto */}
            <DeleteConfirmationModal
                show={showDeleteModal}
                onClose={closeDeleteModal}
                onConfirm={confirmDelete}
                title="¿Eliminar producto?"
                message="Estás a punto de eliminar el siguiente producto:"
                itemName={productToDelete?.title}
                warningMessage="Esta acción eliminará el producto y todas sus imágenes asociadas."
                confirmText="Eliminar Producto"
                processing={isDeleting}
            />

            {/* Modal para Crear Oferta */}
            {showOfferModal && (
                <div className="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
                    <div className="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
                        <div className="mt-3">
                            {/* Header */}
                            <div className="flex items-center justify-between mb-4">
                                <h3 className="text-lg font-medium text-gray-900">
                                    {isEditMode ? 'Editar Oferta' : 'Crear Oferta'}
                                </h3>
                                <button
                                    onClick={closeOfferModal}
                                    className="text-gray-400 hover:text-gray-600"
                                    disabled={isProcessingOffer}
                                >
                                    <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>

                            {/* Información del producto */}
                            <div className="mb-4 p-3 bg-gray-50 rounded-lg">
                                <h4 className="font-medium text-gray-900">{selectedProduct?.title}</h4>
                                <p className="text-sm text-gray-600">Precio actual: {selectedProduct?.formatted_price}</p>
                            </div>

                            {/* Formulario */}
                            <div className="space-y-4">
                                {/* Precio de Oferta */}
                                <div>
                                    <label htmlFor="offer_price" className="block text-sm font-medium text-gray-700 mb-1">
                                        Precio de Oferta <span className="text-red-500">*</span>
                                    </label>
                                    <input
                                        type="number"
                                        id="offer_price"
                                        step="0.01"
                                        min="0.01"
                                        max={selectedProduct?.price - 0.01}
                                        placeholder="Ingrese el precio de oferta"
                                        className="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                                        value={offerForm.data.offer_price}
                                        onChange={(e) => offerForm.setData('offer_price', e.target.value)}
                                        disabled={isProcessingOffer}
                                    />
                                    {offerForm.errors.offer_price && (
                                        <p className="mt-1 text-sm text-red-600">{offerForm.errors.offer_price}</p>
                                    )}
                                </div>

                                {/* Fecha de Inicio */}
                                <div>
                                    <label htmlFor="start_date" className="block text-sm font-medium text-gray-700 mb-1">
                                        Fecha de Inicio
                                    </label>
                                    <input
                                        type="datetime-local"
                                        id="start_date"
                                        className="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                                        value={offerForm.data.start_date}
                                        onChange={(e) => offerForm.setData('start_date', e.target.value)}
                                        disabled={isProcessingOffer}
                                    />
                                    {offerForm.errors.start_date && (
                                        <p className="mt-1 text-sm text-red-600">{offerForm.errors.start_date}</p>
                                    )}
                                    <p className="mt-1 text-xs text-gray-500">
                                        Opcional: Si no se especifica, la oferta comenzará inmediatamente
                                    </p>
                                </div>

                                {/* Fecha de Fin */}
                                <div>
                                    <label htmlFor="end_date" className="block text-sm font-medium text-gray-700 mb-1">
                                        Fecha de Fin
                                    </label>
                                    <input
                                        type="datetime-local"
                                        id="end_date"
                                        className="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                                        value={offerForm.data.end_date}
                                        onChange={(e) => offerForm.setData('end_date', e.target.value)}
                                        disabled={isProcessingOffer}
                                    />
                                    {offerForm.errors.end_date && (
                                        <p className="mt-1 text-sm text-red-600">{offerForm.errors.end_date}</p>
                                    )}
                                    <p className="mt-1 text-xs text-gray-500">
                                        Opcional: Si no se especifica, la oferta no tendrá fecha de vencimiento
                                    </p>
                                </div>

                                {/* Estado Activo */}
                                <div>
                                    <label className="flex items-center">
                                        <input
                                            type="checkbox"
                                            className="rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-500"
                                            checked={offerForm.data.is_active}
                                            onChange={(e) => offerForm.setData('is_active', e.target.checked)}
                                            disabled={isProcessingOffer}
                                        />
                                        <span className="ml-2 text-sm font-medium text-gray-700">
                                            Oferta activa
                                        </span>
                                    </label>
                                    <p className="mt-1 text-xs text-gray-500">
                                        Puedes crear la oferta inactiva y activarla más tarde
                                    </p>
                                </div>

                                <div className="text-xs text-gray-500 bg-blue-50 p-3 rounded-md">
                                    <p className="font-medium mb-1">💡 Consejos:</p>
                                    <ul className="space-y-1">
                                        <li>• Solo el precio de oferta es obligatorio</li>
                                        <li>• Sin fechas, la oferta estará activa hasta que la desactives</li>
                                        <li>• Puedes crear ofertas programadas para fechas futuras</li>
                                    </ul>
                                </div>
                            </div>

                            {/* Buttons */}
                            <div className="flex space-x-3 mt-6">
                                <button
                                    onClick={closeOfferModal}
                                    className="flex-1 px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 border border-gray-300 rounded-md hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500"
                                    disabled={isProcessingOffer}
                                >
                                    Cancelar
                                </button>
                                <button
                                    onClick={createQuickOffer}
                                    className="flex-1 px-4 py-2 text-sm font-medium text-white bg-green-600 border border-transparent rounded-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 disabled:opacity-50"
                                    disabled={isProcessingOffer || !offerForm.data.offer_price}
                                >
                                    {isProcessingOffer ? 
                                        (isEditMode ? 'Actualizando...' : 'Creando...') : 
                                        (isEditMode ? 'Actualizar Oferta' : 'Crear Oferta')
                                    }
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            )}
            
            {/* Modal para Eliminar Oferta */}
            {showDeleteOfferModal && (
                <div className="fixed inset-0 z-50 overflow-y-auto">
                    <div className="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                        <div className="fixed inset-0 transition-opacity" aria-hidden="true">
                            <div className="absolute inset-0 bg-gray-500 opacity-75" onClick={closeDeleteOfferModal}></div>
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
                                                ¿Estás seguro de que quieres eliminar la oferta del producto "{offerToDelete?.title}"?
                                            </p>
                                            {offerToDelete?.current_offer && (
                                                <div className="mt-3 p-3 bg-gray-50 rounded-lg">
                                                    <div className="text-sm text-gray-700">
                                                        <div className="flex justify-between">
                                                            <span>Precio regular:</span>
                                                            <span>{offerToDelete.formatted_price}</span>
                                                        </div>
                                                        <div className="flex justify-between font-medium">
                                                            <span>Precio oferta:</span>
                                                            <span className="text-red-600">{offerToDelete.current_offer.formatted_offer_price}</span>
                                                        </div>
                                                        {offerToDelete.current_offer.percentage_discount && (
                                                            <div className="flex justify-between">
                                                                <span>Descuento:</span>
                                                                <span className="text-green-600">{Math.round(offerToDelete.current_offer.percentage_discount)}%</span>
                                                            </div>
                                                        )}
                                                    </div>
                                                </div>
                                            )}
                                            <p className="text-sm text-gray-500 mt-2">
                                                Esta acción no se puede deshacer.
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div className="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                                <button
                                    type="button"
                                    onClick={confirmDeleteOffer}
                                    disabled={isDeletingOffer}
                                    className="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-red-600 text-base font-medium text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 sm:ml-3 sm:w-auto sm:text-sm disabled:opacity-50"
                                >
                                    {isDeletingOffer ? 'Eliminando...' : 'Eliminar Oferta'}
                                </button>
                                <button
                                    type="button"
                                    onClick={closeDeleteOfferModal}
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