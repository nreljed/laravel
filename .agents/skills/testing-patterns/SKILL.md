---
name: testing-patterns
description: "PHPUnit testing conventions for Cost Breaker 360. Activate when writing, editing, or debugging tests; creating factories; using CreatesTestUsers trait; running php artisan test; or when user mentions tests, testing, factories, or assertions. Covers feature tests, permission tests, validation tests, and the project's specific test patterns."
license: MIT
metadata:
  author: laravel
---

# Testing Patterns

## Test Framework

- **PHPUnit 13** (not Pest)
- Feature tests in `tests/Feature/{Domain}/`
- Unit tests rare — prefer feature tests
- Shared trait: `Tests\Concerns\CreatesTestUsers`

## Creating Tests

```bash
php artisan make:test --phpunit {TestName}        # Feature test
php artisan make:test --phpunit --unit {TestName}  # Unit test
```

Always pass `--no-interaction` to Artisan commands.

## Test Structure

```php
<?php

namespace Tests\Feature\Blog;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Nreljed\Blog\Database\Factories\PostFactory;
use Nreljed\Blog\Models\Post;
use Nreljed\Platform\Database\Seeders\RolesAndPermissionsSeeder;
use Tests\Concerns\CreatesTestUsers;
use Tests\TestCase;

class BlogCrudTest extends TestCase
{
    use CreatesTestUsers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_admin_can_list_posts(): void
    {
        $response = $this->actingAs($this->admin())->get(route('dashboard.posts.index'));
        $response->assertStatus(200);
    }
}
```

## Key Patterns

### User Creation (CreatesTestUsers trait)

```php
$this->admin()                    // Creates admin user with admin role
$this->editor()                   // Creates user with editor role
$this->contributor()              // Creates user with author role
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

public function test_admin_can_access(): void
{
    $response = $this->actingAs($this->admin())->get(route('dashboard.posts.index'));
    $response->assertStatus(200);
}
```

### Validation Tests

```php
public function test_requires_title(): void
{
    $response = $this->actingAs($this->admin())->post(route('dashboard.posts.store'), [
        'content' => 'Content without a title.',
    ]);
    $response->assertSessionHasErrors('title');
}
```

### Database Assertions

```php
$this->assertDatabaseHas('posts', ['title' => 'Test', 'status' => 'draft']);
$this->assertDatabaseMissing('posts', ['id' => $post->id]);
```

### Factory States

```php
PostFactory::new()->create();                    // Default
PostFactory::new()->published()->create();       // Published
PostFactory::new()->draft()->create();           // Draft
PostFactory::new()->archived()->create();        // Archived
PostFactory::new()->future()->create();          // Scheduled
```

## Running Tests

```bash
php artisan test --compact                              # All tests (serial)
php artisan test --parallel --compact                   # All tests, parallel via paratest (~34-44s)
php artisan test --compact tests/Feature/Blog/          # Specific file
php artisan test --compact --filter=testName            # By name
npm run test                                            # Vitest frontend tests
npm run check:ci                                        # Full CI pipeline
```

## Performance Tests

Query-count helpers live on `tests/TestCase.php` and use `DB::listen`:

```php
$queries = $this->captureQueryCount(function () {
    $service->getStats();   // cached → 0 queries on repeat call
});

$this->assertQueryCount(0, function () {
    $this->get('/dashboard');
});
```

- `tests/Feature/Performance/DatabasePerformanceTest.php` — cache TTLs (DashboardService stats, SettingsService groups/keys), N+1 guards (query count constant across 1 vs 5 posts via `PostFactory::new()`)
- `tests/Feature/Performance/DatabaseIndexTest.php` — asserts indexes exist (posts composite `status+published_at`, explicit `user_id`, notifications unread meta, settings key unique, audit_logs action/user_id)
- SQLite does NOT auto-index foreign keys — new FKs need explicit index migrations

## Naming Convention

- `test_method_name_snake_case(): void`
- Prefix with `test_`
- Use descriptive names: `test_admin_can_delete_post`, `test_requires_authentication`

## Common Pitfalls

- Always `seed(RolesAndPermissionsSeeder::class)` in `setUp()` — roles must exist before testing permissions
- Use `$this->actingAs($user)` not `auth()->login($user)`
- Verify user with `$user->forceFill(['email_verified_at' => now])->save()` for verified-only routes
- Deploy tests: token via POST body `['token' => $this->token]`, unauthenticated returns 403 (not 401)
- Notification tests: unauthenticated returns 401 JSON (not redirect) due to JSON exception handler
