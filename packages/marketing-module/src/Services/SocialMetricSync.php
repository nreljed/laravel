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
        protected ?MetricSyncBudget $budget = null,
    ) {}

    /**
     * @return array{
     *     selected: int,
     *     processed: int,
     *     ok: int,
     *     unavailable: int,
     *     errors: int,
     *     skipped: int,
     *     budget_exhausted: int,
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

        // A platform-scoped run honors that platform's own per-run cap.
        if ($platform !== null && $platform !== '') {
            $limit = min($limit, $this->budget()->settings($platform)['max_per_run']);
        }

        $query = SocialPostTarget::query()
            ->with('account')
            ->where('status', DeliveryStatus::Published->value)
            ->whereNotNull('external_post_id')
            ->where('external_post_id', '!=', '')
            ->whereNotNull('published_at')
            ->where('published_at', '>=', $now->subDays($days))
            // Dead letters only move when an operator replays them.
            ->where('metrics_status', '!=', MetricSyncStatus::DeadLetter->value);

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
            'skipped' => 0,
            'budget_exhausted' => 0,
            'dry_run' => $dryRun,
        ];

        if ($dryRun) {
            return $summary;
        }

        $processedByPlatform = [];
        $exhaustedPlatforms = [];

        foreach ($targets as $index => $target) {
            $targetPlatform = $target->account?->platform;
            $platformKey = $targetPlatform ?? '';

            if (($processedByPlatform[$platformKey] ?? 0) >= $this->budget()->settings($targetPlatform)['max_per_run']) {
                $summary['skipped']++;

                continue;
            }

            if ($exhaustedPlatforms[$platformKey] ?? false) {
                $summary['budget_exhausted']++;

                continue;
            }

            if (! $this->budget()->consume($targetPlatform, $now)) {
                $exhaustedPlatforms[$platformKey] = true;
                $summary['budget_exhausted']++;

                continue;
            }

            $summary['processed']++;
            $processedByPlatform[$platformKey] = ($processedByPlatform[$platformKey] ?? 0) + 1;

            $result = $this->syncTarget($target, $now);

            $summary[$result]++;

            $delay = $this->budget()->requestDelay($targetPlatform);

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
                'metrics_attempts' => 0,
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
                    'metrics_attempts' => 0,
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
        $attempts = (int) $target->metrics_attempts + 1;
        $maxAttempts = max(1, (int) config('marketing.metrics.max_attempts', 5));

        // A permanent provider error cannot be fixed by trying again; park it
        // immediately. Transient errors get `max_attempts` tries first.
        $deadLetter = ! $error->retryable || $attempts >= $maxAttempts;

        $message = $deadLetter
            ? sprintf('%s Dead letter after %d attempt(s).', $error->message, $attempts)
            : sprintf('%s Attempt %d of %d.', $error->message, $attempts, $maxAttempts);

        $target->forceFill([
            'metrics_status' => $deadLetter ? MetricSyncStatus::DeadLetter : MetricSyncStatus::Error,
            'metrics_attempts' => $attempts,
            'metrics_error' => Str::limit($message, 500),
        ])->save();

        if (in_array($error->code, ['token_rejected', 'token_missing', 'account_revoked'], true)) {
            $account?->forceFill(['status' => SocialAccountStatus::Expired])->save();
        }

        return 'errors';
    }

    /**
     * Re-queue every dead letter so the next run retries it.
     */
    public function replayDeadLetters(?string $platform = null, int $limit = 100): int
    {
        $query = SocialPostTarget::query()
            ->where('metrics_status', MetricSyncStatus::DeadLetter->value);

        if ($platform !== null && $platform !== '') {
            $query->whereHas('account', static function ($query) use ($platform): void {
                $query->where('platform', $platform);
            });
        }

        $targets = $query->limit(max(1, $limit))->get();

        foreach ($targets as $target) {
            $this->replayDeadLetter($target);
        }

        return $targets->count();
    }

    /**
     * Re-queue one delivery if it is parked as a dead letter.
     */
    public function replayDeadLetter(SocialPostTarget $target): bool
    {
        if ($target->metrics_status !== MetricSyncStatus::DeadLetter) {
            return false;
        }

        $target->forceFill([
            'metrics_status' => MetricSyncStatus::Pending,
            'metrics_attempts' => 0,
            'metrics_attempted_at' => null,
            'metrics_error' => null,
        ])->save();

        return true;
    }

    protected function budget(): MetricSyncBudget
    {
        return $this->budget ??= new MetricSyncBudget;
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
