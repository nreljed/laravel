<?php

namespace Nreljed\DashboardCore\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Nreljed\DashboardCore\Models\AuditLog;
use Nreljed\DashboardCore\Services\SettingsService;

#[Signature('app:prune-audit-logs {--days= : Override the retention days from settings}')]
#[Description('Delete audit log entries older than the configured retention period')]
class PruneAuditLogs extends Command
{
    public function handle(): void
    {
        $days = $this->option('days') !== null
            ? (int) $this->option('days')
            : (int) SettingsService::get('audit_logs.retention_days', 90);

        if ($days < 1) {
            $this->warn('Retention days must be at least 1. Skipping.');

            return;
        }

        $cutoff = now()->subDays($days);
        $deleted = AuditLog::where('created_at', '<', $cutoff)->delete();

        $this->info("Deleted {$deleted} audit log entr".($deleted === 1 ? 'y' : 'ies')." older than {$days} days.");
    }
}
