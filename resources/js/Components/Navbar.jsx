import { useState, useEffect } from 'react';
import { Link } from '@inertiajs/react';

export default function Navbar({ auth }) {
    const [isScrolled, setIsScrolled] = useState(false);
    const [isMenuOpen, setIsMenuOpen] = useState(false);

    useEffect(() => {
        const handleScroll = () => {
            if (window.scrollY > 50) {
                setIsScrolled(true);
            } else {
                setIsScrolled(false);
            }
        };

        window.addEventListener('scroll', handleScroll);
        return () => window.removeEventListener('scroll', handleScroll);
    }, []);

    return (
        <nav
            className={`fixed top-0 left-0 w-full z-50 transition-all duration-300 ${
                isScrolled
                    ? 'backdrop-blur-lg'
                    : 'bg-transparent'
            }`}
            style={
                isScrolled && !isMenuOpen
                    ? {
                          maskImage: 'linear-gradient(to bottom, rgba(0,0,0,1) 60%, rgba(0,0,0,0) 100%)',
                          WebkitMaskImage: 'linear-gradient(to bottom, rgba(0,0,0,1) 60%, rgba(0,0,0,0) 100%)',
                      }
                    : {}
            }
        >
            <div className="max-w-7xl mx-auto px-6 lg:px-8">
                <div className="flex items-center justify-between h-20">
                    {/* Logo */}
                    <div className="flex-shrink-0">
                        <Link href="/">
                            <img
                                src="/images/chispas-frias-logo.png"
                                alt="Chispas Frías"
                                className="h-12 w-auto"
                            />
                        </Link>
                    </div>

                    {/* Mobile Menu Button */}
                    <div className="md:hidden">
                        <button
                            type="button"
                            className="inline-flex items-center justify-center rounded-lg p-2 text-chalk hover:text-gold transition"
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
                            className="text-chalk hover:text-gold transition font-medium"
                        >
                            Inicio
                        </Link>
                        <Link
                            href={route('products.index')}
                            className="text-chalk hover:text-gold transition font-medium"
                        >
                            Productos
                        </Link>
                        <Link
                            href="#servicios"
                            className="text-chalk hover:text-gold transition font-medium"
                        >
                            Servicios
                        </Link>
                        <Link
                            href="#contacto"
                            className="text-chalk hover:text-gold transition font-medium"
                        >
                            Contacto
                        </Link>

                        {/* Auth Links */}
                        {auth.user ? (
                            <Link
                                href={route('dashboard')}
                                className="text-chalk hover:text-gold transition font-medium"
                            >
                                Dashboard
                            </Link>
                        ) : (
                            <Link
                                href={route('login')}
                                className="px-4 py-2 bg-gold text-navy rounded-lg hover:bg-gold/90 transition font-semibold"
                            >
                                Iniciar Sesión
                            </Link>
                        )}
                    </div>
                </div>
            </div>

            {/* Mobile Menu Overlay */}
            {isMenuOpen && (
                <div
                    className="fixed inset-0 bg-navy/80 backdrop-blur-sm z-40 md:hidden"
                    onClick={() => setIsMenuOpen(false)}
                />
            )}

            {/* Mobile Menu Sidebar */}
            <div
                className={`fixed top-0 right-0 h-full w-[60%] bg-navy/95 backdrop-blur-lg shadow-2xl z-50 md:hidden transform transition-transform duration-300 ease-in-out ${
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
                            href="#servicios"
                            className="text-chalk hover:text-gold transition font-medium text-lg"
                            onClick={() => setIsMenuOpen(false)}
                        >
                            Servicios
                        </Link>
                        <Link
                            href="#contacto"
                            className="text-chalk hover:text-gold transition font-medium text-lg"
                            onClick={() => setIsMenuOpen(false)}
                        >
                            Contacto
                        </Link>

                        <div className="pt-6 mt-6 border-t border-chalk/20">
                            {auth.user ? (
                                <Link
                                    href={route('dashboard')}
                                    className="text-chalk hover:text-gold transition font-medium text-lg"
                                    onClick={() => setIsMenuOpen(false)}
                                >
                                    Dashboard
                                </Link>
                            ) : (
                                <Link
                                    href={route('login')}
                                    className="inline-flex items-center justify-center px-6 py-3 bg-gold text-navy rounded-lg hover:bg-gold/90 transition font-semibold text-base"
                                    onClick={() => setIsMenuOpen(false)}
                                >
                                    Iniciar Sesión
                                </Link>
                            )}
                        </div>
                    </div>
                </div>
            </div>
        </nav>
    );
}
