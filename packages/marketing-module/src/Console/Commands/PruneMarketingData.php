<?php

namespace Nreljed\MarketingModule\Console\Commands;

use Illuminate\Console\Command;
use Nreljed\MarketingModule\Services\MarketingRetention;

class PruneMarketingData extends Command
{
    protected $signature = 'marketing:prune {--dry-run : Report counts without deleting}';

    protected $description = 'Prune old metric snapshots, tracked-link clicks and finished workflow runs according to retention settings.';

    public function handle(MarketingRetention $retention): int
    {
        $result = $retention->prune((bool) $this->option('dry-run'));

        $this->info(sprintf(
            '%s %d metric snapshots, %d link clicks and %d workflow runs.',
            $result['dry_run'] ? 'Would prune' : 'Pruned',
            $result['metrics'],
            $result['clicks'],
            $result['workflow_runs'],
        ));

        return self::SUCCESS;
    }
}
