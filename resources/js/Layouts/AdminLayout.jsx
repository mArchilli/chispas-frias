import React from 'react';
import { Link, usePage } from '@inertiajs/react';
import { Transition } from '@headlessui/react';

export default function AdminLayout({ children, header = null }) {
    const { auth } = usePage().props;
    const [sidebarOpen, setSidebarOpen] = React.useState(false);

    const navigation = [
        { name: 'Dashboard', href: '/admin/dashboard', current: false },
        { name: 'Categorías', href: '/admin/categories', current: false },
        { name: 'Productos', href: '/admin/products', current: false },
        { name: 'Órdenes', href: '/admin/orders', current: false },
        { name: 'Usuarios', href: '/admin/users', current: false },
    ];

    const currentPath = usePage().url;
    const updatedNavigation = navigation.map(item => ({
        ...item,
        current: currentPath.startsWith(item.href)
    }));

    return (
        <div className="min-h-screen bg-gray-100">
            {/* Sidebar */}
            <div className="hidden md:fixed md:inset-y-0 md:flex md:w-64 md:flex-col">
                <div className="flex min-h-0 flex-1 flex-col bg-gray-800">
                    <div className="flex h-16 flex-shrink-0 items-center bg-gray-900 px-4">
                        <Link href="/admin/dashboard" className="flex items-center">
                            <h1 className="text-xl font-bold text-white">Chispas Frías</h1>
                        </Link>
                    </div>
                    <div className="flex flex-1 flex-col overflow-y-auto">
                        <nav className="flex-1 space-y-1 px-2 py-4">
                            {updatedNavigation.map((item) => (
                                <Link
                                    key={item.name}
                                    href={item.href}
                                    className={`${
                                        item.current
                                            ? 'bg-gray-900 text-white'
                                            : 'text-gray-300 hover:bg-gray-700 hover:text-white'
                                    } group flex items-center px-2 py-2 text-sm font-medium rounded-md`}
                                >
                                    {item.name}
                                </Link>
                            ))}
                        </nav>
                        
                        {/* User section */}
                        <div className="flex-shrink-0 px-2 pb-4 space-y-1">
                            {/* Divider */}
                            <div className="border-t border-gray-700 my-4"></div>
                            
                            {/* Back to site */}
                            <Link
                                href="/"
                                className="text-gray-300 hover:bg-gray-700 hover:text-white group flex items-center px-2 py-2 text-sm font-medium rounded-md"
                            >
                                <svg className="mr-3 h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                                </svg>
                                Volver al Sitio
                            </Link>

                            {/* Profile */}
                            <Link
                                href="/profile"
                                className="text-gray-300 hover:bg-gray-700 hover:text-white group flex items-center px-2 py-2 text-sm font-medium rounded-md"
                            >
                                <svg className="mr-3 h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                </svg>
                                Mi Perfil
                            </Link>

                            {/* Logout */}
                            <Link
                                href={route('logout')}
                                method="post"
                                as="button"
                                className="text-gray-300 hover:bg-gray-700 hover:text-red-300 group flex items-center px-2 py-2 text-sm font-medium rounded-md w-full text-left"
                            >
                                <svg className="mr-3 h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                                </svg>
                                Cerrar Sesión
                            </Link>
                        </div>
                    </div>
                </div>
            </div>

            {/* Main content */}
            <div className="md:pl-64 flex flex-col flex-1">
                {/* Top navigation */}
                <div className="sticky top-0 z-10 md:hidden pl-1 pt-1 sm:pl-3 sm:pt-3 bg-gray-100">
                    <button
                        type="button"
                        className="-ml-0.5 -mt-0.5 h-12 w-12 inline-flex items-center justify-center rounded-md text-gray-500 hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-indigo-500"
                        onClick={() => setSidebarOpen(true)}
                    >
                        <span className="sr-only">Abrir sidebar</span>
                        <svg className="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                    </button>
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

            {/* Mobile sidebar */}
            <Transition show={sidebarOpen} as={React.Fragment}>
                <div className="fixed inset-0 flex z-40 md:hidden">
                    <Transition.Child
                        as={React.Fragment}
                        enter="transition-opacity ease-linear duration-300"
                        enterFrom="opacity-0"
                        enterTo="opacity-100"
                        leave="transition-opacity ease-linear duration-300"
                        leaveFrom="opacity-100"
                        leaveTo="opacity-0"
                    >
                        <div className="fixed inset-0 bg-gray-600 bg-opacity-75" onClick={() => setSidebarOpen(false)} />
                    </Transition.Child>

                    <Transition.Child
                        as={React.Fragment}
                        enter="transition ease-in-out duration-300 transform"
                        enterFrom="-translate-x-full"
                        enterTo="translate-x-0"
                        leave="transition ease-in-out duration-300 transform"
                        leaveFrom="translate-x-0"
                        leaveTo="-translate-x-full"
                    >
                        <div className="relative flex-1 flex flex-col max-w-xs w-full bg-gray-800">
                            <Transition.Child
                                as={React.Fragment}
                                enter="ease-in-out duration-300"
                                enterFrom="opacity-0"
                                enterTo="opacity-100"
                                leave="ease-in-out duration-300"
                                leaveFrom="opacity-100"
                                leaveTo="opacity-0"
                            >
                                <div className="absolute top-0 right-0 -mr-12 pt-2">
                                    <button
                                        type="button"
                                        className="ml-1 flex items-center justify-center h-10 w-10 rounded-full focus:outline-none focus:ring-2 focus:ring-inset focus:ring-white"
                                        onClick={() => setSidebarOpen(false)}
                                    >
                                        <span className="sr-only">Cerrar sidebar</span>
                                        <svg className="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                                        </svg>
                                    </button>
                                </div>
                            </Transition.Child>
                            <div className="flex-1 h-0 pt-5 pb-4 overflow-y-auto">
                                <div className="flex-shrink-0 flex items-center px-4">
                                    <h1 className="text-xl font-bold text-white">Admin Panel</h1>
                                </div>
                                <nav className="mt-5 px-2 space-y-1 flex-1">
                                    {updatedNavigation.map((item) => (
                                        <Link
                                            key={item.name}
                                            href={item.href}
                                            className={`${
                                                item.current
                                                    ? 'bg-gray-900 text-white'
                                                    : 'text-gray-300 hover:bg-gray-700 hover:text-white'
                                            } group flex items-center px-2 py-2 text-base font-medium rounded-md`}
                                            onClick={() => setSidebarOpen(false)}
                                        >
                                            {item.name}
                                        </Link>
                                    ))}
                                </nav>

                                {/* Mobile User section */}
                                <div className="px-2 mt-6 space-y-1">
                                    {/* Divider */}
                                    <div className="border-t border-gray-700 my-4"></div>
                                    
                                    {/* Back to site */}
                                    <Link
                                        href="/"
                                        className="text-gray-300 hover:bg-gray-700 hover:text-white group flex items-center px-2 py-2 text-base font-medium rounded-md"
                                        onClick={() => setSidebarOpen(false)}
                                    >
                                        <svg className="mr-3 h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                                        </svg>
                                        Volver al Sitio
                                    </Link>

                                    {/* Profile */}
                                    <Link
                                        href="/profile"
                                        className="text-gray-300 hover:bg-gray-700 hover:text-white group flex items-center px-2 py-2 text-base font-medium rounded-md"
                                        onClick={() => setSidebarOpen(false)}
                                    >
                                        <svg className="mr-3 h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                        </svg>
                                        Mi Perfil
                                    </Link>

                                    {/* Logout */}
                                    <Link
                                        href={route('logout')}
                                        method="post"
                                        as="button"
                                        className="text-gray-300 hover:bg-gray-700 hover:text-red-300 group flex items-center px-2 py-2 text-base font-medium rounded-md w-full text-left"
                                        onClick={() => setSidebarOpen(false)}
                                    >
                                        <svg className="mr-3 h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                                        </svg>
                                        Cerrar Sesión
                                    </Link>
                                </div>
                            </div>
                        </div>
                    </Transition.Child>
                </div>
            </Transition>
        </div>
    );
}