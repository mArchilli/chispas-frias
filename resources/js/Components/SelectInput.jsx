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
                'border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm ' +
                className
            }
            ref={select}
        >
            {children}
        </select>
    );
});