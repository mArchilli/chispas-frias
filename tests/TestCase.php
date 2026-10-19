<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
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
    protected function setUp(): void
    {
        parent::setUp();

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
