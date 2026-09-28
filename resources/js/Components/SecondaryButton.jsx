export default function SecondaryButton({
    type = 'button',
    className = '',
    disabled,
    children,
    ...props
}) {
    return (
        <button
            {...props}
            type={type}
            className={
                `inline-flex items-center rounded-full border border-gray-200 bg-surface px-4 py-2 text-xs font-semibold uppercase tracking-widest text-navy-900 shadow-sm transition duration-150 ease-in-out hover:border-ice-500 hover:bg-ice-50 focus:outline-none focus:ring-2 focus:ring-ice-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:bg-gray-200 disabled:text-gray-500 ${
                    disabled && 'opacity-70'
                } ` + className
            }
            disabled={disabled}
        >
            {children}
        </button>
    );
}
