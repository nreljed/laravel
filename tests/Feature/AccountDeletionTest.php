<?php

namespace Tests\Feature;

use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTestUsers;
use Tests\TestCase;

class AccountDeletionTest extends TestCase
{
    use CreatesTestUsers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_profile_deletion_requires_current_password(): void
    {
        $user = $this->regularUser();

        $response = $this
            ->actingAs($user)
            ->from('/dashboard/account')
            ->delete('/dashboard/account', [
                'current_password' => 'wrong-password',
                'confirmation' => 'delete my account',
            ]);

        $response->assertSessionHasErrors('current_password');
        $this->assertNotNull($user->fresh());
    }

    public function test_profile_deletion_works_with_correct_password(): void
    {
        $user = $this->regularUser();

        $response = $this
            ->actingAs($user)
            ->delete('/dashboard/account', [
                'current_password' => 'password',
                'confirmation' => 'delete my account',
            ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect('/');
        $this->assertNull($user->fresh());
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }

    public function test_account_deletion_requires_current_password(): void
    {
        $user = $this->regularUser();

        $response = $this
            ->actingAs($user)
            ->from('/dashboard/account')
            ->post('/dashboard/account/verify-delete-password', [
                'current_password' => 'wrong-password',
            ]);

        $response->assertSessionHasErrors('current_password');
        $this->assertNotNull($user->fresh());
    }

    public function test_account_deletion_requires_confirmation_string(): void
    {
        $user = $this->regularUser();

        $response = $this
            ->actingAs($user)
            ->from('/dashboard/account')
            ->delete('/dashboard/account', [
                'current_password' => 'password',
                'confirmation' => 'wrong string',
            ]);

        $response->assertSessionHasErrors('confirmation');
        $this->assertNotNull($user->fresh());
    }

    public function test_account_deletion_works_with_correct_password_and_confirmation(): void
    {
        $user = $this->regularUser();

        $response = $this
            ->actingAs($user)
            ->delete('/dashboard/account', [
                'current_password' => 'password',
                'confirmation' => 'delete my account',
            ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect('/');
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }
}
