<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ProductVariant>
 */
class ProductVariantFactory extends Factory
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
            'name' => ucfirst(fake()->unique()->word()),
            'color_hex' => fake()->hexColor(),
            'is_custom_color' => false,
            'price_addon' => fake()->randomElement([0, 0, 250, 500]),
            'stock' => fake()->numberBetween(0, 50),
            'sku' => fake()->optional()->regexify('[A-Z]{2}-[A-Z0-9]{3,5}'),
            'sort_order' => 0,
            'is_active' => true,
        ];
    }

    /**
     * Variante sin recargo sobre el precio base.
     */
    public function sinRecargo(): static
    {
        return $this->state(fn (array $attributes) => ['price_addon' => 0]);
    }

    /**
     * Variante "a elección del cliente" (color libre).
     */
    public function customColor(): static
    {
        return $this->state(fn (array $attributes) => ['is_custom_color' => true]);
    }

    /**
     * Variante con stock ilimitado (null), mismo criterio que products.stock.
     */
    public function stockIlimitado(): static
    {
        return $this->state(fn (array $attributes) => ['stock' => null]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['is_active' => false]);
    }
}
