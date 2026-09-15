<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get('/dashboard/account/profile');

        $response->assertOk();
    }

    public function test_profile_information_can_be_updated(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/dashboard/account/profile', [
                'display_name' => 'Test User',
                'first_name' => 'Test',
                'last_name' => 'User',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('dashboard.account.page.profile'));

        $user->refresh();

        $this->assertSame('Test User', $user->display_name);
        $this->assertSame('Test', $user->first_name);
        $this->assertSame('User', $user->last_name);
    }

    public function test_email_verification_status_is_unchanged_when_the_email_address_is_unchanged(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $response = $this
            ->actingAs($user)
            ->patch('/dashboard/account/profile', [
                'display_name' => 'Test User',
                'first_name' => 'Test',
                'last_name' => 'User',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('dashboard.account.page.profile'));

        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    public function test_user_can_delete_their_account(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->delete('/dashboard/account', [
                'confirmation' => 'delete my account',
                'current_password' => 'password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/');

        $this->assertGuest();
        $this->assertNull($user->fresh());
    }

    public function test_correct_password_must_be_provided_to_delete_account(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from('/dashboard/account/profile')
            ->delete('/dashboard/account', [
                'confirmation' => 'delete my account',
                'current_password' => 'wrong-password',
            ]);

        $response
            ->assertSessionHasErrors('current_password')
            ->assertRedirect('/dashboard/account/profile');

        $this->assertNotNull($user->fresh());
    }
}
