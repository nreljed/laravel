<?php

namespace Nreljed\MarketingModule\Console\Commands;

use Illuminate\Console\Command;
use Nreljed\MarketingModule\Services\WorkflowEngine;

class ProcessWorkflows extends Command
{
    protected $signature = 'marketing:process-workflows';

    protected $description = 'Process delayed workflow steps that are due.';

    public function handle(WorkflowEngine $engine): int
    {
        $processed = $engine->processPendingRuns();

        $this->info("Processed {$processed} workflow step(s).");

        return self::SUCCESS;
    }
}
