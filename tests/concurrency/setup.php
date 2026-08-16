<?php

/**
 * Prepara la base de datos MySQL dedicada al test de concurrencia de StockService:
 * la crea si hace falta, corre las migraciones desde cero, y siembra un producto
 * con el stock indicado más dos órdenes (de 1 unidad cada una) sobre ese producto.
 *
 * Imprime por stdout un JSON con los ids sembrados: {"product_id", "order_id_1",
 * "order_id_2"}. Cualquier error se reporta por stderr con exit code 1.
 *
 * Uso: php setup.php <stock_inicial>
 */

require __DIR__ . '/bootstrap.php';

use App\Models\Order;
use App\Models\Product;
use Illuminate\Support\Facades\Artisan;

try {
    $stockInicial = isset($argv[1]) ? (int) $argv[1] : 1;

    $connectionConfig = config('database.connections.mysql');

    $serverPdo = new PDO(
        sprintf('mysql:host=%s;port=%s;charset=utf8mb4', $connectionConfig['host'], $connectionConfig['port']),
        $connectionConfig['username'],
        $connectionConfig['password']
    );
    $serverPdo->exec('CREATE DATABASE IF NOT EXISTS `' . STOCK_LOCK_TEST_DATABASE . '` DEFAULT CHARACTER SET utf8mb4');

    Artisan::call('migrate:fresh', [
        '--database' => 'mysql',
        '--force' => true,
    ]);

    $product = Product::factory()->create(['stock' => $stockInicial]);

    $order1 = Order::factory()->create();
    $order1->items()->create([
        'product_id' => $product->id,
        'product_title' => $product->title,
        'cantidad' => 1,
        'precio_unitario' => $product->price,
        'subtotal' => $product->price,
    ]);

    $order2 = Order::factory()->create();
    $order2->items()->create([
        'product_id' => $product->id,
        'product_title' => $product->title,
        'cantidad' => 1,
        'precio_unitario' => $product->price,
        'subtotal' => $product->price,
    ]);

    echo json_encode([
        'product_id' => $product->id,
        'order_id_1' => $order1->id,
        'order_id_2' => $order2->id,
    ]);
    exit(0);
} catch (\Throwable $e) {
    fwrite(STDERR, get_class($e) . ': ' . $e->getMessage() . "\n" . $e->getTraceAsString());
    exit(1);
}
