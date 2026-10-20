<?php

namespace Tests;

use FilesystemIterator;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * Red de seguridad: ningún test puede correr contra una base que no sea SQLite en memoria.
     * phpunit.xml ya fuerza DB_CONNECTION=sqlite / DB_DATABASE=:memory:, pero si alguien lo toca
     * (o corre con otra config) los RefreshDatabase terminarían migrando y borrando la base local
     * de trabajo. Mejor frenar en seco que migrar sobre MySQL.
     *
     * Los tests de concurrencia (tests/Feature/*ConcurrencyTest.php) abren sus propias conexiones
     * PDO a bases MySQL dedicadas (chispas_frias_stock_lock_test / chispas_frias_discount_lock_test)
     * y no usan la conexión por defecto de la aplicación, así que no se ven afectados.
     */
    /**
     * Opt-in de los tests que necesitan un MySQL real (concurrencia): crean una base dedicada y le
     * corren migrate:fresh. Por defecto se saltean; corren con el grupo `mysql` Y RUN_MYSQL_LOCK_TEST=1:
     *
     *     RUN_MYSQL_LOCK_TEST=1 php artisan test --group=mysql
     */
    protected function saltearSiNoSePidio(): void
    {
        if (! filter_var(getenv('RUN_MYSQL_LOCK_TEST'), FILTER_VALIDATE_BOOLEAN)) {
            $this->markTestSkipped(
                'Test de concurrencia con MySQL real: se saltea por defecto. '.
                'Para correrlo: RUN_MYSQL_LOCK_TEST=1 php artisan test --group=mysql'
            );
        }
    }

    /** Contenido de public/docs/ al empezar el test (ver controlarQueNoSeEscribaEnPublicDocs). */
    private array $publicDocsAlEmpezar = [];

    /**
     * Lista (rutas relativas, ordenadas) todo lo que hay en public/docs/ del repo real. Es la
     * carpeta donde la app guarda los PDFs de documentos (docs/pdfs): los tests no pueden dejar
     * nada ahí. Se usa base_path() a propósito, porque un test puede redirigir public_path().
     */
    private function contenidoDePublicDocs(): array
    {
        $raiz = base_path('public'.DIRECTORY_SEPARATOR.'docs');

        if (! is_dir($raiz)) {
            return [];
        }

        $entradas = [];
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($raiz, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($it as $entrada) {
            $entradas[] = str_replace('\\', '/', substr($entrada->getPathname(), strlen($raiz) + 1));
        }

        sort($entradas);

        return $entradas;
    }

    /**
     * Control: si un test escribe en public/docs/ (todo lo de public/ lo puede servir el web), falla.
     * Para subir archivos, usar Storage::fake() o redirigir public_path() a un directorio temporal
     * con $this->app->usePublicPath() y borrarlo en tearDown (ver AdminDocumentTest).
     */
    private function controlarQueNoSeEscribaEnPublicDocs(): void
    {
        $nuevas = array_values(array_diff($this->contenidoDePublicDocs(), $this->publicDocsAlEmpezar));

        $this->assertSame(
            [],
            $nuevas,
            'El test escribió en public/docs/: '.implode(', ', $nuevas).'. Los tests no pueden escribir en public/.'
        );
    }

    protected function tearDown(): void
    {
        $this->controlarQueNoSeEscribaEnPublicDocs();

        parent::tearDown();
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->publicDocsAlEmpezar = $this->contenidoDePublicDocs();

        $conexion = config('database.default');
        $driver = config("database.connections.{$conexion}.driver");
        $base = config("database.connections.{$conexion}.database");

        if ($driver !== 'sqlite' || $base !== ':memory:') {
            throw new RuntimeException(
                "Los tests solo pueden correr contra SQLite en memoria (conexión '{$conexion}', driver '{$driver}', base '{$base}'). ".
                'Revisá phpunit.xml: DB_CONNECTION=sqlite y DB_DATABASE=:memory:.'
            );
        }
    }
}
