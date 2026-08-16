<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ProductPriceTier>
 */
class ProductPriceTierFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'cantidad_minima' => fake()->numberBetween(2, 20),
            'precio_unitario' => fake()->randomFloat(2, 10, 500),
        ];
    }
}
