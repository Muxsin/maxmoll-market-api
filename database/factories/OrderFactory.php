<?php

namespace Database\Factories;

use App\Models\Warehouse;
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
            'customer' => fake()->name(),
            'completed_at' => fake()->optional()->dateTimeBetween('now', '+1 month'),
            'warehouse_id' => Warehouse::all()->random()->id,
            'status' => fake()->randomElement(['active', 'completed', 'canceled']),
        ];
    }
}
