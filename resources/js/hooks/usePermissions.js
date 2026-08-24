import { usePage } from '@inertiajs/react';

/**
 * Centraliza la lectura de auth.user.role compartido por Inertia (ver
 * HandleInertiaRequests) para que el chequeo "¿soy admin?" no se repita
 * copiado en cada página del admin. El backend es la fuente real de verdad
 * (Gate 'borrar-catalogo'); esto sólo maneja qué se muestra en el front.
 */
export default function usePermissions() {
    const { auth } = usePage().props;
    const role = auth?.user?.role ?? null;

    return {
        role,
        isAdmin: role === 'admin',
        isVendedor: role === 'vendedor',
        canBorrarCatalogo: role === 'admin',
    };
}
