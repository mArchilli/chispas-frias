import React from 'react';

export default function StatusDot({ active, title }) {
    return (
        <span
            className={`inline-flex h-1.5 w-1.5 flex-shrink-0 rounded-full ${active ? 'bg-emerald-500' : 'bg-slate-300'}`}
            title={title ?? (active ? 'Activa' : 'Inactiva')}
        />
    );
}
