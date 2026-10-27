---
name: telescope-debugging
description: "Laravel Telescope debugging and inspection for Cost Breaker 360. Activate when working with Telescope, debugging requests, inspecting queries, viewing logs, profiling, or when user mentions telescope, debug, profiling, request inspection, query log, or monitoring. Covers Telescope watchers, entries, tagging, and production configuration."
license: MIT
metadata:
  author: laravel
---

# Telescope Debugging

## Overview

Laravel Telescope (v5.20) provides real-time insight into requests, exceptions, log entries, database queries, queued jobs, mail, notifications, cache operations, scheduled tasks, and more.

## Access

- **Local**: `http://laravel.local/telescope`
- **Production**: Disabled by default (see Production section)

## Watchers

Telescope watchers record application activity. Configured in `config/telescope.php`:

| Watcher | Records |
|---|---|
| `RequestWatcher` | HTTP requests, headers, payload, response |
| `QueryWatcher` | Database queries with bindings and timing |
| `ExceptionWatcher` | Unhandled exceptions with stack traces |
| `LogWatcher` | Log entries (debug, info, warning, error) |
| `JobWatcher` | Queued job dispatch and processing |
| `MailWatcher` | Sent emails |
| `NotificationWatcher` | Dispatched notifications |
| `CacheWatcher` | Cache hits, misses, writes, deletes |
| `ScheduleWatcher` | Scheduled task execution |
| `RedisWatcher` | Redis commands |
| `ModelWatcher` | Eloquent model events (create, update, delete) |
| `ViewWatcher` | Rendered views |
| `EventWatcher` | Dispatched events |
| `CommandWatcher` | Artisan command execution |
| `DumpWatcher` | `dump()` / `dd()` output |

## Configuration

```php
// config/telescope.php
'watchers' => [
    Watchers\RequestWatcher::class => [
        'enabled' => env('TELESCOPE_REQUEST_WATCHER', true),
        'size_limit' => env('TELESCOPE_RESPONSE_SIZE_LIMIT', 64),
    ],
    Watchers\QueryWatcher::class => [
        'enabled' => env('TELESCOPE_QUERY_WATCHER', true),
        'slow' => 100, // ms threshold for slow queries
    ],
    // ... other watchers
],
```

## Tagging Entries

Tag Telescope entries for filtering:

```php
use Laravel\Telescope\Telescope;

Telescope::tag(function (IncomingEntry $entry, $batchId) {
    if ($entry->type === 'request') {
        return ['status:' . $entry->content['response_status']];
    }
    return [];
});
```

## Filtering

Filter which entries to record:

```php
Telescope::filter(function (IncomingEntry $entry, $batchId) {
    if ($entry->isReportableException()) {
        return true;
    }
    if ($entry->type === 'request') {
        return $entry->content['response_status'] >= 400;
    }
    return false;
});
```

## Pruning

```bash
# Prune old entries (default: 24 hours)
php artisan telescope:prune

# Prune with custom hours
php artisan telescope:prune --hours=48
```

## Production Configuration

Telescope should be **disabled or restricted** in production:

```php
// config/telescope.php
'enabled' => env('TELESCOPE_ENABLED', false),

// Or restrict to specific users
Gate::define('viewTelescope', function ($user) {
    return in_array($user->email, ['admin@example.com']);
});
```

## Common Debugging Workflows

### Slow Queries
1. Open Telescope → Queries tab
2. Sort by "Time" column
3. Look for queries > 100ms
4. Check for missing indexes or N+1 patterns

### Failed Jobs
1. Open Telescope → Jobs tab
2. Filter by "Failed" status
3. Inspect exception and stack trace
4. Check retry count and backoff

### Request Inspection
1. Open Telescope → Requests tab
2. Click on a request to see headers, payload, response
3. Check for validation errors in response

### Exception Tracking
1. Open Telescope → Exceptions tab
2. View full stack trace
3. Check if exception is reportable

## Key Files

| File | Purpose |
|---|---|
| `config/telescope.php` | Telescope configuration |
| `app/Providers/TelescopeServiceProvider.php` | Gate definition, filtering |
| `vendor/laravel/telescope/` | Telescope package |

## Common Pitfalls

- **Production exposure**: Never leave Telescope enabled in production without authentication gate.
- **Storage growth**: Telescope stores a lot of data. Run `telescope:prune` regularly or set up a scheduled task.
- **Performance impact**: Telescope adds overhead. Disable watchers you don't need in production.
- **Sensitive data**: Telescope records request payloads and headers. Ensure sensitive data (passwords, tokens) is filtered.
- **N+1 detection**: Use the Query watcher to spot N+1 patterns. Look for repeated identical queries.
- **Memory**: Large response bodies are truncated by `size_limit`. Increase if needed for debugging.
