import { Link } from '@inertiajs/react';

export default function NavLink({
    active = false,
    className = '',
    children,
    ...props
}) {
    return (
        <Link
            {...props}
            className={
                'inline-flex items-center border-b-2 px-1 pt-1 text-sm font-medium leading-5 transition duration-150 ease-in-out focus:outline-none ' +
                (active
                    ? 'border-ice-500 text-navy-900 focus:border-ice-500'
                    : 'border-transparent text-gray-500 hover:border-ice-500 hover:text-navy-700 focus:border-ice-500 focus:text-navy-700') +
                className
            }
        >
            {children}
        </Link>
    );
}
