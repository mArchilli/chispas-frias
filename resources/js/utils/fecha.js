/**
 * Único lugar del front que formatea fechas. Lo guardado en la base está en UTC y NO se convierte:
 * se muestra en hora argentina (la misma zona que `config('app.display_timezone')` en el back, ver
 * App\Support\ZonaHoraria), sin importar la zona del navegador de quien mira.
 *
 * Solo sirve para instantes (created_at, updated_at…). Las vigencias de ofertas y códigos
 * (start_date / end_date) se guardan "tal cual se tipearon" y se muestran con timeZone: 'UTC' en
 * sus pantallas: no pasan por acá.
 */
export const ZONA_HORARIA = 'America/Argentina/Buenos_Aires';

const soloFecha = new Intl.DateTimeFormat('es-AR', {
    timeZone: ZONA_HORARIA,
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
});

const fechaYHora = new Intl.DateTimeFormat('es-AR', {
    timeZone: ZONA_HORARIA,
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
    hourCycle: 'h23',
});

const soloHora = new Intl.DateTimeFormat('es-AR', {
    timeZone: ZONA_HORARIA,
    hour: 'numeric',
    hourCycle: 'h23',
});

function aFecha(valor) {
    const fecha = valor instanceof Date ? valor : new Date(valor);

    return Number.isNaN(fecha.getTime()) ? null : fecha;
}

/** "2026-10-19T02:30:00.000000Z" → "18/10/2026" (hora argentina). Sin fecha → null. */
export function formatearFecha(valor) {
    const fecha = valor ? aFecha(valor) : null;

    return fecha ? soloFecha.format(fecha) : null;
}

/** "2026-10-19T02:30:00.000000Z" → "18/10/2026, 23:30" (hora argentina). Sin fecha → null. */
export function formatearFechaHora(valor) {
    const fecha = valor ? aFecha(valor) : null;

    return fecha ? fechaYHora.format(fecha) : null;
}

/** Hora actual (0-23) en Argentina, para saludos y similares. */
export function horaActualArgentina(ahora = new Date()) {
    return Number(soloHora.format(ahora));
}
