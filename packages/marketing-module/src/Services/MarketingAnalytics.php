<?php

namespace Nreljed\MarketingModule\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Nreljed\MarketingModule\Enums\DeliveryStatus;
use Nreljed\MarketingModule\Enums\MetricSyncStatus;
use Nreljed\MarketingModule\Enums\PostStatus;
use Nreljed\MarketingModule\Enums\WorkflowRunStatus;
use Nreljed\MarketingModule\Models\Campaign;
use Nreljed\MarketingModule\Models\Contact;
use Nreljed\MarketingModule\Models\MarketingLink;
use Nreljed\MarketingModule\Models\SocialPost;
use Nreljed\MarketingModule\Models\SocialPostMetric;
use Nreljed\MarketingModule\Models\SocialPostTarget;
use Nreljed\MarketingModule\Models\Workflow;
use Nreljed\MarketingModule\Models\WorkflowRun;

/**
 * Read-only reporting for the marketing dashboard.
 *
 * The numbers describe the engine's own records - what was scheduled, what
 * each platform returned, which campaigns those deliveries belonged to - and
 * deliberately stop there. Attribution to revenue belongs to the shop.
 *
 * Engagement snapshots are cumulative, not deltas. Every metric aggregate in
 * this service therefore reads the latest snapshot per delivery; summing the
 * snapshot table would multiply a post's reach by the number of days it was
 * collected.
 */
class MarketingAnalytics
{
    /**
     * The periods the UI and CSV export expose.
     *
     * @var list<int>
     */
    public const PERIODS = [7, 30, 90];

    /**
     * @return array<string, mixed>
     */
    public function overview(int $days = 30): array
    {
        $period = $this->period($days);
        $posts = $this->postsForPeriod($period);
        $targets = $this->targetsForPeriod($period);

        return [
            'period' => $this->periodPayload($period),
            'summary' => $this->summary($period, $posts, $targets),
            'coverage' => $this->coverage($targets),
            'series' => $this->series($period, $targets),
            'platforms' => $this->platforms($targets),
            'top_posts' => $this->topPosts($period, $posts, $targets),
            'campaigns' => $this->campaignRows($period, $posts, $targets, 10),
            'workflows' => $this->workflows($period['days']),
            'links' => $this->links($period, 10),
        ];
    }

    /**
     * Rows used by the CSV exporter. The page uses the capped versions above;
     * exports are complete for the selected period.
     *
     * @return list<array<string, mixed>>
     */
    public function dataset(string $dataset, int $days = 30): array
    {
        $period = $this->period($days);
        $posts = $this->postsForPeriod($period);
        $targets = $this->targetsForPeriod($period);

        return match ($dataset) {
            'posts' => $this->postRows($period, $posts, $targets),
            'campaigns' => $this->campaignRows($period, $posts, $targets),
            'links' => $this->links($period),
            default => [],
        };
    }

    /**
     * @return array{days: int, start: CarbonImmutable, end: CarbonImmutable}
     */
    private function period(int $days): array
    {
        $days = in_array($days, self::PERIODS, true) ? $days : 30;

        $start = CarbonImmutable::today()->subDays($days - 1)->startOfDay();

        return [
            'days' => $days,
            'start' => $start,
            'end' => CarbonImmutable::today()->endOfDay(),
        ];
    }

    /**
     * @param  array{days: int, start: CarbonImmutable, end: CarbonImmutable}  $period
     * @return array<string, mixed>
     */
    private function periodPayload(array $period): array
    {
        return [
            'days' => $period['days'],
            'start' => $period['start']->toDateString(),
            'end' => $period['end']->toDateString(),
            'label' => $period['start']->format('M j').' - '.$period['end']->format('M j, Y'),
        ];
    }

    /**
     * @param  array{days: int, start: CarbonImmutable, end: CarbonImmutable}  $period
     * @return Collection<int, SocialPost>
     */
    private function postsForPeriod(array $period): Collection
    {
        return SocialPost::query()
            ->with('campaign:id,name')
            ->whereRaw(
                'COALESCE(published_at, scheduled_at, created_at) BETWEEN ? AND ?',
                [$period['start'], $period['end']],
            )
            ->get();
    }

    /**
     * @param  array{days: int, start: CarbonImmutable, end: CarbonImmutable}  $period
     * @return Collection<int, SocialPostTarget>
     */
    private function targetsForPeriod(array $period): Collection
    {
        return SocialPostTarget::query()
            ->with([
                'account:id,platform,account_name',
                'latestMetric',
                'post:id,campaign_id,body,status,published_at,scheduled_at,created_at',
                'post.campaign:id,name',
            ])
            ->whereRaw(
                'COALESCE(published_at, last_attempted_at, created_at) BETWEEN ? AND ?',
                [$period['start'], $period['end']],
            )
            ->get();
    }

    /**
     * @param  array{days: int, start: CarbonImmutable, end: CarbonImmutable}  $period
     * @param  Collection<int, SocialPost>  $posts
     * @param  Collection<int, SocialPostTarget>  $targets
     * @return array<string, mixed>
     */
    private function summary(array $period, Collection $posts, Collection $targets): array
    {
        $status = $posts->countBy(
            static fn (SocialPost $post): string => $post->status?->value ?? 'unknown',
        );

        $delivered = $targets->filter->isPublished();
        $failed = $targets->filter(
            static fn (SocialPostTarget $target): bool => $target->status === DeliveryStatus::Failed,
        );
        $attempted = $delivered->count() + $failed->count();
        $metrics = $this->metricTotals($delivered);

        return [
            'posts' => $posts->count(),
            'scheduled' => (int) ($status[PostStatus::Scheduled->value] ?? 0),
            'published' => (int) ($status[PostStatus::Published->value] ?? 0),
            'partial' => (int) ($status[PostStatus::Partial->value] ?? 0),
            'failed' => (int) ($status[PostStatus::Failed->value] ?? 0),
            'deliveries' => $targets->count(),
            'delivered' => $delivered->count(),
            'delivery_failures' => $failed->count(),
            'success_rate' => $attempted > 0
                ? round(($delivered->count() / $attempted) * 100, 1)
                : 0.0,
            'contacts' => Contact::query()
                ->whereBetween('created_at', [$period['start'], $period['end']])
                ->count(),
            'campaigns' => $posts
                ->pluck('campaign_id')
                ->filter()
                ->unique()
                ->count(),
            'active_workflows' => Workflow::query()->where('is_active', true)->count(),
            'impressions' => $metrics['impressions'],
            'reach' => $metrics['reach'],
            'engagements' => $metrics['engagements'],
            'clicks' => $metrics['clicks'],
            'engagement_rate' => $metrics['engagement_rate'],
            'click_through_rate' => $metrics['click_through_rate'],
        ];
    }

    /**
     * @param  Collection<int, SocialPostTarget>  $targets
     * @return array<string, mixed>
     */
    private function coverage(Collection $targets): array
    {
        $delivered = $targets->filter->isPublished();
        $withMetrics = $delivered->filter(
            static fn (SocialPostTarget $target): bool => $target->latestMetric !== null,
        );
        $lastSynced = $delivered
            ->pluck('metrics_synced_at')
            ->filter()
            ->sortDesc()
            ->first();

        return [
            'metrics_enabled' => (bool) config('marketing.metrics.enabled', false),
            'delivered' => $delivered->count(),
            'with_metrics' => $withMetrics->count(),
            'unavailable' => $delivered->filter(
                static fn (SocialPostTarget $target): bool => $target->metrics_status === MetricSyncStatus::Unavailable,
            )->count(),
            'errors' => $delivered->filter(
                static fn (SocialPostTarget $target): bool => $target->metrics_status === MetricSyncStatus::Error,
            )->count(),
            'pending' => $delivered->filter(
                static fn (SocialPostTarget $target): bool => $target->latestMetric === null
                    && ($target->metrics_status === null || $target->metrics_status === MetricSyncStatus::Pending),
            )->count(),
            'last_synced_at' => $lastSynced?->toIso8601String(),
            'coverage_rate' => $delivered->count() > 0
                ? round(($withMetrics->count() / $delivered->count()) * 100, 1)
                : 0.0,
        ];
    }

    /**
     * @param  array{days: int, start: CarbonImmutable, end: CarbonImmutable}  $period
     * @param  Collection<int, SocialPostTarget>  $targets
     * @return list<array<string, mixed>>
     */
    private function series(array $period, Collection $targets): array
    {
        $points = [];

        for ($offset = 0; $offset < $period['days']; $offset++) {
            $date = $period['start']->addDays($offset);

            $points[$date->toDateString()] = [
                'date' => $date->toDateString(),
                'label' => $date->format('M j'),
                'delivered' => 0,
                'impressions' => 0,
                'engagements' => 0,
                'clicks' => 0,
            ];
        }

        foreach ($targets as $target) {
            $eventAt = $this->targetEventAt($target);

            if ($eventAt === null) {
                continue;
            }

            $key = $eventAt->toDateString();

            if (! isset($points[$key])) {
                continue;
            }

            if ($target->isPublished()) {
                $points[$key]['delivered']++;
            }

            $metric = $target->latestMetric;

            if ($metric === null) {
                continue;
            }

            $points[$key]['impressions'] += (int) ($metric->impressions ?? 0);
            $points[$key]['engagements'] += (int) ($metric->engagements() ?? 0);
            $points[$key]['clicks'] += (int) ($metric->clicks ?? 0);
        }

        return array_values($points);
    }

    /**
     * @param  Collection<int, SocialPostTarget>  $targets
     * @return list<array<string, mixed>>
     */
    private function platforms(Collection $targets): array
    {
        return $targets
            ->groupBy(
                static fn (SocialPostTarget $target): string => (string) ($target->account?->platform ?? 'unknown'),
            )
            ->map(function (Collection $group, string $platform): array {
                $metrics = $this->metricTotals($group->filter->isPublished());

                return [
                    'platform' => $platform,
                    'label' => $this->platformLabel($platform),
                    'total' => $group->count(),
                    'delivered' => $group->filter->isPublished()->count(),
                    'failed' => $group->filter(
                        static fn (SocialPostTarget $target): bool => $target->status === DeliveryStatus::Failed,
                    )->count(),
                    'with_metrics' => $metrics['with_metrics'],
                    'impressions' => $metrics['impressions'],
                    'engagements' => $metrics['engagements'],
                    'clicks' => $metrics['clicks'],
                ];
            })
            ->sortByDesc('total')
            ->values()
            ->all();
    }

    /**
     * @param  array{days: int, start: CarbonImmutable, end: CarbonImmutable}  $period
     * @param  Collection<int, SocialPost>  $posts
     * @param  Collection<int, SocialPostTarget>  $targets
     * @return list<array<string, mixed>>
     */
    private function topPosts(array $period, Collection $posts, Collection $targets): array
    {
        $rows = $this->postRows($period, $posts, $targets);

        $rows = array_values(array_filter(
            $rows,
            static fn (array $row): bool => $row['delivered'] > 0,
        ));

        usort($rows, static function (array $a, array $b): int {
            return [$b['impressions'] ?? 0, $b['engagements'] ?? 0, $b['delivered']]
                <=> [$a['impressions'] ?? 0, $a['engagements'] ?? 0, $a['delivered']];
        });

        return array_slice($rows, 0, 10);
    }

    /**
     * @param  array{days: int, start: CarbonImmutable, end: CarbonImmutable}  $period
     * @param  Collection<int, SocialPost>  $posts
     * @param  Collection<int, SocialPostTarget>  $targets
     * @return list<array<string, mixed>>
     */
    private function postRows(array $period, Collection $posts, Collection $targets): array
    {
        $byPost = $targets->groupBy('social_post_id');

        return $posts
            ->map(function (SocialPost $post) use ($byPost): array {
                /** @var Collection<int, SocialPostTarget> $postTargets */
                $postTargets = $byPost->get($post->id, collect());
                $delivered = $postTargets->filter->isPublished();
                $metrics = $this->metricTotals($delivered);
                $eventAt = $this->postEventAt($post);

                return [
                    'id' => $post->id,
                    'body' => Str::limit(trim(strip_tags((string) ($post->body ?? ''))), 120) ?: 'Post #'.$post->id,
                    'campaign_id' => $post->campaign_id,
                    'campaign' => $post->campaign?->name,
                    'status' => $post->status?->value,
                    'status_label' => $post->status?->label(),
                    'platforms' => $postTargets
                        ->map(static fn (SocialPostTarget $target): string => (string) ($target->account?->platform ?? 'unknown'))
                        ->unique()
                        ->map(fn (string $platform): string => $this->platformLabel($platform))
                        ->values()
                        ->all(),
                    'deliveries' => $postTargets->count(),
                    'delivered' => $delivered->count(),
                    'failed' => $postTargets->filter(
                        static fn (SocialPostTarget $target): bool => $target->status === DeliveryStatus::Failed,
                    )->count(),
                    'impressions' => $metrics['impressions'],
                    'reach' => $metrics['reach'],
                    'engagements' => $metrics['engagements'],
                    'clicks' => $metrics['clicks'],
                    'engagement_rate' => $metrics['engagement_rate'],
                    'click_through_rate' => $metrics['click_through_rate'],
                    'published_at' => $eventAt?->toIso8601String(),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  array{days: int, start: CarbonImmutable, end: CarbonImmutable}  $period
     * @param  Collection<int, SocialPost>  $posts
     * @param  Collection<int, SocialPostTarget>  $targets
     * @return list<array<string, mixed>>
     */
    private function campaignRows(
        array $period,
        Collection $posts,
        Collection $targets,
        ?int $limit = null,
    ): array {
        $grouped = $posts
            ->filter(static fn (SocialPost $post): bool => $post->campaign_id !== null)
            ->groupBy('campaign_id');

        if ($grouped->isEmpty()) {
            return [];
        }

        $campaigns = Campaign::query()
            ->whereIn('id', $grouped->keys()->all())
            ->get()
            ->keyBy('id');

        $targetsByPost = $targets->groupBy('social_post_id');

        $rows = $grouped
            ->map(function (Collection $campaignPosts, int|string $campaignId) use ($campaigns, $targetsByPost): ?array {
                /** @var Campaign|null $campaign */
                $campaign = $campaigns->get($campaignId);

                if ($campaign === null) {
                    return null;
                }

                $campaignTargets = $campaignPosts
                    ->flatMap(static fn (SocialPost $post): Collection => $targetsByPost->get($post->id, collect()))
                    ->values();
                $delivered = $campaignTargets->filter->isPublished();
                $failed = $campaignTargets->filter(
                    static fn (SocialPostTarget $target): bool => $target->status === DeliveryStatus::Failed,
                );
                $metrics = $this->metricTotals($delivered);

                return [
                    'id' => $campaign->id,
                    'name' => $campaign->name,
                    'status' => $campaign->status?->value,
                    'status_label' => $campaign->status?->label(),
                    'objective' => $campaign->objective?->value,
                    'objective_label' => $campaign->objective?->label(),
                    'posts' => $campaignPosts->count(),
                    'scheduled' => $campaignPosts->filter(
                        static fn (SocialPost $post): bool => $post->status === PostStatus::Scheduled,
                    )->count(),
                    'deliveries' => $campaignTargets->count(),
                    'delivered' => $delivered->count(),
                    'failed' => $failed->count(),
                    'delivery_rate' => $campaignTargets->count() > 0
                        ? round(($delivered->count() / $campaignTargets->count()) * 100, 1)
                        : 0.0,
                    'impressions' => $metrics['impressions'],
                    'reach' => $metrics['reach'],
                    'engagements' => $metrics['engagements'],
                    'clicks' => $metrics['clicks'],
                    'engagement_rate' => $metrics['engagement_rate'],
                    'click_through_rate' => $metrics['click_through_rate'],
                ];
            })
            ->filter()
            ->sortBy([['delivered', 'desc'], ['name', 'asc']])
            ->values();

        if ($limit !== null) {
            $rows = $rows->take($limit);
        }

        return $rows->values()->all();
    }

    /**
     * @param  array{days: int, start: CarbonImmutable, end: CarbonImmutable}  $period
     * @return list<array<string, mixed>>
     */
    private function links(array $period, ?int $limit = null): array
    {
        $query = MarketingLink::query()
            ->join('marketing_link_clicks as clicks', 'clicks.marketing_link_id', '=', 'marketing_links.id')
            ->whereBetween('clicks.clicked_at', [$period['start'], $period['end']])
            ->select([
                'marketing_links.id',
                'marketing_links.social_post_id',
                'marketing_links.destination_url',
                'marketing_links.utm_campaign',
                'marketing_links.short_code',
            ])
            ->selectRaw('COUNT(clicks.id) as clicks')
            ->selectRaw('MAX(clicks.clicked_at) as last_clicked_at')
            ->groupBy([
                'marketing_links.id',
                'marketing_links.social_post_id',
                'marketing_links.destination_url',
                'marketing_links.utm_campaign',
                'marketing_links.short_code',
            ])
            ->orderByRaw('COUNT(clicks.id) DESC');

        if ($limit !== null) {
            $query->limit($limit);
        }

        return $query
            ->get()
            ->map(function (object $row): array {
                return [
                    'id' => (int) $row->id,
                    'social_post_id' => $row->social_post_id !== null ? (int) $row->social_post_id : null,
                    'destination_url' => (string) $row->destination_url,
                    'utm_campaign' => $row->utm_campaign,
                    'short_code' => (string) $row->short_code,
                    'short_url' => $this->trackedUrl((string) $row->short_code),
                    'clicks' => (int) $row->clicks,
                    'conversions' => 0,
                    'last_clicked_at' => $row->last_clicked_at !== null
                        ? CarbonImmutable::parse((string) $row->last_clicked_at)->toIso8601String()
                        : null,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function workflows(int $days): array
    {
        $period = $this->period($days);

        return [
            'active' => Workflow::query()->where('is_active', true)->count(),
            'total' => Workflow::query()->count(),
            'runs' => WorkflowRun::query()
                ->whereBetween('ran_at', [$period['start'], $period['end']])
                ->count(),
            'failures' => WorkflowRun::query()
                ->whereBetween('ran_at', [$period['start'], $period['end']])
                ->where('status', WorkflowRunStatus::Failed->value)
                ->count(),
            'recent' => WorkflowRun::query()
                ->with('workflow')
                ->whereBetween('ran_at', [$period['start'], $period['end']])
                ->latest('ran_at')
                ->limit(10)
                ->get()
                ->map(static fn (WorkflowRun $run): array => [
                    'id' => $run->id,
                    'workflow' => $run->workflow?->name,
                    'event' => $run->event,
                    'status' => $run->status?->value,
                    'status_label' => $run->status?->label(),
                    'message' => $run->message,
                    'ran_at' => $run->ran_at?->toIso8601String(),
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * Latest-snapshot totals for a set of deliveries.
     *
     * @param  Collection<int, SocialPostTarget>  $targets
     * @return array{with_metrics: int, impressions: ?int, reach: ?int, engagements: ?int, clicks: ?int, engagement_rate: ?float, click_through_rate: ?float}
     */
    private function metricTotals(Collection $targets): array
    {
        $withMetrics = 0;
        $impressions = null;
        $reach = null;
        $engagements = null;
        $clicks = null;

        foreach ($targets as $target) {
            /** @var SocialPostMetric|null $metric */
            $metric = $target->latestMetric;

            if ($metric === null) {
                continue;
            }

            $withMetrics++;
            $impressions = $this->addNullable($impressions, $metric->impressions);
            $reach = $this->addNullable($reach, $metric->reach);
            $clicks = $this->addNullable($clicks, $metric->clicks);
            $engagements = $this->addNullable($engagements, $metric->engagements());
        }

        return [
            'with_metrics' => $withMetrics,
            'impressions' => $impressions,
            'reach' => $reach,
            'engagements' => $engagements,
            'clicks' => $clicks,
            'engagement_rate' => $this->rate($engagements, $impressions),
            'click_through_rate' => $this->rate($clicks, $impressions),
        ];
    }

    private function addNullable(?int $total, ?int $value): ?int
    {
        if ($value === null) {
            return $total;
        }

        return ($total ?? 0) + $value;
    }

    private function rate(?int $numerator, ?int $denominator): ?float
    {
        if ($numerator === null || $denominator === null || $denominator === 0) {
            return null;
        }

        return round(($numerator / $denominator) * 100, 1);
    }

    private function targetEventAt(SocialPostTarget $target): ?CarbonImmutable
    {
        $at = $target->published_at ?? $target->last_attempted_at ?? $target->created_at;

        return $at !== null ? CarbonImmutable::instance($at) : null;
    }

    private function postEventAt(SocialPost $post): ?CarbonImmutable
    {
        $at = $post->published_at ?? $post->scheduled_at ?? $post->created_at;

        return $at !== null ? CarbonImmutable::instance($at) : null;
    }

    private function platformLabel(string $platform): string
    {
        return match ($platform) {
            'x' => 'X',
            'linkedin' => 'LinkedIn',
            default => ucfirst($platform),
        };
    }

    private function trackedUrl(string $shortCode): string
    {
        $base = trim((string) (config('marketing.links.base_url') ?: config('app.url')));
        $base = rtrim($base, '/');
        $prefix = trim((string) config('marketing.links.prefix', 'm'), '/');

        if ($base === '') {
            return $prefix !== '' ? $prefix.'/'.$shortCode : $shortCode;
        }

        return $base.'/'.($prefix !== '' ? $prefix.'/' : '').$shortCode;
    }
}
