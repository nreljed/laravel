<?php

namespace Nreljed\ShopModule\Commands;

use Illuminate\Console\Command;
use Nreljed\ShopModule\Services\WebhookService;

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
