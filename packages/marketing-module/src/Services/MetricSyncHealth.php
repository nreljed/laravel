<?php

namespace Nreljed\MarketingModule\Services;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Nreljed\MarketingModule\Enums\MetricSyncStatus;
use Nreljed\MarketingModule\Models\SocialPostTarget;

/**
 * Read-only view of metric collection health.
 *
 * The sync service records what happened per delivery; this service answers
 * the operator's questions across deliveries: what is failing, where are the
 * dead letters, and how much of each platform's request budget is gone.
 *
 * Budget usage lives in the cache, so a shared cache store is what makes the
 * number meaningful across worker processes.
 */
class MetricSyncHealth
{
    public function __construct(
        protected MetricSyncBudget $budget,
    ) {}

    /**
     * @return array{
     *     generated_at: string,
     *     config: array{
     *         enabled: bool,
     *         max_attempts: int,
     *         retry_after_minutes: int,
     *         sync_interval_hours: int
     *     },
     *     totals: array<string, int>,
     *     platforms: list<array<string, mixed>>,
     *     dead_letters: list<array<string, mixed>>
     * }
     */
    public function overview(int $deadLetterLimit = 25, ?CarbonInterface $now = null): array
    {
        $now = CarbonImmutable::instance($now ?? CarbonImmutable::now());
        $platforms = $this->platformRows($now);
        $totals = $this->totals($platforms);

        return [
            'generated_at' => $now->toIso8601String(),
            'config' => [
                'enabled' => (bool) config('marketing.metrics.enabled', false),
                'max_attempts' => max(1, (int) config('marketing.metrics.max_attempts', 5)),
                'retry_after_minutes' => max(1, (int) config('marketing.metrics.retry_after_minutes', 60)),
                'sync_interval_hours' => max(1, (int) config('marketing.metrics.sync_interval_hours', 6)),
            ],
            'totals' => $totals,
            'platforms' => $platforms,
            'dead_letters' => $this->deadLetters($deadLetterLimit),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function deadLetters(int $limit = 25, ?string $platform = null): array
    {
        $query = SocialPostTarget::query()
            ->with(['account', 'post'])
            ->where('metrics_status', MetricSyncStatus::DeadLetter->value);

        if ($platform !== null && $platform !== '') {
            $query->whereHas('account', static function ($query) use ($platform): void {
                $query->where('platform', $platform);
            });
        }

        return $query
            ->orderByDesc('metrics_attempted_at')
            ->limit(max(1, $limit))
            ->get()
            ->map(function (SocialPostTarget $target): array {
                $platform = (string) ($target->account?->platform ?? 'unknown');
                $body = trim(strip_tags((string) ($target->post?->body ?? '')));

                return [
                    'id' => $target->id,
                    'platform' => $platform,
                    'platform_label' => $this->platformLabel($platform),
                    'post_id' => $target->social_post_id,
                    'body' => Str::limit($body, 120) ?: 'Post #'.$target->social_post_id,
                    'external_url' => $target->external_url,
                    'attempts' => (int) $target->metrics_attempts,
                    'error' => $target->metrics_error,
                    'attempted_at' => $target->metrics_attempted_at?->toIso8601String(),
                    'published_at' => $target->published_at?->toIso8601String(),
                ];
            })
            ->all();
    }

    /**
     * Per-platform delivery status counts plus today's request budget.
     *
     * @return list<array<string, mixed>>
     */
    protected function platformRows(CarbonInterface $now): array
    {
        $rows = DB::table('social_post_targets as t')
            ->leftJoin('social_accounts as a', 'a.id', '=', 't.social_account_id')
            ->selectRaw("coalesce(a.platform, 'unknown') as platform")
            ->selectRaw('count(*) as total')
            ->selectRaw('sum(case when t.metrics_status = ? then 1 else 0 end) as pending', [MetricSyncStatus::Pending->value])
            ->selectRaw('sum(case when t.metrics_status = ? then 1 else 0 end) as ok', [MetricSyncStatus::Ok->value])
            ->selectRaw('sum(case when t.metrics_status = ? then 1 else 0 end) as unavailable', [MetricSyncStatus::Unavailable->value])
            ->selectRaw('sum(case when t.metrics_status = ? then 1 else 0 end) as error', [MetricSyncStatus::Error->value])
            ->selectRaw('sum(case when t.metrics_status = ? then 1 else 0 end) as dead_letter', [MetricSyncStatus::DeadLetter->value])
            ->selectRaw('max(case when t.metrics_status = ? then t.metrics_synced_at else null end) as last_synced_at', [MetricSyncStatus::Ok->value])
            ->groupByRaw("coalesce(a.platform, 'unknown')")
            ->get();

        $byPlatform = [];
        foreach ($rows as $row) {
            $platform = (string) $row->platform;
            $byPlatform[$platform] = $this->platformPayload(
                $platform,
                [
                    'total' => (int) $row->total,
                    'pending' => (int) $row->pending,
                    'ok' => (int) $row->ok,
                    'unavailable' => (int) $row->unavailable,
                    'error' => (int) $row->error,
                    'dead_letter' => (int) $row->dead_letter,
                ],
                $row->last_synced_at,
                $now,
            );
        }

        // A platform with a configured budget but no deliveries still belongs
        // on the page: the budget is a cap the operator needs to see.
        foreach (array_keys((array) config('marketing.metrics.platforms', [])) as $platform) {
            $platform = (string) $platform;

            if (! isset($byPlatform[$platform])) {
                $byPlatform[$platform] = $this->platformPayload($platform, [], null, $now);
            }
        }

        $platforms = array_values($byPlatform);
        usort($platforms, static function (array $a, array $b): int {
            return [$b['total'], $a['platform']] <=> [$a['total'], $b['platform']];
        });

        return $platforms;
    }

    /**
     * @param  array<string, int>  $counts
     * @return array<string, mixed>
     */
    protected function platformPayload(string $platform, array $counts, mixed $lastSyncedAt, CarbonInterface $now): array
    {
        $settings = $this->budget->settings($platform);
        $used = $this->budget->used($platform, $now);
        $remaining = $this->budget->remaining($platform, $now);

        return [
            'platform' => $platform,
            'label' => $this->platformLabel($platform),
            'total' => $counts['total'] ?? 0,
            'pending' => $counts['pending'] ?? 0,
            'ok' => $counts['ok'] ?? 0,
            'unavailable' => $counts['unavailable'] ?? 0,
            'error' => $counts['error'] ?? 0,
            'dead_letter' => $counts['dead_letter'] ?? 0,
            'requests_per_minute' => $settings['requests_per_minute'],
            'max_per_run' => $settings['max_per_run'],
            'daily_request_budget' => $settings['daily_request_budget'],
            'budget_used' => $used,
            'budget_remaining' => $remaining,
            'budget_exhausted' => $remaining !== null && $remaining <= 0,
            'last_synced_at' => $lastSyncedAt !== null ? CarbonImmutable::parse((string) $lastSyncedAt)->toIso8601String() : null,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $platforms
     * @return array<string, int>
     */
    protected function totals(array $platforms): array
    {
        $totals = [
            'total' => 0,
            'pending' => 0,
            'ok' => 0,
            'unavailable' => 0,
            'error' => 0,
            'dead_letter' => 0,
        ];

        /** @var Collection<int, array<string, mixed>> $collection */
        $collection = collect($platforms);

        foreach (array_keys($totals) as $key) {
            $totals[$key] = (int) $collection->sum($key);
        }

        return $totals;
    }

    protected function platformLabel(string $platform): string
    {
        return match ($platform) {
            'x' => 'X',
            'linkedin' => 'LinkedIn',
            'unknown' => 'Unknown',
            default => ucfirst($platform),
        };
    }
}
