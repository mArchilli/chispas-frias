<?php

use App\Rukoz\EspejoStock;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Tareas programadas
|--------------------------------------------------------------------------
|
| Las tareas se registran acá con el facade Schedule y las dispara UN solo
| cron del servidor, cada minuto:
|
|     * * * * * cd /ruta/al/proyecto && php artisan schedule:run >> /dev/null 2>&1
|
| (en el hosting compartido ese cron se configura desde el panel; ver los pasos de deploy).
| Sin ese cron, nada de lo que se registre abajo corre. Con RUKOZ_INTEGRACION=true el paquete
| rukoz/core-client agrega las suyas (rukoz:poll, rukoz:procesar-inbox, rukoz:sync-stock, ...).
|
| `php artisan schedule:list` muestra lo registrado y cuándo corre cada tarea.
|
*/

// 10.2 — Red de seguridad del espejo de stock: una vez por día se vuelca a las columnas TODO lo que hay en `rukoz_stock`
// (sin llamar al Central; `rukoz:sync-stock`, que programa el paquete, ya reconcilia el caché cada hora). Corrige una
// columna que hubiera quedado atrás. Solo con INTEGRACION_STOCK (y RUKOZ_INTEGRACION) encendidos.
Schedule::command('chispasfrias:stock-cargar', ['--local'])
    ->dailyAt('03:45')
    ->withoutOverlapping()
    ->when(fn () => EspejoStock::activo());
