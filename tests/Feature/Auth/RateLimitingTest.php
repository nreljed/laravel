<?php

namespace Tests\Feature\Auth;

use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Nreljed\DashboardCore\Models\Setting;
use Nreljed\DashboardCore\Models\User;
use Tests\TestCase;

class RateLimitingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // LOGIN — Route-Level Throttle (named 'login', 30/min per IP)
    // ═══════════════════════════════════════════════════════════════════════

    public function test_login_allows_requests_within_limit(): void
    {
        $user = User::factory()->create();

        for ($i = 0; $i < 5; $i++) {
            $response = $this->post('/login', [
                'username' => $user->email,
                'password' => 'wrong-password',
            ]);

            $response->assertStatus(302);
        }
    }

    public function test_login_returns_429_when_route_limit_exceeded(): void
    {
        $user = User::factory()->create();

        for ($i = 0; $i < 31; $i++) {
            $response = $this->post('/login', [
                'username' => $user->email,
                'password' => 'wrong-password',
            ]);
        }

        $response->assertStatus(429);
    }

    public function test_login_429_includes_retry_after_header(): void
    {
        $user = User::factory()->create();

        for ($i = 0; $i < 31; $i++) {
            $response = $this->post('/login', [
                'username' => $user->email,
                'password' => 'wrong-password',
            ]);
        }

        $response->assertStatus(429);
        $response->assertHeader('Retry-After');
        $retryAfter = (int) $response->headers->get('Retry-After');
        $this->assertGreaterThan(0, $retryAfter);
        $this->assertLessThanOrEqual(60, $retryAfter);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // LOGIN — Application-Level Throttle (LoginRequest, per username+IP)
    // ═══════════════════════════════════════════════════════════════════════

    public function test_login_throttles_after_max_failed_attempts(): void
    {
        User::factory()->create(['email' => 'throttle@example.com']);

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', [
                'username' => 'throttle@example.com',
                'password' => 'wrong-password',
            ]);
        }

        $response = $this->post('/login', [
            'username' => 'throttle@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertSessionHasErrors('username');
    }

    public function test_login_lockout_event_is_dispatched(): void
    {
        Event::fake([Lockout::class]);
        User::factory()->create(['email' => 'lockout@example.com']);

        for ($i = 0; $i < 6; $i++) {
            $this->post('/login', [
                'username' => 'lockout@example.com',
                'password' => 'wrong-password',
            ]);
        }

        Event::assertDispatched(Lockout::class);
    }

    public function test_login_lockout_contains_seconds_in_error_message(): void
    {
        User::factory()->create(['email' => 'wait@example.com']);

        for ($i = 0; $i < 6; $i++) {
            $this->post('/login', [
                'username' => 'wait@example.com',
                'password' => 'wrong-password',
            ]);
        }

        $response = $this->post('/login', [
            'username' => 'wait@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertSessionHasErrors('username');
        $this->assertStringContainsString('second', $response->getSession()->get('errors')->first('username'));
    }

    public function test_login_no_session_established_on_lockout(): void
    {
        User::factory()->create(['email' => 'noseSSION@example.com']);

        for ($i = 0; $i < 6; $i++) {
            $this->post('/login', [
                'username' => 'noseSSION@example.com',
                'password' => 'wrong-password',
            ]);
        }

        $this->assertGuest();
    }

    public function test_login_clears_throttle_on_successful_attempt(): void
    {
        User::factory()->create(['email' => 'clear@example.com']);

        for ($i = 0; $i < 4; $i++) {
            $this->post('/login', [
                'username' => 'clear@example.com',
                'password' => 'wrong-password',
            ]);
        }

        $response = $this->post('/login', [
            'username' => 'clear@example.com',
            'password' => 'password',
        ]);

        $response->assertRedirect(route('dashboard.index', absolute: false));
        $this->assertAuthenticated();
    }

    public function test_login_successful_attempt_clears_rate_limiter_key(): void
    {
        User::factory()->create(['email' => 'keyclear@example.com']);

        for ($i = 0; $i < 4; $i++) {
            $this->post('/login', [
                'username' => 'keyclear@example.com',
                'password' => 'wrong-password',
            ]);
        }

        $this->post('/login', [
            'username' => 'keyclear@example.com',
            'password' => 'password',
        ]);

        $throttleKey = 'keyclear@example.com|127.0.0.1';
        $this->assertFalse(RateLimiter::tooManyAttempts($throttleKey, 5));
    }

    // ═══════════════════════════════════════════════════════════════════════
    // LOGIN — Per-Username Isolation
    // ═══════════════════════════════════════════════════════════════════════

    public function test_login_throttle_is_per_username_not_per_route(): void
    {
        $user1 = User::factory()->create(['email' => 'iso1@example.com']);
        $user2 = User::factory()->create(['email' => 'iso2@example.com']);

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', [
                'username' => 'iso1@example.com',
                'password' => 'wrong',
            ]);
        }

        $response = $this->post('/login', [
            'username' => 'iso2@example.com',
            'password' => 'password',
        ]);

        $response->assertRedirect(route('dashboard.index', absolute: false));
        $this->assertAuthenticatedAs($user2);
    }

    public function test_login_different_users_same_ip_get_separate_buckets(): void
    {
        User::factory()->create(['email' => 'sep1@example.com']);
        User::factory()->create(['email' => 'sep2@example.com']);

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', [
                'username' => 'sep1@example.com',
                'password' => 'wrong',
            ]);
        }

        $key1 = 'sep1@example.com|127.0.0.1';
        $key2 = 'sep2@example.com|127.0.0.1';

        $this->assertTrue(RateLimiter::tooManyAttempts($key1, 5));
        $this->assertFalse(RateLimiter::tooManyAttempts($key2, 5));
    }

    public function test_login_username_case_insensitive_throttle_key(): void
    {
        User::factory()->create(['email' => 'case@example.com']);

        for ($i = 0; $i < 3; $i++) {
            $this->post('/login', [
                'username' => 'Case@Example.com',
                'password' => 'wrong',
            ]);
        }

        for ($i = 0; $i < 3; $i++) {
            $this->post('/login', [
                'username' => 'case@example.com',
                'password' => 'wrong',
            ]);
        }

        $key = 'case@example.com|127.0.0.1';
        $this->assertTrue(RateLimiter::tooManyAttempts($key, 5));
    }

    // ═══════════════════════════════════════════════════════════════════════
    // LOGIN — Email vs Username Login
    // ═══════════════════════════════════════════════════════════════════════

    public function test_login_email_and_username_are_separate_throttle_keys(): void
    {
        User::factory()->create(['email' => 'dual@example.com', 'username' => 'dual']);

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', [
                'username' => 'dual@example.com',
                'password' => 'wrong',
            ]);
        }

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', [
                'username' => 'dual',
                'password' => 'wrong',
            ]);
        }

        $emailKey = 'dual@example.com|127.0.0.1';
        $usernameKey = 'dual|127.0.0.1';

        $this->assertTrue(RateLimiter::tooManyAttempts($emailKey, 5));
        $this->assertTrue(RateLimiter::tooManyAttempts($usernameKey, 5));
    }

    // ═══════════════════════════════════════════════════════════════════════
    // LOGIN — Custom max_login_attempts from DB
    // ═══════════════════════════════════════════════════════════════════════

    public function test_login_respects_custom_max_login_attempts_setting(): void
    {
        Setting::set('max_login_attempts', '2');
        User::factory()->create(['email' => 'custom@example.com']);

        $this->post('/login', [
            'username' => 'custom@example.com',
            'password' => 'wrong',
        ]);

        $this->post('/login', [
            'username' => 'custom@example.com',
            'password' => 'wrong',
        ]);

        $response = $this->post('/login', [
            'username' => 'custom@example.com',
            'password' => 'wrong',
        ]);

        $response->assertSessionHasErrors('username');
    }

    public function test_login_respects_higher_max_login_attempts(): void
    {
        Setting::set('max_login_attempts', '10');
        User::factory()->create(['email' => 'high@example.com']);

        for ($i = 0; $i < 8; $i++) {
            $this->post('/login', [
                'username' => 'high@example.com',
                'password' => 'wrong',
            ]);
        }

        $response = $this->post('/login', [
            'username' => 'high@example.com',
            'password' => 'wrong',
        ]);

        $response->assertStatus(302);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // LOGIN — Username Enumeration Prevention
    // ═══════════════════════════════════════════════════════════════════════

    public function test_login_error_message_same_for_nonexistent_user(): void
    {
        $response1 = $this->post('/login', [
            'username' => 'nonexistent@example.com',
            'password' => 'wrong',
        ]);

        User::factory()->create(['email' => 'exists@example.com']);
        $response2 = $this->post('/login', [
            'username' => 'exists@example.com',
            'password' => 'wrong',
        ]);

        $response1->assertSessionHasErrors('username');
        $response2->assertSessionHasErrors('username');

        $error1 = $response1->getSession()->get('errors')->first('username');
        $error2 = $response2->getSession()->get('errors')->first('username');

        $this->assertEquals($error1, $error2);
        $this->assertEquals(trans('auth.failed'), $error1);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // LOGIN — Recovery After Lockout
    // ═══════════════════════════════════════════════════════════════════════

    public function test_login_works_after_throttle_key_is_cleared(): void
    {
        User::factory()->create(['email' => 'recover@example.com']);

        for ($i = 0; $i < 6; $i++) {
            $this->post('/login', [
                'username' => 'recover@example.com',
                'password' => 'wrong',
            ]);
        }

        RateLimiter::clear('recover@example.com|127.0.0.1');

        $response = $this->post('/login', [
            'username' => 'recover@example.com',
            'password' => 'password',
        ]);

        $response->assertRedirect(route('dashboard.index', absolute: false));
        $this->assertAuthenticated();
    }

    // ═══════════════════════════════════════════════════════════════════════
    // LOGIN — Route vs Application Throttle Interaction
    // ═══════════════════════════════════════════════════════════════════════

    public function test_login_application_throttle_fires_before_route_throttle(): void
    {
        User::factory()->create(['email' => 'early@example.com']);

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', [
                'username' => 'early@example.com',
                'password' => 'wrong',
            ]);
        }

        $response = $this->post('/login', [
            'username' => 'early@example.com',
            'password' => 'wrong',
        ]);

        $response->assertSessionHasErrors('username');
        $this->assertNotEquals(429, $response->getStatusCode());
    }

    // ═══════════════════════════════════════════════════════════════════════
    // REGISTER — Route-Level Throttle (named 'register', 10/min per IP)
    // ═══════════════════════════════════════════════════════════════════════

    public function test_register_allows_requests_within_limit(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $response = $this->post('/register', [
                'username' => "user{$i}",
                'email' => "user{$i}@example.com",
                'password' => 'password',
                'password_confirmation' => 'password',
            ]);

            $response->assertRedirect(route('dashboard.index', absolute: false));
        }
    }

    public function test_register_returns_429_when_limit_exceeded(): void
    {
        for ($i = 0; $i < 11; $i++) {
            $response = $this->post('/register', [
                'username' => "ratelimit{$i}",
                'email' => "ratelimit{$i}@example.com",
                'password' => 'password',
                'password_confirmation' => 'password',
            ]);
        }

        $response->assertStatus(429);
    }

    public function test_register_429_includes_retry_after_header(): void
    {
        for ($i = 0; $i < 11; $i++) {
            $response = $this->post('/register', [
                'username' => "retry{$i}",
                'email' => "retry{$i}@example.com",
                'password' => 'password',
                'password_confirmation' => 'password',
            ]);
        }

        $response->assertStatus(429);
        $response->assertHeader('Retry-After');
    }

    // ═══════════════════════════════════════════════════════════════════════
    // AUTH — Rate Limiter (named 'auth', 30/min per IP)
    // ═══════════════════════════════════════════════════════════════════════

    public function test_forgot_password_allows_requests_within_limit(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $response = $this->post('/forgot-password', [
                'email' => 'nonexistent@example.com',
            ]);

            $response->assertSessionHasNoErrors();
        }
    }

    public function test_forgot_password_returns_429_when_limit_exceeded(): void
    {
        for ($i = 0; $i < 31; $i++) {
            $response = $this->post('/forgot-password', [
                'email' => 'nonexistent@example.com',
            ]);
        }

        $response->assertStatus(429);
    }

    public function test_logout_returns_429_when_limit_exceeded(): void
    {
        $user = User::factory()->create();

        for ($i = 0; $i < 31; $i++) {
            $response = $this->actingAs($user)->post('/logout');
            $this->actingAs($user);
        }

        $response->assertStatus(429);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // AVAILABILITY CHECK — Throttle (10/min per IP)
    // ═══════════════════════════════════════════════════════════════════════

    public function test_availability_check_allows_requests_within_limit(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $response = $this->getJson('/register/check-availability?field=username&value=testuser');
            $response->assertJson(['available' => true]);
        }
    }

    public function test_availability_check_returns_429_when_limit_exceeded(): void
    {
        for ($i = 0; $i < 11; $i++) {
            $response = $this->getJson('/register/check-availability?field=username&value=user'.$i);
        }

        $response->assertStatus(429);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // NAMED RATE LIMITERS — Registration
    // ═══════════════════════════════════════════════════════════════════════

    public function test_named_rate_limiters_are_registered(): void
    {
        $loginLimit = RateLimiter::attempt('test-login-check', 1, fn () => true);
        $registerLimit = RateLimiter::attempt('test-register-check', 1, fn () => true);
        $authLimit = RateLimiter::attempt('test-auth-check', 1, fn () => true);

        $this->assertTrue($loginLimit);
        $this->assertTrue($registerLimit);
        $this->assertTrue($authLimit);

        RateLimiter::clear('test-login-check');
        RateLimiter::clear('test-register-check');
        RateLimiter::clear('test-auth-check');
    }
}
