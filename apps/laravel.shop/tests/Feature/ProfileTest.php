<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
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

    public function test_profile_social_media_is_persisted_with_normalized_urls(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/dashboard/account/profile', [
                'social_media' => [
                    ['social_title' => 'Twitter', 'social_value' => 'https://twitter.com/johndoe'],
                    ['social_title' => 'Other', 'social_value' => 'example.com', 'social_title_custom' => 'Portfolio'],
                ],
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('dashboard.account.page.profile'));

        $user->refresh();

        $this->assertSame([
            ['social_title' => 'Twitter', 'social_value' => 'https://twitter.com/johndoe'],
            ['social_title' => 'Portfolio', 'social_value' => 'https://example.com'],
        ], $user->social_links);
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

    public function test_security_page_is_displayed(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get('/dashboard/account/security');

        $response->assertOk();
    }

    public function test_user_can_update_their_email_address(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from('/dashboard/account/security')
            ->post('/dashboard/account/email', [
                'email' => 'new@example.com',
                'current_password' => 'password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', 'Email updated successfully.')
            ->assertRedirect('/dashboard/account/security');

        $this->assertSame('new@example.com', $user->refresh()->email);
    }

    public function test_current_password_must_be_provided_to_update_email(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from('/dashboard/account/security')
            ->post('/dashboard/account/email', [
                'email' => 'new@example.com',
                'current_password' => 'wrong-password',
            ]);

        $response
            ->assertSessionHasErrors('current_password')
            ->assertRedirect('/dashboard/account/security');

        $this->assertSame($user->email, $user->refresh()->email);
    }

    public function test_duplicate_email_is_rejected_when_updating_email(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create(['email' => 'taken@example.com']);

        $response = $this
            ->actingAs($user)
            ->from('/dashboard/account/security')
            ->post('/dashboard/account/email', [
                'email' => 'taken@example.com',
                'current_password' => 'password',
            ]);

        $response
            ->assertSessionHasErrors('email')
            ->assertRedirect('/dashboard/account/security');

        $this->assertSame($user->email, $user->refresh()->email);
    }

    public function test_user_can_update_their_password(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from('/dashboard/account/security')
            ->post('/dashboard/account/password', [
                'current_password' => 'password',
                'password' => 'new-password123',
                'password_confirmation' => 'new-password123',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertSessionHas('password_status')
            ->assertRedirect('/dashboard/account/security');

        $this->assertTrue(Hash::check('new-password123', $user->refresh()->password));
    }

    public function test_current_password_must_be_provided_to_update_password(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from('/dashboard/account/security')
            ->post('/dashboard/account/password', [
                'current_password' => 'wrong-password',
                'password' => 'new-password123',
                'password_confirmation' => 'new-password123',
            ]);

        $response
            ->assertSessionHasErrors('current_password')
            ->assertRedirect('/dashboard/account/security');

        $this->assertTrue(Hash::check('password', $user->refresh()->password));
    }

    public function test_password_confirmation_is_required_when_updating_password(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from('/dashboard/account/security')
            ->post('/dashboard/account/password', [
                'current_password' => 'password',
                'password' => 'new-password123',
                'password_confirmation' => 'different-password',
            ]);

        $response
            ->assertSessionHasErrors('password')
            ->assertRedirect('/dashboard/account/security');

        $this->assertTrue(Hash::check('password', $user->refresh()->password));
    }

    public function test_account_mutation_routes_are_rate_limited(): void
    {
        $routes = Route::getRoutes();

        foreach ([
            'dashboard.account.email',
            'dashboard.account.password',
            'dashboard.account.destroy',
            'dashboard.account.verify-delete-password',
        ] as $routeName) {
            $this->assertContains('throttle:auth', $routes->getByName($routeName)->middleware());
        }
    }
}
