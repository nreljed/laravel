<?php

namespace Nreljed\Platform\Tests;

use Nreljed\Platform\Deploy\Services\DeployService;
use Nreljed\Platform\Services\CoreVersionService;
use PHPUnit\Framework\TestCase;

/**
 * Contract test for the .env backup retention cap.
 *
 * Framework-free on purpose: pruneEnvBackups() only resolves filesystem
 * globs/unlinks, so it can be asserted without booting the application. The
 * cap exists so the /deploy/env endpoints cannot accumulate `.env.backup.*`
 * snapshots without bound on busy CI pipelines.
 */
final class EnvBackupRetentionTest extends TestCase
{
    private DeployService $deployService;

    private string $temp;

    protected function setUp(): void
    {
        $this->deployService = new DeployService(new CoreVersionService);
        $this->temp = sys_get_temp_dir().'/env-backup-'.uniqid();
        mkdir($this->temp, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->rrmdir($this->temp);
    }

    public function test_keeps_newest_retention_backups(): void
    {
        $env = $this->temp.'/.env';
        file_put_contents($env, 'APP_KEY=base64:test');

        foreach ([100, 200, 300, 400, 500, 600, 700] as $ts) {
            file_put_contents($env.'.backup.'.$ts, "old-$ts");
        }

        $pruned = $this->deployService->pruneEnvBackups($env, retention: 3);

        $this->assertSame(4, $pruned);
        $this->assertCount(3, glob($env.'.backup.*') ?: []);
        $this->assertFileExists($env.'.backup.500');
        $this->assertFileExists($env.'.backup.600');
        $this->assertFileExists($env.'.backup.700');
        $this->assertFileDoesNotExist($env.'.backup.100');
        $this->assertFileDoesNotExist($env.'.backup.400');
    }

    public function test_retention_never_drops_below_one(): void
    {
        $env = $this->temp.'/.env';
        file_put_contents($env, 'APP_KEY=base64:test');

        foreach ([100, 200] as $ts) {
            file_put_contents($env.'.backup.'.$ts, "old-$ts");
        }

        $pruned = $this->deployService->pruneEnvBackups($env, retention: 0);

        $this->assertSame(1, $pruned);
        $this->assertCount(1, glob($env.'.backup.*') ?: []);
    }

    public function test_no_backups_means_nothing_to_prune(): void
    {
        $env = $this->temp.'/.env';
        file_put_contents($env, 'APP_KEY=base64:test');

        $pruned = $this->deployService->pruneEnvBackups($env, retention: 5);

        $this->assertSame(0, $pruned);
    }

    public function test_no_env_file_means_nothing_to_prune(): void
    {
        $pruned = $this->deployService->pruneEnvBackups($this->temp.'/.env', retention: 5);

        $this->assertSame(0, $pruned);
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