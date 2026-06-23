<?php

namespace Tests\Feature\Modules\Booking;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\BootsModules;
use Tests\TestCase;

class BookingCrudTest extends TestCase
{
    use BootsModules, RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootModules();

        $this->admin = User::factory()->create(['email' => 'admin@test.com']);
        $this->admin->assignRole('admin');
    }

    public function test_admin_can_view_bookings_index(): void
    {
        $this->actingAs($this->admin);

        $response = $this->get(route('dashboard.bookings.index'));

        $this->assertNotEquals(404, $response->getStatusCode());
        $this->assertNotEquals(403, $response->getStatusCode());
        $this->assertNotEquals(302, $response->getStatusCode());
    }
}
