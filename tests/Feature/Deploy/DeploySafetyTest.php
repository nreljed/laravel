<?php

namespace Tests\Feature\Deploy;

use Illuminate\Process\Factory;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Nreljed\Platform\Deploy\Services\DeployService;
use Tests\TestCase;

/**
 * Safe-install guarantees: a broken or malicious release must never be able to
 * leave the app dead (the shop outage was a vendor/ purge dying mid-extract).
 *
 * These tests exercise the atomic install path added in platform (installZipFile:
 * stage → boot-check → swap, with the previous vendor/ kept as backup). They are
 * skipped when the pin of the platform package predates the safe installer, so
 * CI stays green on older locks and activates once the vendor copy is updated.
 *
 * The swap runs against a sandboxed install root (deploy.install_root) — never
 * against the running app's real vendor/.
 */
class DeploySafetyTest extends TestCase
{
    private string $sandbox = '';

    private string $stagingDir = '';

    protected function setUp(): void
    {
        parent::setUp();

        if (! method_exists(DeployService::class, 'installZipFile')) {
            $this->markTestSkipped('Platform safe-install (installZipFile) not present in pinned vendor copy.');
        }

        $this->sandbox = storage_path('app/deploy-sandbox-'.getmypid().'-'.uniqid());
        $this->stagingDir = storage_path('app/deploy-staging-*');

        File::makeDirectory($this->sandbox.'/vendor', 0755, true);
        File::put($this->sandbox.'/vendor/autoload.php', "<?php\n // live vendor\n");
        File::put($this->sandbox.'/artisan', "<?php\n require __DIR__.'/vendor/autoload.php';\n");

        Config::set('deploy.install_root', $this->sandbox);
    }

    protected function tearDown(): void
    {
        if ($this->sandbox !== '') {
            File::deleteDirectory($this->sandbox);

            foreach (File::glob($this->stagingDir) as $stale) {
                File::deleteDirectory($stale);
            }

            Config::set('deploy.install_root', base_path());
        }

        parent::tearDown();
    }

    public function test_install_zip_rejects_garbage_archive_without_swapping_anything(): void
    {
        $zipPath = storage_path('app/test-garbage-'.uniqid().'.zip');
        File::put($zipPath, str_repeat('x', 2048));

        $output = [];
        $zipFiles = [];
        $service = app(DeployService::class);

        try {
            $result = $service->installZipFile($zipPath, $zipFiles, $output);

            $this->assertFalse($result);
            $this->assertContains('Failed to open ZIP', $output);
            $this->assertTrue(File::isDirectory($this->sandbox.'/vendor'));
            $this->assertTrue(File::exists($this->sandbox.'/vendor/autoload.php'));
            $this->assertEmpty(File::glob($this->stagingDir), 'Staging dir leaked after rejection.');
        } finally {
            File::delete($zipPath);
        }
    }

    public function test_install_zip_rejects_release_when_boot_check_fails_and_restores_previous_vendor(): void
    {
        $zipPath = $this->buildMinimalZip('with-broken-vendor');

        Process::fake([
            '*artisan --version*' => Process::result(errorOutput: 'boot check bombed', exitCode: 1),
        ]);

        $output = [];
        $zipFiles = [];
        $service = app(DeployService::class);

        try {
            $result = $service->installZipFile($zipPath, $zipFiles, $output);

            $this->assertFalse($result);
            $this->assertNotNull(
                collect($output)->first(fn (string $line) => str_contains($line, 'Boot check failed')),
                'Expected a boot-check failure line in output: '.implode(PHP_EOL, $output)
            );

            // The previous vendor/ must come back exactly as it was, alongside
            // the app files that stayed live.
            $this->assertTrue(File::isDirectory($this->sandbox.'/vendor'));
            $this->assertTrue(File::exists($this->sandbox.'/vendor/autoload.php'));
            $this->assertTrue(File::exists($this->sandbox.'/artisan'));

            $this->assertEmpty(File::glob($this->stagingDir), 'Staging dir leaked after rejection.');
        } finally {
            File::delete($zipPath);
        }
    }

    public function test_install_zip_swaps_staged_release_into_place_on_clean_boot_check(): void
    {
        $zipPath = $this->buildMinimalZip('clean-release', includeMarker: 'deployed-marker.txt');

        Process::fake([
            '*artisan --version*' => Process::result(output: 'Laravel Framework 13.0.0', exitCode: 0),
        ]);

        $output = [];
        $zipFiles = [];
        $service = app(DeployService::class);

        try {
            $result = $service->installZipFile($zipPath, $zipFiles, $output);

            $this->assertTrue($result, implode(PHP_EOL, $output));
            $this->assertTrue(File::exists($this->sandbox.'/deployed-marker.txt'), 'Release files were not swapped in.');
            $this->assertTrue(File::exists($this->sandbox.'/vendor/autoload.php'), 'Staged vendor was not installed.');
            $this->assertSame("<?php\n // minimal staged autoload\n", File::get($this->sandbox.'/vendor/autoload.php'));

            $this->assertEmpty(File::glob($this->stagingDir), 'Staging dir leaked after success.');
            $this->assertEmpty(File::glob($this->stagingDir.'-vendor-backup'), 'Vendor backup survived a success.');
        } finally {
            File::delete($zipPath);
        }
    }

    public function test_install_zip_heals_stranded_vendor_backup_from_killed_previous_run(): void
    {
        $backupDir = storage_path('app/deploy-staging-'.uniqid().'-vendor-backup');
        File::makeDirectory($backupDir, 0755, true);
        File::put($backupDir.'/autoload.php', "<?php\n // stranded backup\n");
        File::deleteDirectory($this->sandbox.'/vendor');

        $output = [];
        $zipFiles = [];
        $service = app(DeployService::class);

        try {
            $result = $service->installZipFile(storage_path('app/does-not-exist-'.uniqid().'.zip'), $zipFiles, $output);

            $this->assertFalse($result);
            $this->assertContains('Interrupted install healed; previous version restored', $output);
            $this->assertTrue(File::isDirectory($this->sandbox.'/vendor'));
            $this->assertTrue(File::exists($this->sandbox.'/vendor/autoload.php'));
            $this->assertSame("<?php\n // stranded backup\n", File::get($this->sandbox.'/vendor/autoload.php'));
            $this->assertEmpty(File::glob(storage_path('app/deploy-staging-*-vendor-backup')), 'Stranded backup not cleaned up after heal.');
        } finally {
            File::deleteDirectory($backupDir);
        }
    }

    public function test_deploy_auto_reports_error_and_keeps_running_when_archive_is_invalid(): void
    {
        $lockPath = storage_path('app/.deploy-lock-'.getmypid().'-'.uniqid());
        $versionPath = storage_path('app/.deployed-version-'.getmypid().'-'.uniqid());
        Config::set('deploy.lock_path', $lockPath);
        Config::set('deploy.version_path', $versionPath);

        Http::fake([
            'api.github.com/repos/*/releases*' => Http::response([
                [
                    'tag_name' => 'v9.9.9',
                    'assets' => [
                        ['name' => 'deploy.zip', 'url' => 'https://api.github.com/repos/nreljed/laravel/assets/987'],
                    ],
                ],
            ]),
            'api.github.com/repos/*/assets/*' => Http::response(str_repeat('y', 2048), 200),
        ]);

        try {
            $this->artisan('deploy:auto')
                ->expectsOutputToContain('Deploy failed: Install failed; previous version kept')
                ->assertExitCode(1);

            $this->assertTrue(File::isDirectory($this->sandbox.'/vendor'));
            $this->assertTrue(File::exists($this->sandbox.'/vendor/autoload.php'));
        } finally {
            File::delete($lockPath);
            File::delete($versionPath);
            Config::set('deploy.lock_path', base_path('.deploy-lock'));
            Config::set('deploy.version_path', base_path('.deployed-version'));
        }
    }

    public function test_install_zip_falls_back_to_shell_when_proc_open_is_disabled(): void
    {
        if (! str_contains(
            (string) File::get(base_path('vendor/nreljed/platform/src/Deploy/Services/DeployService.php')),
            'proc_open'
        )) {
            $this->markTestSkipped('bootCheck proc_open fallback not present in pinned vendor copy.');
        }

        // Simulate the host disabling proc_open: swap the Process factory (the
        // facade resolves Factory::class) with a partial mock whose pending
        // process throws the exact LogicException Symfony raises when proc_open
        // is unavailable. core bootCheck must catch it and fall back.
        $pending = \Mockery::mock(PendingProcess::class)->makePartial();
        $pending->shouldReceive('run')->andThrow(
            new \LogicException('The Process class relies on proc_open, which is not available on your PHP installation.')
        );
        $factory = \Mockery::mock(Factory::class)->makePartial();
        $factory->shouldReceive('newPendingProcess')->andReturn($pending);
        Process::swap($factory);

        $zipPath = $this->buildMinimalZip('no-proc-open', includeMarker: 'deployed-marker.txt');

        $output = [];
        $zipFiles = [];
        $service = app(DeployService::class);

        try {
            $result = $service->installZipFile($zipPath, $zipFiles, $output);

            $this->assertTrue($result, implode(PHP_EOL, $output));
            $this->assertTrue(File::exists($this->sandbox.'/deployed-marker.txt'), 'Release files were not swapped in.');
            $this->assertTrue(File::exists($this->sandbox.'/vendor/autoload.php'), 'Staged vendor was not installed.');

            $bootLines = collect($output)->filter(fn (string $line) => str_contains($line, 'Boot check passed'));
            $this->assertGreaterThan(0, $bootLines->count(), 'Expected a fallback boot-check line: '.implode(PHP_EOL, $output));
        } finally {
            File::delete($zipPath);
        }
    }

    /**
     * Build a tiny deploy-style zip (vendor/autoload.php + artisan anchor) so
     * the staged-tree validation has something real to look at.
     */
    private function buildMinimalZip(string $suffix, string $includeMarker = ''): string
    {
        $zipPath = storage_path('app/test-deploy-'.$suffix.'-'.uniqid().'.zip');
        $zip = new \ZipArchive;

        $this->assertTrue($zip->open($zipPath, \ZipArchive::CREATE) === true, 'Could not create test zip.');

        $zip->addFromString('artisan', "<?php\n require __DIR__.'/vendor/autoload.php';\n");
        $zip->addFromString('vendor/autoload.php', "<?php\n // minimal staged autoload\n");
        $zip->addFromString('README.md', "test release: $suffix\n");

        // Structural boot-check skeleton (used when the host blocks proc_open).
        foreach ([
            'bootstrap/app.php' => "<?php\n // app\n",
            'bootstrap/providers.php' => "<?php\n return [];\n",
            'public/index.php' => "<?php\n require __DIR__.'/../vendor/autoload.php';\n",
            'config/app.php' => "<?php\n return [];\n",
            'routes/web.php' => "<?php\n\n",
        ] as $path => $contents) {
            $zip->addFromString($path, $contents);
        }

        if ($includeMarker !== '') {
            $zip->addFromString($includeMarker, date('c')."\n");
        }

        $zip->close();

        return $zipPath;
    }
}
