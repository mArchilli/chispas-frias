export const ESTADO_LABELS = {
    pendiente: 'Pendiente',
    despachado: 'Despachado',
    cancelado: 'Cancelado',
};

export const ESTADO_BADGE_CLASSES = {
    pendiente: 'bg-ice-100 text-navy-700',
    despachado: 'bg-navy-900 text-white',
    cancelado: 'bg-gray-200 text-gray-500',
};

export function estadoLabel(estado) {
    return ESTADO_LABELS[estado] ?? estado;
}

export function estadoBadgeClasses(estado) {
    return ESTADO_BADGE_CLASSES[estado] ?? 'bg-background text-graphite';
}
