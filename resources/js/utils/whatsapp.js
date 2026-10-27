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

/**
 * Normaliza un teléfono cargado a mano por el cliente (ej. "11 2345-6789", "011 15 2345-6789", "+54 9 351 676-6208") al formato
 * internacional sin `+` que esperan wa.me / whatsapp:// para celulares argentinos (549 + área + número, sin el 0 ni el 15). Si no se
 * reconoce como un número argentino de 10 dígitos, se devuelve tal cual (solo dígitos), asumiendo que ya trae su código de país.
 * `null` si no hay dígitos suficientes para armar un número.
 */
export function normalizarTelefonoWhatsApp(telefono) {
    let digitos = soloDigitos(telefono).replace(/^00/, '');

    if (digitos.length < 8) return null;

    if (digitos.startsWith('54')) {
        // 54 + 10 dígitos (fijo/sin 9) → falta el 9 de celular; 549 + 10 ya está bien.
        if (digitos.length === 12) digitos = `549${digitos.slice(2)}`;
        return digitos;
    }

    digitos = digitos.replace(/^0/, '');

    // "15" entre el código de área (2 a 4 dígitos) y el número: 12 dígitos → 10.
    if (digitos.length === 12) {
        for (const largoArea of [2, 3, 4]) {
            if (digitos.slice(largoArea, largoArea + 2) === '15') {
                digitos = digitos.slice(0, largoArea) + digitos.slice(largoArea + 2);
                break;
            }
        }
    }

    return digitos.length === 10 ? `549${digitos}` : digitos;
}

/** Abre WhatsApp con un mensaje para `numero` (ya normalizado): app en mobile, WhatsApp Web en desktop. */
export function abrirWhatsApp(numero, mensaje = '') {
    const texto = encodeURIComponent(mensaje);

    if (/Android|iPhone|iPad|iPod/i.test(navigator.userAgent)) {
        window.location.href = `https://wa.me/${numero}?text=${texto}`;
    } else {
        window.open(`https://web.whatsapp.com/send?phone=${numero}&text=${texto}`, '_blank', 'noopener,noreferrer');
    }
}

/** Lo que el empaquetador necesita SÍ O SÍ para despachar con transportista (espeja App\Rukoz\DatosDeEnvio::REQUERIDOS). */
export const DATOS_DE_ENVIO = [
    { campo: 'dni', nombre: 'DNI', inputMode: 'numeric', autoComplete: 'off' },
    { campo: 'city', nombre: 'Localidad', inputMode: 'text', autoComplete: 'off' },
    { campo: 'postal_code', nombre: 'Código postal', inputMode: 'numeric', autoComplete: 'off' },
    { campo: 'phone', nombre: 'Teléfono', inputMode: 'tel', autoComplete: 'off' },
];

/** Los datos de envío que le faltan a un pedido (`[{ campo, nombre }]`). */
export const datosDeEnvioFaltantes = (pedido) =>
    DATOS_DE_ENVIO.filter(({ campo }) => String(pedido?.[campo] ?? '').trim() === '');

/** Mensaje para pedirle al cliente, por WhatsApp, lo que falta para despachar. `faltantes` = `[{ nombre }]`. */
export function buildPedidoDatosFaltantesMessage(pedido, faltantes) {
    return [
        `¡Hola ${pedido.nombre_completo}! 👋 Te escribimos de *Chispas Frías* por tu pedido #${pedido.id}.`,
        '',
        'Para poder despacharlo necesitamos que nos pases:',
        ...faltantes.map(({ nombre }) => `• ${nombre}`),
        '',
        '¡Gracias! 😊',
    ].join('\n');
}
