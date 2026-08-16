<?php

/**
 * Llama a StockService::descontar() para la orden pasada por argv y reporta el
 * resultado por stdout en una sola línea. Pensado para correr como proceso PHP
 * independiente, lanzado en paralelo con otra instancia de este mismo script sobre
 * otra orden, para forzar dos transacciones realmente simultáneas contra el lock
 * de MySQL (`SELECT ... FOR UPDATE`) que usa StockService.
 *
 * Salida por stdout (una sola línea):
 *   - "OK"                                          → descontó sin problema.
 *   - "INSUFICIENTE:<productId>:<pedido>:<stock>"    → StockInsuficienteException.
 *   - "ERROR:<clase>:<mensaje>"                      → cualquier otro error.
 *
 * Uso: php worker.php <order_id>
 */

require __DIR__ . '/bootstrap.php';

use App\Exceptions\StockInsuficienteException;
use App\Models\Order;
use App\Services\StockService;

try {
    $order = Order::findOrFail((int) $argv[1]);
    app(StockService::class)->descontar($order);
    echo 'OK';
} catch (StockInsuficienteException $e) {
    echo 'INSUFICIENTE:' . $e->productId . ':' . $e->cantidadSolicitada . ':' . $e->stockDisponible;
} catch (\Throwable $e) {
    echo 'ERROR:' . get_class($e) . ':' . $e->getMessage();
}
