import { Link } from '@inertiajs/react';

export default function ResponsiveNavLink({
    active = false,
    className = '',
    children,
    ...props
}) {
    return (
        <Link
            {...props}
            className={`flex w-full items-start border-l-4 py-2 pe-4 ps-3 ${
                active
                    ? 'border-ice-500 bg-ice-50 text-navy-700 focus:border-ice-500 focus:bg-ice-100 focus:text-navy-900'
                    : 'border-transparent text-gray-500 hover:border-gray-200 hover:bg-ice-50 hover:text-graphite focus:border-ice-500 focus:bg-ice-50 focus:text-graphite'
            } text-base font-medium transition duration-150 ease-in-out focus:outline-none ${className}`}
        >
            {children}
        </Link>
    );
}
