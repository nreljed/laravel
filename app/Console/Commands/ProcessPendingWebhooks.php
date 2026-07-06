<?php

namespace App\Console\Commands;

use App\Modules\Shop\Services\WebhookService;
use Illuminate\Console\Command;

class ProcessPendingWebhooks extends Command
{
    protected $signature = 'webhooks:process';

    protected $description = 'Process pending webhook deliveries';

    public function handle(WebhookService $webhookService): int
    {
        $processed = $webhookService->processPending();

        $this->info("Processed {$processed} pending webhook(s).");

        return self::SUCCESS;
    }
}
