<?php

namespace Nreljed\MarketingModule\Services;

use Carbon\CarbonImmutable;
use Nreljed\MarketingModule\Enums\DeliveryStatus;
use Nreljed\MarketingModule\Enums\PostStatus;
use Nreljed\MarketingModule\Enums\WorkflowRunStatus;
use Nreljed\MarketingModule\Models\Campaign;
use Nreljed\MarketingModule\Models\Contact;
use Nreljed\MarketingModule\Models\SocialPost;
use Nreljed\MarketingModule\Models\SocialPostTarget;
use Nreljed\MarketingModule\Models\Workflow;
use Nreljed\MarketingModule\Models\WorkflowRun;

/**
 * Read-only reporting for the marketing dashboard.
 *
 * The numbers describe the engine's own records - what was scheduled, what
 * each platform returned, which campaigns those deliveries belonged to - and
 * deliberately stop there. Attribution to revenue belongs to the shop.
 */
class MarketingAnalytics
{
    /**
     * @return array<string, mixed>
     */
    public function overview(int $days = 14): array
    {
        return [
            'summary' => $this->summary(),
            'platforms' => $this->platforms(),
            'series' => $this->series($days),
            'campaigns' => $this->campaigns(),
            'workflows' => $this->workflows(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function summary(): array
    {
        /** @var object|null $posts */
        $posts = SocialPost::query()
            ->selectRaw(
                'COUNT(*) as total,
                 SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as scheduled,
                 SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as published,
                 SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as partial,
                 SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as failed',
                [
                    PostStatus::Scheduled->value,
                    PostStatus::Published->value,
                    PostStatus::Partial->value,
                    PostStatus::Failed->value,
                ],
            )
            ->first();

        /** @var object|null $deliveries */
        $deliveries = SocialPostTarget::query()
            ->selectRaw(
                'COUNT(*) as total,
                 SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as delivered,
                 SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as failed',
                [DeliveryStatus::Published->value, DeliveryStatus::Failed->value],
            )
            ->first();

        $total = (int) ($deliveries->total ?? 0);
        $delivered = (int) ($deliveries->delivered ?? 0);
        $failed = (int) ($deliveries->failed ?? 0);
        $attempted = $delivered + $failed;

        return [
            'posts' => (int) ($posts->total ?? 0),
            'scheduled' => (int) ($posts->scheduled ?? 0),
            'published' => (int) ($posts->published ?? 0),
            'partial' => (int) ($posts->partial ?? 0),
            'failed' => (int) ($posts->failed ?? 0),
            'deliveries' => $total,
            'delivered' => $delivered,
            'delivery_failures' => $failed,
            'success_rate' => $attempted > 0 ? round(($delivered / $attempted) * 100, 1) : 0.0,
            'contacts' => Contact::query()->count(),
            'campaigns' => Campaign::query()->count(),
            'active_workflows' => Workflow::query()->where('is_active', true)->count(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function platforms(): array
    {
        return SocialPostTarget::query()
            ->join('social_accounts', 'social_accounts.id', '=', 'social_post_targets.social_account_id')
            ->selectRaw(
                'social_accounts.platform as platform,
                 COUNT(*) as total,
                 SUM(CASE WHEN social_post_targets.status = ? THEN 1 ELSE 0 END) as delivered,
                 SUM(CASE WHEN social_post_targets.status = ? THEN 1 ELSE 0 END) as failed',
                [DeliveryStatus::Published->value, DeliveryStatus::Failed->value],
            )
            ->groupBy('social_accounts.platform')
            ->orderByDesc('total')
            ->get()
            ->map(static fn (object $row): array => [
                'platform' => (string) $row->platform,
                'label' => ucfirst((string) $row->platform),
                'total' => (int) $row->total,
                'delivered' => (int) $row->delivered,
                'failed' => (int) $row->failed,
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{date: string, count: int}>
     */
    public function series(int $days = 14): array
    {
        $days = max(1, $days);
        $start = CarbonImmutable::today()->subDays($days - 1);

        $counts = SocialPostTarget::query()
            ->where('status', DeliveryStatus::Published->value)
            ->where('published_at', '>=', $start->startOfDay())
            ->get(['published_at'])
            ->groupBy(static fn (SocialPostTarget $target): string => $target->published_at?->toDateString() ?? '')
            ->map(static fn ($group): int => $group->count());

        $series = [];

        for ($offset = 0; $offset < $days; $offset++) {
            $date = $start->addDays($offset);

            $series[] = [
                'date' => $date->format('M j'),
                'count' => (int) ($counts[$date->toDateString()] ?? 0),
            ];
        }

        return $series;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function campaigns(int $limit = 10): array
    {
        return Campaign::query()
            ->with('posts.targets')
            ->latest('id')
            ->limit($limit)
            ->get()
            ->map(static function (Campaign $campaign): array {
                $targets = $campaign->posts->flatMap->targets;

                $deliveries = $targets->count();
                $delivered = $targets->filter->isPublished()->count();
                $failed = $targets->filter(
                    static fn (SocialPostTarget $target): bool => $target->status === DeliveryStatus::Failed,
                )->count();

                return [
                    'id' => $campaign->id,
                    'name' => $campaign->name,
                    'status' => $campaign->status?->value,
                    'status_label' => $campaign->status?->label(),
                    'objective' => $campaign->objective?->value,
                    'objective_label' => $campaign->objective?->label(),
                    'posts' => $campaign->posts->count(),
                    'scheduled' => $campaign->posts->filter(
                        static fn (SocialPost $post): bool => $post->status === PostStatus::Scheduled,
                    )->count(),
                    'deliveries' => $deliveries,
                    'delivered' => $delivered,
                    'failed' => $failed,
                    'delivery_rate' => $deliveries > 0 ? round(($delivered / $deliveries) * 100, 1) : 0.0,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function workflows(int $days = 30): array
    {
        $start = CarbonImmutable::today()->subDays($days - 1);

        return [
            'active' => Workflow::query()->where('is_active', true)->count(),
            'total' => Workflow::query()->count(),
            'runs' => WorkflowRun::query()->where('ran_at', '>=', $start)->count(),
            'failures' => WorkflowRun::query()
                ->where('ran_at', '>=', $start)
                ->where('status', WorkflowRunStatus::Failed->value)
                ->count(),
            'recent' => WorkflowRun::query()
                ->with('workflow')
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
}
