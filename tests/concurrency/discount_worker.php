<?php

/**
 * Llama a DiscountCodeService::registrarUso() para el código pasado por argv
 * y reporta el resultado por stdout en una sola línea. Pensado para correr
 * como proceso PHP independiente, lanzado en paralelo con otra instancia de
 * este mismo script sobre el mismo código, para forzar dos transacciones
 * realmente simultáneas contra el lock de MySQL (`SELECT ... FOR UPDATE`)
 * que usa DiscountCodeService::registrarUso().
 *
 * Salida por stdout (una sola línea):
 *   - "OK"        → registró el uso sin problema.
 *   - "AGOTADO"    → DiscountCodeInvalidoException (código ya agotado bajo lock).
 *   - "ERROR:<clase>:<mensaje>" → cualquier otro error.
 *
 * Uso: php discount_worker.php <discount_code_id>
 */

require __DIR__ . '/discount_bootstrap.php';

use App\Exceptions\DiscountCodeInvalidoException;
use App\Models\DiscountCode;
use App\Services\DiscountCodeService;

try {
    $discountCode = DiscountCode::findOrFail((int) $argv[1]);
    app(DiscountCodeService::class)->registrarUso($discountCode);
    echo 'OK';
} catch (DiscountCodeInvalidoException $e) {
    echo 'AGOTADO';
} catch (\Throwable $e) {
    echo 'ERROR:' . get_class($e) . ':' . $e->getMessage();
}
