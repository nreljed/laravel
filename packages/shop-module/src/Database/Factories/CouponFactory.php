<?php

namespace Nreljed\ShopModule\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Nreljed\ShopModule\Models\Coupon;

/**
 * @extends Factory<Coupon>
 */
class CouponFactory extends Factory
{
    protected $model = Coupon::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->bothify('SAVE####')),
            'type' => 'percentage',
            'value' => fake()->numberBetween(5, 40),
            'used_count' => 0,
            'is_active' => true,
        ];
    }
}