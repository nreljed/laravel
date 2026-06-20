<?php

namespace App\Console\Commands;

use App\Services\NotificationService;
use Illuminate\Console\Command;

class PurgeNotifications extends Command
{
    protected $signature = 'notifications:purge {--days=90 : Number of days to retain}';

    protected $description = 'Purge notifications older than the specified retention period';

    public function handle(NotificationService $notificationService): int
    {
        $days = (int) $this->option('days');

        $deleted = $notificationService->purge($days);

        $this->info("Purged {$deleted} notifications older than {$days} days.");

        return Command::SUCCESS;
    }
}
