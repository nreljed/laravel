<?php

namespace Nreljed\MarketingModule\Services;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Nreljed\MarketingModule\Enums\WorkflowRunStatus;
use Nreljed\MarketingModule\Models\MarketingLinkClick;
use Nreljed\MarketingModule\Models\SocialPostMetric;
use Nreljed\MarketingModule\Models\WorkflowRun;

/**
 * Applies the module's retention windows.
 *
 * Three tables grow without bound: daily metric snapshots, tracked-link click
 * events and workflow runs. Each has its own window in config so an operator
 * can keep the automation audit trail longer than the high-volume click table.
 *
 * Only terminal workflow runs are eligible: a `pending` run is unfinished
 * work, not history.
 */
class MarketingRetention
{
    /**
     * How many rows each window would remove right now.
     *
     * @return array{
     *     metrics: int,
     *     clicks: int,
     *     workflow_runs: int,
     *     cutoffs: array{metrics: string, clicks: string, workflow_runs: string}
     * }
     */
    public function plan(?CarbonInterface $now = null): array
    {
        $now = CarbonImmutable::instance($now ?? CarbonImmutable::now());

        $metricCutoff = $this->metricCutoff($now);
        $clickCutoff = $this->clickCutoff($now);
        $runCutoff = $this->runCutoff($now);

        return [
            'metrics' => SocialPostMetric::query()
                ->where('metric_date', '<', $metricCutoff->toDateString())
                ->count(),
            'clicks' => MarketingLinkClick::query()
                ->where('clicked_at', '<', $clickCutoff)
                ->count(),
            'workflow_runs' => $this->terminalRuns($runCutoff)->count(),
            'cutoffs' => [
                'metrics' => $metricCutoff->toIso8601String(),
                'clicks' => $clickCutoff->toIso8601String(),
                'workflow_runs' => $runCutoff->toIso8601String(),
            ],
        ];
    }

    /**
     * Delete everything past its window, or report what would go.
     *
     * @return array{
     *     metrics: int,
     *     clicks: int,
     *     workflow_runs: int,
     *     cutoffs: array{metrics: string, clicks: string, workflow_runs: string},
     *     dry_run: bool
     * }
     */
    public function prune(bool $dryRun = false, ?CarbonInterface $now = null): array
    {
        $now = CarbonImmutable::instance($now ?? CarbonImmutable::now());
        $plan = $this->plan($now);
        $plan['dry_run'] = $dryRun;

        if ($dryRun) {
            return $plan;
        }

        $metricCutoff = $this->metricCutoff($now)->toDateString();
        $clickCutoff = $this->clickCutoff($now);
        $runCutoff = $this->runCutoff($now);

        DB::transaction(function () use ($metricCutoff, $clickCutoff, $runCutoff): void {
            SocialPostMetric::query()->where('metric_date', '<', $metricCutoff)->delete();
            MarketingLinkClick::query()->where('clicked_at', '<', $clickCutoff)->delete();
            $this->terminalRuns($runCutoff)->delete();
        });

        return $plan;
    }

    protected function terminalRuns(CarbonInterface $cutoff): Builder
    {
        return WorkflowRun::query()
            ->whereIn('status', [
                WorkflowRunStatus::Success->value,
                WorkflowRunStatus::Failed->value,
                WorkflowRunStatus::Skipped->value,
            ])
            ->where('ran_at', '<', $cutoff);
    }

    protected function metricCutoff(CarbonInterface $now): CarbonImmutable
    {
        $days = max(1, (int) config('marketing.metrics.retention_days', 365));

        return CarbonImmutable::instance($now)->startOfDay()->subDays($days);
    }

    protected function clickCutoff(CarbonInterface $now): CarbonImmutable
    {
        $days = max(1, (int) config('marketing.links.retention_days', 365));

        return CarbonImmutable::instance($now)->subDays($days);
    }

    protected function runCutoff(CarbonInterface $now): CarbonImmutable
    {
        $days = max(1, (int) config('marketing.workflows.retention_days', 180));

        return CarbonImmutable::instance($now)->subDays($days);
    }
}
