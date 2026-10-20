export const ESTADO_LABELS = {
    pendiente: 'Pendiente',
    confirmado: 'Confirmado',
    empaquetado: 'Empaquetado',
    despachado: 'Despachado',
    entregado: 'Entregado',
    cancelado: 'Cancelado',
    anulado: 'Anulado',
};

export const ESTADO_BADGE_CLASSES = {
    pendiente: 'bg-ice-100 text-navy-700',
    confirmado: 'bg-blue-100 text-blue-800',
    empaquetado: 'bg-indigo-100 text-indigo-800',
    despachado: 'bg-navy-900 text-white',
    entregado: 'bg-emerald-100 text-emerald-800',
    cancelado: 'bg-gray-200 text-gray-500',
    anulado: 'bg-red-100 text-red-700',
};

export function estadoLabel(estado) {
    return ESTADO_LABELS[estado] ?? estado;
}

export function estadoBadgeClasses(estado) {
    return ESTADO_BADGE_CLASSES[estado] ?? 'bg-background text-graphite';
}
