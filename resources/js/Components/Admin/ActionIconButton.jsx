import React from 'react';
import { Link } from '@inertiajs/react';

const TONE_CLASSES = {
    default: 'text-slate-400 hover:bg-slate-100 hover:text-slate-700',
    danger: 'text-slate-400 hover:bg-rose-50 hover:text-rose-600',
    active: 'text-gold bg-gold/10 hover:bg-gold/15',
};

/**
 * Botón de acción compacto con ícono + tooltip nativo (title), usado en las
 * filas/cards de listados del admin (Categorías, Productos). `tone="active"`
 * marca visualmente un estado ya activado (ej. destacado, oferta vigente).
 */
export default function ActionIconButton({
    href,
    onClick,
    icon: Icon,
    iconProps = {},
    label,
    tone = 'default',
    disabled = false,
}) {
    const className = `flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-md transition ${TONE_CLASSES[tone]} ${
        disabled ? 'pointer-events-none opacity-40' : ''
    }`;

    if (href) {
        return (
            <Link href={href} onClick={(e) => e.stopPropagation()} title={label} className={className}>
                <Icon className="h-4 w-4" {...iconProps} />
            </Link>
        );
    }

    return (
        <button
            type="button"
            onClick={(e) => {
                e.stopPropagation();
                onClick?.();
            }}
            title={label}
            disabled={disabled}
            className={className}
        >
            <Icon className="h-4 w-4" {...iconProps} />
        </button>
    );
}
