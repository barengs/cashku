<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Ingredient>
 */
class IngredientFactory extends Factory
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
            'unit' => fake()->randomElement(['kg', 'g', 'l', 'ml', 'pcs']),
            'cost_per_unit' => fake()->randomFloat(2, 10, 100),
            'minimum_stock' => fake()->numberBetween(5, 20),
        ];
    }
}
