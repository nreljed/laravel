# Testing

## Stack

| Tool         | Purpose                |
| ------------ | ---------------------- |
| PHPUnit 13   | PHP tests              |
| Vitest       | TypeScript/React tests |
| ESLint       | Linting                |
| Laravel Pint | PHP code style         |
| TypeScript   | Type checking          |

## Commands

```bash
php artisan test --compact                          # All tests (compact output)
php artisan test --compact tests/Feature/Auth/      # Specific file
php artisan test --compact --filter=testName        # By name
npm run test                                        # Vitest
npm run typecheck                                   # tsc --noEmit
npm run lint                                        # ESLint
vendor/bin/pint                                     # Laravel Pint
npm run check:ci                                    # Full CI pipeline
```

## PHPUnit Config

Tests use SQLite in-memory database:

```xml
<server name="DB_CONNECTION" value="sqlite"/>
<server name="DB_DATABASE" value=":memory:"/>
```

Telescope, Pulse, and Nightwatch are disabled in tests.

## Test Structure

```
tests/
  Concerns/
    CreatesTestUsers.php          # Shared trait: admin(), regularUser(), createUserWithRole()
  Feature/
    Ai/                           # AI provider CRUD + connection tests
    Auth/                         # Authentication, registration, rate limiting, password, email verification
      AuthenticationTest.php      # Login, logout, invalid credentials
      RegistrationTest.php        # User registration
      RateLimitingTest.php        # Login/register/auth rate limiters, per-username throttle
      PasswordResetTest.php       # Forgot/reset password flow
      PasswordConfirmationTest.php # Password confirmation
      PasswordUpdateTest.php      # Password update from account page
      EmailVerificationTest.php   # Email verification flow
    Blog/                         # Blog CRUD, permissions, categories, tags, SEO, public
    Deploy/                       # Deploy endpoint token auth, upload
    Public/                       # Public pages (welcome, about, services, contact)
    Admin/                        # Role creation, permission management, impersonation, bulk ops
    Notifications/                # Notification polling, service
    FeatureRegistryTest.php       # Singleton, nav items, permissions
    AccountDeletionTest.php       # Account deletion flow
    SettingsRegistrationGateTest.php # Registration toggle
    AuthorizationTest.php         # Cross-feature authorization
    DataIsolationTest.php         # User data isolation
    ProfileTest.php               # Profile updates
    RoleAssignmentTest.php        # Role assignment
    PermissionSyncTest.php        # Permission sync
```

## Key Patterns

### User Creation (CreatesTestUsers trait)

```php
$this->admin()                    // Creates admin user with admin role
$this->regularUser()              // Creates user with user role
$this->createUserWithRole('slug') // Creates user with specific role
$this->seedRolesAndPermissions()  // Seeds roles and permissions
```

### Permission Tests

```php
public function test_requires_permission(): void
{
    $user = $this->regularUser();
    $response = $this->actingAs($user)->get(route('dashboard.posts.index'));
    $response->assertStatus(403);
}
```

### Rate Limiting Tests

Rate limiting tests use `RefreshDatabase` and make repeated requests to hit the limit:

```php
public function test_login_returns_429_when_limit_exceeded(): void
{
    $user = User::factory()->create();

    for ($i = 0; $i < 31; $i++) {
        $this->post('/login', [
            'username' => $user->email,
            'password' => 'wrong-password',
        ]);
    }

    $response->assertStatus(429);
}
```

### Factory States

```php
User::factory()->create();           // Default (unverified)
User::factory()->admin()->create();  // Admin role
PostFactory::new()->published()->create();
PostFactory::new()->draft()->create();
PostFactory::new()->archived()->create();
PostFactory::new()->future()->create();
```

## CI Pipeline

`npm run check:ci` runs:

1. `npm run lint` — ESLint
2. `npm run typecheck` — TypeScript
3. `npm run format:check` — Prettier
4. `npm run test` — Vitest
5. `vendor/bin/pint --dirty` — Laravel Pint
6. `php artisan test --compact` — PHPUnit
