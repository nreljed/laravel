<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Structural boundary guard for the consuming app (mirrors the package-level
 * NamespaceIntegrityTest): the app must never declare the Nreljed\* package
 * namespaces, must not ship byte-identical copies of package sources, and the
 * boundary scripts (foundation-check, frontend-boundary-check) must stay clean.
 *
 * This test intentionally does NOT boot the application.
 */
final class BoundaryIntegrityTest extends TestCase
{
    /**
     * App-owned files that legitimately match a package source byte-for-byte.
     *
     * resources/js/test-setup.ts is the app's Vitest wiring (referenced by
     * vitest.config.js setupFiles); the platform package ships an identical
     * file for its own suite. The app copy is owned here, not a fork.
     */
    private const ALLOWED_APP_DUPLICATES = [
        'resources/js/test-setup.ts',
    ];

    private string $root;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = dirname(__DIR__, 2);
    }

    public function test_app_php_never_declares_a_package_namespace(): void
    {
        $violations = [];

        foreach ($this->phpFiles([$this->root.'/app', $this->root.'/database']) as $file) {
            $relative = str_replace('\\', '/', substr($file, strlen($this->root.'/')));

            $content = (string) file_get_contents($file);

            if (preg_match('/^namespace\s+Nreljed\\\\/m', $content)) {
                $violations[] = $relative;
            }
        }

        $this->assertSame(
            [],
            $violations,
            'App files declaring a package-owned Nreljed\* namespace:'.PHP_EOL.implode(PHP_EOL, $violations)
        );
    }

    public function test_app_php_does_not_duplicate_package_php_sources(): void
    {
        $this->assertNoByteDuplicates(
            $this->phpDuplicates('app', 'php'),
            'App PHP files that are byte-identical to a package source:'
        );
    }

    public function test_app_frontend_does_not_duplicate_package_frontend_sources(): void
    {
        $this->assertNoByteDuplicates(
            $this->phpDuplicates('frontend', 'frontend'),
            'App frontend files that are byte-identical to a package source:'
        );
    }

    public function test_boundary_scripts_report_clean(): void
    {
        foreach (['foundation-check.php', 'frontend-boundary-check.php'] as $script) {
            $path = $this->root.'/scripts/'.$script;

            $this->assertFileExists($path, "Missing boundary script: $path");

            $exit = $this->runScript($script);

            $this->assertSame(0, $exit['code'], "{$script} failed: {$exit['stdout']}{$exit['stderr']}");
        }
    }

    /**
     * @param  array<string, list<string>>  $duplicates
     */
    private function assertNoByteDuplicates(array $duplicates, string $message): void
    {
        $lines = [];

        foreach ($duplicates as $appFile => $packageTwin) {
            $lines[] = sprintf('%s  ->  %s', $appFile, $packageTwin[0]);
        }

        $this->assertSame([], $duplicates, $message.PHP_EOL.implode(PHP_EOL, $lines));
    }

    /**
     * Hash index of package sources, then find app files colliding with it.
     *
     * @return array<string, list<string>> app file => list of package twin paths
     */
    private function phpDuplicates(string $appScope, string $packageScope): array
    {
        $packageFiles = match ($packageScope) {
            'php' => $this->packagePhpSourceFiles(),
            'frontend' => $this->packageFrontendFiles(),
            default => [],
        };

        $index = [];

        foreach ($packageFiles as $packageFile => $label) {
            $hash = (string) sha1_file($packageFile);

            $index[$hash][] = $label;
        }

        $appFiles = match ($appScope) {
            'app' => $this->phpFiles([$this->root.'/app', $this->root.'/database', $this->root.'/routes']),
            'frontend' => $this->frontendFiles($this->root.'/resources/js'),
            default => [],
        };

        $duplicates = [];

        foreach ($appFiles as $file) {
            $hash = (string) sha1_file($file);

            $relative = str_replace('\\', '/', substr($file, strlen($this->root.'/')));

            if (isset($index[$hash]) && ! in_array($relative, self::ALLOWED_APP_DUPLICATES, true)) {
                $duplicates[$relative] = $index[$hash];
            }
        }

        return $duplicates;
    }

    /**
     * Installed package PHP sources this app depends on.
     *
     * @return array<string, string> absolute path => label
     */
    private function packagePhpSourceFiles(): array
    {
        $files = [];

        foreach ($this->requiredPackageNames() as $name) {
            $dir = $this->root.'/vendor/nreljed/'.$name.'/src';

            if (! is_dir($dir)) {
                continue;
            }

            foreach ($this->phpFiles([$dir]) as $file) {
                $files[$file] = 'vendor/nreljed/'.$name.'/src/'.str_replace('\\', '/', substr($file, strlen($dir.'/')));
            }
        }

        return $files;
    }

    /**
     * Sibling workspace package frontend sources this app depends on.
     *
     * @return array<string, string> absolute path => label
     */
    private function packageFrontendFiles(): array
    {
        $files = [];

        foreach ($this->requiredPackageNames() as $name) {
            $dir = $this->root.'/../../packages/'.$name;

            if (! is_dir($dir)) {
                continue;
            }

            foreach ($this->frontendFiles($dir) as $file) {
                $files[$file] = 'packages/'.$name.'/'.str_replace('\\', '/', substr($file, strlen($dir.'/')));
            }
        }

        return $files;
    }

    /**
     * Composer package names this app requires (nreljed/* only).
     *
     * @return list<string>
     */
    private function requiredPackageNames(): array
    {
        $composer = json_decode((string) file_get_contents($this->root.'/composer.json'), true);

        $names = [];

        foreach (array_merge($composer['require'] ?? [], $composer['require-dev'] ?? []) as $name => $_version) {
            if (preg_match('#^nreljed/([a-z-]+)$#', (string) $name, $m)) {
                $names[] = $m[1];
            }
        }

        sort($names);

        return $names;
    }

    /**
     * @param  list<string>  $roots
     * @return list<string>
     */
    private function phpFiles(array $roots): array
    {
        $files = [];

        foreach ($roots as $dir) {
            if (! is_dir($dir)) {
                continue;
            }

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($dir)
            );

            foreach ($iterator as $file) {
                /** @var SplFileInfo $file */
                if ($file->isFile() && $file->getExtension() === 'php') {
                    $files[] = $file->getPathname();
                }
            }
        }

        sort($files);

        return $files;
    }

    /**
     * @return list<string>
     */
    private function frontendFiles(string $dir): array
    {
        if (! is_dir($dir)) {
            return [];
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir)
        );

        $files = [];

        foreach ($iterator as $file) {
            /** @var SplFileInfo $file */
            if ($file->isFile() && in_array($file->getExtension(), ['ts', 'tsx'], true)) {
                $files[] = $file->getPathname();
            }
        }

        sort($files);

        return $files;
    }

    /**
     * @return array{code: int, stdout: string, stderr: string}
     */
    private function runScript(string $script): array
    {
        $descriptors = [
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open(
            [PHP_BINARY, $this->root.'/scripts/'.$script, $this->root],
            $descriptors,
            $pipes,
            $this->root
        );

        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);

        fclose($pipes[1]);
        fclose($pipes[2]);

        return [
            'code' => proc_close($process),
            'stdout' => (string) $stdout,
            'stderr' => (string) $stderr,
        ];
    }
}
