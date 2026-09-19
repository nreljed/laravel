<?php

namespace Nreljed\Platform\Tests;

use Nreljed\Platform\Services\DatabaseBackupService;
use PHPUnit\Framework\TestCase;

/**
 * Framework-free contract tests for the backup service's filesystem logic.
 *
 * Deliberately avoids booting the application: directory resolution, listing
 * and pruning only touch the filesystem, so they run inside the package's own
 * CI without a consuming app. The dump strategies themselves (mysqldump, PDO,
 * sqlite copy) are integration-tested inside consuming apps.
 */
final class DatabaseBackupServiceTest extends TestCase
{
    private DatabaseBackupService $service;

    private string $temp;

    protected function setUp(): void
    {
        $this->temp = sys_get_temp_dir().'/backup-service-'.uniqid();
        mkdir($this->temp, 0777, true);
        $this->service = new DatabaseBackupService($this->temp);
    }

    protected function tearDown(): void
    {
        $this->rrmdir($this->temp);
    }

    public function test_backup_directory_resolves_absolute_path_and_creates_it(): void
    {
        $nested = $this->temp.'/nested/dir';
        $service = new DatabaseBackupService($nested);

        $this->assertSame($nested, $service->backupDirectory());
        $this->assertDirectoryExists($nested);
    }

    public function test_latest_backup_returns_newest_blob(): void
    {
        file_put_contents($this->temp.'/backup-20260918-0100-mysql.gz', 'a');
        sleep(1);
        file_put_contents($this->temp.'/backup-20260918-0300-mysql.gz', 'b');

        $latest = $this->service->latestBackup();

        $this->assertNotNull($latest);
        $this->assertStringEndsWith('backup-20260918-0300-mysql.gz', $latest);
    }

    public function test_latest_backup_returns_null_when_empty(): void
    {
        $this->assertNull($this->service->latestBackup());
    }

    public function test_backups_ignores_unrelated_files(): void
    {
        file_put_contents($this->temp.'/backup-20260918-0100-mysql.gz', 'a');
        file_put_contents($this->temp.'/notes.txt', 'not a backup');
        file_put_contents($this->temp.'/backup.sql', 'raw dump');

        $backups = $this->service->backups();

        $this->assertCount(1, $backups);
        $this->assertSame('backup-20260918-0100-mysql.gz', basename($backups[0]['path']));
    }

    public function test_prune_keeps_newest_within_retention(): void
    {
        for ($i = 1; $i <= 10; $i++) {
            file_put_contents($this->temp.'/backup-2026091'.($i % 10).'-0'.($i % 10).'00-mysql.gz', 'x');
            sleep(1);
        }

        $pruned = $this->service->prune(3);

        $this->assertSame(7, $pruned);
        $this->assertCount(3, $this->service->backups());
    }

    public function test_prune_noop_when_count_within_retention(): void
    {
        file_put_contents($this->temp.'/backup-20260918-0100-mysql.gz', 'a');

        $this->assertSame(0, $this->service->prune(5));
        $this->assertCount(1, $this->service->backups());
    }

    private function rrmdir(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        $items = scandir($dir) ?: [];
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir.DIRECTORY_SEPARATOR.$item;
            is_dir($path) ? $this->rrmdir($path) : unlink($path);
        }
        rmdir($dir);
    }
}