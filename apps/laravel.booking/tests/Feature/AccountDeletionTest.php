<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountDeletionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    protected function createUser(): User
    {
        $user = User::factory()->create();
        $role = Role::where('slug', 'customer')->first();
        $user->roles()->attach($role);

        return $user;
    }

    public function test_profile_deletion_requires_current_password(): void
    {
        $user = $this->createUser();

        $response = $this
            ->actingAs($user)
            ->from('/dashboard/account/profile')
            ->delete('/dashboard/account', [
                'confirmation' => 'delete my account',
                'current_password' => 'wrong-password',
            ]);

        $response->assertSessionHasErrors('current_password');
        $this->assertNotNull($user->fresh());
    }

    public function test_profile_deletion_works_with_correct_password(): void
    {
        $user = $this->createUser();

        $response = $this
            ->actingAs($user)
            ->delete('/dashboard/account', [
                'confirmation' => 'delete my account',
                'current_password' => 'password',
            ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect('/');
        $this->assertNull($user->fresh());
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }

    public function test_account_deletion_requires_current_password(): void
    {
        $user = $this->createUser();

        $response = $this
            ->actingAs($user)
            ->from('/dashboard/account/profile')
            ->delete('/dashboard/account', [
                'confirmation' => 'delete my account',
                'current_password' => 'wrong-password',
            ]);

        $response->assertSessionHasErrors('current_password');
        $this->assertNotNull($user->fresh());
    }

    public function test_account_deletion_requires_confirmation_string(): void
    {
        $user = $this->createUser();

        $response = $this
            ->actingAs($user)
            ->from('/dashboard/account/profile')
            ->delete('/dashboard/account', [
                'confirmation' => 'wrong string',
                'current_password' => 'password',
            ]);

        $response->assertSessionHasErrors('confirmation');
        $this->assertNotNull($user->fresh());
    }

    public function test_account_deletion_works_with_correct_password_and_confirmation(): void
    {
        $user = $this->createUser();

        $response = $this
            ->actingAs($user)
            ->delete('/dashboard/account', [
                'confirmation' => 'delete my account',
                'current_password' => 'password',
            ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect('/');
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }
}
