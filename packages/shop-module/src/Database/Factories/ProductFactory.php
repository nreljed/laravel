<?php

namespace Nreljed\ShopModule\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Nreljed\ShopModule\Models\Product;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => ucfirst(fake()->words(3, true)),
            'price' => fake()->randomFloat(2, 10, 500),
            'stock_quantity' => fake()->numberBetween(0, 250),
            'low_stock_threshold' => 5,
            'track_stock' => true,
            'status' => 'published',
            'type' => 'simple',
            'is_featured' => false,
            'is_taxable' => true,
        ];
    }
}