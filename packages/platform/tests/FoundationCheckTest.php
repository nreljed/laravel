<?php

namespace Nreljed\Platform\Tests;

use FilesystemIterator;
use Nreljed\Platform\Support\FoundationCheck;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

final class FoundationCheckTest extends TestCase
{
    private string $tmp;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tmp = sys_get_temp_dir().'/foundation-check-'.bin2hex(random_bytes(6));

        mkdir($this->tmp.'/vendor/nreljed/platform', 0777, true);
        mkdir($this->tmp.'/bootstrap', 0777, true);
        mkdir($this->tmp.'/routes', 0777, true);
    }

    protected function tearDown(): void
    {
        $this->rrmdir($this->tmp);

        parent::tearDown();
    }

    public function test_clean_tree_passes(): void
    {
        $this->writeManifest([
            'app_owned_files' => [
                'bootstrap/app.php' => ['marker' => 'wired by Nreljed\\Platform\\PlatformServiceProvider'],
                'routes/web.php' => ['marker' => 'reserved for project-specific routes'],
            ],
            'forbidden_project_paths' => [
                'app/Http/Controllers/DeployController.php',
            ],
        ]);

        $this->write('bootstrap/app.php', "<?php\n\nwired by Nreljed\\Platform\\PlatformServiceProvider\n");
        $this->write('routes/web.php', "<?php\n\n// reserved for project-specific routes\n");

        $result = (new FoundationCheck($this->tmp))->run();

        $this->assertTrue($result['clean'], implode(', ', $result['issues']));
        $this->assertSame([], $result['issues']);
    }

    public function test_missing_manifest_reported(): void
    {
        $result = (new FoundationCheck($this->tmp))->run();

        $this->assertFalse($result['clean']);
        $this->assertStringContainsString('manifest missing', $result['issues'][0]);
    }

    public function test_unreadable_manifest_reported(): void
    {
        $this->write('vendor/nreljed/platform/core-manifest.json', 'not-json{');

        $result = (new FoundationCheck($this->tmp))->run();

        $this->assertFalse($result['clean']);
        $this->assertStringContainsString('manifest unreadable', $result['issues'][0]);
    }

    public function test_missing_app_owned_file_reported(): void
    {
        $this->writeManifest([
            'app_owned_files' => [
                'routes/web.php' => ['marker' => 'reserved for project-specific routes'],
            ],
        ]);

        $result = (new FoundationCheck($this->tmp))->run();

        $this->assertFalse($result['clean']);
        $this->assertStringContainsString('missing app-owned file: routes/web.php', $result['issues'][0]);
    }

    public function test_marker_drift_reported(): void
    {
        $this->writeManifest([
            'app_owned_files' => [
                'routes/web.php' => ['marker' => 'reserved for project-specific routes'],
            ],
        ]);

        $this->write('routes/web.php', "<?php\n\n// this file was overwritten by the project\n");

        $result = (new FoundationCheck($this->tmp))->run();

        $this->assertFalse($result['clean']);
        $this->assertStringContainsString('drifted from core', $result['issues'][0]);
    }

    public function test_forbidden_project_path_reported(): void
    {
        $this->writeManifest([
            'forbidden_project_paths' => [
                'app/Http/Controllers/DeployController.php',
            ],
        ]);

        mkdir($this->tmp.'/app/Http/Controllers', 0777, true);
        $this->write('app/Http/Controllers/DeployController.php', '<?php // orphan');

        $result = (new FoundationCheck($this->tmp))->run();

        $this->assertFalse($result['clean']);
        $this->assertStringContainsString('forbidden project path exists', $result['issues'][0]);
    }

    public function test_multiple_issues_reported_together(): void
    {
        $this->writeManifest([
            'app_owned_files' => [
                'routes/web.php' => ['marker' => 'reserved for project-specific routes'],
            ],
            'forbidden_project_paths' => [
                'app/Http/Controllers/DeployController.php',
            ],
        ]);

        mkdir($this->tmp.'/app/Http/Controllers', 0777, true);
        $this->write('app/Http/Controllers/DeployController.php', '<?php // orphan');

        $result = (new FoundationCheck($this->tmp))->run();

        $this->assertCount(2, $result['issues']);
    }

    /**
     * @param  array<string, mixed>  $manifest
     */
    private function writeManifest(array $manifest): void
    {
        $this->write('vendor/nreljed/platform/core-manifest.json', (string) json_encode($manifest));
    }

    private function write(string $rel, string $content): void
    {
        $path = $this->tmp.'/'.$rel;

        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }

        file_put_contents($path, $content);
    }

    private function rrmdir(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item) {
            if ($item->isDir()) {
                rmdir((string) $item->getPathname());
            } else {
                unlink((string) $item->getPathname());
            }
        }

        rmdir($dir);
    }
}
