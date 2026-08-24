<?php

/**
 * Prepara la base de datos MySQL dedicada al test de concurrencia de
 * DiscountCodeService: la crea si hace falta, corre las migraciones desde
 * cero, y siembra un código de descuento con usage_limit=1 (single-use).
 *
 * Imprime por stdout un JSON con el id sembrado: {"discount_code_id"}.
 * Cualquier error se reporta por stderr con exit code 1.
 *
 * Uso: php discount_setup.php
 */

require __DIR__ . '/discount_bootstrap.php';

use App\Models\DiscountCode;
use Illuminate\Support\Facades\Artisan;

try {
    $connectionConfig = config('database.connections.mysql');

    $serverPdo = new PDO(
        sprintf('mysql:host=%s;port=%s;charset=utf8mb4', $connectionConfig['host'], $connectionConfig['port']),
        $connectionConfig['username'],
        $connectionConfig['password']
    );
    $serverPdo->exec('CREATE DATABASE IF NOT EXISTS `' . DISCOUNT_LOCK_TEST_DATABASE . '` DEFAULT CHARACTER SET utf8mb4');

    Artisan::call('migrate:fresh', [
        '--database' => 'mysql',
        '--force' => true,
    ]);

    $discountCode = DiscountCode::create([
        'code' => 'CARRERA1',
        'percentage' => 10,
        'usage_limit' => 1,
        'usage_count' => 0,
        'is_active' => true,
    ]);

    echo json_encode([
        'discount_code_id' => $discountCode->id,
    ]);
    exit(0);
} catch (\Throwable $e) {
    fwrite(STDERR, get_class($e) . ': ' . $e->getMessage() . "\n" . $e->getTraceAsString());
    exit(1);
}
