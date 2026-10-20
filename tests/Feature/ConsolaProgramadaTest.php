<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * routes/console.php está listo para registrar tareas programadas (Schedule) sin haber
 * registrado ninguna todavía.
 */
class ConsolaProgramadaTest extends TestCase
{
    public function test_el_planificador_arranca_y_se_puede_listar(): void
    {
        $this->artisan('schedule:list')->assertExitCode(0);
    }

    public function test_el_archivo_importa_el_facade_schedule_para_registrar_tareas(): void
    {
        $this->assertStringContainsString(
            'use Illuminate\Support\Facades\Schedule;',
            file_get_contents(base_path('routes/console.php'))
        );
    }
}
