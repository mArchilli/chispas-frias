<?php

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
| Hoy no hay ninguna. Las tareas se registran acá con el facade Schedule y las dispara UN solo
| cron del servidor, cada minuto:
|
|     * * * * * cd /ruta/al/proyecto && php artisan schedule:run >> /dev/null 2>&1
|
| (en el hosting compartido ese cron se configura desde el panel; ver los pasos de deploy).
| Sin ese cron, nada de lo que se registre abajo corre.
|
| Ejemplo (no activar sin definir antes el comando y la frecuencia real):
|
|     Schedule::command('pedidos:cancelar-vencidos')->everyFifteenMinutes()->withoutOverlapping();
|
| `php artisan schedule:list` muestra lo registrado y cuándo corre cada tarea.
|
*/
