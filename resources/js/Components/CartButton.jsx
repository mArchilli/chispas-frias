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
            className={`block fixed bottom-[88px] md:bottom-[100px] right-6 z-50 transition-all duration-500 ${show ? 'opacity-100 translate-y-0 pointer-events-auto' : 'opacity-0 translate-y-10 pointer-events-none'}`}
        >
            <Link
                href={route('cart.index')}
                className="group flex h-14 w-14 items-center justify-center rounded-full border-2 border-white bg-navy p-0 text-white shadow-lg transition-all duration-300 hover:-translate-y-1 hover:bg-navy/90 hover:shadow-xl md:h-16 md:w-16"
                aria-label="Ver carrito de compras"
            >
                {/* Icono del carrito */}
                <div className="relative">
                    <svg 
                        className="h-6 w-6 md:h-7 md:w-7"
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
                        <span className="absolute -top-2 -right-2 bg-gold text-navy text-xs md:text-sm font-bold rounded-full h-5 w-5 md:h-6 md:w-6 flex items-center justify-center min-w-[20px] md:min-w-[24px] px-1">
                            {cartCount > 99 ? '99+' : cartCount}
                        </span>
                    )}
                </div>

                
            </Link>
        </div>
    );
}
