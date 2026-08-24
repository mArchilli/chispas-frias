<?php

/**
 * Bootstrap mínimo de Laravel para los scripts standalone usados por
 * tests/Feature/DiscountCodeConcurrencyTest.php. Mismo criterio que
 * bootstrap.php (usado por el test de concurrencia de stock): cada script
 * corre en su propio proceso PHP separado del proceso de PHPUnit, así que
 * apuntar acá la conexión `mysql` a una base de datos de test dedicada no
 * afecta a la suite principal (sqlite en memoria) ni a la base de desarrollo.
 */

const DISCOUNT_LOCK_TEST_DATABASE = 'chispas_frias_discount_lock_test';

require __DIR__ . '/../../vendor/autoload.php';

/** @var \Illuminate\Foundation\Application $app */
$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

config(['database.connections.mysql.database' => DISCOUNT_LOCK_TEST_DATABASE]);
config(['database.default' => 'mysql']);
\Illuminate\Support\Facades\DB::purge('mysql');

return $app;
