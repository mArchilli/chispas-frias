import { useState, useEffect } from 'react';
import { Link } from '@inertiajs/react';
import axios from 'axios';

export default function CartButton() {
    const [show, setShow] = useState(false);
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
        const handleScroll = () => {
            setShow(window.scrollY > 0);
        };
        window.addEventListener('scroll', handleScroll);
        handleScroll(); // Para el estado inicial
        return () => window.removeEventListener('scroll', handleScroll);
    }, []);

    useEffect(() => {
        fetchCartCount();
        
        // Actualizar contador cuando hay cambios en el carrito
        const handleCartUpdate = () => {
            fetchCartCount();
        };
        
        window.addEventListener('cart-updated', handleCartUpdate);
        return () => window.removeEventListener('cart-updated', handleCartUpdate);
    }, []);

    return (
        <div
            className={`block fixed bottom-[88px] md:bottom-[120px] right-6 z-50 transition-all duration-500 ${show ? 'opacity-100 translate-y-0 pointer-events-auto' : 'opacity-0 translate-y-10 pointer-events-none'}`}
        >
            <Link
                href={route('cart.index')}
                className="flex items-center gap-3 md:gap-4 bg-navy hover:bg-navy/90 text-white px-5 py-3 md:px-7 md:py-4 rounded-full shadow-lg hover:shadow-xl transition-all duration-300 hover:scale-105 group border-2 border-white"
                aria-label="Ver carrito de compras"
            >
                {/* Icono del carrito */}
                <div className="relative">
                    <svg 
                        className="w-6 h-6 md:w-8 md:h-8" 
                        fill="none" 
                        stroke="currentColor" 
                        viewBox="0 0 24 24"
                    >
                        <path 
                            strokeLinecap="round" 
                            strokeLinejoin="round" 
                            strokeWidth={2} 
                            d="M3 3h2l.4 2M7 13h10l4-8H5.4m0 0L7 13m0 0l-2.5 5L21 18"
                        />
                    </svg>
                    
                    {/* Contador de items */}
                    {cartCount > 0 && (
                        <span className="absolute -top-2 -right-2 bg-gold text-navy text-xs md:text-sm font-bold rounded-full h-5 w-5 md:h-6 md:w-6 flex items-center justify-center min-w-[20px] md:min-w-[24px] px-1">
                            {cartCount > 99 ? '99+' : cartCount}
                        </span>
                    )}
                </div>

                
            </Link>
        </div>
    );
}
