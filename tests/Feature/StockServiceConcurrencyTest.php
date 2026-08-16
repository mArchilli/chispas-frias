<?php

namespace Tests\Feature;

use PDO;
use PDOException;
use Tests\TestCase;

/**
 * Test de concurrencia real de StockService::descontar() contra MySQL.
 *
 * No usa sqlite (la suite por defecto): sqlite en memoria no soporta locking real
 * entre conexiones separadas, así que no sirve para probar que `lockForUpdate()`
 * serializa dos transacciones concurrentes sobre el mismo producto. En cambio,
 * lanza dos procesos PHP independientes (ver tests/concurrency/worker.php), cada
 * uno con su propia conexión a MySQL, descontando 1 unidad de un producto con
 * stock=1 en paralelo. Exactamente uno debe tener éxito.
 *
 * Si no hay un MySQL accesible en este entorno, el test se skipea en vez de
 * romper el resto de la suite.
 */
class StockServiceConcurrencyTest extends TestCase
{
    private const TEST_DATABASE = 'chispas_frias_stock_lock_test';

    private const SCRIPTS_DIR = __DIR__ . '/../concurrency';

    public function test_dos_ordenes_concurrentes_sobre_stock_uno_solo_una_descuenta(): void
    {
        if (! extension_loaded('pdo_mysql')) {
            $this->markTestSkipped('La extensión pdo_mysql no está disponible en este entorno.');
        }

        $host = config('database.connections.mysql.host');
        $port = config('database.connections.mysql.port');
        $username = config('database.connections.mysql.username');
        $password = config('database.connections.mysql.password');

        try {
            new PDO(
                "mysql:host={$host};port={$port};charset=utf8mb4",
                $username,
                $password,
                [PDO::ATTR_TIMEOUT => 3]
            );
        } catch (PDOException $e) {
            $this->markTestSkipped('MySQL no está disponible en este entorno: ' . $e->getMessage());

            return;
        }

        $setupScript = self::SCRIPTS_DIR . '/setup.php';
        $workerScript = self::SCRIPTS_DIR . '/worker.php';

        [$setupOutput, $setupError, $setupExit] = $this->runScript([$setupScript, '1']);
        $this->assertSame(
            0,
            $setupExit,
            "El script de setup falló.\nSTDOUT: {$setupOutput}\nSTDERR: {$setupError}"
        );

        $ids = json_decode($setupOutput, true);
        $this->assertIsArray(
            $ids,
            "El script de setup no devolvió JSON válido.\nSTDOUT: {$setupOutput}\nSTDERR: {$setupError}"
        );

        $productId = (int) $ids['product_id'];
        $orderId1 = (int) $ids['order_id_1'];
        $orderId2 = (int) $ids['order_id_2'];

        // Arrancar los dos workers antes de esperar a ninguno, para que ambas
        // transacciones se solapen realmente en el tiempo.
        [$process1, $pipes1] = $this->startScript([$workerScript, (string) $orderId1]);
        [$process2, $pipes2] = $this->startScript([$workerScript, (string) $orderId2]);

        [$output1, $error1, $exit1] = $this->finishScript($process1, $pipes1);
        [$output2, $error2, $exit2] = $this->finishScript($process2, $pipes2);

        $this->assertSame(0, $exit1, "El worker de la orden {$orderId1} terminó con error.\nSTDOUT: {$output1}\nSTDERR: {$error1}");
        $this->assertSame(0, $exit2, "El worker de la orden {$orderId2} terminó con error.\nSTDOUT: {$output2}\nSTDERR: {$error2}");

        $resultados = [$output1, $output2];
        $exitosos = array_filter($resultados, fn ($r) => $r === 'OK');
        $insuficientes = array_filter($resultados, fn ($r) => str_starts_with($r, 'INSUFICIENTE:'));

        $this->assertCount(
            1,
            $exitosos,
            "Se esperaba exactamente un descuento exitoso.\nResultado orden {$orderId1}: {$output1}\nResultado orden {$orderId2}: {$output2}"
        );
        $this->assertCount(
            1,
            $insuficientes,
            "Se esperaba exactamente un fallo por stock insuficiente.\nResultado orden {$orderId1}: {$output1}\nResultado orden {$orderId2}: {$output2}"
        );

        // Verificar el estado final con una conexión propia a la base de datos de
        // test, independiente de la conexión sqlite que usa el resto de la suite.
        $testPdo = new PDO(
            "mysql:host={$host};port={$port};dbname=" . self::TEST_DATABASE . ';charset=utf8mb4',
            $username,
            $password
        );

        $stock = $testPdo->query('SELECT stock FROM products WHERE id = ' . $productId)->fetchColumn();
        $this->assertSame(0, (int) $stock, 'El stock final del producto debería ser 0.');

        $movimientos = $testPdo->query(
            'SELECT cantidad, motivo, order_id FROM stock_movements WHERE product_id = ' . $productId
        )->fetchAll(PDO::FETCH_ASSOC);

        $this->assertCount(
            1,
            $movimientos,
            'Debe existir un único movimiento de stock: la orden que falló no debe haber generado ninguno.'
        );
        $this->assertSame(-1, (int) $movimientos[0]['cantidad']);
        $this->assertSame('orden_creada', $movimientos[0]['motivo']);
        $this->assertContains((int) $movimientos[0]['order_id'], [$orderId1, $orderId2]);
    }

    /**
     * @param  array<int, string>  $args
     * @return array{0: string, 1: string, 2: int}
     */
    private function runScript(array $args): array
    {
        [$process, $pipes] = $this->startScript($args);

        return $this->finishScript($process, $pipes);
    }

    /**
     * @param  array<int, string>  $args
     * @return array{0: resource, 1: array<int, resource>}
     */
    private function startScript(array $args)
    {
        $command = array_merge([PHP_BINARY], $args);

        $process = proc_open(
            $command,
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            dirname(__DIR__, 2)
        );

        if ($process === false) {
            $this->fail('No se pudo iniciar el proceso: ' . implode(' ', $args));
        }

        return [$process, $pipes];
    }

    /**
     * @param  resource  $process
     * @param  array<int, resource>  $pipes
     * @return array{0: string, 1: string, 2: int}
     */
    private function finishScript($process, array $pipes): array
    {
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        return [trim($stdout), trim($stderr), $exitCode];
    }
}
