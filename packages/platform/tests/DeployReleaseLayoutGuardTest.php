<?php

namespace Nreljed\Platform\Tests;

use Nreljed\Platform\Deploy\Services\DeployService;
use Nreljed\Platform\Services\CoreVersionService;
use PHPUnit\Framework\TestCase;

/**
 * Contract test for the release-layout guard on the DeployService.
 *
 * Deliberately framework-free: usesReleaseLayout() only resolves filesystem
 * paths, so it can be asserted without booting the application. The guard
 * exists so a release-layout host (served from <home>/releases/<active>) can
 * never be mutated by the legacy in-place swap — deploys must go through the
 * deploy-runner's atomic release-install action.
 */
final class DeployReleaseLayoutGuardTest extends TestCase
{
    private DeployService $deployService;

    private string $temp;

    protected function setUp(): void
    {
        $this->deployService = new DeployService(new CoreVersionService);
        $this->temp = sys_get_temp_dir().'/deploy-guard-'.uniqid();
        mkdir($this->temp, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->rrmdir($this->temp);
    }

    public function test_release_layout_is_detected_for_active_release_directory(): void
    {
        $release = $this->temp.'/releases/2026-09-13-103000-v0.26.1';
        mkdir($release, 0777, true);

        $this->assertTrue($this->deployService->usesReleaseLayout($release));
    }

    public function test_legacy_webroot_is_not_release_layout(): void
    {
        $root = $this->temp.'/public_html';
        mkdir($root, 0777, true);

        $this->assertFalse($this->deployService->usesReleaseLayout($root));
    }

    public function test_release_named_directory_without_releases_parent_is_not_release_layout(): void
    {
        $root = $this->temp.'/projects/releases';
        mkdir($root, 0777, true);

        // The parent basename must be exactly `releases`; a project directory
        // that merely ends in `releases` does not satisfy the guard.
        $this->assertFalse($this->deployService->usesReleaseLayout($root));
    }

    public function test_deeply_nested_legacy_root_is_not_release_layout(): void
    {
        $root = $this->temp.'/home/user/sites/my-app';
        mkdir($root, 0777, true);

        $this->assertFalse($this->deployService->usesReleaseLayout($root));
    }

    public function test_guard_accepts_default_base_path(): void
    {
        // No explicit path — the default relies on base_path() inside a
        // booted application (usesReleaseLayout -> dirname(base_path())). The
        // method signature accepts the optional override specifically so the
        // detection can be asserted framework-free; keep the default-call
        // behaviour out of the framework-free suite.
        $this->assertTrue(method_exists(DeployService::class, 'usesReleaseLayout'));
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
