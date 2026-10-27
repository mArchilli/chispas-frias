/**
 * Los datos de contacto que recuerda el checkout en ESTE dispositivo (localStorage) para no volver a pedirlos: tipo de entrega,
 * nombre, teléfono y provincia. No se guarda nada más (ni DNI, ni observaciones, ni el pedido). Todo va con try/catch: en modo
 * privado o con el almacenamiento bloqueado simplemente no se recuerda nada.
 */
const CLAVE = 'chispas_frias_cliente';

export function leerClienteGuardado() {
    try {
        const datos = JSON.parse(localStorage.getItem(CLAVE));
        return datos && typeof datos === 'object' ? datos : null;
    } catch {
        return null;
    }
}

export function guardarCliente({ tipo, nombre, telefono, provincia }) {
    try {
        localStorage.setItem(CLAVE, JSON.stringify({ tipo, nombre, telefono, provincia }));
    } catch {
        // Sin almacenamiento utilizable: no se recuerda y listo.
    }
}

export function borrarClienteGuardado() {
    try {
        localStorage.removeItem(CLAVE);
    } catch {
        // Idem.
    }
}
