/**
 * Único lugar del front que formatea fechas. Lo guardado en la base está en UTC y NO se convierte:
 * se muestra en hora argentina (la misma zona que `config('app.display_timezone')` en el back, ver
 * App\Support\ZonaHoraria), sin importar la zona del navegador de quien mira.
 *
 * Sirve para cualquier instante (created_at, updated_at, y también las vigencias start_date /
 * end_date de ofertas y códigos): lo que el admin tipea en un `datetime-local` es hora argentina,
 * el back lo guarda en UTC (ZonaHoraria::deEntrada) y acá se convierte de vuelta
 * (`aValorDeInput` para rellenar el formulario, `formatearFecha` para mostrarlo).
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

const partesDeFecha = new Intl.DateTimeFormat('en-CA', {
    timeZone: ZONA_HORARIA,
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
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

/**
 * Instante guardado (UTC) → valor de un `<input type="datetime-local">` en hora argentina.
 * "2026-11-01T02:59:00.000000Z" → "2026-10-31T23:59". Sin fecha → ''.
 */
export function aValorDeInput(valor) {
    const fecha = valor ? aFecha(valor) : null;

    if (!fecha) {
        return '';
    }

    const p = Object.fromEntries(partesDeFecha.formatToParts(fecha).map((parte) => [parte.type, parte.value]));

    return `${p.year}-${p.month}-${p.day}T${p.hour}:${p.minute}`;
}

/** Hoy a las 00:00 (día argentino) como valor de `datetime-local`: "2026-10-19T00:00". */
export function inicioDeHoyParaInput(ahora = new Date()) {
    return `${aValorDeInput(ahora).slice(0, 10)}T00:00`;
}

/** Hora actual (0-23) en Argentina, para saludos y similares. */
export function horaActualArgentina(ahora = new Date()) {
    return Number(soloHora.format(ahora));
}
