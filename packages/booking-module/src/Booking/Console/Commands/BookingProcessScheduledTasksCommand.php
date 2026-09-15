<?php

namespace Nreljed\BookingModule\Booking\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Nreljed\BookingModule\Booking\Services\BookingEmailService;
use Nreljed\BookingModule\Booking\Services\BookingStatusManager;
use Nreljed\Platform\Services\SettingsService;

class BookingProcessScheduledTasksCommand extends Command
{
    protected $signature = 'bookings:process-scheduled-tasks';

    protected $description = 'Process booking auto status transitions and scheduled guest emails';

    public function handle(BookingStatusManager $statusManager): int
    {
        $outcome = ['transitions' => [], 'emails' => []];

        try {
            $outcome['transitions'] = $statusManager->processAutoTransitions();
        } catch (\Throwable $e) {
            Log::error('bookings:process-scheduled-tasks failed processing transitions: '.$e->getMessage(), [
                'exception' => $e,
            ]);
        }

        try {
            $outcome['emails'] = BookingEmailService::processScheduledEmails();
        } catch (\Throwable $e) {
            Log::error('bookings:process-scheduled-tasks failed processing emails: '.$e->getMessage(), [
                'exception' => $e,
            ]);
        }

        try {
            SettingsService::set('booking_cron_last_run', now()->toDateTimeString());
        } catch (\Throwable $e) {
            Log::error('bookings:process-scheduled-tasks failed persisting cron marker: '.$e->getMessage());
        }

        $this->info(json_encode([
            'transitions' => $outcome['transitions'],
            'emails' => $outcome['emails'],
        ]));

        return self::SUCCESS;
    }
}
