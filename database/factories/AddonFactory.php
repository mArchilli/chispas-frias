<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Addon>
 */
class AddonFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true),
            'description' => fake()->optional()->sentence(),
            'price' => fake()->randomFloat(2, 100, 1500),
            'requires_text' => false,
            'text_placeholder' => null,
            'max_characters' => 40,
            'is_active' => true,
        ];
    }

    /**
     * Add-on que exige un texto de personalización del cliente.
     */
    public function conTexto(): static
    {
        return $this->state(fn (array $attributes) => [
            'requires_text' => true,
            'text_placeholder' => 'Nombre a grabar',
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['is_active' => false]);
    }
}
