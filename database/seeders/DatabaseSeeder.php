<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use RuntimeException;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Estos seeders cargan datos de prueba (productos de ejemplo, un admin): jamás en producción.
        if (app()->environment('production')) {
            throw new RuntimeException('El seeder no se puede ejecutar en producción.');
        }

        $this->call([
            UserSeeder::class,
            CategoriesSeeder::class,
            ProductsSeeder::class,
            CardPaymentPlanSeeder::class,
        ]);
    }
}
