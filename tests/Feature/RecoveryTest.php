<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Nreljed\Platform\Database\Seeders\RolesAndPermissionsSeeder;
use Nreljed\Platform\Http\Controllers\RecoveryController;
use Nreljed\Platform\Models\RecoveryToken;
use Nreljed\Platform\Models\Setting;
use Nreljed\Platform\Services\SettingsService;
use Tests\Concerns\CreatesTestUsers;
use Tests\TestCase;

class RecoveryTest extends TestCase
{
    use CreatesTestUsers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_recovery_page_is_accessible_during_maintenance(): void
    {
        Setting::create(['key' => 'maintenance_mode', 'value' => '1', 'group' => 'maintenance']);
        SettingsService::clearCache();

        $response = $this->get('/maintenance/recovery');
        $response->assertStatus(200);
        $response->assertSee('Recover Access');
    }

    public function test_request_token_sends_email_to_admin(): void
    {
        Setting::create(['key' => 'maintenance_mode', 'value' => '1', 'group' => 'maintenance']);
        SettingsService::clearCache();

        $admin = $this->admin();
        $admin->forceFill(['email_verified_at' => now()])->save();

        $response = $this->postJson('/maintenance/recovery', [
            'email' => $admin->email,
        ]);

        $response->assertOk();
        $response->assertJson(['message' => 'If an admin account exists with that email, a recovery link has been sent.']);
        $this->assertDatabaseCount('recovery_tokens', 1);
    }

    public function test_request_token_does_not_reveal_if_email_exists(): void
    {
        Setting::create(['key' => 'maintenance_mode', 'value' => '1', 'group' => 'maintenance']);
        SettingsService::clearCache();

        $response = $this->postJson('/maintenance/recovery', [
            'email' => 'nonexistent@example.com',
        ]);

        $response->assertOk();
        $response->assertJson(['message' => 'If an admin account exists with that email, a recovery link has been sent.']);
    }

    public function test_request_token_is_rate_limited(): void
    {
        Setting::create(['key' => 'maintenance_mode', 'value' => '1', 'group' => 'maintenance']);
        SettingsService::clearCache();

        for ($i = 0; $i < 4; $i++) {
            $this->postJson('/maintenance/recovery', ['email' => 'test@example.com']);
        }

        $response = $this->postJson('/maintenance/recovery', ['email' => 'test@example.com']);
        $response->assertStatus(429);
    }

    public function test_claim_valid_token_sets_cookie_and_redirects(): void
    {
        $recoveryToken = RecoveryToken::generate();

        $response = $this->get('/maintenance/recovery/'.$recoveryToken->getPlaintext());
        $response->assertRedirect('/login');
        $response->assertCookie('maintenance_bypass');

        $recoveryToken->refresh();
        $this->assertTrue($recoveryToken->used);
    }

    public function test_claim_used_token_is_rejected(): void
    {
        $recoveryToken = RecoveryToken::generate();
        $recoveryToken->markUsed();

        $response = $this->get('/maintenance/recovery/'.$recoveryToken->getPlaintext());
        $response->assertRedirect('/login');
        $response->assertSessionHasErrors('email');
    }

    public function test_claim_expired_token_is_rejected(): void
    {
        $recoveryToken = RecoveryToken::create([
            'token_hash' => Hash::make(str_repeat('a', 64)),
            'used' => false,
            'expires_at' => now()->subHour(),
        ]);

        $response = $this->get('/maintenance/recovery/'.str_repeat('a', 64));
        $response->assertRedirect('/login');
        $response->assertSessionHasErrors('email');
    }

    public function test_claim_nonexistent_token_is_rejected(): void
    {
        $response = $this->get('/maintenance/recovery/nonexistent-token');
        $response->assertRedirect('/login');
        $response->assertSessionHasErrors('email');
    }

    public function test_recovery_token_valid_for_24_hours(): void
    {
        $token = RecoveryToken::generate();
        $this->assertTrue($token->isValid());
        $this->assertTrue($token->expires_at->isFuture());
        $this->assertLessThanOrEqual(24 * 60, now()->diffInMinutes($token->expires_at));
    }

    public function test_send_recovery_emails_creates_token_for_all_admins(): void
    {
        $admin1 = $this->admin();
        $admin1->forceFill(['email_verified_at' => now()])->save();
        $admin2 = $this->admin();
        $admin2->forceFill(['email_verified_at' => now()])->save();

        RecoveryController::sendRecoveryEmails();

        $this->assertDatabaseCount('recovery_tokens', 1);
    }
}
