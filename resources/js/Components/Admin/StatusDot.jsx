import React from 'react';

export default function StatusDot({ active, title }) {
    return (
        <span
            className={`inline-flex h-1.5 w-1.5 flex-shrink-0 rounded-full ${active ? 'bg-ice-500' : 'bg-gray-200'}`}
            title={title ?? (active ? 'Activa' : 'Inactiva')}
        />
    );
}
