import React from 'react';
import { Head, Link } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';

export default function Dashboard({ stats = {} }) {
    const dashboardStats = [
        {
            name: 'Total Categorías',
            stat: stats.categories_count || '0',
            description: 'Categorías principales y subcategorías',
            href: '/admin/categories',
            icon: (
                <svg className="h-8 w-8 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                </svg>
            ),
            color: 'bg-gradient-to-br from-blue-500 to-blue-600'
        },
        {
            name: 'Productos Activos',
            stat: stats.products_count || '0',
            description: 'Productos disponibles en catálogo',
            href: '/admin/products',
            icon: (
                <svg className="h-8 w-8 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                </svg>
            ),
            color: 'bg-gradient-to-br from-green-500 to-green-600'
        },
        {
            name: 'Productos en Oferta',
            stat: stats.offers_count || '0',
            description: 'Productos con descuentos activos',
            href: '/admin/products?filter=offers',
            icon: (
                <svg className="h-8 w-8 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
                </svg>
            ),
            color: 'bg-gradient-to-br from-red-500 to-red-600'
        }
    ];

    const quickActions = [
        {
            name: 'Crear Producto',
            description: 'Agregar nuevo producto al catálogo',
            href: '/admin/products/create',
            icon: (
                <svg className="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                </svg>
            ),
            color: 'bg-green-600 hover:bg-green-700'
        },
        {
            name: 'Crear Categoría',
            description: 'Nueva categoría o subcategoría',
            href: '/admin/categories/create',
            icon: (
                <svg className="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                </svg>
            ),
            color: 'bg-blue-600 hover:bg-blue-700'
        },
        {
            name: 'Gestionar Ofertas',
            description: 'Crear y administrar descuentos',
            href: '/admin/products',
            icon: (
                <svg className="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
                </svg>
            ),
            color: 'bg-red-600 hover:bg-red-700'
        },
        {
            name: 'Ver Sitio Web',
            description: 'Ir al sitio público',
            href: '/',
            icon: (
                <svg className="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                </svg>
            ),
            color: 'bg-purple-600 hover:bg-purple-700'
        },
    ];

    return (
        <AdminLayout
            header={
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-3xl font-bold text-gray-900">Panel de Administración</h1>
                        <p className="mt-2 text-lg text-gray-600">Bienvenido al centro de control de Chispas Frías</p>
                    </div>
                    <div className="flex space-x-3">
                        <Link
                            href="/admin/products/create"
                            className="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg font-medium transition-colors duration-200 flex items-center space-x-2"
                        >
                            <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                            </svg>
                            <span>Nuevo Producto</span>
                        </Link>
                        <Link
                            href="/"
                            target="_blank"
                            className="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-lg font-medium transition-colors duration-200 flex items-center space-x-2"
                        >
                            <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                            </svg>
                            <span>Ver Sitio</span>
                        </Link>
                    </div>
                </div>
            }
        >
            <Head title="Dashboard - Admin" />

            <div className="space-y-8">
                {/* Estadísticas Principales */}
                <div>
                    <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                        {dashboardStats.map((item) => (
                            <Link
                                key={item.name}
                                href={item.href}
                                className="group relative overflow-hidden rounded-xl shadow-lg hover:shadow-xl transition-all duration-300 transform hover:-translate-y-1"
                            >
                                <div className={`${item.color} p-6`}>
                                    <div className="flex items-center justify-between">
                                        <div className="flex-1">
                                            <p className="text-white/80 text-sm font-medium">{item.name}</p>
                                            <p className="text-3xl font-bold text-white mt-2">{item.stat}</p>
                                            <p className="text-white/70 text-xs mt-1">{item.description}</p>
                                        </div>
                                        <div className="ml-4">
                                            {item.icon}
                                        </div>
                                    </div>
                                </div>
                                <div className="absolute inset-0 bg-white/10 opacity-0 group-hover:opacity-100 transition-opacity duration-300" />
                            </Link>
                        ))}
                    </div>
                </div>

                {/* Acciones Rápidas */}
                <div>
                    <div className="bg-white rounded-xl shadow-lg p-6">
                        <h3 className="text-lg font-semibold text-gray-900 mb-6 flex items-center">
                            <svg className="h-5 w-5 text-blue-600 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                            </svg>
                            Acciones Rápidas
                        </h3>
                        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                            {quickActions.map((action) => (
                                <Link
                                    key={action.name}
                                    href={action.href}
                                    className={`${action.color} text-white rounded-lg p-4 block hover:shadow-lg transition-all duration-200 transform hover:scale-105`}
                                >
                                    <div className="flex items-center">
                                        <div className="flex-shrink-0">
                                            {action.icon}
                                        </div>
                                        <div className="ml-3">
                                            <p className="font-medium">{action.name}</p>
                                            <p className="text-sm opacity-90">{action.description}</p>
                                        </div>
                                    </div>
                                </Link>
                            ))}
                        </div>
                    </div>
                </div>
            </div>
        </AdminLayout>
    );
}