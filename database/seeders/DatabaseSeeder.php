<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::factory()->create([
            'name' => 'Duilio',
            'email' => 'duilio@chispasfrias.com',
            'password' => 'Duilio1-!',
        ]);

        // Seed categories and products
        $this->call([
            CategoriesSeeder::class,
            ProductsSeeder::class,
        ]);
    }
}
