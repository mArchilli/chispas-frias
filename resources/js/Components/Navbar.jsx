import { useState, useEffect } from 'react';
import { Link } from '@inertiajs/react';

export default function Navbar({ auth }) {
    const [isScrolled, setIsScrolled] = useState(false);

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
                isScrolled
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

                    {/* Navigation Links */}
                    <div className="flex items-center space-x-8">
                        <Link
                            href="/"
                            className="text-chalk hover:text-gold transition font-medium"
                        >
                            Inicio
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
        </nav>
    );
}
