<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'order_type' => 'preorder',
            'status' => 'pending',
            'payment_method' => 'cash',
            'payment_status' => 'unpaid',
            'queue_number' => fake()->numberBetween(1, 50),
            'subtotal' => $subtotal = fake()->randomFloat(2, 20000, 200000),
            'discount_amount' => 0,
            'total' => $subtotal,
        ];
    }
}
