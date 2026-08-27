<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CardPaymentPlan>
 */
class CardPaymentPlanFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $installments = fake()->randomElement([1, 3, 6, 12]);

        return [
            'name' => $installments.' '.($installments === 1 ? 'cuota' : 'cuotas'),
            'installments' => $installments,
            'surcharge_percentage' => fake()->randomElement([10, 20, 35, 50]),
            'sort_order' => 0,
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['is_active' => false]);
    }
}
