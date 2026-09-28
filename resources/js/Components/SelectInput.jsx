import { forwardRef, useRef } from 'react';

export default forwardRef(function SelectInput(
    { children, className = '', ...props },
    ref
) {
    const select = ref ? ref : useRef();

    return (
        <select
            {...props}
            className={
                'rounded-md border-gray-200 bg-surface text-graphite shadow-sm focus:border-ice-500 focus:ring-ice-500 ' +
                className
            }
            ref={select}
        >
            {children}
        </select>
    );
});
