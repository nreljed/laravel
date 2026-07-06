<?php

namespace Database\Factories;

use App\Modules\Properties\Models\Property;
use Illuminate\Database\Eloquent\Factories\Factory;

class PropertyFactory extends Factory
{
    protected $model = Property::class;

    public function definition(): array
    {
        return [
            'name' => fake()->company().' '.fake()->streetName(),
            'slug' => fake()->unique()->slug(2),
            'description' => fake()->paragraph(),
            'price' => fake()->randomFloat(2, 50000, 5000000),
            'currency' => 'USD',
            'surface' => fake()->randomFloat(2, 30, 500),
            'surface_unit' => 'm²',
            'status' => 'published',
            'published_at' => now(),
            'featured' => false,
            'address' => fake()->address(),
            'city' => fake()->city(),
            'state' => fake()->state(),
            'zip' => fake()->postcode(),
            'country' => fake()->country(),
            'latitude' => fake()->latitude(),
            'longitude' => fake()->longitude(),
        ];
    }
}
