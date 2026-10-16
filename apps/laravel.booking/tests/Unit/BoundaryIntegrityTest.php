<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Structural boundary guard for the consuming app (mirrors the package-level
 * NamespaceIntegrityTest): the app must never declare the Nreljed\* package
 * namespaces, must not ship byte-identical copies of package sources, must not
 * shadow a package factory/seeder with a non-subclass reimplementation, and the
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

    /**
     * The app may subclass a package factory/seeder (a thin seam so the class
     * resolves to App\Models\*), but it must never keep a full reimplementation
     * under the same class name. A stale fork silently keeps running with
     * package fixes (e.g. a new column) missing, so a name collision that is
     * not a subclass of the package class is a defect.
     */
    public function test_app_factories_and_seeders_do_not_fork_package_classes(): void
    {
        $packageClasses = $this->packageFactoryAndSeederClasses();
        $violations = [];

        foreach ($this->phpFiles([$this->root.'/database/factories', $this->root.'/database/seeders']) as $file) {
            $basename = pathinfo($file, PATHINFO_FILENAME);

            if (! isset($packageClasses[$basename])) {
                continue;
            }

            $content = (string) file_get_contents($file);

            if (! preg_match('/\bclass\s+\w+\s+extends\s+(\w+)/', $content, $matches)) {
                continue;
            }

            // A thin seam extends a class imported from Nreljed\*; anything else
            // that merely shares the package class name is a stale fork.
            if ($this->extendsPackageClass($content, $matches[1])) {
                continue;
            }

            $relative = str_replace('\\', '/', substr($file, strlen($this->root.'/')));

            $violations[] = $relative.'  ->  '.$packageClasses[$basename];
        }

        $this->assertSame(
            [],
            $violations,
            'App factory/seeder files shadowing a package class without extending it:'.PHP_EOL.implode(PHP_EOL, $violations)
        );
    }

    public function test_boundary_scripts_are_clean(): void
    {
        foreach (['foundation-check.php', 'frontend-boundary-check.php'] as $script) {
            $result = $this->runScript($script);

            $this->assertSame(
                0,
                $result['code'],
                'Boundary script failed: '.$script.PHP_EOL.$result['stdout'].$result['stderr']
            );
        }
    }

    /**
     * @param  array<string, list<string>>  $duplicates
     */
    private function assertNoByteDuplicates(array $duplicates, string $message): void
    {
        $lines = [];

        foreach ($duplicates as $appFile => $packageFiles) {
            $lines[] = $appFile.'  ->  '.implode(', ', $packageFiles);
        }

        $this->assertSame([], $duplicates, $message.PHP_EOL.implode(PHP_EOL, $lines));
    }

    /**
     * @return array<string, list<string>>
     */
    private function phpDuplicates(string $appScope, string $packageScope): array
    {
        $packageFiles = match ($packageScope) {
            'php' => $this->packagePhpSourceFiles(),
            'frontend' => $this->packageFrontendFiles(),
            default => [],
        };

        $index = [];

        foreach ($packageFiles as $file => $label) {
            $hash = (string) sha1_file($file);

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
     * Package factory/seeder class basenames => package label. Any app file with
     * a matching basename must be a thin subclass, never a reimplementation.
     *
     * @return array<string, string>
     */
    private function packageFactoryAndSeederClasses(): array
    {
        $classes = [];

        foreach ($this->packagePhpSourceFiles() as $file => $label) {
            if (! preg_match('#/Database/(Factories|Seeders)/#', str_replace('\\', '/', $file))) {
                continue;
            }

            $classes[pathinfo($file, PATHINFO_FILENAME)] = $label;
        }

        return $classes;
    }

    /**
     * Whether the given content extends a class imported from the Nreljed\*
     * namespace (the thin-subclass seam pattern), rather than reimplementing a
     * package class under the same name. The alias is what matters: an import
     * `use Nreljed\...\Foo as Bar;` satisfies `class X extends Bar`.
     */
    private function extendsPackageClass(string $content, string $extends): bool
    {
        if (! preg_match_all('/^use\s+(Nreljed\\\\[^;]+);/m', $content, $matches)) {
            return false;
        }

        foreach ($matches[1] as $import) {
            $import = trim((string) preg_replace('/\s+/', ' ', $import));

            if (preg_match('/^.+?\s+as\s+(\w+)$/i', $import, $alias)) {
                $local = $alias[1];
            } else {
                $local = substr($import, (int) strrpos($import, '\\') + 1);
            }

            if ($local === $extends) {
                return true;
            }
        }

        return false;
    }

    /**
     * Package frontend sources this app depends on. Resolved from the installed
     * vendor tree (upstream deploy repos) or the workspace sibling (mono), so
     * the guard works in both layouts.
     *
     * @return array<string, string> absolute path => label
     */
    private function packageFrontendFiles(): array
    {
        $files = [];

        foreach ($this->requiredPackageNames() as $name) {
            $candidates = [
                'vendor/nreljed/'.$name.'/resources/js',
                'vendor/nreljed/'.$name.'/src/Resources/js',
                '../../packages/'.$name.'/resources/js',
                '../../packages/'.$name.'/src/Resources/js',
            ];

            foreach ($candidates as $relative) {
                $dir = $this->root.'/'.$relative;

                if (! is_dir($dir)) {
                    continue;
                }

                foreach ($this->frontendFiles($dir) as $file) {
                    $files[$file] = $relative.'/'.str_replace('\\', '/', substr($file, strlen($dir.'/')));
                }
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
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open(
            [PHP_BINARY, $this->root.'/scripts/'.$script, $this->root],
            $descriptors,
            $pipes
        );

        if (! is_resource($process)) {
            return ['code' => 1, 'stdout' => '', 'stderr' => 'Unable to run '.$script];
        }

        fclose($pipes[0]);

        $stdout = (string) stream_get_contents($pipes[1]);
        $stderr = (string) stream_get_contents($pipes[2]);

        fclose($pipes[1]);
        fclose($pipes[2]);

        return [
            'code' => proc_close($process),
            'stdout' => $stdout,
            'stderr' => $stderr,
        ];
    }
}
