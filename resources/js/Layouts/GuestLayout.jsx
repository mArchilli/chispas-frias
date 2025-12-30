import ApplicationLogo from '@/Components/ApplicationLogo';
import { Link } from '@inertiajs/react';

export default function GuestLayout({ children }) {
    return (
        <div
            className="font-sans flex min-h-screen flex-col items-center pt-8 pb-12"
            style={{
                backgroundImage: "url('/images/fondo-productos.png')",
                backgroundSize: 'cover',
                backgroundPosition: 'center',
            }}
        >
            <div className="w-full max-w-7xl px-6 lg:px-8">
                <div className="flex justify-center items-center py-6">
                    <Link href="/">
                        <img src="/images/chispas-frias-logo.png" alt="Chispas Frías" className="h-44 md:h-52 lg:h-56 w-auto transform transition-transform duration-300 hover:scale-105 hover:shadow-xl" />
                    </Link>
                </div>
            </div>

                <div className="mt-6 w-full px-6 sm:px-0 sm:max-w-md">
                <div className="w-full overflow-hidden bg-white/95 px-8 py-8 shadow-lg sm:rounded-2xl border-2 border-navy/10 hover:shadow-2xl hover:scale-[1.02] transition-all duration-500">
                    {children}
                </div>
            </div>
        </div>
    );
}
