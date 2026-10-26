<?php

namespace Nreljed\MarketingModule\Services;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Cache;

/**
 * Per-platform request budget and pacing for metric collection.
 *
 * Two limits apply, both overridable per platform in
 * `marketing.metrics.platforms`:
 *
 * - `requests_per_minute` paces calls inside one run.
 * - `daily_request_budget` caps calls for the UTC day. The counter lives in the
 *   application cache under a date-scoped key, so it resets by construction and
 *   survives across worker processes when the app uses a shared cache store.
 *
 * A budget of `0` means "no cap" rather than "no calls".
 */
class MetricSyncBudget
{
    /**
     * @return array{requests_per_minute: int, max_per_run: int, daily_request_budget: int}
     */
    public function settings(?string $platform): array
    {
        $metrics = (array) config('marketing.metrics', []);
        $overrides = $platform !== null && $platform !== ''
            ? (array) (($metrics['platforms'][$platform] ?? []) ?: [])
            : [];

        return [
            'requests_per_minute' => max(0, (int) ($overrides['requests_per_minute'] ?? $metrics['requests_per_minute'] ?? 30)),
            'max_per_run' => max(1, (int) ($overrides['max_per_run'] ?? $metrics['max_per_run'] ?? 100)),
            'daily_request_budget' => max(0, (int) ($overrides['daily_request_budget'] ?? $metrics['daily_request_budget'] ?? 0)),
        ];
    }

    public function used(?string $platform, ?CarbonInterface $now = null): int
    {
        return (int) Cache::get($this->key($platform, $now), 0);
    }

    /**
     * Remaining calls for the day, or `null` when the platform is uncapped.
     */
    public function remaining(?string $platform, ?CarbonInterface $now = null): ?int
    {
        $budget = $this->settings($platform)['daily_request_budget'];

        if ($budget === 0) {
            return null;
        }

        return max(0, $budget - $this->used($platform, $now));
    }

    /**
     * Reserve one call from today's budget.
     *
     * Returns false when the budget is exhausted; the reserve is rolled back so
     * an exhausted platform never overshoots its cap.
     */
    public function consume(?string $platform, ?CarbonInterface $now = null): bool
    {
        $budget = $this->settings($platform)['daily_request_budget'];
        $key = $this->key($platform, $now);

        Cache::add($key, 0, $this->ttl($now));
        $used = (int) Cache::increment($key);

        if ($budget === 0) {
            return true;
        }

        if ($used > $budget) {
            Cache::decrement($key);

            return false;
        }

        return true;
    }

    /**
     * Forget today's usage for a platform, e.g. after an operator raises the
     * budget limit.
     */
    public function reset(?string $platform, ?CarbonInterface $now = null): void
    {
        Cache::forget($this->key($platform, $now));
    }

    /**
     * Microseconds to wait between provider calls, derived from the limit.
     */
    public function requestDelay(?string $platform): int
    {
        $perMinute = $this->settings($platform)['requests_per_minute'];

        return $perMinute > 0 ? (int) floor(60_000_000 / $perMinute) : 0;
    }

    protected function key(?string $platform, ?CarbonInterface $now = null): string
    {
        $now = CarbonImmutable::instance($now ?? CarbonImmutable::now());

        return sprintf(
            'marketing:metrics:budget:%s:%s',
            $platform !== null && $platform !== '' ? $platform : 'all',
            $now->utc()->toDateString(),
        );
    }

    protected function ttl(?CarbonInterface $now = null): int
    {
        $now = CarbonImmutable::instance($now ?? CarbonImmutable::now());

        // Keep the counter a little past midnight so clock skew between workers
        // cannot resurrect yesterday's usage.
        return max(60, $now->utc()->endOfDay()->diffInSeconds($now) + 3600);
    }
}
