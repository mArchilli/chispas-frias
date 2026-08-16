import React from 'react';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { Transition } from '@headlessui/react';
import { estadoLabel, estadoBadgeClasses } from '@/utils/orders';

export default function Index({ orders, filters = {} }) {
    const { flash } = usePage().props;
    const [showFlash, setShowFlash] = React.useState(!!(flash?.success || flash?.error));

    const searchForm = useForm({
        search: filters?.search || '',
        estado: filters?.estado || '',
    });

    const handleSearch = (e) => {
        e.preventDefault();
        searchForm.get(route('admin.orders.index'), {
            preserveState: true,
            replace: true,
        });
    };

    const clearFilters = () => {
        searchForm.reset();
        searchForm.get(route('admin.orders.index'));
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
                    <div>
                        <h1 className="text-2xl font-semibold text-gray-900">Gestión de Órdenes</h1>
                        <p className="text-sm text-gray-600">Pedidos realizados a través del carrito de compras</p>
                    </div>
                </div>
            }
        >
            <Head title="Órdenes - Admin" />

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
                    <form onSubmit={handleSearch} className="flex flex-col sm:flex-row gap-4">
                        <div className="flex-1">
                            <input
                                type="text"
                                placeholder="Buscar por nombre, apellido, DNI o email..."
                                className="block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                                value={searchForm.data.search}
                                onChange={(e) => searchForm.setData('search', e.target.value)}
                            />
                        </div>
                        <div className="sm:w-48">
                            <select
                                className="block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                                value={searchForm.data.estado}
                                onChange={(e) => searchForm.setData('estado', e.target.value)}
                            >
                                <option value="">Todos los estados</option>
                                <option value="pendiente">Pendiente</option>
                                <option value="despachado">Despachado</option>
                                <option value="cancelado">Cancelado</option>
                            </select>
                        </div>
                        <div className="flex gap-2">
                            <button
                                type="submit"
                                className="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 active:bg-gray-900 focus:outline-none focus:border-gray-900 focus:ring focus:ring-gray-300 disabled:opacity-25 transition"
                            >
                                Buscar
                            </button>
                            {(filters?.search || filters?.estado) && (
                                <button
                                    type="button"
                                    onClick={clearFilters}
                                    className="inline-flex items-center px-4 py-2 bg-gray-500 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-600 active:bg-gray-700 focus:outline-none focus:border-gray-700 focus:ring focus:ring-gray-300 disabled:opacity-25 transition"
                                >
                                    Limpiar
                                </button>
                            )}
                        </div>
                    </form>
                </div>
            </div>

            {/* Orders Table */}
            <div className="bg-white overflow-hidden shadow rounded-lg">
                {orders?.data?.length > 0 ? (
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-gray-200">
                            <thead className="bg-gray-50">
                                <tr>
                                    <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fecha</th>
                                    <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Cliente</th>
                                    <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th>
                                    <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Estado</th>
                                    <th scope="col" className="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Acciones</th>
                                </tr>
                            </thead>
                            <tbody className="bg-white divide-y divide-gray-200">
                                {orders.data.map((order) => (
                                    <tr key={order.id} className="hover:bg-gray-50">
                                        <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{order.created_at}</td>
                                        <td className="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                            {order.name} {order.lastname}
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{order.formatted_total}</td>
                                        <td className="px-6 py-4 whitespace-nowrap">
                                            <span className={`inline-flex items-center px-2 py-1 rounded-full text-xs font-medium ${estadoBadgeClasses(order.estado)}`}>
                                                {estadoLabel(order.estado)}
                                            </span>
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                            <Link
                                                href={route('admin.orders.show', order.id)}
                                                className="text-blue-600 hover:text-blue-800"
                                            >
                                                Ver detalle
                                            </Link>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                ) : (
                    <div className="text-center py-12">
                        <svg className="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1} d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                        </svg>
                        <h3 className="mt-2 text-sm font-medium text-gray-900">No hay órdenes</h3>
                        <p className="mt-1 text-sm text-gray-500">
                            No se encontraron órdenes con los filtros aplicados.
                        </p>
                    </div>
                )}

                {/* Pagination */}
                {orders?.links && (
                    <div className="bg-white px-4 py-3 flex items-center justify-between border-t border-gray-200 sm:px-6">
                        <div className="flex-1 flex justify-between sm:hidden">
                            {orders?.prev_page_url && (
                                <Link
                                    href={orders.prev_page_url}
                                    className="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50"
                                >
                                    Anterior
                                </Link>
                            )}
                            {orders?.next_page_url && (
                                <Link
                                    href={orders.next_page_url}
                                    className="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50"
                                >
                                    Siguiente
                                </Link>
                            )}
                        </div>
                        <div className="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                            <div>
                                <p className="text-sm text-gray-700">
                                    Mostrando <span className="font-medium">{orders?.from || 0}</span> a{' '}
                                    <span className="font-medium">{orders?.to || 0}</span> de{' '}
                                    <span className="font-medium">{orders?.total || 0}</span> resultados
                                </p>
                            </div>
                            <div>
                                <nav className="relative z-0 inline-flex rounded-md shadow-sm -space-x-px">
                                    {orders?.links?.map((link, index) => (
                                        link.url ? (
                                            <Link
                                                key={index}
                                                href={link.url}
                                                className={`relative inline-flex items-center px-4 py-2 border text-sm font-medium ${
                                                    link.active
                                                        ? 'z-10 bg-blue-50 border-blue-500 text-blue-600'
                                                        : 'bg-white border-gray-300 text-gray-500 hover:bg-gray-50'
                                                } ${index === 0 ? 'rounded-l-md' : ''} ${
                                                    index === orders.links.length - 1 ? 'rounded-r-md' : ''
                                                }`}
                                                dangerouslySetInnerHTML={{ __html: link.label }}
                                            />
                                        ) : (
                                            <span
                                                key={index}
                                                className={`relative inline-flex items-center px-4 py-2 border text-sm font-medium bg-gray-100 border-gray-300 text-gray-400 ${
                                                    index === 0 ? 'rounded-l-md' : ''
                                                } ${index === orders.links.length - 1 ? 'rounded-r-md' : ''}`}
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
        </AdminLayout>
    );
}
