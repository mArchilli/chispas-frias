import React from 'react';
import { Link, usePage } from '@inertiajs/react';
import { Transition } from '@headlessui/react';

export default function AdminLayout({ children, header = null }) {
    const { auth } = usePage().props;
    const [sidebarOpen, setSidebarOpen] = React.useState(false);
    const [sidebarCollapsed, setSidebarCollapsed] = React.useState(() => {
        // Leer el estado inicial desde localStorage
        if (typeof window !== 'undefined') {
            const saved = localStorage.getItem('sidebarCollapsed');
            return saved ? JSON.parse(saved) : false;
        }
        return false;
    });

    // Guardar el estado en localStorage cuando cambie
    React.useEffect(() => {
        localStorage.setItem('sidebarCollapsed', JSON.stringify(sidebarCollapsed));
    }, [sidebarCollapsed]);

    const navigation = [
        { 
            name: 'Dashboard', 
            href: '/admin/dashboard', 
            icon: (
                <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2H5a2 2 0 00-2-2z" />
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M8 5a2 2 0 012-2h4a2 2 0 012 2v6a2 2 0 01-2 2H10a2 2 0 01-2-2V5z" />
                </svg>
            ),
            current: false 
        },
        { 
            name: 'Categorías', 
            href: '/admin/categories', 
            icon: (
                <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                </svg>
            ),
            current: false 
        },
        { 
            name: 'Productos', 
            href: '/admin/products', 
            icon: (
                <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                </svg>
            ),
            current: false 
        },
    ];

    const currentPath = usePage().url;
    const updatedNavigation = navigation.map(item => ({
        ...item,
        current: currentPath.startsWith(item.href)
    }));

    return (
        <div className="min-h-screen bg-gray-100">
            {/* Desktop Sidebar */}
            <div className={`hidden md:fixed md:inset-y-0 md:flex md:flex-col transition-all duration-300 ${
                sidebarCollapsed ? 'md:w-16' : 'md:w-64'
            }`}>
                <div className="flex min-h-0 flex-1 flex-col bg-gray-800">
                    {/* Header with toggle button */}
                    <div className="flex h-16 flex-shrink-0 items-center justify-between bg-gray-900 px-4">
                        <Link href="/admin/dashboard" className={`flex items-center ${sidebarCollapsed ? 'justify-center' : ''}`}>
                            {sidebarCollapsed ? (
                                <span className="text-xl font-bold text-white">CF</span>
                            ) : (
                                <h1 className="text-xl font-bold text-white">Chispas Frías</h1>
                            )}
                        </Link>
                        {!sidebarCollapsed && (
                            <button
                                onClick={() => setSidebarCollapsed(true)}
                                className="text-gray-300 hover:text-white focus:outline-none focus:ring-2 focus:ring-gray-300 rounded"
                            >
                                <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M11 19l-7-7 7-7m8 14l-7-7 7-7" />
                                </svg>
                            </button>
                        )}
                    </div>

                    {/* Collapsed expand button */}
                    {sidebarCollapsed && (
                        <div className="px-2 py-2 border-b border-gray-700">
                            <button
                                onClick={() => setSidebarCollapsed(false)}
                                className="w-full flex justify-center text-gray-300 hover:text-white hover:bg-gray-700 rounded p-2 focus:outline-none focus:ring-2 focus:ring-gray-300"
                            >
                                <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M13 5l7 7-7 7M5 5l7 7-7 7" />
                                </svg>
                            </button>
                        </div>
                    )}
                    
                    {/* Navigation section */}
                    <nav className="flex-1 space-y-1 px-2 py-4 overflow-y-auto">
                        {updatedNavigation.map((item) => (
                            <Link
                                key={item.name}
                                href={item.href}
                                className={`${
                                    item.current
                                        ? 'bg-gray-900 text-white'
                                        : 'text-gray-300 hover:bg-gray-700 hover:text-white'
                                } group flex items-center px-2 py-2 text-sm font-medium rounded-md transition-all duration-200 ${
                                    sidebarCollapsed ? 'justify-center' : ''
                                }`}
                                title={sidebarCollapsed ? item.name : undefined}
                            >
                                <span className={sidebarCollapsed ? '' : 'mr-3'}>
                                    {item.icon}
                                </span>
                                {!sidebarCollapsed && item.name}
                            </Link>
                        ))}
                    </nav>
                        
                    {/* User section - positioned at bottom */}
                    <div className="flex-shrink-0 px-2 pb-4 space-y-1">
                        {/* Divider */}
                        <div className="border-t border-gray-700 mb-4"></div>
                        
                        {/* Back to site */}
                        <Link
                            href="/"
                            className={`text-gray-300 hover:bg-gray-700 hover:text-white group flex items-center px-2 py-2 text-sm font-medium rounded-md transition-all duration-200 ${
                                sidebarCollapsed ? 'justify-center' : ''
                            }`}
                            title={sidebarCollapsed ? 'Volver al Sitio' : undefined}
                        >
                            <span className={sidebarCollapsed ? '' : 'mr-3'}>
                                <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                                </svg>
                            </span>
                            {!sidebarCollapsed && 'Volver al Sitio'}
                        </Link>

                        {/* Profile */}
                        <Link
                            href="/profile"
                            className={`text-gray-300 hover:bg-gray-700 hover:text-white group flex items-center px-2 py-2 text-sm font-medium rounded-md transition-all duration-200 ${
                                sidebarCollapsed ? 'justify-center' : ''
                            }`}
                            title={sidebarCollapsed ? 'Mi Perfil' : undefined}
                        >
                            <span className={sidebarCollapsed ? '' : 'mr-3'}>
                                <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                </svg>
                            </span>
                            {!sidebarCollapsed && 'Mi Perfil'}
                        </Link>

                        {/* Logout */}
                        <Link
                            href={route('logout')}
                            method="post"
                            as="button"
                            className={`text-gray-300 hover:bg-gray-700 hover:text-red-300 group flex items-center px-2 py-2 text-sm font-medium rounded-md w-full text-left transition-all duration-200 ${
                                sidebarCollapsed ? 'justify-center' : ''
                            }`}
                            title={sidebarCollapsed ? 'Cerrar Sesión' : undefined}
                        >
                            <span className={sidebarCollapsed ? '' : 'mr-3'}>
                                <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                                </svg>
                            </span>
                            {!sidebarCollapsed && 'Cerrar Sesión'}
                        </Link>
                    </div>
                </div>
            </div>

            {/* Main content */}
            <div className={`flex flex-col flex-1 transition-all duration-300 ${
                sidebarCollapsed ? 'md:pl-16' : 'md:pl-64'
            }`}>
                {/* Mobile top navigation */}
                <div className="sticky top-0 z-10 md:hidden bg-gray-800 border-b border-gray-700">
                    <div className="flex items-center justify-between h-16 px-4">
                        <button
                            type="button"
                            className="flex items-center justify-center h-10 w-10 rounded-md text-gray-300 hover:text-white hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-gray-300"
                            onClick={() => setSidebarOpen(true)}
                        >
                            <span className="sr-only">Abrir menú</span>
                            <svg className="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 6h16M4 12h16M4 18h16" />
                            </svg>
                        </button>
                        
                        <Link href="/admin/dashboard" className="flex items-center">
                            <h1 className="text-lg font-bold text-white">Chispas Frías</h1>
                        </Link>
                        
                        <div className="w-10 h-10 flex items-center justify-center">
                            <div className="w-8 h-8 bg-gray-700 rounded-full flex items-center justify-center">
                                <span className="text-xs font-medium text-gray-300">
                                    {auth?.user?.name ? auth.user.name.charAt(0).toUpperCase() : 'U'}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <main className="flex-1">
                    {/* Page header */}
                    {header && (
                        <div className="bg-white shadow">
                            <div className="px-4 sm:px-6 lg:px-8">
                                <div className="py-6">
                                    {header}
                                </div>
                            </div>
                        </div>
                    )}

                    {/* Page content */}
                    <div className="px-4 sm:px-6 lg:px-8 py-8">
                        {children}
                    </div>
                </main>
            </div>

            {/* Mobile sidebar overlay */}
            <Transition show={sidebarOpen} as={React.Fragment}>
                <div className="fixed inset-0 flex z-50 md:hidden" role="dialog" aria-modal="true">
                    <Transition.Child
                        as="div"
                        enter="transition-opacity ease-linear duration-300"
                        enterFrom="opacity-0"
                        enterTo="opacity-100"
                        leave="transition-opacity ease-linear duration-300"
                        leaveFrom="opacity-100"
                        leaveTo="opacity-0"
                        className="fixed inset-0 bg-gray-600 bg-opacity-75"
                        aria-hidden="true"
                        onClick={() => setSidebarOpen(false)}
                    />

                    <Transition.Child
                        as="div"
                        enter="transition ease-in-out duration-300 transform"
                        enterFrom="-translate-x-full"
                        enterTo="translate-x-0"
                        leave="transition ease-in-out duration-300 transform"
                        leaveFrom="translate-x-0"
                        leaveTo="-translate-x-full"
                        className="relative flex-1 flex flex-col w-full bg-gray-800"
                    >
                            <div className="flex-shrink-0 flex items-center justify-between px-4 py-4 bg-gray-900">
                                <Link 
                                    href="/admin/dashboard" 
                                    className="flex items-center"
                                    onClick={() => setSidebarOpen(false)}
                                >
                                    <h1 className="text-xl font-bold text-white">Chispas Frías</h1>
                                </Link>
                                
                                <button
                                    type="button"
                                    className="flex items-center justify-center h-10 w-10 rounded-md text-gray-300 hover:text-white hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-300"
                                    onClick={() => setSidebarOpen(false)}
                                >
                                    <span className="sr-only">Cerrar menú</span>
                                    <svg className="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>
                            
                            <div className="mt-5 flex-1 h-0 overflow-y-auto">
                                <nav className="px-2 space-y-1">
                                    {updatedNavigation.map((item) => (
                                        <Link
                                            key={item.name}
                                            href={item.href}
                                            onClick={() => setSidebarOpen(false)}
                                            className={`${
                                                item.current
                                                    ? 'bg-gray-900 text-white'
                                                    : 'text-gray-300 hover:bg-gray-700 hover:text-white'
                                            } group flex items-center px-2 py-2 text-base font-medium rounded-md`}
                                        >
                                            <span className="mr-4">
                                                {item.icon}
                                            </span>
                                            {item.name}
                                        </Link>
                                    ))}
                                </nav>
                                
                                {/* Mobile user section */}
                                <div className="px-2 mt-6 pb-6 space-y-1">
                                    <div className="border-t border-gray-700 mb-4 pt-4"></div>
                                    
                                    <Link
                                        href="/"
                                        onClick={() => setSidebarOpen(false)}
                                        className="text-gray-300 hover:bg-gray-700 hover:text-white group flex items-center px-2 py-2 text-base font-medium rounded-md"
                                    >
                                        <span className="mr-4">
                                            <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                                            </svg>
                                        </span>
                                        Volver al Sitio
                                    </Link>

                                    <Link
                                        href="/profile"
                                        onClick={() => setSidebarOpen(false)}
                                        className="text-gray-300 hover:bg-gray-700 hover:text-white group flex items-center px-2 py-2 text-base font-medium rounded-md"
                                    >
                                        <span className="mr-4">
                                            <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                            </svg>
                                        </span>
                                        Mi Perfil
                                    </Link>

                                    <Link
                                        href={route('logout')}
                                        method="post"
                                        as="button"
                                        onClick={() => setSidebarOpen(false)}
                                        className="text-gray-300 hover:bg-gray-700 hover:text-red-300 group flex items-center px-2 py-2 text-base font-medium rounded-md w-full text-left"
                                    >
                                        <span className="mr-4">
                                            <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                                            </svg>
                                        </span>
                                        Cerrar Sesión
                                    </Link>
                                </div>
                            </div>
                    </Transition.Child>
                </div>
            </Transition>
        </div>
    );
}