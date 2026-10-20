<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Corrección de datos, una sola vez.
 *
 * Hasta ahora las vigencias de ofertas y códigos de descuento (`start_date` / `end_date`) se guardaban
 * "tal cual se tipearon" en el `datetime-local` del admin, o sea hora argentina escrita como si fuera
 * UTC. Como la app compara contra now() en UTC, una oferta "hasta 31/10 23:59" vencía a las 20:59
 * hora argentina. Desde ahora el back interpreta lo que tipea el admin como hora argentina y lo
 * guarda en UTC (ZonaHoraria::deEntrada), así que las filas viejas hay que correrlas el desfase
 * de Argentina (UTC-3, sin horario de verano): "2026-10-31 23:59:00" → "2026-11-01 02:59:00".
 *
 * Tablas y columnas: product_offers.start_date/end_date y discount_codes.start_date/end_date
 * (los NULL —"sin inicio"/"sin fin"— no se tocan).
 */
return new class extends Migration
{
    private const ZONA = 'America/Argentina/Buenos_Aires';

    private const TABLAS = [
        'product_offers' => ['start_date', 'end_date'],
        'discount_codes' => ['start_date', 'end_date'],
    ];

    public function up(): void
    {
        $this->correr(sumar: true);
    }

    public function down(): void
    {
        $this->correr(sumar: false);
    }

    private function correr(bool $sumar): void
    {
        // Minutos que Argentina está detrás de UTC (180). Fijo todo el año; se calcula en vez de escribirlo.
        $minutos = -Carbon::now(self::ZONA)->utcOffset();

        foreach (self::TABLAS as $tabla => $columnas) {
            DB::table($tabla)->orderBy('id')->each(function ($fila) use ($tabla, $columnas, $minutos, $sumar) {
                $cambios = [];

                foreach ($columnas as $columna) {
                    if ($fila->{$columna} === null) {
                        continue;
                    }

                    $fecha = Carbon::parse($fila->{$columna}, 'UTC');
                    $cambios[$columna] = ($sumar ? $fecha->addMinutes($minutos) : $fecha->subMinutes($minutos))
                        ->format('Y-m-d H:i:s');
                }

                if ($cambios !== []) {
                    DB::table($tabla)->where('id', $fila->id)->update($cambios);
                }
            });
        }
    }
};
