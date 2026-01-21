<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Table;
use App\Models\Shift;
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
            'branch_id' => Branch::factory(),
            'shift_id' => Shift::factory(),
            'table_id' => Table::factory(),
            'customer_name' => fake()->name(),
            'type' => fake()->randomElement(['dine_in', 'takeaway']),
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'total_amount' => 0,
        ];
    }
}
