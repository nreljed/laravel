<?php

namespace Nreljed\ShopModule\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Nreljed\Platform\Models\User;
use Nreljed\ShopModule\Models\Order;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $subtotal = fake()->randomFloat(2, 20, 800);

        return [
            'user_id' => User::factory(),
            'status' => 'pending',
            'subtotal' => $subtotal,
            'tax_total' => round($subtotal * 0.2, 2),
            'shipping_total' => fake()->randomFloat(2, 0, 40),
            'discount_total' => 0,
            'total' => $subtotal,
            'currency' => 'EUR',
            'payment_status' => 'pending',
            'customer_email' => fake()->safeEmail(),
            'customer_name' => fake()->name(),
            'customer_phone' => fake()->phoneNumber(),
        ];
    }
}
