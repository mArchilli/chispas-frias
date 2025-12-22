import React, { useState } from 'react';
import { Head, Link, useForm, usePage, router } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { Transition } from '@headlessui/react';
import DeleteConfirmationModal from '@/Components/DeleteConfirmationModal';

export default function Index({ products, categories, filters = {} }) {
    const { flash } = usePage().props;
    const [showFlash, setShowFlash] = React.useState(!!(flash?.success || flash?.error));
    const [showDeleteModal, setShowDeleteModal] = useState(false);
    const [productToDelete, setProductToDelete] = useState(null);
    const [isDeleting, setIsDeleting] = useState(false);

    const searchForm = useForm({
        search: filters?.search || '',
        category: filters?.category || '',
        status: filters?.status || '',
        stock: filters?.stock || '',
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

            {/* Products Table */}
            <div className="bg-white overflow-hidden shadow rounded-lg">
                <div className="overflow-x-auto">
                    <table className="min-w-full divide-y divide-gray-200">
                        <thead className="bg-gray-50">
                            <tr>
                                <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Producto
                                </th>
                                <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Categoría
                                </th>
                                <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Precio
                                </th>
                                <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Stock
                                </th>
                                <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Estado
                                </th>
                                <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Fecha
                                </th>
                                <th scope="col" className="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Acciones
                                </th>
                            </tr>
                        </thead>
                        <tbody className="bg-white divide-y divide-gray-200">
                            {products?.data?.map((product) => (
                                <tr key={product.id} className="hover:bg-gray-50">
                                    <td className="px-6 py-4 whitespace-nowrap">
                                        <div className="flex items-center">
                                            {product.primary_image && (
                                                <div className="flex-shrink-0 h-12 w-12">
                                                    <img
                                                        className="h-12 w-12 rounded-md object-cover"
                                                        src={`/storage/${product.primary_image}`}
                                                        alt={product.title}
                                                    />
                                                </div>
                                            )}
                                            <div className={product.primary_image ? "ml-4" : ""}>
                                                <div className="text-sm font-medium text-gray-900">
                                                    {product.title}
                                                </div>
                                                <div className="text-sm text-gray-500">
                                                    SKU: {product.sku || 'No definido'}
                                                </div>
                                                {product.is_featured && (
                                                    <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                                        Destacado
                                                    </span>
                                                )}
                                            </div>
                                        </div>
                                    </td>
                                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        {product.category.parent_name && (
                                            <div className="text-xs text-gray-400">{product.category.parent_name}</div>
                                        )}
                                        <div>{product.category.name}</div>
                                    </td>
                                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        {product.formatted_price}
                                    </td>
                                    <td className="px-6 py-4 whitespace-nowrap">
                                        <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${
                                            product.in_stock
                                                ? 'bg-green-100 text-green-800'
                                                : 'bg-red-100 text-red-800'
                                        }`}>
                                            {product.stock} {product.stock === 1 ? 'unidad' : 'unidades'}
                                        </span>
                                    </td>
                                    <td className="px-6 py-4 whitespace-nowrap">
                                        <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${
                                            product.is_active
                                                ? 'bg-green-100 text-green-800'
                                                : 'bg-red-100 text-red-800'
                                        }`}>
                                            {product.is_active ? 'Visible' : 'Oculto'}
                                        </span>
                                    </td>
                                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        {product.created_at}
                                    </td>
                                    <td className="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        <div className="flex items-center justify-end space-x-2">
                                            <Link
                                                href={route('admin.products.show', product.id)}
                                                className="text-blue-600 hover:text-blue-900"
                                            >
                                                Ver
                                            </Link>
                                            <Link
                                                href={route('admin.products.edit', product.id)}
                                                className="text-indigo-600 hover:text-indigo-900"
                                            >
                                                Editar
                                            </Link>
                                            <button
                                                onClick={() => handleDeleteProduct(product)}
                                                className="text-red-600 hover:text-red-900"
                                            >
                                                Eliminar
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

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
        </AdminLayout>
    );
}