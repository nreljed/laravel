<?php

namespace Tests\Feature\Modules\Booking;

use App\Models\User;
use App\Modules\Booking\Models\Booking;
use App\Modules\Booking\Services\BookingGuestTokenService;
use App\Modules\Properties\Models\Property;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\BootsModules;
use Tests\TestCase;

class BookingGuestPortalTest extends TestCase
{
    use BootsModules, RefreshDatabase;

    protected User $host;

    protected Property $property;

    protected Booking $booking;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootModules();

        $this->host = User::factory()->create();
        $this->host->assignRole('admin');

        $this->property = Property::factory()->create(['user_id' => $this->host->id]);

        $this->booking = Booking::factory()->create([
            'property_id' => $this->property->id,
            'user_id' => $this->host->id,
            'guest_email' => 'guest@test.com',
            'guest_name' => 'Test Guest',
            'start_date' => now()->addDays(10),
            'end_date' => now()->addDays(14),
            'status' => 'confirmed',
        ]);
    }

    public function test_guest_can_view_booking_with_valid_token(): void
    {
        $token = BookingGuestTokenService::generateToken($this->booking->id, 'guest@test.com');

        $response = $this->get(route('booking.view', [
            'booking' => $this->booking->id,
            'token' => $token,
        ]));

        $this->assertNotEquals(404, $response->getStatusCode());
        $this->assertNotEquals(403, $response->getStatusCode());
    }
}
