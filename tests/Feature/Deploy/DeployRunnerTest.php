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

    public function test_env_get_parses_quoted_and_unquoted_values(): void
    {
        $env = $this->sandbox.'/.env';
        File::put($env, "DEPLOY_TOKEN='abc-123'\nGITHUB_TOKEN=\"xyz\"\nEMPTY=\n# comment\n");

        $this->assertSame('abc-123', \rd_env_get($env, 'DEPLOY_TOKEN'));
        $this->assertSame('xyz', \rd_env_get($env, 'GITHUB_TOKEN'));
        $this->assertSame('', \rd_env_get($env, 'EMPTY'));
        $this->assertSame('', \rd_env_get($env, 'MISSING'));
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
}
