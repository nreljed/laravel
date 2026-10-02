<?php

namespace Nreljed\BookingModule\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Nreljed\BookingModule\Properties\Models\Property;
use Nreljed\Platform\Models\User;

/**
 * @extends Factory<Property>
 */
class PropertyFactory extends Factory
{
    protected $model = Property::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => ucfirst(fake()->words(3, true)).' Villa',
            'description' => fake()->paragraph(),
            'address' => fake()->streetAddress(),
            'city' => fake()->city(),
            'state' => fake()->state(),
            'zip' => fake()->postcode(),
            'country' => fake()->country(),
            'latitude' => fake()->latitude(),
            'longitude' => fake()->longitude(),
            'price' => fake()->randomFloat(2, 120, 1200),
            'currency' => 'USD',
            'status' => 'draft',
            'featured' => fake()->boolean(20),
            'metadata' => [],
        ];
    }
}