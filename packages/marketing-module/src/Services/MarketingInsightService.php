<?php

namespace Nreljed\MarketingModule\Services;

use Carbon\CarbonImmutable;
use Nreljed\MarketingModule\Agents\MarketingAnalystAgent;
use Nreljed\MarketingModule\Enums\MarketingInsightConfidence;
use Nreljed\MarketingModule\Enums\MarketingInsightStatus;
use Nreljed\MarketingModule\Enums\MarketingInsightType;
use Nreljed\MarketingModule\Models\MarketingBrief;
use Nreljed\MarketingModule\Models\MarketingInsight;
use Nreljed\Platform\Models\User;

/**
 * Rule-based insights derived from observed marketing data.
 *
 * The service is deterministic and auditable: it only uses the aggregates the
 * analytics service already trusts. An "apply" action creates a draft only.
 */
class MarketingInsightService
{
    public function __construct(private readonly MarketingAnalytics $analytics) {}

    /**
     * @return list<MarketingInsight>
     */
    public function refresh(int $days = 30): array
    {
        $period = $this->period($days);
        $overview = $this->analytics->overview($days);
        $created = [];

        $created[] = $this->maybeCreate($period, $overview, $this->coverageInsight($period, $overview));
        $created[] = $this->maybeCreate($period, $overview, $this->topPostInsight($period, $overview));
        $created[] = $this->maybeCreate($period, $overview, $this->clickInsight($period, $overview));
        $created[] = $this->maybeCreate($period, $overview, $this->campaignInsight($period, $overview));

        return array_values(array_filter($created));
    }

    /**
     * @return list<MarketingInsight>
     */
    public function analyzeWithAgent(User $user, int $days, MarketingAnalystAgent $agent): array
    {
        $days = in_array($days, [7, 30, 90], true) ? $days : 30;
        $period = $this->period($days);

        try {
            $result = $agent->analyze($user, $days);
        } catch (\Throwable) {
            return [];
        }

        $created = [];

        foreach (($result['insights'] ?? []) as $item) {
            if (! is_array($item)) {
                continue;
            }

            $title = trim((string) ($item['title'] ?? ''));

            if ($title === '') {
                continue;
            }

            $type = MarketingInsightType::tryFrom((string) ($item['type'] ?? ''))
                ?? MarketingInsightType::Content;
            $confidence = MarketingInsightConfidence::tryFrom((string) ($item['confidence'] ?? ''))
                ?? MarketingInsightConfidence::Medium;

            $fingerprint = hash('sha256', json_encode([
                $period['start']->toDateString(),
                $period['end']->toDateString(),
                'ai',
                $title,
            ], JSON_UNESCAPED_SLASHES));

            $existing = MarketingInsight::query()->where('fingerprint', $fingerprint)->first();

            if ($existing !== null && $existing->status !== MarketingInsightStatus::New) {
                $created[] = $existing;

                continue;
            }

            $record = MarketingInsight::query()->updateOrCreate(
                ['fingerprint' => $fingerprint],
                [
                    'period_days' => $days,
                    'period_start' => $period['start']->toDateString(),
                    'period_end' => $period['end']->toDateString(),
                    'type' => $type,
                    'title' => $title,
                    'summary' => trim((string) ($item['summary'] ?? '')),
                    'recommendation' => trim((string) ($item['recommendation'] ?? '')),
                    'evidence' => array_values(array_filter($item['evidence'] ?? [], 'is_string')),
                    'confidence' => $confidence,
                    'status' => MarketingInsightStatus::New,
                    'generated_by' => 'ai',
                    'metadata' => [
                        'risks' => $result['risks'] ?? [],
                    ],
                ],
            );

            $created[] = $record;
        }

        return array_values(array_filter($created));
    }

    /**
     * @param  array{days:int,start:CarbonImmutable,end:CarbonImmutable,label:string}  $period
     * @param  array<string,mixed>  $overview
     * @param  array<string,mixed>|null  $insight
     */
    private function maybeCreate(array $period, array $overview, ?array $insight): ?MarketingInsight
    {
        if ($insight === null) {
            return null;
        }

        $fingerprint = hash('sha256',
            json_encode([
                $period['start']->toDateString(),
                $period['end']->toDateString(),
                $insight['type'],
                $insight['key'],
            ], JSON_UNESCAPED_SLASHES),
        );

        $existing = MarketingInsight::query()->where('fingerprint', $fingerprint)->first();

        if ($existing !== null && $existing->status !== MarketingInsightStatus::New) {
            return null;
        }

        return MarketingInsight::query()->firstOrCreate(
            ['fingerprint' => $fingerprint],
            [
                'period_days' => $period['days'],
                'period_start' => $period['start']->toDateString(),
                'period_end' => $period['end']->toDateString(),
                'type' => $insight['type'],
                'title' => $insight['title'],
                'summary' => $insight['summary'],
                'recommendation' => $insight['recommendation'],
                'evidence' => $insight['evidence'],
                'confidence' => $insight['confidence'],
                'status' => MarketingInsightStatus::New,
                'generated_by' => 'rules',
            ],
        );
    }

    public function apply(MarketingInsight $insight, User $user): MarketingInsight
    {
        $insight->forceFill([
            'status' => MarketingInsightStatus::Applied,
            'applied_by' => $user->id,
            'applied_at' => now(),
        ])->save();

        MarketingBrief::create([
            'title' => $insight->title,
            'objective' => 'engagement',
            'audience' => 'Audience from applied insight',
            'key_message' => $insight->recommendation,
            'channels' => [],
            'status' => 'draft',
            'created_by' => $user->id,
            'metadata' => [
                'source_insight_id' => $insight->id,
                'evidence' => $insight->evidence,
            ],
        ]);

        return $insight->refresh();
    }

    /**
     * @param  array{days:int,start:CarbonImmutable,end:CarbonImmutable,label:string}  $period
     * @param  array<string,mixed>  $overview
     * @return array<string,mixed>|null
     */
    private function coverageInsight(array $period, array $overview): ?array
    {
        $coverage = $overview['coverage'] ?? [];
        $delivered = (int) ($coverage['delivered'] ?? 0);
        $with = (int) ($coverage['with_metrics'] ?? 0);

        if ($delivered === 0 || $with >= $delivered) {
            return null;
        }

        $rate = (int) round(($with / $delivered) * 100);

        return [
            'type' => MarketingInsightType::Coverage,
            'key' => 'coverage',
            'title' => 'Close the measurement gap',
            'summary' => "Only {$with} of {$delivered} delivered posts have metric snapshots ({$rate}%). Missing metrics make engagement and rates hard to trust.",
            'recommendation' => 'Run metrics sync for this period, confirm provider scopes, or publish to a smaller set of accounts until coverage improves. Create a draft post only after coverage is stable.',
            'evidence' => [
                "period: {$period['label']}",
                "delivered: {$delivered}",
                "with_metrics: {$with}",
                'coverage_rate: '.($coverage['coverage_rate'] ?? 'null').'% ',
                'unavailable: '.($coverage['unavailable'] ?? 0),
                'errors: '.($coverage['errors'] ?? 0),
            ],
            'confidence' => MarketingInsightConfidence::High,
        ];
    }

    /**
     * @param  array{days:int,start:CarbonImmutable,end:CarbonImmutable,label:string}  $period
     * @param  array<string,mixed>  $overview
     * @return array<string,mixed>|null
     */
    private function topPostInsight(array $period, array $overview): ?array
    {
        $posts = $overview['top_posts'] ?? [];

        if (count($posts) === 0) {
            return null;
        }

        $top = $posts[0];
        $impressions = $top['impressions'] ?? null;

        if ($impressions === null || (int) $impressions === 0) {
            return null;
        }

        return [
            'type' => MarketingInsightType::Content,
            'key' => 'top-post',
            'title' => 'Reuse what already worked',
            'summary' => "The top post in {$period['label']} has {$impressions} impressions and {$top['engagements']} engagements.",
            'recommendation' => 'Draft a brief based on this post’s hook and CTA, adapt it to another platform, and schedule it as a test with tracked clicks. Keep the original post unchanged.',
            'evidence' => [
                'post: '.($top['body'] ?? ('Post #'.$top['id'])),
                "impressions: {$impressions}",
                'engagements: '.($top['engagements'] ?? 'null'),
                'engagement_rate: '.($top['engagement_rate'] ?? 'null').'% ',
                'platforms: '.implode(', ', $top['platforms'] ?? []),
            ],
            'confidence' => MarketingInsightConfidence::High,
        ];
    }

    /**
     * @param  array{days:int,start:CarbonImmutable,end:CarbonImmutable,label:string}  $period
     * @param  array<string,mixed>  $overview
     * @return array<string,mixed>|null
     */
    private function clickInsight(array $period, array $overview): ?array
    {
        $links = $overview['links'] ?? [];
        $clicks = 0;

        foreach ($links as $link) {
            $clicks += (int) ($link['clicks'] ?? 0);
        }

        if ($clicks === 0 || count($links) === 0) {
            return null;
        }

        return [
            'type' => MarketingInsightType::ClickThrough,
            'key' => 'clicks',
            'title' => 'Tracked clicks are coming in',
            'summary' => "{$clicks} tracked clicks were recorded across ".count($links)." links in {$period['label']}.",
            'recommendation' => 'Review the top linked destination and add tracked links to a new draft post with a tighter call-to-action. Conversions are not yet reported (no conversion contract).',
            'evidence' => [
                "tracked_clicks: {$clicks}",
                'links: '.count($links),
            ],
            'confidence' => MarketingInsightConfidence::Medium,
        ];
    }

    /**
     * @param  array{days:int,start:CarbonImmutable,end:CarbonImmutable,label:string}  $period
     * @param  array<string,mixed>  $overview
     * @return array<string,mixed>|null
     */
    private function campaignInsight(array $period, array $overview): ?array
    {
        $campaigns = $overview['campaigns'] ?? [];

        if (count($campaigns) === 0) {
            return null;
        }

        $best = null;
        $bestRate = -1.0;

        foreach ($campaigns as $campaign) {
            $rate = $campaign['engagement_rate'] ?? null;

            if ($rate !== null && (float) $rate > $bestRate) {
                $bestRate = (float) $rate;
                $best = $campaign;
            }
        }

        if ($best === null || $bestRate <= 0) {
            return null;
        }

        return [
            'type' => MarketingInsightType::Engagement,
            'key' => 'campaign-engagement',
            'title' => 'Scale a proven campaign angle',
            'summary' => "Campaign \"{$best['name']}\" has {$bestRate}% engagement rate across its posts in {$period['label']}.",
            'recommendation' => 'Create a new brief tied to this campaign objective and run a small experiment with one variant. Define success metrics before publishing.',
            'evidence' => [
                "campaign: {$best['name']}",
                "engagement_rate: {$bestRate}%",
                'posts: '.($best['posts'] ?? 0),
                'deliveries: '.($best['deliveries'] ?? 0),
            ],
            'confidence' => MarketingInsightConfidence::Medium,
        ];
    }

    /**
     * @return array{days:int,start:CarbonImmutable,end:CarbonImmutable,label:string}
     */
    private function period(int $days): array
    {
        $days = in_array($days, [7, 30, 90], true) ? $days : 30;
        $start = CarbonImmutable::today()->subDays($days - 1)->startOfDay();

        return [
            'days' => $days,
            'start' => $start,
            'end' => CarbonImmutable::today()->endOfDay(),
            'label' => $start->format('M j').' - '.CarbonImmutable::today()->format('M j, Y'),
        ];
    }

    public function canCreateExperimentFrom(MarketingInsight $insight): bool
    {
        return in_array($insight->type, [MarketingInsightType::Content, MarketingInsightType::Engagement], true);
    }
}
