<?php

namespace Nreljed\Platform\Tests;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Guards the package's view namespace contract.
 *
 * `PlatformServiceProvider` publishes its views under the `core::` namespace.
 * Rendering a package view by its bare name only looks in the host's
 * `resources/views`, so an install that never published the views returned
 * 500 "View [recovery.request] not found" for the maintenance recovery page
 * and would 500 on the maintenance page too. Every package-owned view must be
 * referenced through the `core::` namespace instead.
 */
final class ViewNamespaceContractTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = dirname(__DIR__);
    }

    public function test_package_views_are_rendered_through_the_core_namespace(): void
    {
        $violations = [];

        foreach ($this->phpFiles() as $file) {
            $content = (string) file_get_contents($file);
            $relative = str_replace('\\', '/', substr($file, strlen($this->root.'/')));

            preg_match_all(
                '/(?:view|View::make)\(\s*[\'"]([^\'"]+)[\'"]/',
                $content,
                $matches,
            );

            foreach ($matches[1] as $view) {
                if (str_contains($view, '::')) {
                    continue;
                }

                // A bare name is only valid when the package does not own that
                // view (the host provides it, e.g. `view('app')`).
                if ($this->packageViewExists($view)) {
                    $violations[] = sprintf('%s renders package view [%s] without the core:: namespace', $relative, $view);
                }
            }
        }

        $this->assertSame([], $violations, 'Package views rendered without the core:: namespace:'.PHP_EOL.implode(PHP_EOL, $violations));
    }

    public function test_recovery_and_maintenance_views_ship_with_the_package(): void
    {
        $this->assertFileExists($this->root.'/resources/views/recovery/request.blade.php');
        $this->assertFileExists($this->root.'/resources/views/maintenance.blade.php');
    }

    private function packageViewExists(string $view): bool
    {
        $path = $this->root.'/resources/views/'.str_replace('.', '/', $view).'.blade.php';

        return is_file($path);
    }

    /**
     * @return list<string>
     */
    private function phpFiles(): array
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->root.'/src')
        );

        $files = [];

        foreach ($iterator as $file) {
            /** @var SplFileInfo $file */
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }

        sort($files);

        return $files;
    }
}
