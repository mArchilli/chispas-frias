<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

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
            'email' => 'chispasfrias.oficial@gmail.com',
            'password' => Hash::make('Chispasfrias1-'),
        ]);

        // Seed categories and products
        $this->call([
            CategoriesSeeder::class,
            ProductsSeeder::class,
            CardPaymentPlanSeeder::class,
        ]);
    }
}
