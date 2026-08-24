<?php

namespace Tests\Feature;

use PDO;
use PDOException;
use Tests\TestCase;

/**
 * Test de concurrencia real de DiscountCodeService::registrarUso() contra
 * MySQL. Mismo criterio que StockServiceConcurrencyTest: sqlite en memoria
 * (la suite por defecto) no soporta locking real entre conexiones separadas,
 * así que no sirve para probar que `lockForUpdate()` serializa dos
 * transacciones concurrentes sobre el mismo código de descuento. En cambio,
 * lanza dos procesos PHP independientes (ver tests/concurrency/discount_worker.php),
 * cada uno con su propia conexión a MySQL, registrando un uso de un código con
 * usage_limit=1 en paralelo. Exactamente uno debe tener éxito.
 *
 * Si no hay un MySQL accesible en este entorno, el test se skipea en vez de
 * romper el resto de la suite.
 */
class DiscountCodeConcurrencyTest extends TestCase
{
    private const TEST_DATABASE = 'chispas_frias_discount_lock_test';

    private const SCRIPTS_DIR = __DIR__ . '/../concurrency';

    public function test_dos_registros_de_uso_concurrentes_sobre_un_codigo_de_un_solo_uso_uno_solo_gana(): void
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

        $setupScript = self::SCRIPTS_DIR . '/discount_setup.php';
        $workerScript = self::SCRIPTS_DIR . '/discount_worker.php';

        [$setupOutput, $setupError, $setupExit] = $this->runScript([$setupScript]);
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

        $discountCodeId = (int) $ids['discount_code_id'];

        // Arrancar los dos workers antes de esperar a ninguno, para que ambas
        // transacciones se solapen realmente en el tiempo.
        [$process1, $pipes1] = $this->startScript([$workerScript, (string) $discountCodeId]);
        [$process2, $pipes2] = $this->startScript([$workerScript, (string) $discountCodeId]);

        [$output1, $error1, $exit1] = $this->finishScript($process1, $pipes1);
        [$output2, $error2, $exit2] = $this->finishScript($process2, $pipes2);

        $this->assertSame(0, $exit1, "El worker 1 terminó con error.\nSTDOUT: {$output1}\nSTDERR: {$error1}");
        $this->assertSame(0, $exit2, "El worker 2 terminó con error.\nSTDOUT: {$output2}\nSTDERR: {$error2}");

        $resultados = [$output1, $output2];
        $exitosos = array_filter($resultados, fn ($r) => $r === 'OK');
        $agotados = array_filter($resultados, fn ($r) => $r === 'AGOTADO');

        $this->assertCount(
            1,
            $exitosos,
            "Se esperaba exactamente un registro de uso exitoso.\nResultado worker 1: {$output1}\nResultado worker 2: {$output2}"
        );
        $this->assertCount(
            1,
            $agotados,
            "Se esperaba exactamente un fallo por código agotado.\nResultado worker 1: {$output1}\nResultado worker 2: {$output2}"
        );

        // Verificar el estado final con una conexión propia a la base de datos de
        // test, independiente de la conexión sqlite que usa el resto de la suite.
        $testPdo = new PDO(
            "mysql:host={$host};port={$port};dbname=" . self::TEST_DATABASE . ';charset=utf8mb4',
            $username,
            $password
        );

        $usageCount = $testPdo->query(
            'SELECT usage_count FROM discount_codes WHERE id = ' . $discountCodeId
        )->fetchColumn();

        $this->assertSame(1, (int) $usageCount, 'usage_count final debería ser 1: ni 0 (se perdió el uso exitoso) ni 2 (se contaron ambos).');
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
