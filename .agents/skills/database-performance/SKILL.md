---
name: database-performance
description: "Query optimization and caching conventions for Cost Breaker 360. Activate when profiling or optimizing Eloquent queries, adding indexes or migrations, using Cache::remember, debugging N+1 issues, or when user mentions performance, query counts, caching, or slow pages. Covers the query-count test helpers, 5-minute cache TTL pattern, explicit FK index migrations (SQLite), and N+1 guard tests."
license: MIT
metadata:
  author: laravel
---

# Database Performance

## Query-Count Test Helpers

`tests/TestCase.php` exposes two helpers backed by `DB::listen`:

```php
// Returns the number of queries executed inside the callback
$queries = $this->captureQueryCount(function () {
    $service->getStats();
});

// Assert an exact query count (fails with a helpful message)
$this->assertQueryCount(0, function () {
    $service->getStats(); // cached → 0 queries
});
```

Use `assertQueryCount` for:

- Cache-hit assertions (repeat calls should run 0 queries)
- N+1 guards (query count must be constant as record count grows)

### N+1 Guard Pattern

```php
// tests/Feature/Performance/DatabasePerformanceTest.php
PostFactory::new()->create();
$queries1 = $this->captureQueryCount(fn () => $this->get(route('dashboard.posts.index')));

PostFactory::new()->count(5)->create();
$queries5 = $this->captureQueryCount(fn () => $this->get(route('dashboard.posts.index')));

$this->assertSame($queries1, $queries5); // index query count must not scale with rows
```

## Cache Conventions (5-min TTL)

Services use `Cache::remember` with a 5-minute TTL:

| Service               | What is cached                         | Invalidation                      |
| --------------------- | -------------------------------------- | --------------------------------- |
| `DashboardService`    | `getStats()`, `getAnalytics()`         | `clearStatsCache()` (forget keys) |
| `SettingsService`     | Settings groups + individual values    | `set()` forgets the affected key  |
| `NotificationService` | Unread count (`notif:unread:{userId}`) | `invalidateUnreadCache($userId)`  |

Pattern:

```php
Cache::remember($key, 5, fn () => /* expensive query */);
```

Rules:

- Invalidate the exact key(s) a write touches — never blanket `Cache::flush()` in app code
- Test invalidation: first call runs N queries, second runs 0, write + repeat runs N again
- Tests must `Cache::flush()` in `setUp()` to avoid cross-test pollution

## Index Conventions (SQLite)

SQLite does **not** auto-index foreign-key columns. Every FK column needs an explicit index migration.

Example (`2026_08_01_000000_add_status_published_at_index_to_posts_table.php`):

```php
Schema::table('posts', function (Blueprint $table) {
    $table->index('user_id');
    $table->index(['status', 'published_at'], 'posts_status_published_at_index');
});
```

Covered indexes (see `tests/Feature/Performance/DatabaseIndexTest.php`):

- `posts`: `user_id`, composite `(status, published_at)`
- `notifications`: unread meta index (null `read_at` scans)
- `settings`: unique `key`
- `audit_logs`: `action`, `user_id`
- `roles` / `permissions`: unique `slug`

When adding a new FK column, add both the column **and** an index in the same migration, then assert it in `DatabaseIndexTest`.

## Performance Test Files

- `tests/Feature/Performance/DatabasePerformanceTest.php` — cache TTLs + N+1 guards
- `tests/Feature/Performance/DatabaseIndexTest.php` — index presence assertions

## Key Rules

- Always use `assertQueryCount` when writing a caching or N+1 test
- 5-minute TTL is the project standard for cached service reads
- Explicit index for every FK column (SQLite does not auto-index)
- Invalidate specific cache keys on write, not the whole store
