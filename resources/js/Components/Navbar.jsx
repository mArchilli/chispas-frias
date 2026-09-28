import { useState, useEffect } from 'react';
import { Link, usePage } from '@inertiajs/react';
import axios from 'axios';
import Topbar from './Topbar';

export default function Navbar({ auth }) {
    const [isMenuOpen, setIsMenuOpen] = useState(false);
    const [cartCount, setCartCount] = useState(0);
    const { url } = usePage();
    const currentPath = url.split(/[?#]/)[0].replace(/\/+$/, '') || '/';
    const isActivePath = (path) => (
        path === '/'
            ? currentPath === '/'
            : currentPath === path || currentPath.startsWith(`${path}/`)
    );
    const desktopNavLinkClass = (active) => [
        'text-sm font-semibold uppercase tracking-[0.12em] text-navy-900 transition hover:text-navy-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ice-500 focus-visible:ring-offset-4',
        active ? 'underline decoration-ice-500 decoration-2 underline-offset-[10px]' : '',
    ].join(' ');
    const mobileNavLinkClass = (active) => [
        'text-lg font-medium uppercase tracking-[0.12em] text-white transition hover:text-ice-500',
        active ? 'underline decoration-ice-500 decoration-2 underline-offset-8' : '',
    ].join(' ');

    // Función para obtener el contador del carrito
    const fetchCartCount = async () => {
        try {
            const response = await axios.get(route('cart.count'));
            setCartCount(response.data.count);
        } catch (error) {
            console.error('Error al obtener contador del carrito:', error);
            setCartCount(0);
        }
    };

    useEffect(() => {
        fetchCartCount();
        
        // Actualizar contador cuando hay cambios en el carrito
        const handleCartUpdate = () => {
            fetchCartCount();
        };
        
        window.addEventListener('cart-updated', handleCartUpdate);
        return () => window.removeEventListener('cart-updated', handleCartUpdate);
    }, []); // Removido auth.user dependency

    return (
        <>
            <header
                className="fixed left-0 top-0 z-50 w-full border-b-2 border-gray-200 bg-surface shadow-soft"
            >
                <div className="border-b border-navy-900/15">
                    <Topbar />
                </div>
                <nav className="w-full">
                <div className="site-shell">
                    <div className="relative flex h-20 items-center justify-between">
                    {/* Logo */}
                    <div className="flex-shrink-0 opacity-100 transition-opacity duration-300">
                        <Link href="/" className="hover:scale-105 transition-transform duration-300">
                            <img
                                src="/images/chispas-frias-logo.png"
                                alt="Chispas Frías"
                                className="h-16 w-auto brightness-0 drop-shadow-sm transition-all duration-300"
                            />
                        </Link>
                    </div>

                    {/* Mobile Menu Button */}
                    <div className="md:hidden">
                        <button
                            type="button"
                            className="inline-flex items-center justify-center rounded-lg p-2 text-navy-900 transition-all duration-300 hover:scale-110 hover:bg-ice-50 hover:text-navy-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ice-500"
                            aria-label={isMenuOpen ? 'Cerrar menú' : 'Abrir menú'}
                            aria-expanded={isMenuOpen}
                            onClick={() => setIsMenuOpen((open) => !open)}
                        >
                            {isMenuOpen ? (
                                <svg
                                    xmlns="http://www.w3.org/2000/svg"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    strokeWidth="2"
                                    strokeLinecap="round"
                                    strokeLinejoin="round"
                                    className="h-6 w-6"
                                >
                                    <path d="M18 6 6 18" />
                                    <path d="M6 6l12 12" />
                                </svg>
                            ) : (
                                <svg
                                    xmlns="http://www.w3.org/2000/svg"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    strokeWidth="2"
                                    strokeLinecap="round"
                                    strokeLinejoin="round"
                                    className="h-6 w-6"
                                >
                                    <path d="M4 6h16" />
                                    <path d="M4 12h16" />
                                    <path d="M4 18h16" />
                                </svg>
                            )}
                        </button>
                    </div>

                    {/* Navigation Links */}
                    <div className="absolute left-1/2 hidden -translate-x-1/2 items-center gap-8 md:flex">
                        <Link
                            href="/"
                            className={desktopNavLinkClass(isActivePath('/'))}
                            aria-current={isActivePath('/') ? 'page' : undefined}
                        >
                            Inicio
                        </Link>
                        <Link
                            href={route('products.index')}
                            className={desktopNavLinkClass(isActivePath('/productos'))}
                            aria-current={isActivePath('/productos') ? 'page' : undefined}
                        >
                            Catálogo
                        </Link>
                        <Link
                            href={route('contact')}
                            className={desktopNavLinkClass(isActivePath('/contacto'))}
                            aria-current={isActivePath('/contacto') ? 'page' : undefined}
                        >
                            Contacto
                        </Link>
                    </div>

                    {/* Carrito */}
                    <Link
                        href={route('cart.index')}
                        className="relative hidden h-12 w-12 items-center justify-center rounded-full border border-storefront bg-storefront text-white shadow-soft transition-all duration-300 hover:-translate-y-0.5 hover:brightness-90 hover:shadow-card focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-storefront focus-visible:ring-offset-2 md:flex"
                        aria-label="Ver carrito de compras"
                    >
                        <svg
                            className="h-6 w-6"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                            aria-hidden="true"
                        >
                            <path
                                strokeLinecap="round"
                                strokeLinejoin="round"
                                strokeWidth={1.8}
                                d="M6.5 8.5h11l1 11.5h-13l1-11.5Z"
                            />
                            <path
                                strokeLinecap="round"
                                strokeLinejoin="round"
                                strokeWidth={1.8}
                                d="M9 10V6.75a3 3 0 0 1 6 0V10"
                            />
                        </svg>

                        {/* Contador de items */}
                        {cartCount > 0 && (
                            <span className="absolute -right-1 -top-1 flex h-5 min-w-[20px] items-center justify-center rounded-full bg-ice-500 px-1 text-xs font-bold text-navy-900">
                                {cartCount > 99 ? '99+' : cartCount}
                            </span>
                        )}
                    </Link>
                    </div>
                </div>
                </nav>
            </header>

        {/* Mobile Menu Overlay */}
        {isMenuOpen && (
            <div
                className="fixed inset-0 bg-graphite/40 backdrop-blur-sm z-40 md:hidden"
                onClick={() => setIsMenuOpen(false)}
            />
        )}

        {/* Mobile Menu Sidebar */}
        <div
            className={`fixed top-0 right-0 h-full w-[40%] bg-navy-900/95 backdrop-blur-lg shadow-2xl z-50 md:hidden transform transition-transform duration-300 ease-in-out ${
                isMenuOpen ? 'translate-x-0' : 'translate-x-full'
            }`}
        >
            <div className="flex flex-col h-full px-6 py-8">
                {/* Close Button */}
                <div className="flex justify-end mb-8">
                    <button
                        type="button"
                        className="inline-flex items-center justify-center rounded-lg p-2 text-white hover:text-ice-500 transition"
                        aria-label="Cerrar menú"
                        onClick={() => setIsMenuOpen(false)}
                    >
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            strokeWidth="2"
                            strokeLinecap="round"
                            strokeLinejoin="round"
                            className="h-6 w-6"
                        >
                            <path d="M18 6 6 18" />
                            <path d="M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                {/* Navigation Links */}
                <div className="flex flex-col gap-6">
                    <Link
                        href="/"
                        className={mobileNavLinkClass(isActivePath('/'))}
                        aria-current={isActivePath('/') ? 'page' : undefined}
                        onClick={() => setIsMenuOpen(false)}
                    >
                        Inicio
                    </Link>
                    <Link
                        href={route('products.index')}
                        className={mobileNavLinkClass(isActivePath('/productos'))}
                        aria-current={isActivePath('/productos') ? 'page' : undefined}
                        onClick={() => setIsMenuOpen(false)}
                    >
                        Catálogo
                    </Link>
                    <Link
                        href={route('contact')}
                        className={mobileNavLinkClass(isActivePath('/contacto'))}
                        aria-current={isActivePath('/contacto') ? 'page' : undefined}
                        onClick={() => setIsMenuOpen(false)}
                    >
                        Contacto
                    </Link>
                    
                    {/* Carrito */}
                    <Link
                        href={route('cart.index')}
                        className="text-white hover:text-ice-500 transition font-medium text-lg flex items-center gap-3"
                        onClick={() => setIsMenuOpen(false)}
                        aria-label="Ver carrito de compras"
                    >
                        <div className="relative">
                            <svg 
                                className="h-6 w-6"
                                fill="none" 
                                stroke="currentColor" 
                                viewBox="0 0 24 24"
                                aria-hidden="true"
                            >
                                <path 
                                    strokeLinecap="round" 
                                    strokeLinejoin="round" 
                                    strokeWidth={1.8}
                                    d="M6.5 8.5h11l1 11.5h-13l1-11.5Z"
                                />
                                <path
                                    strokeLinecap="round"
                                    strokeLinejoin="round"
                                    strokeWidth={1.8}
                                    d="M9 10V6.75a3 3 0 0 1 6 0V10"
                                />
                            </svg>
                            
                            {/* Contador de items */}
                            {cartCount > 0 && (
                                <span className="absolute -top-2 -right-2 bg-ice-500 text-navy-900 text-xs font-bold rounded-full h-5 w-5 flex items-center justify-center min-w-[20px] px-1">
                                    {cartCount > 99 ? '99+' : cartCount}
                                </span>
                            )}
                        </div>
                        <span>Carrito</span>
                    </Link>


                </div>
            </div>
        </div>
        </>
    );
}
