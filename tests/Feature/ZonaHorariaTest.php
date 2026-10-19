<?php

namespace Tests\Feature;

use App\Support\ZonaHoraria;
use Carbon\Carbon;
use Tests\TestCase;

/**
 * La base guarda UTC (config('app.timezone'), que NO se cambia); el negocio vive en hora
 * argentina (config('app.display_timezone')). ZonaHoraria es el único lugar que convierte.
 */
class ZonaHorariaTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_la_zona_guardada_sigue_siendo_utc_y_la_de_visualizacion_es_argentina(): void
    {
        $this->assertSame('UTC', config('app.timezone'));
        $this->assertSame('America/Argentina/Buenos_Aires', config('app.display_timezone'));
        $this->assertSame('America/Argentina/Buenos_Aires', ZonaHoraria::nombre());
    }

    public function test_formatear_convierte_de_utc_a_hora_argentina_sin_tocar_el_original(): void
    {
        $guardado = Carbon::parse('2026-10-19 02:30:00', 'UTC');

        $this->assertSame('18/10/2026 23:30', ZonaHoraria::formatear($guardado));
        $this->assertSame('2026-10-18', ZonaHoraria::formatear($guardado, 'Y-m-d'));
        $this->assertNull(ZonaHoraria::formatear(null));

        // La instancia original sigue en UTC: nada se convirtió "en el dato".
        $this->assertSame('2026-10-19 02:30:00', $guardado->format('Y-m-d H:i:s'));
        $this->assertSame('UTC', $guardado->getTimezone()->getName());
    }

    public function test_rango_del_dia_va_de_03_utc_a_las_02_59_utc_del_dia_siguiente(): void
    {
        [$inicio, $fin] = ZonaHoraria::rangoDelDia('2026-10-31');

        $this->assertSame('2026-10-31 03:00:00', $inicio->format('Y-m-d H:i:s'));
        $this->assertSame('2026-11-01 02:59:59', $fin->format('Y-m-d H:i:s'));
        $this->assertSame('UTC', $inicio->getTimezone()->getName());
    }

    public function test_rango_del_mes_respeta_el_ultimo_dia_argentino(): void
    {
        [$inicio, $fin] = ZonaHoraria::rangoDelMes('2026-10-15');

        $this->assertSame('2026-10-01 03:00:00', $inicio->format('Y-m-d H:i:s'));
        $this->assertSame('2026-11-01 02:59:59', $fin->format('Y-m-d H:i:s'));
    }

    public function test_ahora_es_hora_argentina(): void
    {
        Carbon::setTestNow('2026-11-01 01:00:00'); // UTC

        $this->assertSame('2026-10-31 22:00:00', ZonaHoraria::ahora()->format('Y-m-d H:i:s'));
    }

    public function test_el_front_usa_la_misma_zona_que_el_back(): void
    {
        $js = file_get_contents(base_path('resources/js/utils/fecha.js'));

        $this->assertStringContainsString("ZONA_HORARIA = '".config('app.display_timezone')."'", $js);
    }
}
