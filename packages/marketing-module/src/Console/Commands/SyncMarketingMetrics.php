<?php

namespace Nreljed\MarketingModule\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Nreljed\MarketingModule\Services\SocialMetricSync;

/**
 * The scheduler's entry point into metric collection.
 *
 * The module ships with collection disabled until a consumer has verified its
 * provider scopes. `--dry-run` still reports the selection when disabled.
 */
class SyncMarketingMetrics extends Command
{
    protected $signature = 'marketing:sync-metrics
        {--days= : Only sync deliveries published within this window}
        {--limit= : Maximum deliveries to process in this run}
        {--platform= : Restrict to one platform key}
        {--force : Ignore the normal freshness window}
        {--dry-run : Report how many deliveries would be synced}';

    protected $description = 'Collect engagement metrics for published marketing deliveries.';

    public function handle(SocialMetricSync $sync): int
    {
        if (! Schema::hasTable('social_post_metrics') || ! Schema::hasTable('social_post_targets')) {
            $this->warn('Marketing metric tables are not migrated yet; nothing to sync.');

            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');

        if (! (bool) config('marketing.metrics.enabled', false) && ! $dryRun) {
            $this->warn('Marketing metric collection is disabled. Set MARKETING_METRICS_ENABLED=true to enable it.');

            return self::SUCCESS;
        }

        $summary = $sync->sync(
            days: $this->intOption('days'),
            limit: $this->intOption('limit'),
            platform: $this->stringOption('platform'),
            force: (bool) $this->option('force'),
            dryRun: $dryRun,
        );

        if ($summary['dry_run']) {
            $this->info("{$summary['selected']} delivery(s) would be synced.");

            return self::SUCCESS;
        }

        $this->info(sprintf(
            'Processed %d of %d delivery(s): %d synced, %d unavailable, %d errored.',
            $summary['processed'],
            $summary['selected'],
            $summary['ok'],
            $summary['unavailable'],
            $summary['errors'],
        ));

        if (($summary['skipped'] ?? 0) > 0) {
            $this->line("{$summary['skipped']} delivery(s) skipped after their platform's per-run cap.");
        }

        if (($summary['budget_exhausted'] ?? 0) > 0) {
            $this->warn("{$summary['budget_exhausted']} delivery(s) deferred: the platform's daily request budget is exhausted.");
        }

        return self::SUCCESS;
    }

    protected function intOption(string $name): ?int
    {
        $value = $this->option($name);

        return is_numeric($value) ? (int) $value : null;
    }

    protected function stringOption(string $name): ?string
    {
        $value = $this->option($name);

        return is_string($value) && $value !== '' ? $value : null;
    }
}
