<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Promotion>
 */
class PromotionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->word(),
            'type' => fake()->randomElement(['percentage', 'fixed_amount']),
            'value' => fake()->numberBetween(10, 50),
            'start_date' => now(),
            'end_date' => now()->addDays(7),
            'is_active' => true,
        ];
    }
}
