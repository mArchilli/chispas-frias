export const ESTADO_LABELS = {
    pendiente: 'Pendiente',
    despachado: 'Despachado',
    cancelado: 'Cancelado',
};

export const ESTADO_BADGE_CLASSES = {
    pendiente: 'bg-amber-100 text-amber-800',
    despachado: 'bg-green-100 text-green-800',
    cancelado: 'bg-red-100 text-red-800',
};

export function estadoLabel(estado) {
    return ESTADO_LABELS[estado] ?? estado;
}

export function estadoBadgeClasses(estado) {
    return ESTADO_BADGE_CLASSES[estado] ?? 'bg-gray-100 text-gray-800';
}
