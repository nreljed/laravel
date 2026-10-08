<?php

namespace Tests\Feature\Deploy;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Tests\TestCase;

class DatabaseBackupCommandTest extends TestCase
{
    protected string $tmpSqlite;

    protected string $backupDir;

    protected function setUp(): void
    {
        $this->backupDir = sys_get_temp_dir().'/db-backup-cmd-test-'.getmypid().'-'.Str::random(6);
        $this->tmpSqlite = sys_get_temp_dir().'/db-backup-cmd-test-'.getmypid().'-'.Str::random(6).'.sqlite';

        putenv('DB_CONNECTION=sqlite');
        putenv('DB_DATABASE='.$this->tmpSqlite);
        $_ENV['DB_CONNECTION'] = 'sqlite';
        $_ENV['DB_DATABASE'] = $this->tmpSqlite;
        $_SERVER['DB_CONNECTION'] = 'sqlite';
        $_SERVER['DB_DATABASE'] = $this->tmpSqlite;

        touch($this->tmpSqlite);

        parent::setUp();

        // env() reads $_SERVER first under the app's Dotenv repository, so the
        // raw superglobals alone are not enough once a worker has booted with
        // phpunit.xml's :memory: — pin the resolved connection explicitly.
        config()->set('database.connections.sqlite.database', $this->tmpSqlite);
        DB::purge('sqlite');

        Artisan::call('migrate:fresh', ['--no-interaction' => true]);

        Config::set('database_backup.dir', $this->backupDir);
        Config::set('database_backup.keep', 3);
    }

    protected function tearDown(): void
    {
        if (File::exists($this->tmpSqlite)) {
            @unlink($this->tmpSqlite);
        }

        File::deleteDirectory($this->backupDir);

        putenv('DB_CONNECTION=sqlite');
        putenv('DB_DATABASE=:memory:');
        $_ENV['DB_CONNECTION'] = 'sqlite';
        $_ENV['DB_DATABASE'] = ':memory:';
        $_SERVER['DB_CONNECTION'] = 'sqlite';
        $_SERVER['DB_DATABASE'] = ':memory:';

        parent::tearDown();
    }

    public function test_command_creates_backup_file(): void
    {
        $exitCode = Artisan::call('database:backup');

        $this->assertSame(0, $exitCode);

        $backups = glob($this->backupDir.'/backup-*.gz');
        $this->assertNotEmpty($backups, 'Expected at least one .gz backup file after running database:backup.');

        $size = filesize($backups[0]);
        $this->assertGreaterThan(0, $size, 'Backup file should not be empty.');
    }

    public function test_command_prunes_old_backups(): void
    {
        File::ensureDirectoryExists($this->backupDir, 0775, true);

        for ($i = 1; $i <= 5; $i++) {
            $ts = new \DateTimeImmutable('-'.($i * 10).' minutes')->format('Ymd-His');
            $fake = $this->backupDir."/backup-{$ts}-sqlite.gz";
            file_put_contents($fake, gzencode("old-backup-{$i}"));
            touch($fake, now()->subMinutes($i * 10)->getTimestamp());
        }

        $exitCode = Artisan::call('database:backup');

        $this->assertSame(0, $exitCode);

        $backups = glob($this->backupDir.'/backup-*.gz');
        $this->assertLessThanOrEqual(4, count($backups), 'Retention should be at most keep+1 (old + new).');
    }

    public function test_command_creates_backup_dir_if_missing(): void
    {
        File::deleteDirectory($this->backupDir);
        $this->assertDirectoryDoesNotExist($this->backupDir);

        $exitCode = Artisan::call('database:backup');

        $this->assertSame(0, $exitCode);
        $this->assertDirectoryExists($this->backupDir);

        $backups = glob($this->backupDir.'/backup-*.gz');
        $this->assertNotEmpty($backups);
    }
}
