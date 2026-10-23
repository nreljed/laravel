<?php

namespace Nreljed\MarketingModule\Services;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Nreljed\MarketingModule\Enums\DeliveryStatus;
use Nreljed\MarketingModule\Enums\MetricSyncStatus;
use Nreljed\MarketingModule\Enums\SocialAccountStatus;
use Nreljed\MarketingModule\Exceptions\MetricFetchException;
use Nreljed\MarketingModule\Models\SocialAccount;
use Nreljed\MarketingModule\Models\SocialPostMetric;
use Nreljed\MarketingModule\Models\SocialPostTarget;
use Nreljed\MarketingModule\Support\NormalizedError;
use Throwable;

/**
 * Collects engagement metrics for published deliveries.
 *
 * One snapshot per delivery per day; repeated syncs update that day's row.
 * A failed request never manufactures a zero and never removes a previous
 * snapshot.
 */
class SocialMetricSync
{
    public function __construct(
        protected SocialChannelRegistry $channels,
        protected MetricNormalizer $normalizer,
        protected AccountTokenRefresher $refresher,
    ) {}

    /**
     * @return array{
     *     selected: int,
     *     processed: int,
     *     ok: int,
     *     unavailable: int,
     *     errors: int,
     *     dry_run: bool
     * }
     */
    public function sync(
        ?CarbonInterface $now = null,
        ?int $days = null,
        ?int $limit = null,
        ?string $platform = null,
        bool $force = false,
        bool $dryRun = false,
    ): array {
        $now = CarbonImmutable::instance($now ?? CarbonImmutable::now());
        $days = max(1, $days ?? (int) config('marketing.metrics.sync_days', 30));
        $limit = max(1, $limit ?? (int) config('marketing.metrics.max_per_run', 100));
        $retryAfter = max(1, (int) config('marketing.metrics.retry_after_minutes', 60));
        $interval = max(1, (int) config('marketing.metrics.sync_interval_hours', 6));

        $query = SocialPostTarget::query()
            ->with('account')
            ->where('status', DeliveryStatus::Published->value)
            ->whereNotNull('external_post_id')
            ->where('external_post_id', '!=', '')
            ->whereNotNull('published_at')
            ->where('published_at', '>=', $now->subDays($days));

        if ($platform !== null && $platform !== '') {
            $query->whereHas('account', static function ($query) use ($platform): void {
                $query->where('platform', $platform);
            });
        }

        if (! $force) {
            $retryBefore = $now->subMinutes($retryAfter);
            $freshBefore = $now->subHours($interval);

            $query->where(static function ($query) use ($retryBefore, $freshBefore): void {
                $query->whereNull('metrics_attempted_at')
                    ->orWhere(static function ($query) use ($retryBefore): void {
                        $query->where('metrics_status', '!=', MetricSyncStatus::Ok->value)
                            ->where('metrics_attempted_at', '<=', $retryBefore);
                    })
                    ->orWhere(static function ($query) use ($freshBefore): void {
                        $query->where('metrics_status', MetricSyncStatus::Ok->value)
                            ->where(static function ($query) use ($freshBefore): void {
                                $query->whereNull('metrics_synced_at')
                                    ->orWhere('metrics_synced_at', '<=', $freshBefore);
                            });
                    });
            });
        }

        $targets = $query
            ->orderByRaw('CASE WHEN metrics_attempted_at IS NULL THEN 0 ELSE 1 END')
            ->orderBy('published_at')
            ->limit($limit)
            ->get();

        $summary = [
            'selected' => $targets->count(),
            'processed' => 0,
            'ok' => 0,
            'unavailable' => 0,
            'errors' => 0,
            'dry_run' => $dryRun,
        ];

        if ($dryRun) {
            return $summary;
        }

        $delay = $this->requestDelay();

        foreach ($targets as $index => $target) {
            $summary['processed']++;

            $result = $this->syncTarget($target, $now);

            $summary[$result]++;

            if ($delay > 0 && $index + 1 < $targets->count()) {
                usleep($delay);
            }
        }

        return $summary;
    }

    /**
     * Sync one delivery and return `ok`, `unavailable`, or `errors`.
     */
    public function syncTarget(SocialPostTarget $target, ?CarbonInterface $now = null): string
    {
        $now = CarbonImmutable::instance($now ?? CarbonImmutable::now());

        $target->forceFill(['metrics_attempted_at' => $now])->save();
        $target->loadMissing('account');

        $account = $target->account;

        if (! $account instanceof SocialAccount) {
            return $this->recordError(
                $target,
                null,
                NormalizedError::permanent('account_missing', 'The social account no longer exists.'),
            );
        }

        if ($account->status === SocialAccountStatus::Revoked) {
            return $this->recordError(
                $target,
                $account,
                NormalizedError::permanent('account_revoked', 'The account grant was revoked.'),
            );
        }

        try {
            $adapter = $this->channels->driver($account->platform);
        } catch (InvalidArgumentException) {
            return $this->recordError(
                $target,
                $account,
                NormalizedError::permanent(
                    'unsupported_platform',
                    "No adapter is configured for [{$account->platform}].",
                ),
            );
        }

        try {
            $this->refresher->refreshIfNeeded($account, $adapter);
            $metrics = $adapter->fetchMetrics((string) $target->external_post_id, $account);
        } catch (MetricFetchException $e) {
            return $this->recordError(
                $target,
                $account,
                new NormalizedError(
                    $e->errorCode,
                    $e->getMessage(),
                    $e->retryable,
                    $e->retryAfter !== null,
                    $e->retryAfter,
                ),
            );
        } catch (Throwable $e) {
            return $this->recordError($target, $account, $adapter->mapError($e));
        }

        $normalized = $this->normalizer->normalize($metrics);
        $hasValue = array_filter(
            $normalized['metrics'],
            static fn (?int $value): bool => $value !== null,
        ) !== [] || $normalized['custom'] !== [];

        if (! $hasValue) {
            $target->forceFill([
                'metrics_status' => MetricSyncStatus::Unavailable,
                'metrics_synced_at' => null,
                'metrics_error' => 'The platform returned no usable metrics for this delivery.',
            ])->save();

            return 'unavailable';
        }

        $metricDate = $now
            ->setTimezone((string) config('app.timezone', 'UTC'))
            ->startOfDay();

        try {
            DB::transaction(function () use ($target, $account, $normalized, $now, $metricDate): void {
                SocialPostMetric::query()->updateOrCreate(
                    [
                        'social_post_target_id' => $target->id,
                        'metric_date' => $metricDate,
                    ],
                    array_merge($normalized['metrics'], [
                        'captured_at' => $now,
                        'custom' => $normalized['custom'] === [] ? null : $normalized['custom'],
                        'raw' => $normalized['raw'] === [] ? null : $normalized['raw'],
                        'source' => $account->platform,
                    ]),
                );

                $target->forceFill([
                    'metrics_status' => MetricSyncStatus::Ok,
                    'metrics_synced_at' => $now,
                    'metrics_error' => null,
                ])->save();
            });
        } catch (Throwable $e) {
            return $this->recordError(
                $target,
                $account,
                NormalizedError::permanent('metric_upsert_failed', $this->safeMessage($e)),
            );
        }

        return 'ok';
    }

    protected function recordError(
        SocialPostTarget $target,
        ?SocialAccount $account,
        NormalizedError $error,
    ): string {
        $target->forceFill([
            'metrics_status' => MetricSyncStatus::Error,
            'metrics_error' => Str::limit($error->message, 500),
        ])->save();

        if (in_array($error->code, ['token_rejected', 'token_missing', 'account_revoked'], true)) {
            $account?->forceFill(['status' => SocialAccountStatus::Expired])->save();
        }

        return 'errors';
    }

    /**
     * Microseconds to wait between provider calls, derived from the limit.
     */
    protected function requestDelay(): int
    {
        $perMinute = (int) config('marketing.metrics.requests_per_minute', 30);

        if ($perMinute <= 0) {
            return 0;
        }

        return (int) floor(60_000_000 / $perMinute);
    }

    /**
     * Keep exception messages usable without leaking credentials or URLs.
     */
    protected function safeMessage(Throwable $e): string
    {
        $message = trim($e->getMessage());

        return $message === '' ? 'The metric snapshot could not be saved.' : Str::limit($message, 500);
    }
}
