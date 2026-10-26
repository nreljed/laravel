# Metric sync governance

Phase 8 adds the operational controls that make metric collection safe to leave
running: per-platform pacing, a daily request budget, a dead-letter queue, and
retention windows. This document describes the defaults and the operator
workflow.

## Pacing and budgets

Every provider call is reserved before the request is made.

| Setting | Config key | Default | Meaning |
|---------|------------|---------|---------|
| Requests per minute | `marketing.metrics.requests_per_minute` | `30` | Sleep between calls inside one run |
| Per-run cap | `marketing.metrics.max_per_run` | `100` | Maximum deliveries processed per run |
| Daily request budget | `marketing.metrics.daily_request_budget` | `0` | Provider calls per UTC day; `0` means uncapped |

Per-platform overrides live under `marketing.metrics.platforms`:

```php
'platforms' => [
    'linkedin' => [
        'requests_per_minute' => 10,
        'max_per_run' => 25,
        'daily_request_budget' => 500,
    ],
],
```

The daily counter is stored in the application cache under a UTC date-scoped key,
so it resets by construction. Use a shared cache store (Redis, database, Memcached)
when more than one worker runs the scheduler; the `array` store is per-process and
only useful in tests.

When a budget runs out, the remaining deliveries are deferred to the next run and
the command reports how many were skipped. The module never exceeds the cap just
to finish a batch.

## Retries and dead letters

A failed delivery moves through these states:

| State | Meaning |
|-------|---------|
| `pending` | Not collected yet, or re-queued after a replay |
| `ok` | A snapshot was stored |
| `unavailable` | The request succeeded but returned no usable numbers |
| `error` | A retryable failure, still inside its attempt budget |
| `dead_letter` | No further automatic retries |

`marketing.metrics.max_attempts` (default `5`) caps retryable failures. A
permanent provider error (revoked token, unsupported platform, rejected scope)
dead-letters immediately: retrying cannot fix it.

Dead letters are excluded from normal sync selection. An operator either fixes
the cause and replays them, or leaves them parked:

```bash
# List
php artisan marketing:metrics-dead-letters

# Filter to one platform
php artisan marketing:metrics-dead-letters --platform=linkedin

# Re-queue up to 50
php artisan marketing:metrics-dead-letters --platform=linkedin --limit=50 --replay
```

Replay clears the attempt count and the last error, and leaves the delivery
`pending` for the next run.

## Operator view

**Marketing → Sync health** (`marketing.analytics.view`) shows:

- delivery counts per platform across every metric state;
- today's budget usage, remaining calls and exhaustion state;
- the most recent dead letters, with a per-row replay action.

The same information is available over the scheduler with
`marketing:check-sync-alerts`, which warns about retrying failures and reports
dead letters separately.

## Retention

`marketing:prune` (weekly by default) removes rows past their window:

| Table | Config key | Default |
|-------|------------|---------|
| `social_post_metrics` | `marketing.metrics.retention_days` | `365` |
| `marketing_link_clicks` | `marketing.links.retention_days` | `365` |
| `workflow_runs` | `marketing.workflows.retention_days` | `180` |

Only terminal workflow runs (`success`, `failed`, `skipped`) are eligible. A
`pending` run is unfinished work and is never pruned.

Use `--dry-run` to see the counts before deleting:

```bash
php artisan marketing:prune --dry-run
```

## Why the budget lives in the cache

The budget is an approximate guard, not billing. A cache flush resets the counter
for the day; that is preferable to a database write on every provider call. Set
the budget with headroom for one flush, and alert on dead letters rather than on
exact call counts.
