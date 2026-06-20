<?php

namespace Database\Seeders;

use App\Modules\Booking\Models\Booking;
use Illuminate\Database\Seeder;

class BookingSeeder extends Seeder
{
    public function run(): void
    {
        Booking::factory(50)->create();

        Booking::factory(5)->pending()->create(['start_date' => now()->addDays(3)->toDateString(), 'end_date' => now()->addDays(6)->toDateString()]);
        Booking::factory(3)->confirmed()->create(['start_date' => now()->addDays(7)->toDateString(), 'end_date' => now()->addDays(10)->toDateString()]);
        Booking::factory(2)->checkedIn()->create(['start_date' => now()->subDays(1)->toDateString(), 'end_date' => now()->addDays(2)->toDateString()]);
        Booking::factory(5)->cancelled()->create();
        Booking::factory(8)->completed()->create();
    }
}
