<?php

namespace Tests\Feature\Modules\Booking;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\BootsModules;
use Tests\TestCase;

class BookingSettingsTest extends TestCase
{
    use BootsModules, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootModules();
    }

    public function test_admin_can_view_global_settings(): void
    {
        $admin = User::factory()->create(['email' => 'admin@test.com']);
        $admin->assignRole('admin');
        $this->actingAs($admin);

        $response = $this->get(route('dashboard.bookings.global-settings.edit'));

        $this->assertNotEquals(404, $response->getStatusCode());
        $this->assertNotEquals(403, $response->getStatusCode());
        $this->assertNotEquals(302, $response->getStatusCode());
    }
}
