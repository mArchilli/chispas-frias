export default function Checkbox({ className = '', ...props }) {
    return (
        <input
            {...props}
            type="checkbox"
            className={
                'rounded border-gray-200 text-navy-900 shadow-sm focus:ring-ice-500 ' +
                className
            }
        />
    );
}
