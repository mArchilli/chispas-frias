<?php

namespace Database\Factories;

use App\Enums\MotivoMovimientoStock;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\StockMovement>
 */
class StockMovementFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $cantidad = fake()->numberBetween(1, 10);

        return [
            'product_id' => Product::factory(),
            'order_id' => null,
            'cantidad' => $cantidad,
            'motivo' => MotivoMovimientoStock::AjusteManual,
            'stock_resultante' => fake()->numberBetween($cantidad, 100),
        ];
    }
}
