import React from 'react';
import { Link } from '@inertiajs/react';

const TONE_CLASSES = {
    default: 'text-gray-500 hover:bg-ice-100 hover:text-graphite/85',
    danger: 'text-gray-500 hover:bg-ice-50 hover:text-navy-700',
    active: 'text-navy-700 bg-ice-100 hover:bg-ice-100',
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
