<?php

namespace Nreljed\BookingModule\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Nreljed\BookingModule\Booking\Enums\BookingStatus;
use Nreljed\BookingModule\Booking\Models\Booking;
use Nreljed\BookingModule\Properties\Models\Property;
use Nreljed\Platform\Models\User;

/**
 * @extends Factory<Booking>
 */
class BookingFactory extends Factory
{
    protected $model = Booking::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'property_id' => Property::factory(),
            'user_id' => User::factory(),
            'start_date' => now()->addDays(3)->toDateString(),
            'end_date' => now()->addDays(5)->toDateString(),
            'status' => BookingStatus::Pending,
            'total_amount' => fake()->randomFloat(2, 200, 2000),
            'currency' => 'USD',
            'guest_count' => fake()->numberBetween(1, 6),
            'notes' => fake()->sentence(),
            'metadata' => [],
        ];
    }
}