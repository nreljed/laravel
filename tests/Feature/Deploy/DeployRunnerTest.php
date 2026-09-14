<?php

namespace Tests\Feature\Deploy;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Standalone deploy-runner control plane (scripts/deploy-runner.php).
 *
 * Exercises the zero-framework functions that heal a killed install and run
 * the safe-swap, without going through HTTP. The runner must work when the app
 * itself is dead — so these tests hit the pure helpers directly.
 */
class DeployRunnerTest extends TestCase
{
    private string $sandbox = '';

    protected function setUp(): void
    {
        parent::setUp();

        $this->sandbox = storage_path('app/runner-sandbox-'.getmypid().'-'.uniqid());
        File::makeDirectory($this->sandbox, 0755, true);

        require_once base_path('scripts/deploy-runner.php');
    }

    protected function tearDown(): void
    {
        if ($this->sandbox !== '') {
            File::deleteDirectory($this->sandbox);
        }

        parent::tearDown();
    }

    /**
     * Directory symlinks are the core primitive of the release model. They are
     * unavailable on Windows PHP without admin/Developer Mode, so tests that
     * exercise symlink creation skip there and run fully on Linux (production).
     */
    protected function requiresSymlinkCapability(): void
    {
        static $works = null;

        if ($works === null) {
            $probe = $this->sandbox.'/symlink-probe';
            File::makeDirectory($probe.'/target', 0755, true);
            $link = $probe.'/link';

            $works = @symlink($probe.'/target', $link) && is_link($link);
        }

        if (! $works) {
            $this->markTestSkipped('Directory symlinks unavailable on this environment.');
        }
    }

    public function test_env_get_parses_quoted_and_unquoted_values(): void
    {
        $env = $this->sandbox.'/.env';
        File::put($env, "DEPLOY_TOKEN='abc-123'\nGITHUB_TOKEN=\"xyz\"\nEMPTY=\n# comment\n");

        $this->assertSame('abc-123', \rd_env_get($env, 'DEPLOY_TOKEN'));
        $this->assertSame('xyz', \rd_env_get($env, 'GITHUB_TOKEN'));
        $this->assertSame('', \rd_env_get($env, 'EMPTY'));
        $this->assertSame('', \rd_env_get($env, 'MISSING'));
    }

    public function test_rrmdir_never_follows_directory_symlinks(): void
    {
        $this->requiresSymlinkCapability();

        $base = $this->sandbox;
        File::makeDirectory($base.'/target', 0755, true);
        File::put($base.'/target/precious.txt', 'keep me');

        File::makeDirectory($base.'/victim', 0755, true);
        symlink($base.'/target', $base.'/victim/storage');
        File::put($base.'/victim/remove-me.txt', 'bye');

        $this->assertTrue(\rd_rrmdir($base.'/victim'));
        $this->assertFalse(File::isDirectory($base.'/victim'), 'victim dir fully removed');
        $this->assertTrue(File::isFile($base.'/target/precious.txt'), 'symlink target contents untouched');
    }

    public function test_list_releases_orders_by_embedded_timestamp_not_mtime(): void
    {
        $rel = $this->sandbox.'/releases';
        foreach (['2026-09-14T17-57-39Z-v0.26.7', '2026-09-13T20-41-41Z-v0.26.2', '2026-09-14T18-00-00Z-v0.26.8'] as $dir) {
            File::makeDirectory($rel.'/'.$dir, 0755, true);
        }

        // Newest release is touched LAST in prod (finalize rename), so set the
        // latest-embedded one old — the sort must use the name, not mtime.
        touch($rel.'/2026-09-14T18-00-00Z-v0.26.8', time() - 3600);
        touch($rel.'/2026-09-13T20-41-41Z-v0.26.2', time());
        touch($rel.'/2026-09-14T17-57-39Z-v0.26.7', time() - 1800);

        $list = \rd_list_releases($this->sandbox);

        $this->assertSame([
            '2026-09-14T18-00-00Z-v0.26.8',
            '2026-09-14T17-57-39Z-v0.26.7',
            '2026-09-13T20-41-41Z-v0.26.2',
        ], $list);
    }

    public function test_authorize_requires_exact_token(): void
    {
        $topo = ['root' => $this->sandbox, 'home' => $this->sandbox];
        File::put($this->sandbox.'/.env', "DEPLOY_TOKEN='secret-token'\n");

        $this->assertTrue(\rd_authorize($topo, 'secret-token'));
        $this->assertFalse(\rd_authorize($topo, 'wrong-token'));
        $this->assertFalse(\rd_authorize($topo, ''));
    }

    public function test_authorize_rejects_when_no_token_in_env(): void
    {
        $topo = ['root' => $this->sandbox, 'home' => $this->sandbox];
        File::put($this->sandbox.'/.env', "APP_ENV=local\n");

        $this->assertFalse(\rd_authorize($topo, 'anything'));
    }

    public function test_find_stranded_backup_locates_newest_backup(): void
    {
        $staging = $this->sandbox.'/storage/app';
        File::makeDirectory($staging, 0755, true);
        File::makeDirectory($staging.'/deploy-staging-old-vendor-backup', 0755, true);
        File::makeDirectory($staging.'/deploy-staging-newer-vendor-backup', 0755, true);
        File::put($staging.'/deploy-staging-newer-vendor-backup/marker', 'x');
        touch($staging.'/deploy-staging-old-vendor-backup', time() - 60);
        touch($staging.'/deploy-staging-newer-vendor-backup', time());

        $found = \rd_find_stranded_backup($this->sandbox);

        $this->assertStringEndsWith('deploy-staging-newer-vendor-backup', $found);
    }

    public function test_find_stranded_backup_returns_empty_when_none(): void
    {
        $this->assertSame('', \rd_find_stranded_backup($this->sandbox));
    }

    public function test_restore_stranded_backup_heals_missing_vendor(): void
    {
        $staging = $this->sandbox.'/storage/app';
        File::makeDirectory($staging.'/deploy-staging-x-vendor-backup', 0755, true);
        File::put($staging.'/deploy-staging-x-vendor-backup/autoload.php', "<?php\n // stranded\n");

        $output = [];
        $result = \rd_restore_stranded_backup($this->sandbox, $output);

        $this->assertTrue($result['restored']);
        $this->assertTrue(File::exists($this->sandbox.'/vendor/autoload.php'));
        $this->assertSame("<?php\n // stranded\n", File::get($this->sandbox.'/vendor/autoload.php'));
        $this->assertFalse(File::isDirectory($staging.'/deploy-staging-x-vendor-backup'));
    }

    public function test_restore_stranded_backup_cleans_stale_backup_when_vendor_present(): void
    {
        File::makeDirectory($this->sandbox.'/vendor', 0755, true);
        File::makeDirectory($this->sandbox.'/storage/app/deploy-staging-y-vendor-backup', 0755, true);

        $output = [];
        $result = \rd_restore_stranded_backup($this->sandbox, $output);

        $this->assertTrue($result['restored']);
        $this->assertFalse(File::isDirectory($this->sandbox.'/storage/app/deploy-staging-y-vendor-backup'));
    }

    public function test_install_swaps_deploy_zip_with_vendor_last(): void
    {
        $zipPath = $this->sandbox.'/deploy.zip';
        $zip = new \ZipArchive;
        $this->assertTrue($zip->open($zipPath, \ZipArchive::CREATE) === true);

        $zip->addFromString('artisan', "<?php echo \"Laravel Runner Test\\n\";\n");
        $zip->addFromString('vendor/autoload.php', "<?php\n // staged autoload\n");
        $zip->addFromString('bootstrap/app.php', "<?php\n // app\n");
        $zip->addFromString('deployed-marker.txt', "release\n");
        $zip->close();

        $topo = ['root' => $this->sandbox, 'home' => $this->sandbox];
        $result = \rd_action_install($topo);

        $this->assertTrue($result['ok'], implode("\n", $result['output']));
        $this->assertTrue(File::exists($this->sandbox.'/vendor/autoload.php'));
        $this->assertSame("<?php\n // staged autoload\n", File::get($this->sandbox.'/vendor/autoload.php'));
        $this->assertTrue(File::exists($this->sandbox.'/deployed-marker.txt'));
    }

    public function test_install_rejects_bad_zip_without_touching_tree(): void
    {
        File::makeDirectory($this->sandbox.'/vendor', 0755, true);
        File::put($this->sandbox.'/vendor/autoload.php', "<?php\n // live\n");
        File::put($this->sandbox.'/deploy.zip', str_repeat('x', 1024));

        $topo = ['root' => $this->sandbox, 'home' => $this->sandbox];
        $result = \rd_action_install($topo);

        $this->assertFalse($result['ok']);
        $this->assertSame('bad_zip', $result['status']);
        $this->assertSame("<?php\n // live\n", File::get($this->sandbox.'/vendor/autoload.php'));
    }

    public function test_install_writes_version_marker_when_version_given(): void
    {
        $zipPath = $this->sandbox.'/deploy.zip';
        $zip = new \ZipArchive;
        $this->assertTrue($zip->open($zipPath, \ZipArchive::CREATE) === true);

        $zip->addFromString('artisan', "<?php echo \"Laravel Runner Test\\n\";\n");
        $zip->addFromString('vendor/autoload.php', "<?php\n // staged autoload\n");
        $zip->addFromString('bootstrap/app.php', "<?php\n // app\n");
        $zip->close();

        $topo = ['root' => $this->sandbox, 'home' => $this->sandbox];
        $result = \rd_action_install($topo, 'v0.21.0');

        $this->assertTrue($result['ok'], implode("\n", $result['output']));
        $this->assertSame('v0.21.0', File::get($this->sandbox.'/.deployed-version'));
        $this->assertTrue(File::exists($this->sandbox.'/vendor/autoload.php'));
    }

    public function test_install_heals_stranded_backup_before_installing(): void
    {
        File::makeDirectory($this->sandbox.'/storage/app/deploy-staging-z-vendor-backup', 0755, true);
        File::put($this->sandbox.'/storage/app/deploy-staging-z-vendor-backup/autoload.php', "<?php\n // heal\n");
        File::put($this->sandbox.'/deploy.zip', str_repeat('x', 1024));

        $topo = ['root' => $this->sandbox, 'home' => $this->sandbox];
        $result = \rd_action_install($topo);

        $this->assertFalse($result['ok']);
        $this->assertSame('healed_previous_run', $result['status']);
        $this->assertTrue(File::exists($this->sandbox.'/vendor/autoload.php'));
        $this->assertSame("<?php\n // heal\n", File::get($this->sandbox.'/vendor/autoload.php'));
    }

    public function test_status_reports_health_and_topology(): void
    {
        File::makeDirectory($this->sandbox.'/vendor', 0755, true);
        File::put($this->sandbox.'/vendor/autoload.php', "<?php\n");
        File::put($this->sandbox.'/.deployed-version', 'v0.42.0');

        $topo = ['root' => $this->sandbox, 'home' => $this->sandbox];
        $status = \rd_action_status($topo);

        $this->assertTrue($status['ok']);
        $this->assertTrue($status['vendor_ok']);
        $this->assertSame('v0.42.0', $status['version']);
        $this->assertFalse($status['deploy_zip']['exists']);
        $this->assertIsArray($status['capabilities']);
    }

    public function test_logs_tail_laravel_log_without_touching_trees(): void
    {
        $logDir = $this->sandbox.'/storage/logs';
        File::makeDirectory($logDir, 0755, true);
        for ($i = 1; $i <= 5; $i++) {
            File::append($logDir.'/laravel.log', "line-$i-sample\n");
        }

        $topo = ['root' => $this->sandbox, 'home' => $this->sandbox];
        $result = \rd_action_logs($topo);

        $this->assertTrue($result['ok']);
        $this->assertSame('ok', $result['status']);
        $joined = implode("\n", $result['output']);
        $this->assertStringContainsString('line-5-sample', $joined);
        $this->assertStringContainsString('line-1-sample', $joined);
    }

    public function test_prune_removes_stale_zips_and_staging(): void
    {
        $storageApp = $this->sandbox.'/storage/app';
        File::makeDirectory($storageApp, 0755, true);

        // Create stale zips
        File::put($storageApp.'/deploy-aaa111.zip', str_repeat('x', 1024));
        File::put($storageApp.'/deploy-bbb222.zip', str_repeat('y', 2048));

        // Create stale staging dirs
        File::makeDirectory($storageApp.'/deploy-staging-aaa111', 0755, true);
        File::put($storageApp.'/deploy-staging-aaa111/file.txt', 'staged');

        File::makeDirectory($this->sandbox.'/vendor', 0755, true);
        File::put($this->sandbox.'/vendor/autoload.php', "<?php\n");

        $topo = ['root' => $this->sandbox, 'home' => $this->sandbox];
        $result = \rd_action_prune($topo);

        $this->assertTrue($result['ok'], implode("\n", $result['output']));
        $this->assertGreaterThanOrEqual(3, $result['removed']);
        $this->assertGreaterThan(0, $result['freed_bytes']);
        $this->assertFalse(File::exists($storageApp.'/deploy-aaa111.zip'));
        $this->assertFalse(File::exists($storageApp.'/deploy-bbb222.zip'));
        $this->assertFalse(File::isDirectory($storageApp.'/deploy-staging-aaa111'));
    }

    public function test_prune_preserves_newest_vendor_backup_when_vendor_missing(): void
    {
        $storageApp = $this->sandbox.'/storage/app';
        File::makeDirectory($storageApp, 0755, true);

        // No live vendor/ present — newest vendor-backup must survive.
        File::makeDirectory($storageApp.'/deploy-staging-old-vendor-backup', 0755, true);
        File::put($storageApp.'/deploy-staging-old-vendor-backup/autoload.php', "<?php\n");
        touch($storageApp.'/deploy-staging-old-vendor-backup', time() - 120);

        File::makeDirectory($storageApp.'/deploy-staging-new-vendor-backup', 0755, true);
        File::put($storageApp.'/deploy-staging-new-vendor-backup/autoload.php', "<?php\n");
        touch($storageApp.'/deploy-staging-new-vendor-backup', time());

        $topo = ['root' => $this->sandbox, 'home' => $this->sandbox];
        $result = \rd_action_prune($topo);

        $this->assertTrue($result['ok'], implode("\n", $result['output']));
        $this->assertTrue(File::isDirectory($storageApp.'/deploy-staging-new-vendor-backup'), 'newest backup preserved');
        $this->assertFalse(File::isDirectory($storageApp.'/deploy-staging-old-vendor-backup'), 'older backup removed');
        $this->assertStringContainsString('Kept newest stranded vendor backup', implode("\n", $result['output']));
    }

    public function test_prune_removes_all_vendor_backups_when_vendor_present(): void
    {
        $storageApp = $this->sandbox.'/storage/app';
        File::makeDirectory($storageApp, 0755, true);

        File::makeDirectory($this->sandbox.'/vendor', 0755, true);
        File::put($this->sandbox.'/vendor/autoload.php', "<?php\n");

        File::makeDirectory($storageApp.'/deploy-staging-x-vendor-backup', 0755, true);
        File::put($storageApp.'/deploy-staging-x-vendor-backup/autoload.php', "<?php\n");

        File::makeDirectory($storageApp.'/deploy-staging-y-vendor-backup', 0755, true);
        File::put($storageApp.'/deploy-staging-y-vendor-backup/autoload.php', "<?php\n");

        $topo = ['root' => $this->sandbox, 'home' => $this->sandbox];
        $result = \rd_action_prune($topo);

        $this->assertTrue($result['ok'], implode("\n", $result['output']));
        $this->assertFalse(File::isDirectory($storageApp.'/deploy-staging-x-vendor-backup'));
        $this->assertFalse(File::isDirectory($storageApp.'/deploy-staging-y-vendor-backup'));
        $this->assertGreaterThanOrEqual(2, $result['removed']);
    }

    public function test_prune_returns_noop_when_no_storage_app(): void
    {
        $topo = ['root' => $this->sandbox, 'home' => $this->sandbox];
        $result = \rd_action_prune($topo);

        $this->assertTrue($result['ok']);
        $this->assertSame('no_storage_app', $result['status']);
        $this->assertSame(0, $result['removed']);
    }

    public function test_prune_reports_partial_and_defers_when_budget_exhausted(): void
    {
        $storageApp = $this->sandbox.'/storage/app';
        File::makeDirectory($storageApp, 0755, true);

        File::put($storageApp.'/deploy-aaa111.zip', str_repeat('x', 1024));

        File::makeDirectory($storageApp.'/deploy-staging-aaa111', 0755, true);
        File::put($storageApp.'/deploy-staging-aaa111/file.txt', 'staged');
        File::makeDirectory($storageApp.'/deploy-staging-aaa111/vendor', 0755, true);
        File::put($storageApp.'/deploy-staging-aaa111/vendor/autoload.php', "<?php\n");

        File::makeDirectory($storageApp.'/deploy-staging-bbb222', 0755, true);
        File::put($storageApp.'/deploy-staging-bbb222/file.txt', 'staged');

        File::makeDirectory($this->sandbox.'/vendor', 0755, true);
        File::put($this->sandbox.'/vendor/autoload.php', "<?php\n");

        $topo = ['root' => $this->sandbox, 'home' => $this->sandbox];

        // A zero-second budget forces the very first deferred check to fire.
        $result = \rd_action_prune($topo, 0);

        $this->assertTrue($result['ok'], implode("\n", $result['output']));
        $this->assertTrue($result['partial']);
        $this->assertGreaterThan(0, $result['remaining']);
        $this->assertFalse(File::exists($storageApp.'/deploy-aaa111.zip'), 'zip pruned regardless of budget');
        $this->assertTrue(File::isDirectory($storageApp.'/deploy-staging-aaa111'), 'trees deferred when budget exhausted');

        // A second run with ample budget finishes the job (idempotent resume).
        $again = \rd_action_prune($topo, 30);
        $this->assertTrue($again['ok'], implode("\n", $again['output']));
        $this->assertFalse($again['partial']);
        $this->assertFalse(File::exists($storageApp.'/deploy-aaa111.zip'));
        $this->assertFalse(File::isDirectory($storageApp.'/deploy-staging-aaa111'));
        $this->assertFalse(File::isDirectory($storageApp.'/deploy-staging-bbb222'));
    }

    public function test_rrmdir_collect_returns_freed_bytes_and_completion(): void
    {
        $dir = $this->sandbox.'/tree';
        File::makeDirectory($dir.'/sub', 0755, true);
        File::put($dir.'/a.txt', str_repeat('a', 512));
        File::put($dir.'/sub/b.txt', str_repeat('b', 1024));

        $result = \rd_rrmdir_collect($dir);

        $this->assertTrue($result['complete']);
        $this->assertSame(1536, $result['freed']);
        $this->assertFalse(File::isDirectory($dir));
    }

    public function test_release_tag_sanitizes_version(): void
    {
        $this->assertSame('v0.24.0-beta.1', \rd_release_tag('v0.24.0-beta.1'));
        $this->assertSame('v0.24.0-9d8f', \rd_release_tag('v0.24.0@9d8f/../'));
        $this->assertSame('unknown', \rd_release_tag('!!!'));
    }

    public function test_list_releases_returns_newest_first_and_skips_hidden(): void
    {
        $releases = $this->sandbox.'/releases';
        File::makeDirectory($releases, 0755, true);
        File::makeDirectory($releases.'/old-release', 0755, true);
        File::makeDirectory($releases.'/new-release', 0755, true);
        File::makeDirectory($releases.'/.staging-abc', 0755, true);
        touch($releases.'/old-release', time() - 3600);
        touch($releases.'/new-release', time());

        $list = \rd_list_releases($this->sandbox);

        $this->assertSame(['new-release', 'old-release'], $list);
        $this->assertNotContains('.staging-abc', $list);
    }

    public function test_active_release_follows_current_symlink_with_newest_fallback(): void
    {
        $this->requiresSymlinkCapability();

        $releases = $this->sandbox.'/releases';
        File::makeDirectory($releases.'/r2', 0755, true);
        File::makeDirectory($releases.'/r1', 0755, true);
        touch($releases.'/r1', time() - 3600);
        touch($releases.'/r2', time());

        // No current symlink -> newest release.
        $this->assertSame($releases.'/r2', \rd_active_release($this->sandbox));

        // Stale current symlink -> resolve target even if not newest.
        symlink('releases/r1', $this->sandbox.'/current');
        $this->assertSame($releases.'/r1', \rd_active_release($this->sandbox));
    }

    public function test_resolve_symlink_handles_relative_and_absolute(): void
    {
        $this->requiresSymlinkCapability();

        File::makeDirectory($this->sandbox.'/releases/r1', 0755, true);
        symlink('releases/r1', $this->sandbox.'/current');

        $this->assertSame($this->sandbox.'/releases/r1', \rd_resolve_symlink($this->sandbox.'/current', $this->sandbox));

        symlink($this->sandbox.'/releases/r1', $this->sandbox.'/current-abs');
        $this->assertSame($this->sandbox.'/releases/r1', \rd_resolve_symlink($this->sandbox.'/current-abs', $this->sandbox));

        $this->assertSame('', \rd_resolve_symlink($this->sandbox.'/missing', $this->sandbox));
    }

    public function test_release_install_from_legacy_creates_release_and_flips_symlinks(): void
    {
        $this->requiresSymlinkCapability();

        $home = $this->sandbox;
        $legacyPublic = $home.'/public';
        File::makeDirectory($legacyPublic, 0755, true);
        File::put($legacyPublic.'/index.php', '<?php legacy');
        File::put($home.'/.env', "DEPLOY_TOKEN='secret-token'\n");
        File::makeDirectory($home.'/storage/app/public', 0755, true);
        File::put($home.'/storage/app/public/logo.png', 'png');

        // Build a minimal deploy.zip that mirrors a Laravel release tree.
        $zipPath = $home.'/deploy.zip';
        $zip = new \ZipArchive;
        $zip->open($zipPath, ZipArchive::CREATE);
        $zip->addFromString('artisan', "<?php\n");
        $zip->addFromString('bootstrap/app.php', "<?php\n");
        $zip->addFromString('vendor/autoload.php', "<?php\n");
        $zip->addFromString('public/index.php', '<?php release');
        $zip->addFromString('.deployed-version', 'v0.24.0');
        $zip->close();

        $topo = ['root' => $home, 'home' => $home, 'layout' => 'legacy'];
        $result = \rd_action_release_install($topo, 'v0.24.0');

        $this->assertTrue($result['ok'], implode("\n", $result['output']));
        $this->assertSame('installed', $result['status']);

        // releases/<ts>-v0.24.0 exists with the shared links.
        $list = \rd_list_releases($home);
        $this->assertCount(1, $list);
        $release = $home.'/releases/'.$list[0];
        $this->assertSame('v0.24.0', trim((string) File::get($release.'/.deployed-version')));
        $this->assertTrue(is_link($release.'/.env'), 'shared .env symlinked');
        $this->assertTrue(is_link($release.'/storage'), 'shared storage symlinked');
        $this->assertTrue(is_link($release.'/public/storage'), 'public/storage symlinked');

        // public/ now a symlink to the release public, legacy dir moved aside.
        $this->assertTrue(is_link($home.'/public'));
        $this->assertSame('releases/'.$list[0].'/public', (string) readlink($home.'/public'));
        $legacy = glob($home.'/public.legacy-*') ?: [];
        $this->assertCount(1, $legacy);
        $this->assertSame('<?php legacy', File::get($legacy[0].'/index.php'));

        // current symlink + deploy.zip consumed + version marker.
        $this->assertSame('releases/'.$list[0], (string) readlink($home.'/current'));
        $this->assertFileDoesNotExist($zipPath);
        $this->assertSame('v0.24.0', trim((string) File::get($home.'/.deployed-version')));

        // Runner self-homed into the new release's public/.
        $this->assertFileExists($release.'/public/runner.php');
    }

    public function test_release_install_subsequent_flips_to_newest_without_downtime(): void
    {
        $this->requiresSymlinkCapability();

        $home = $this->sandbox;

        // Bootstrap release layout with one release.
        File::makeDirectory($home.'/releases/r1', 0755, true);
        File::put($home.'/releases/r1/.deployed-version', 'v0.1.0');
        File::put($home.'/releases/r1/artisan', "<?php\n");
        File::put($home.'/releases/r1/vendor/autoload.php', "<?php\n");
        File::put($home.'/releases/r1/bootstrap/app.php', "<?php\n");
        symlink('releases/r1', $home.'/current');
        symlink($home.'/storage/app/public', $home.'/releases/r1/public/storage');
        symlink('releases/r1/public', $home.'/public');
        File::put($home.'/.env', "DEPLOY_TOKEN='secret-token'\n");
        File::makeDirectory($home.'/storage/app/public', 0755, true);

        // New deploy.zip.
        $zipPath = $home.'/deploy.zip';
        $zip = new \ZipArchive;
        $zip->open($zipPath, ZipArchive::CREATE);
        $zip->addFromString('artisan', "<?php\n");
        $zip->addFromString('bootstrap/app.php', "<?php\n");
        $zip->addFromString('vendor/autoload.php', "<?php\n");
        $zip->addFromString('public/index.php', '<?php v2');
        $zip->close();

        $topo = ['root' => $home.'/releases/r1', 'home' => $home, 'layout' => 'release'];
        $result = \rd_action_release_install($topo, 'v0.2.0');

        $this->assertTrue($result['ok'], implode("\n", $result['output']));
        $this->assertSame('installed', $result['status']);

        $list = \rd_list_releases($home);
        $this->assertCount(2, $list);
        $this->assertSame('r1', $list[1], 'old release retained');
        $this->assertSame('v0.2.0', trim((string) File::get($home.'/releases/'.$list[0].'/.deployed-version')));

        // public + current point at the newest release.
        $this->assertSame('releases/'.$list[0].'/public', (string) readlink($home.'/public'));
        $this->assertSame('releases/'.$list[0], (string) readlink($home.'/current'));
        $this->assertSame('<?php v2', File::get($home.'/public/index.php'));
    }

    public function test_release_install_auto_prunes_to_keep3(): void
    {
        $this->requiresSymlinkCapability();

        $home = $this->sandbox;
        $rel = $home.'/releases';

        // Bootstrap release layout with three releases (r1 oldest … r3 newest).
        foreach (['r1', 'r2', 'r3'] as $i => $name) {
            File::makeDirectory($rel.'/'.$name, 0755, true);
            File::put($rel.'/'.$name.'/.deployed-version', 'v0.'.($i + 1).'.0');
            File::put($rel.'/'.$name.'/public/index.php', '<?php '.$name);
            touch($rel.'/'.$name, time() - (3 - $i) * 3600);
        }
        symlink('releases/r3', $home.'/current');
        symlink('releases/r3/public', $home.'/public');
        File::put($home.'/.env', "DEPLOY_TOKEN='secret-token'\n");
        File::makeDirectory($home.'/storage/app/public', 0755, true);

        // Install a fourth release.
        $zipPath = $home.'/deploy.zip';
        $zip = new \ZipArchive;
        $zip->open($zipPath, ZipArchive::CREATE);
        $zip->addFromString('artisan', "<?php\n");
        $zip->addFromString('bootstrap/app.php', "<?php\n");
        $zip->addFromString('vendor/autoload.php', "<?php\n");
        $zip->addFromString('public/index.php', '<?php v4');
        $zip->close();

        $topo = ['root' => $rel.'/r3', 'home' => $home, 'layout' => 'release'];
        $result = \rd_action_release_install($topo, 'v0.4.0');

        $this->assertTrue($result['ok'], implode("\n", $result['output']));
        $this->assertSame('installed', $result['status']);

        $list = \rd_list_releases($home);
        $this->assertCount(3, $list, 'install auto-prunes to keep=3');
        $this->assertFalse(File::isDirectory($rel.'/r1'), 'oldest release pruned');
        $this->assertTrue(File::isDirectory($rel.'/r2'));
        $this->assertTrue(File::isDirectory($rel.'/r3'));
        $this->assertSame('v0.4.0', trim((string) File::get($home.'/releases/'.$list[0].'/.deployed-version')), 'newest release kept');
    }

    public function test_release_cleanup_prunes_stale_legacy_webroot_backups(): void
    {
        $home = $this->sandbox;
        $rel = $home.'/releases';

        foreach (['r2', 'r1'] as $i => $name) {
            File::makeDirectory($rel.'/'.$name, 0755, true);
            File::put($rel.'/'.$name.'/file.txt', 'x');
            touch($rel.'/'.$name, time() - $i * 3600);
        }

        // Stale legacy webroot backup older than a day.
        $stale = $home.'/public.legacy-20260901T000000Z';
        File::makeDirectory($stale, 0755, true);
        File::put($stale.'/index.php', '<?php stale');
        touch($stale, time() - 90000);

        // Fresh legacy webroot backup (recent migration) must survive.
        $fresh = $home.'/public.legacy-20260914T000000Z';
        File::makeDirectory($fresh, 0755, true);
        File::put($fresh.'/index.php', '<?php fresh');
        touch($fresh, time() - 60);

        $topo = ['root' => $home, 'home' => $home, 'layout' => 'release'];
        $result = \rd_action_release_cleanup($topo, 2);

        $this->assertTrue($result['ok'], implode("\n", $result['output']));
        $this->assertFalse(File::isDirectory($stale), 'stale legacy webroot pruned');
        $this->assertTrue(File::isDirectory($fresh), 'fresh legacy webroot kept');
    }

    public function test_release_rollback_returns_to_previous_release(): void
    {
        $this->requiresSymlinkCapability();

        $home = $this->sandbox;
        $rel = $home.'/releases';

        foreach (['r2', 'r1'] as $name) {
            File::makeDirectory($rel.'/'.$name, 0755, true);
            File::put($rel.'/'.$name.'/public/index.php', '<?php '.$name);
        }
        touch($rel.'/r1', time() - 3600);
        touch($rel.'/r2', time());
        File::put($rel.'/r1/.deployed-version', 'v0.1.0');
        File::put($rel.'/r2/.deployed-version', 'v0.2.0');
        symlink('releases/r2', $home.'/current');
        symlink('releases/r2/public', $home.'/public');

        $topo = ['root' => $rel.'/r2', 'home' => $home, 'layout' => 'release'];
        $result = \rd_action_release_rollback($topo);

        $this->assertTrue($result['ok'], implode("\n", $result['output']));
        $this->assertSame('rolled_back', $result['status']);
        $this->assertSame('r1', $result['release']);
        $this->assertSame('releases/r1', (string) readlink($home.'/current'));
        $this->assertSame('releases/r1/public', (string) readlink($home.'/public'));
        $this->assertSame('v0.1.0', trim((string) File::get($home.'/.deployed-version')));
    }

    public function test_release_rollback_restores_legacy_webroot_when_no_previous_release(): void
    {
        $this->requiresSymlinkCapability();

        $home = $this->sandbox;
        File::makeDirectory($home.'/releases/r1/public', 0755, true);
        File::put($home.'/releases/r1/public/index.php', '<?php release');
        File::makeDirectory($home.'/public.legacy-20260101T000000Z', 0755, true);
        File::put($home.'/public.legacy-20260101T000000Z/index.php', '<?php legacy');
        symlink('releases/r1', $home.'/current');
        symlink('releases/r1/public', $home.'/public');

        $topo = ['root' => $home.'/releases/r1', 'home' => $home, 'layout' => 'release'];
        $result = \rd_action_release_rollback($topo);

        $this->assertTrue($result['ok'], implode("\n", $result['output']));
        $this->assertSame('rolled_back_to_legacy', $result['status']);
        $this->assertFalse(is_link($home.'/public'));
        $this->assertSame('<?php legacy', File::get($home.'/public/index.php'));
        $this->assertFileDoesNotExist($home.'/current');
    }

    public function test_release_cleanup_deletes_older_releases_beyond_keep(): void
    {
        $home = $this->sandbox;
        $rel = $home.'/releases';

        foreach (['r3', 'r2', 'r1'] as $i => $name) {
            File::makeDirectory($rel.'/'.$name, 0755, true);
            File::put($rel.'/'.$name.'/file.txt', 'x');
            // r3 newest … r1 oldest.
            touch($rel.'/'.$name, time() - $i * 3600);
        }

        $topo = ['root' => $home, 'home' => $home, 'layout' => 'release'];
        $result = \rd_action_release_cleanup($topo, 2);

        $this->assertTrue($result['ok'], implode("\n", $result['output']));
        $this->assertSame(2, $result['keep']);
        $this->assertSame(1, $result['removed']);
        $this->assertFalse(File::isDirectory($rel.'/r1'));
        $this->assertTrue(File::isDirectory($rel.'/r2'));
        $this->assertTrue(File::isDirectory($rel.'/r3'));
    }

    public function test_status_reports_release_layout_and_active_release(): void
    {
        $this->requiresSymlinkCapability();

        $home = $this->sandbox;
        $rel = $home.'/releases';

        File::makeDirectory($rel.'/r1/public', 0755, true);
        File::put($rel.'/r1/.deployed-version', 'v0.9.0');
        File::put($rel.'/r1/vendor/autoload.php', "<?php\n");
        symlink('releases/r1', $home.'/current');
        symlink('releases/r1/public', $home.'/public');

        $topo = ['root' => $rel.'/r1', 'home' => $home, 'layout' => 'release'];
        $status = \rd_action_status($topo);

        $this->assertTrue($status['ok']);
        $this->assertSame('release', $status['layout']);
        $this->assertSame('v0.9.0', $status['version']);
        $this->assertTrue($status['vendor_ok']);
        $this->assertSame('releases/r1', $status['current_symlink']);
        $this->assertCount(1, $status['releases']);
        $this->assertTrue($status['releases'][0]['active']);
        $this->assertStringContainsString('releases/r1', $status['webroot']);
    }
}
