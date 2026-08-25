<?php

namespace Database\Factories;

use App\Enums\EstadoOrden;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Order>
 */
class OrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->firstName(),
            'lastname' => fake()->lastName(),
            'dni' => fake()->numerify('########'),
            'province' => fake()->state(),
            'city' => fake()->city(),
            'address' => null,
            'number' => null,
            'between_streets' => null,
            'postal_code' => fake()->postcode(),
            'phone' => fake()->numerify('##########'),
            'email' => fake()->safeEmail(),
            'observations' => null,
            'estado' => EstadoOrden::Pendiente,
            'total' => fake()->randomFloat(2, 10, 500),
            'mensaje_whatsapp' => null,
        ];
    }

    /**
     * Indicate that the order is despachada.
     */
    public function despachado(): static
    {
        return $this->state(fn (array $attributes) => [
            'estado' => EstadoOrden::Despachado,
        ]);
    }

    /**
     * Indicate that the order is cancelada.
     */
    public function cancelado(): static
    {
        return $this->state(fn (array $attributes) => [
            'estado' => EstadoOrden::Cancelado,
        ]);
    }
}
