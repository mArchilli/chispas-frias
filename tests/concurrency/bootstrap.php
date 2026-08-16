<?php

/**
 * Bootstrap mínimo de Laravel para los scripts standalone usados por
 * tests/Feature/StockServiceConcurrencyTest.php.
 *
 * Cada script (`setup.php`, `worker.php`) corre en su propio proceso PHP separado
 * del proceso de PHPUnit, así que apuntar la conexión `mysql` a una base de datos
 * de test acá no afecta ni pisa la configuración de la suite principal (que corre
 * sobre sqlite en memoria) ni la base de datos de desarrollo.
 */

const STOCK_LOCK_TEST_DATABASE = 'chispas_frias_stock_lock_test';

require __DIR__ . '/../../vendor/autoload.php';

/** @var \Illuminate\Foundation\Application $app */
$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

config(['database.connections.mysql.database' => STOCK_LOCK_TEST_DATABASE]);
config(['database.default' => 'mysql']);
\Illuminate\Support\Facades\DB::purge('mysql');

return $app;
