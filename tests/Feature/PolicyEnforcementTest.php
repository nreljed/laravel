<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Modules\Booking\Models\Booking;
use App\Modules\Properties\Models\Property;
use App\Policies\BookingPolicy;
use App\Policies\PropertyPolicy;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\BootsModules;
use Tests\TestCase;

class PolicyEnforcementTest extends TestCase
{
    use BootsModules, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->bootModules();
    }

    protected function createUserWithRole(string $roleSlug): User
    {
        $user = User::factory()->create();
        $role = Role::where('slug', $roleSlug)->first();
        $user->roles()->attach($role);

        return $user;
    }

    public function test_user_cannot_view_another_users_property(): void
    {
        $user1 = $this->createUserWithRole('crm_user');
        $user2 = $this->createUserWithRole('crm_user');

        $property = Property::factory()->create(['user_id' => $user1->id]);

        $policy = app(PropertyPolicy::class);

        $this->assertTrue($policy->view($user1, $property));
        $this->assertFalse($policy->view($user2, $property));
    }

    public function test_user_cannot_update_another_users_property(): void
    {
        $user1 = $this->createUserWithRole('crm_user');
        $user2 = $this->createUserWithRole('crm_user');

        $property = Property::factory()->create(['user_id' => $user1->id]);

        $policy = app(PropertyPolicy::class);

        $this->assertTrue($policy->update($user1, $property));
        $this->assertFalse($policy->update($user2, $property));
    }

    public function test_user_cannot_delete_another_users_property(): void
    {
        $user1 = $this->createUserWithRole('crm_user');
        $user2 = $this->createUserWithRole('crm_user');

        $property = Property::factory()->create(['user_id' => $user1->id]);

        $policy = app(PropertyPolicy::class);

        $this->assertTrue($policy->delete($user1, $property));
        $this->assertFalse($policy->delete($user2, $property));
    }

    public function test_admin_can_view_any_property(): void
    {
        $admin = $this->createUserWithRole('admin');
        $user = $this->createUserWithRole('crm_user');

        $property = Property::factory()->create(['user_id' => $user->id]);

        $policy = app(PropertyPolicy::class);

        $this->assertTrue($policy->view($admin, $property));
        $this->assertTrue($policy->update($admin, $property));
        $this->assertTrue($policy->delete($admin, $property));
    }

    public function test_user_cannot_view_another_users_booking(): void
    {
        $user1 = $this->createUserWithRole('crm_user');
        $user2 = $this->createUserWithRole('crm_user');

        $property = Property::factory()->create(['user_id' => $user1->id]);

        $booking = Booking::factory()->create([
            'user_id' => $user1->id,
            'property_id' => $property->id,
        ]);

        $policy = app(BookingPolicy::class);

        $this->assertTrue($policy->view($user1, $booking));
        $this->assertFalse($policy->view($user2, $booking));
    }

    public function test_user_cannot_update_another_users_booking(): void
    {
        $user1 = $this->createUserWithRole('crm_user');
        $user2 = $this->createUserWithRole('crm_user');

        $property = Property::factory()->create(['user_id' => $user1->id]);

        $booking = Booking::factory()->create([
            'user_id' => $user1->id,
            'property_id' => $property->id,
        ]);

        $policy = app(BookingPolicy::class);

        $this->assertTrue($policy->update($user1, $booking));
        $this->assertFalse($policy->update($user2, $booking));
    }

    public function test_only_admin_can_transition_bookings(): void
    {
        $admin = $this->createUserWithRole('admin');
        $user = $this->createUserWithRole('crm_user');

        $property = Property::factory()->create(['user_id' => $user->id]);

        $booking = Booking::factory()->create([
            'user_id' => $user->id,
            'property_id' => $property->id,
        ]);

        $policy = app(BookingPolicy::class);

        $this->assertFalse($policy->transition($user, $booking));
        $this->assertTrue($policy->transition($admin, $booking));
    }
}
