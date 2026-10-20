import { usePage } from '@inertiajs/react';

/**
 * Número de WhatsApp de ventas. Vive en el servidor (config/tienda.php ← WHATSAPP_NUMERO) y llega
 * al front como prop compartida de Inertia (HandleInertiaRequests → props.tienda.whatsapp); no se
 * escribe en ningún componente.
 */
export function useWhatsappNumero() {
    const { tienda } = usePage().props;

    return soloDigitos(tienda?.whatsapp);
}

/**
 * URL pública de la tienda (sin barra final). Vive en el servidor (APP_URL → config('app.url'))
 * y llega como prop compartida (props.tienda.url); se usa en los datos estructurados (JSON-LD).
 */
export function useUrlTienda() {
    const { tienda } = usePage().props;

    return String(tienda?.url ?? '').replace(/\/+$/, '');
}

export function soloDigitos(numero) {
    return String(numero ?? '').replace(/\D/g, '');
}

/** Link `wa.me` al número, con un texto prellenado opcional. */
export function whatsappLink(numero, texto = '') {
    const query = texto ? `?text=${encodeURIComponent(texto)}` : '';

    return `https://wa.me/${soloDigitos(numero)}${query}`;
}

/** Celular argentino (54 9 + área de 2 dígitos + 8) → "+54 9 AA NNNN-NNNN"; otro formato → "+<dígitos>". */
export function formatearWhatsapp(numero) {
    const digitos = soloDigitos(numero);
    const celularAr = digitos.match(/^549(\d{2})(\d{4})(\d{4})$/);

    if (celularAr) {
        return `+54 9 ${celularAr[1]} ${celularAr[2]}-${celularAr[3]}`;
    }

    return digitos ? `+${digitos}` : '';
}
