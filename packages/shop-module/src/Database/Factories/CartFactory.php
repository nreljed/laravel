<?php

namespace Nreljed\ShopModule\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Nreljed\ShopModule\Models\Cart;

/**
 * @extends Factory<Cart>
 */
class CartFactory extends Factory
{
    protected $model = Cart::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'session_id' => fake()->unique()->uuid(),
            'discount' => 0,
        ];
    }
}