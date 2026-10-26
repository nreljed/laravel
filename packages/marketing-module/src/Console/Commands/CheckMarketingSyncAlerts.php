<?php

namespace Nreljed\MarketingModule\Console\Commands;

use Illuminate\Console\Command;
use Nreljed\MarketingModule\Enums\MetricSyncStatus;
use Nreljed\MarketingModule\Models\SocialPostTarget;

/**
 * A cheap daily digest of metric-collection problems.
 *
 * The detailed view is the sync-health page; this command exists so a failed
 * provider stays visible in the scheduler output even if nobody opens it.
 */
class CheckMarketingSyncAlerts extends Command
{
    protected $signature = 'marketing:check-sync-alerts';

    protected $description = 'Report deliveries whose metric sync failed, is unavailable, or was dead-lettered.';

    public function handle(): int
    {
        $errors = SocialPostTarget::query()
            ->where('metrics_status', MetricSyncStatus::Error->value)
            ->count();
        $unavailable = SocialPostTarget::query()
            ->where('metrics_status', MetricSyncStatus::Unavailable->value)
            ->count();
        $deadLetters = SocialPostTarget::query()
            ->where('metrics_status', MetricSyncStatus::DeadLetter->value)
            ->count();

        if ($errors > 0) {
            $this->warn("{$errors} delivery metric sync(s) failed and will be retried.");
        }

        if ($deadLetters > 0) {
            $this->error("{$deadLetters} delivery metric sync(s) are dead-lettered. Review or replay them from Marketing > Sync health.");
        }

        if ($unavailable > 0) {
            $this->info("{$unavailable} delivery metric sync(s) are unavailable.");
        }

        if ($errors === 0 && $unavailable === 0 && $deadLetters === 0) {
            $this->info('No metric sync alerts.');
        }

        return self::SUCCESS;
    }
}
