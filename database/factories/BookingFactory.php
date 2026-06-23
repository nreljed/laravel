<?php

namespace Database\Factories;

use App\Models\User;
use App\Modules\Booking\Models\Booking;
use App\Modules\Properties\Models\Property;
use Illuminate\Database\Eloquent\Factories\Factory;

class BookingFactory extends Factory
{
    protected $model = Booking::class;

    public function definition(): array
    {
        return [
            'property_id' => Property::factory(),
            'user_id' => User::factory(),
            'start_date' => fake()->dateTimeBetween('+1 week', '+2 weeks')->format('Y-m-d'),
            'end_date' => fake()->dateTimeBetween('+2 weeks', '+3 weeks')->format('Y-m-d'),
            'status' => 'confirmed',
            'total_amount' => fake()->randomFloat(2, 200, 5000),
            'currency' => 'USD',
            'guest_count' => fake()->numberBetween(1, 6),
            'guest_name' => fake()->name(),
            'guest_email' => fake()->email(),
        ];
    }
}
