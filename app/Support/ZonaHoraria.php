<?php

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Único lugar del back que sabe en qué zona horaria "vive" el negocio.
 *
 * Lo guardado en la base está en `config('app.timezone')` (UTC) y NO se convierte: las fechas
 * se interpretan y se muestran en `config('app.display_timezone')`
 * (America/Argentina/Buenos_Aires). Un "día" del panel es un día argentino: de 00:00 a 23:59:59
 * hora de Buenos Aires, que en UTC cae entre las 03:00 de ese día y las 02:59 del siguiente. Por
 * eso los filtros por día/mes no pueden usar `DATE(created_at)`, `whereDate()` ni `whereMonth()`
 * directo sobre lo guardado: un pedido de las 22:00 argentinas ya es "mañana" en UTC.
 *
 * Contraparte en el front: resources/js/utils/fecha.js.
 */
class ZonaHoraria
{
    public static function nombre(): string
    {
        return config('app.display_timezone');
    }

    /** "Ahora", expresado en hora argentina. */
    public static function ahora(): Carbon
    {
        return Carbon::now(self::nombre());
    }

    /** Un instante cualquiera (de la base, de now(), etc.) expresado en hora argentina. */
    public static function aLocal(CarbonInterface $fecha): Carbon
    {
        return Carbon::instance($fecha)->timezone(self::nombre());
    }

    /**
     * Fecha lista para mostrar, en hora argentina. Null si no hay fecha.
     * Es el único lugar donde se convierte para mostrar: usarlo en vez de ->format() a secas.
     */
    public static function formatear(?CarbonInterface $fecha, string $formato = 'd/m/Y H:i'): ?string
    {
        return $fecha === null ? null : self::aLocal($fecha)->format($formato);
    }

    /**
     * Una fecha tipeada por el admin (p. ej. el `datetime-local` de una vigencia: "2026-10-31T23:59")
     * es hora argentina; esto la devuelve como el instante real, en la zona de la base (UTC), listo
     * para guardar. Un valor que ya trae su desfase ("...Z", "-03:00") se respeta. Vacío → null.
     *
     * Es la inversa de lo que hace el front al mostrar (utils/fecha.js `aValorDeInput`).
     */
    public static function deEntrada(?string $valor): ?Carbon
    {
        $valor = trim((string) $valor);

        if ($valor === '') {
            return null;
        }

        return Carbon::parse($valor, self::nombre())->timezone(config('app.timezone'));
    }

    /**
     * Un día calendario ("YYYY-MM-DD" o una fecha ya en hora argentina) como [inicio, fin] de ESE
     * día argentino, ya convertidos a la zona de la base para usarlos directo en un whereBetween().
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public static function rangoDelDia(string|CarbonInterface $dia): array
    {
        $local = self::enZonaLocal($dia);

        return self::aZonaDeBase($local->copy()->startOfDay(), $local->copy()->endOfDay());
    }

    /**
     * El mes calendario argentino que contiene a $dia, como [inicio, fin] en la zona de la base.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public static function rangoDelMes(string|CarbonInterface $dia): array
    {
        $local = self::enZonaLocal($dia);

        return self::aZonaDeBase($local->copy()->startOfMonth(), $local->copy()->endOfMonth());
    }

    /**
     * Interpreta $dia como fecha argentina: un string "YYYY-MM-DD" es ese día en hora argentina
     * (no en UTC); un Carbon se convierte a hora argentina.
     */
    public static function enZonaLocal(string|CarbonInterface $dia): Carbon
    {
        return is_string($dia)
            ? Carbon::parse($dia, self::nombre())
            : self::aLocal($dia);
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private static function aZonaDeBase(Carbon $inicio, Carbon $fin): array
    {
        $base = config('app.timezone');

        // Laravel formatea un Carbon en su propia zona al armar la consulta, así que hay que
        // llevarlo a la zona en la que se guardó la columna antes de usarlo.
        return [$inicio->timezone($base), $fin->timezone($base)];
    }
}
