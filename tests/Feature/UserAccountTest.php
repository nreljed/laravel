<?php

namespace Tests\Feature;

use App\Ai\Models\AiProvider;
use App\Models\NotificationPreference;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UserAccountTest extends TestCase
{
    use RefreshDatabase;

    // ── Account Page ──────────────────────────────────────────────

    public function test_account_page_is_displayed(): void
    {
        $user = User::factory()->create([
            'display_name' => 'Test Display',
            'first_name' => 'John',
            'last_name' => 'Doe',
        ]);

        $response = $this->actingAs($user)->get(route('dashboard.account.page.profile'));

        $response->assertOk();
    }

    public function test_account_page_requires_auth(): void
    {
        $response = $this->get(route('dashboard.account'));

        $response->assertRedirect('/login');
    }

    // ── Profile Update ────────────────────────────────────────────

    public function test_profile_can_be_updated(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->patch(route('dashboard.account.profile'), [
            'display_name' => 'New Name',
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'bio' => 'Hello world',
            'url' => 'https://example.com',
            'contact_email' => 'contact@example.com',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $user->refresh();
        $this->assertSame('New Name', $user->display_name);
        $this->assertSame('Jane', $user->first_name);
        $this->assertSame('Smith', $user->last_name);
        $this->assertSame('Hello world', $user->bio);
        $this->assertSame('https://example.com', $user->url);
        $this->assertSame('contact@example.com', $user->contact_email);
    }

    public function test_profile_accepts_null_values(): void
    {
        $user = User::factory()->create([
            'display_name' => 'Old Name',
            'first_name' => 'Old',
            'bio' => 'Old bio',
        ]);

        $response = $this->actingAs($user)->patch(route('dashboard.account.profile'), [
            'display_name' => null,
            'first_name' => null,
            'last_name' => null,
            'bio' => null,
            'url' => null,
        ]);

        $response->assertSessionHasNoErrors();

        $user->refresh();
        $this->assertNull($user->display_name);
        $this->assertNull($user->first_name);
        $this->assertNull($user->bio);
    }

    public function test_profile_rejects_invalid_url(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->patch(route('dashboard.account.profile'), [
            'url' => 'not-a-url',
        ]);

        $response->assertSessionHasErrors('url');
    }

    public function test_profile_rejects_bio_over_1000_chars(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->patch(route('dashboard.account.profile'), [
            'bio' => str_repeat('a', 1001),
        ]);

        $response->assertSessionHasErrors('bio');
    }

    // ── Email Update ──────────────────────────────────────────────

    public function test_email_can_be_updated(): void
    {
        $user = User::factory()->create([
            'email' => 'old@example.com',
            'password' => bcrypt('password'),
        ]);

        $response = $this->actingAs($user)->post(route('dashboard.account.email'), [
            'email' => 'new@example.com',
            'current_password' => 'password',
        ]);

        $response->assertSessionHasNoErrors();

        $user->refresh();
        $this->assertSame('new@example.com', $user->email);
    }

    public function test_email_update_requires_current_password(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('dashboard.account.email'), [
            'email' => 'new@example.com',
            'current_password' => 'wrong-password',
        ]);

        $response->assertSessionHasErrors('current_password');
    }

    public function test_email_update_rejects_duplicate_email(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);
        $user = User::factory()->create(['email' => 'mine@example.com', 'password' => bcrypt('password')]);

        $response = $this->actingAs($user)->post(route('dashboard.account.email'), [
            'email' => 'taken@example.com',
            'current_password' => 'password',
        ]);

        $response->assertSessionHasErrors('email');
    }

    public function test_same_email_does_not_change(): void
    {
        $user = User::factory()->create([
            'email' => 'same@example.com',
            'password' => bcrypt('password'),
        ]);

        $response = $this->actingAs($user)->post(route('dashboard.account.email'), [
            'email' => 'same@example.com',
            'current_password' => 'password',
        ]);

        $response->assertSessionHasNoErrors();
        $user->refresh();
        $this->assertSame('same@example.com', $user->email);
    }

    public function test_email_update_rejects_invalid_email(): void
    {
        $user = User::factory()->create(['password' => bcrypt('password')]);

        $response = $this->actingAs($user)->post(route('dashboard.account.email'), [
            'email' => 'not-an-email',
            'current_password' => 'password',
        ]);

        $response->assertSessionHasErrors('email');
    }

    // ── Password Update ───────────────────────────────────────────

    public function test_password_can_be_changed(): void
    {
        $user = User::factory()->create(['password' => bcrypt('password')]);

        $response = $this->actingAs($user)->post(route('dashboard.account.password'), [
            'current_password' => 'password',
            'password' => 'NewSecure123!',
            'password_confirmation' => 'NewSecure123!',
        ]);

        $response->assertSessionHasNoErrors();

        $user->refresh();
        $this->assertTrue(Hash::check('NewSecure123!', $user->password));
    }

    public function test_password_change_requires_current_password(): void
    {
        $user = User::factory()->create(['password' => bcrypt('password')]);

        $response = $this->actingAs($user)->post(route('dashboard.account.password'), [
            'current_password' => 'wrong-password',
            'password' => 'NewSecure123!',
            'password_confirmation' => 'NewSecure123!',
        ]);

        $response->assertSessionHasErrors('current_password');
    }

    public function test_password_change_rejects_same_as_current(): void
    {
        $user = User::factory()->create(['password' => bcrypt('password')]);

        $response = $this->actingAs($user)->post(route('dashboard.account.password'), [
            'current_password' => 'password',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertSessionHasErrors('password');
    }

    public function test_password_change_requires_confirmation(): void
    {
        $user = User::factory()->create(['password' => bcrypt('password')]);

        $response = $this->actingAs($user)->post(route('dashboard.account.password'), [
            'current_password' => 'password',
            'password' => 'NewSecure123!',
            'password_confirmation' => 'different',
        ]);

        $response->assertSessionHasErrors('password');
    }

    // ── Address Update ────────────────────────────────────────────

    public function test_billing_address_can_be_updated(): void
    {
        $user = User::factory()->create();

        $address = [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'company' => 'Acme Inc',
            'address_line1' => '123 Main St',
            'city' => 'Springfield',
            'state' => 'IL',
            'postcode' => '62704',
            'country' => 'US',
        ];

        $response = $this->actingAs($user)->put(route('dashboard.account.address'), [
            'billing_address' => $address,
        ]);

        $response->assertSessionHasNoErrors();

        $user->refresh();
        $this->assertSame('John', $user->billing_address['first_name']);
        $this->assertSame('Acme Inc', $user->billing_address['company']);
        $this->assertSame('US', $user->billing_address['country']);
    }

    public function test_shipping_address_can_be_updated(): void
    {
        $user = User::factory()->create();

        $address = [
            'first_name' => 'Jane',
            'address_line1' => '456 Oak Ave',
            'city' => 'Chicago',
            'country' => 'US',
        ];

        $response = $this->actingAs($user)->put(route('dashboard.account.address'), [
            'shipping_address' => $address,
        ]);

        $response->assertSessionHasNoErrors();

        $user->refresh();
        $this->assertSame('Jane', $user->shipping_address['first_name']);
        $this->assertSame('Chicago', $user->shipping_address['city']);
    }

    public function test_both_addresses_can_be_updated_simultaneously(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->put(route('dashboard.account.address'), [
            'billing_address' => ['first_name' => 'Bill', 'city' => 'Boston'],
            'shipping_address' => ['first_name' => 'Ship', 'city' => 'Seattle'],
        ]);

        $response->assertSessionHasNoErrors();

        $user->refresh();
        $this->assertSame('Bill', $user->billing_address['first_name']);
        $this->assertSame('Ship', $user->shipping_address['first_name']);
    }

    public function test_addresses_can_be_cleared(): void
    {
        $user = User::factory()->create([
            'billing_address' => ['first_name' => 'Old'],
            'shipping_address' => ['first_name' => 'Old'],
        ]);

        $response = $this->actingAs($user)->put(route('dashboard.account.address'), [
            'billing_address' => null,
            'shipping_address' => null,
        ]);

        $response->assertSessionHasNoErrors();

        $user->refresh();
        $this->assertNull($user->billing_address);
        $this->assertNull($user->shipping_address);
    }

    public function test_address_rejects_invalid_country_code(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->put(route('dashboard.account.address'), [
            'billing_address' => ['country' => 'TOOLONG'],
        ]);

        $response->assertSessionHasErrors('billing_address.country');
    }

    // ── Notification Preferences ──────────────────────────────────

    public function test_notification_preferences_can_be_updated(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('dashboard.account.notifications'), [
            'preferences' => [
                ['type' => 'comment', 'channel' => 'in_app', 'enabled' => true],
                ['type' => 'comment', 'channel' => 'email', 'enabled' => false],
            ],
        ]);

        $response->assertSessionHasNoErrors();

        $this->assertDatabaseHas('notification_preferences', [
            'user_id' => $user->id,
            'type' => 'comment',
            'channel' => 'in_app',
            'enabled' => true,
        ]);
        $this->assertDatabaseHas('notification_preferences', [
            'user_id' => $user->id,
            'type' => 'comment',
            'channel' => 'email',
            'enabled' => false,
        ]);
    }

    public function test_notification_preferences_can_be_toggled(): void
    {
        $user = User::factory()->create();

        NotificationPreference::create([
            'user_id' => $user->id,
            'type' => 'comment',
            'channel' => 'in_app',
            'enabled' => true,
        ]);

        $response = $this->actingAs($user)->post(route('dashboard.account.notifications'), [
            'preferences' => [
                ['type' => 'comment', 'channel' => 'in_app', 'enabled' => false],
            ],
        ]);

        $response->assertSessionHasNoErrors();

        $pref = NotificationPreference::where('user_id', $user->id)->first();
        $this->assertFalse((bool) $pref->enabled);
    }

    public function test_notification_preferences_rejects_invalid_channel(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('dashboard.account.notifications'), [
            'preferences' => [
                ['type' => 'comment', 'channel' => 'sms', 'enabled' => true],
            ],
        ]);

        $response->assertSessionHasErrors('preferences.*.channel');
    }

    // ── Avatar Upload & Delete ────────────────────────────────────

    public function test_avatar_can_be_uploaded(): void
    {
        Storage::fake('avatars');
        $user = User::factory()->create();

        $file = UploadedFile::fake()->image('avatar.jpg', 200, 200)->size(100);

        $response = $this->actingAs($user)->postJson(route('dashboard.account.avatar.upload'), [
            'avatar' => $file,
        ]);

        $response->assertOk()->assertJson([
            'success' => true,
        ]);

        $user->refresh();
        $this->assertNotNull($user->avatar);
    }

    public function test_avatar_rejects_non_image_file(): void
    {
        $user = User::factory()->create();

        $file = UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');

        $response = $this->actingAs($user)->postJson(route('dashboard.account.avatar.upload'), [
            'avatar' => $file,
        ]);

        $response->assertStatus(422);
    }

    public function test_avatar_rejects_file_over_5mb(): void
    {
        $user = User::factory()->create();

        $file = UploadedFile::fake()->image('avatar.jpg', 200, 200)->size(6000);

        $response = $this->actingAs($user)->postJson(route('dashboard.account.avatar.upload'), [
            'avatar' => $file,
        ]);

        $response->assertStatus(422);
    }

    public function test_avatar_delete_removes_avatar(): void
    {
        Storage::fake('avatars');
        $user = User::factory()->create(['avatar' => 'old-avatar.webp']);

        $response = $this->actingAs($user)->deleteJson(route('dashboard.account.avatar.destroy'));

        $response->assertOk()->assertJson([
            'success' => true,
        ]);

        $user->refresh();
        $this->assertNull($user->avatar);
    }

    public function test_avatar_delete_when_no_avatar_is_noop(): void
    {
        $user = User::factory()->create(['avatar' => null]);

        $response = $this->actingAs($user)->deleteJson(route('dashboard.account.avatar.destroy'));

        $response->assertOk()->assertJson([
            'success' => true,
        ]);

        $this->assertNull($user->fresh()->avatar);
    }

    public function test_avatar_requires_auth(): void
    {
        $response = $this->postJson(route('dashboard.account.avatar.upload'), [
            'avatar' => UploadedFile::fake()->image('avatar.jpg'),
        ]);

        $response->assertUnauthorized();
    }

    // ── Delete Account ────────────────────────────────────────────

    public function test_account_can_be_deleted(): void
    {
        $user = User::factory()->create(['password' => bcrypt('password')]);

        $response = $this->actingAs($user)->delete(route('dashboard.account.destroy'), [
            'current_password' => 'password',
            'confirmation' => 'delete my account',
        ]);

        $response->assertRedirect('/');
        $this->assertGuest();
        $this->assertNull($user->fresh());
    }

    public function test_account_deletion_requires_correct_password(): void
    {
        $user = User::factory()->create(['password' => bcrypt('password')]);

        $response = $this->actingAs($user)->from('/dashboard/account')->delete(route('dashboard.account.destroy'), [
            'current_password' => 'wrong',
            'confirmation' => 'delete my account',
        ]);

        $response->assertSessionHasErrors('current_password');
        $this->assertNotNull($user->fresh());
    }

    public function test_account_deletion_requires_exact_confirmation(): void
    {
        $user = User::factory()->create(['password' => bcrypt('password')]);

        $response = $this->actingAs($user)->from('/dashboard/account')->delete(route('dashboard.account.destroy'), [
            'current_password' => 'password',
            'confirmation' => 'please delete',
        ]);

        $response->assertSessionHasErrors('confirmation');
        $this->assertNotNull($user->fresh());
    }

    public function test_account_deletion_with_feedback(): void
    {
        $user = User::factory()->create(['password' => bcrypt('password')]);

        $response = $this->actingAs($user)->delete(route('dashboard.account.destroy'), [
            'current_password' => 'password',
            'confirmation' => 'delete my account',
            'feedback' => 'Not satisfied with the service.',
        ]);

        $response->assertRedirect('/');
        $this->assertNull($user->fresh());
    }

    public function test_account_deletion_cleans_up_notifications(): void
    {
        $user = User::factory()->create(['password' => bcrypt('password')]);

        NotificationPreference::create([
            'user_id' => $user->id,
            'type' => 'comment',
            'channel' => 'in_app',
            'enabled' => true,
        ]);

        $this->actingAs($user)->delete(route('dashboard.account.destroy'), [
            'current_password' => 'password',
            'confirmation' => 'delete my account',
        ]);

        $this->assertDatabaseMissing('notification_preferences', ['user_id' => $user->id]);
    }

    public function test_account_deletion_cleans_up_ai_providers(): void
    {
        $user = User::factory()->create(['password' => bcrypt('password')]);

        AiProvider::create([
            'user_id' => $user->id,
            'provider' => 'openai',
            'api_key_encrypted' => encrypt('test-key'),
            'preferred_model' => 'gpt-4o',
        ]);

        $this->actingAs($user)->delete(route('dashboard.account.destroy'), [
            'current_password' => 'password',
            'confirmation' => 'delete my account',
        ]);

        $this->assertDatabaseMissing('ai_providers', ['user_id' => $user->id]);
    }

    // ── Verify Delete Password ────────────────────────────────────

    public function test_verify_delete_password_returns_true_for_correct_password(): void
    {
        $user = User::factory()->create(['password' => bcrypt('password')]);

        $response = $this->actingAs($user)->postJson(route('dashboard.account.verify-delete-password'), [
            'current_password' => 'password',
        ]);

        $response->assertOk()->assertJson(['verified' => true]);
    }

    public function test_verify_delete_password_fails_for_wrong_password(): void
    {
        $user = User::factory()->create(['password' => bcrypt('password')]);

        $response = $this->actingAs($user)->postJson(route('dashboard.account.verify-delete-password'), [
            'current_password' => 'wrong',
        ]);

        $response->assertJsonValidationErrors('current_password');
    }

    // ── Unauthenticated Access ────────────────────────────────────

    public function test_profile_update_requires_auth(): void
    {
        $response = $this->patchJson(route('dashboard.account.profile'), [
            'display_name' => 'Hacker',
        ]);

        $response->assertUnauthorized();
    }

    public function test_email_update_requires_auth(): void
    {
        $response = $this->postJson(route('dashboard.account.email'), [
            'email' => 'hacker@evil.com',
            'current_password' => 'password',
        ]);

        $response->assertUnauthorized();
    }

    public function test_password_update_requires_auth(): void
    {
        $response = $this->postJson(route('dashboard.account.password'), [
            'current_password' => 'password',
            'password' => 'NewSecure123!',
            'password_confirmation' => 'NewSecure123!',
        ]);

        $response->assertUnauthorized();
    }

    public function test_address_update_requires_auth(): void
    {
        $response = $this->putJson(route('dashboard.account.address'), [
            'billing_address' => ['city' => 'Hacked'],
        ]);

        $response->assertUnauthorized();
    }

    public function test_notification_prefs_requires_auth(): void
    {
        $response = $this->postJson(route('dashboard.account.notifications'), [
            'preferences' => [
                ['type' => 'test', 'channel' => 'in_app', 'enabled' => true],
            ],
        ]);

        $response->assertUnauthorized();
    }

    public function test_delete_account_requires_auth(): void
    {
        $response = $this->deleteJson(route('dashboard.account.destroy'), [
            'current_password' => 'password',
            'confirmation' => 'delete my account',
        ]);

        $response->assertUnauthorized();
    }
}
