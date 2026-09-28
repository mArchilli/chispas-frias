export default function PrimaryButton({
    className = '',
    disabled,
    children,
    ...props
}) {
    return (
        <button
            {...props}
            className={
                `inline-flex items-center rounded-full border border-transparent bg-storefront px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white transition duration-150 ease-in-out hover:brightness-90 focus:outline-none focus:ring-2 focus:ring-storefront focus:ring-offset-2 active:brightness-90 disabled:cursor-not-allowed disabled:bg-gray-200 disabled:text-gray-500 ${
                    disabled && 'opacity-70'
                } ` + className
            }
            disabled={disabled}
        >
            {children}
        </button>
    );
}
