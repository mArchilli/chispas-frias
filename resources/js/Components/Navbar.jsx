import { useState, useEffect } from 'react';
import { Link } from '@inertiajs/react';
import axios from 'axios';
import Topbar from './Topbar';

export default function Navbar({ auth }) {
    const [isMenuOpen, setIsMenuOpen] = useState(false);
    const [cartCount, setCartCount] = useState(0);

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
                className="fixed left-0 top-0 z-50 w-full border-b-2 border-navy bg-white shadow-[0_4px_16px_rgba(10,31,68,0.08)]"
            >
                <Topbar />
                <nav className="w-full">
                <div className="site-shell">
                    <div className="flex items-center justify-between h-20">
                    {/* Logo */}
                    <div className="flex-shrink-0 opacity-100 transition-opacity duration-300">
                        <Link href="/" className="hover:scale-105 transition-transform duration-300">
                            <img
                                src="/images/chispas-frias-logo.png"
                                alt="Chispas Frías"
                                className="h-16 w-auto transition-all duration-300"
                                style={{
                                    filter: 'brightness(0) drop-shadow(0 4px 10px rgba(10,31,68,0.18))'
                                }}
                            />
                        </Link>
                    </div>

                    {/* Mobile Menu Button */}
                    <div className="md:hidden">
                        <button
                            type="button"
                            className="inline-flex items-center justify-center rounded-lg p-2 text-navy hover:text-navy/70 hover:scale-110 transition-all duration-300"
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
                    <div className="hidden md:flex items-center space-x-8">
                        <Link
                            href="/"
                            className="text-navy hover:text-navy/70 transition font-medium"
                        >
                            Inicio
                        </Link>
                        <Link
                            href={route('products.index')}
                            className="text-navy hover:text-navy/70 transition font-medium"
                        >
                            Productos
                        </Link>
                        <Link
                            href={route('services')}
                            className="text-navy hover:text-navy/70 transition font-medium"
                        >
                            Servicios
                        </Link>
                        <Link
                            href={route('contact')}
                            className="text-navy hover:text-navy/70 transition font-medium"
                        >
                            Contacto
                        </Link>
                        
                        {/* Carrito */}
                        <Link
                            href={route('cart.index')}
                            className="flex h-10 w-10 items-center justify-center rounded-full border border-navy/15 bg-navy/[0.03] text-navy transition-all duration-300 hover:-translate-y-0.5 hover:bg-navy hover:text-white"
                            aria-label="Ver carrito de compras"
                        >
                            <div className="relative">
                                <svg 
                                    className="h-5 w-5"
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
                                    <span className="absolute -top-2 -right-2 bg-gold text-navy text-xs font-bold rounded-full h-5 w-5 flex items-center justify-center min-w-[20px] px-1">
                                        {cartCount > 99 ? '99+' : cartCount}
                                    </span>
                                )}
                            </div>
                        </Link>
                    </div>
                    </div>
                </div>
                </nav>
            </header>

        {/* Mobile Menu Overlay */}
        {isMenuOpen && (
            <div
                className="fixed inset-0 bg-black/40 backdrop-blur-sm z-40 md:hidden"
                onClick={() => setIsMenuOpen(false)}
            />
        )}

        {/* Mobile Menu Sidebar */}
        <div
            className={`fixed top-0 right-0 h-full w-[40%] bg-navy/95 backdrop-blur-lg shadow-2xl z-50 md:hidden transform transition-transform duration-300 ease-in-out ${
                isMenuOpen ? 'translate-x-0' : 'translate-x-full'
            }`}
        >
            <div className="flex flex-col h-full px-6 py-8">
                {/* Close Button */}
                <div className="flex justify-end mb-8">
                    <button
                        type="button"
                        className="inline-flex items-center justify-center rounded-lg p-2 text-chalk hover:text-gold transition"
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
                        className="text-chalk hover:text-gold transition font-medium text-lg"
                        onClick={() => setIsMenuOpen(false)}
                    >
                        Inicio
                    </Link>
                    <Link
                        href={route('products.index')}
                        className="text-chalk hover:text-gold transition font-medium text-lg"
                        onClick={() => setIsMenuOpen(false)}
                    >
                        Productos
                    </Link>
                    <Link
                        href={route('services')}
                        className="text-chalk hover:text-gold transition font-medium text-lg"
                        onClick={() => setIsMenuOpen(false)}
                    >
                        Servicios
                    </Link>
                    <Link
                        href={route('contact')}
                        className="text-chalk hover:text-gold transition font-medium text-lg"
                        onClick={() => setIsMenuOpen(false)}
                    >
                        Contacto
                    </Link>
                    
                    {/* Carrito */}
                    <Link
                        href={route('cart.index')}
                        className="text-chalk hover:text-gold transition font-medium text-lg flex items-center gap-3"
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
                                <span className="absolute -top-2 -right-2 bg-gold text-navy text-xs font-bold rounded-full h-5 w-5 flex items-center justify-center min-w-[20px] px-1">
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
