<?php

namespace Nreljed\Platform\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Nreljed\Platform\Services\DatabaseBackupService;

#[Signature('database:backup {--keep= : Override the retention count from config}')]
#[Description('Create a gzipped database dump and prune old backups to the retention window')]
class DatabaseBackup extends Command
{
    public function handle(DatabaseBackupService $service): int
    {
        if (! config('database_backup.enabled', true)) {
            $this->warn('Database backups are disabled (DB_BACKUP_ENABLED=false).');

            return self::SUCCESS;
        }

        $output = [];

        try {
            $backup = $service->createBackup($output);
        } catch (\Throwable $e) {
            $this->error('Backup failed: '.$e->getMessage());

            return self::FAILURE;
        }

        foreach ($output as $line) {
            $this->line("  $line");
        }

        $keep = $this->option('keep') !== null ? (int) $this->option('keep') : null;
        $pruned = $service->prune($keep);
        $kept = (int) ($keep ?? config('database_backup.keep', 7));

        $this->info(sprintf(
            'Backup written: %s (%s). %s old backup(s) pruned, keeping %d.',
            $backup['name'],
            $this->humanSize($backup['size']),
            $pruned,
            $kept,
        ));

        return self::SUCCESS;
    }

    private function humanSize(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 1).' MB';
        }

        if ($bytes >= 1024) {
            return round($bytes / 1024, 1).' KB';
        }

        return $bytes.' B';
    }
}
