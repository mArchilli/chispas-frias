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
        <>
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
                                className="h-12 w-auto transition-all duration-300"
                                style={isScrolled ? {
                                    filter: 'drop-shadow(0 0 40px rgba(0,0,0,1)) drop-shadow(0 0 20px rgba(0,0,0,1)) drop-shadow(0 8px 20px rgba(0,0,0,1)) brightness(1.15)'
                                } : {
                                    filter: 'drop-shadow(0 4px 20px rgba(0,0,0,0.5))'
                                }}
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
                                    style={isScrolled ? {
                                        filter: 'drop-shadow(0 0 50px rgba(0,0,0,1)) drop-shadow(0 0 30px rgba(0,0,0,1)) drop-shadow(0 10px 30px rgba(0,0,0,1)) drop-shadow(0 0 10px rgba(0,0,0,1))'
                                    } : {
                                        filter: 'drop-shadow(0 4px 30px rgba(0,0,0,0.8))'
                                    }}
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
                            style={isScrolled ? {
                                textShadow: '0 0 15px rgba(0,0,0,1), 0 0 8px rgba(0,0,0,1), 0 2px 10px rgba(0,0,0,0.9)'
                            } : {}}
                        >
                            Inicio
                        </Link>
                        <Link
                            href={route('products.index')}
                            className="text-chalk hover:text-gold transition font-medium"
                            style={isScrolled ? {
                                textShadow: '0 0 15px rgba(0,0,0,1), 0 0 8px rgba(0,0,0,1), 0 2px 10px rgba(0,0,0,0.9)'
                            } : {}}
                        >
                            Productos
                        </Link>
                        <Link
                            href="#servicios"
                            className="text-chalk hover:text-gold transition font-medium"
                            style={isScrolled ? {
                                textShadow: '0 0 15px rgba(0,0,0,1), 0 0 8px rgba(0,0,0,1), 0 2px 10px rgba(0,0,0,0.9)'
                            } : {}}
                        >
                            Servicios
                        </Link>
                        <Link
                            href="#contacto"
                            className="text-chalk hover:text-gold transition font-medium"
                            style={isScrolled ? {
                                textShadow: '0 0 15px rgba(0,0,0,1), 0 0 8px rgba(0,0,0,1), 0 2px 10px rgba(0,0,0,0.9)'
                            } : {}}
                        >
                            Contacto
                        </Link>
                    </div>
                </div>
            </div>
        </nav>

        {/* Mobile Menu Overlay */}
        {isMenuOpen && (
            <div
                className="fixed inset-0 bg-navy/80 backdrop-blur-sm z-40 md:hidden"
                onClick={() => setIsMenuOpen(false)}
            />
        )}

        {/* Mobile Menu Sidebar */}
        <div
            className={`fixed top-0 right-0 h-full w-[70%] bg-navy/95 backdrop-blur-lg shadow-2xl z-50 md:hidden transform transition-transform duration-300 ease-in-out ${
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
                </div>
            </div>
        </div>
        </>
    );
}
