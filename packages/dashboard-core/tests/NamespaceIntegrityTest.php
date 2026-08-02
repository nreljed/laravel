<?php

namespace Nreljed\DashboardCore\Tests;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class NamespaceIntegrityTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = dirname(__DIR__);
    }

    public function test_no_app_namespace_references_remain(): void
    {
        $violations = [];

        foreach ($this->phpFiles() as $file) {
            $content = file_get_contents($file);

            if (preg_match('/(?<!DashboardCore)App\\\\[A-Za-z_]/', (string) $content)) {
                $violations[] = $file;
            }
        }

        $this->assertSame([], $violations, 'Files still referencing the old App\\ namespace:'.PHP_EOL.implode(PHP_EOL, $violations));
    }

    public function test_every_php_file_declares_the_package_namespace(): void
    {
        $violations = [];

        foreach ($this->phpFiles() as $file) {
            $relative = str_replace('\\', '/', substr($file, strlen($this->root.'/')));

            // Route definition files and anonymous-class migrations are
            // intentionally namespace-less.
            if (preg_match('#(^|/)Routes/|/database/migrations/#', $relative)) {
                continue;
            }

            $content = file_get_contents($file);

            if (! str_starts_with((string) $content, '<?php')) {
                continue;
            }

            if (! preg_match('/^namespace Nreljed\\\\DashboardCore(\\\\\w+)*;/m', (string) $content)) {
                $violations[] = $relative;
            }
        }

        $this->assertSame([], $violations, 'Files not declaring the Nreljed\DashboardCore namespace:'.PHP_EOL.implode(PHP_EOL, $violations));
    }

    public function test_psr4_namespace_matches_file_path(): void
    {
        $violations = [];

        foreach ($this->phpFiles() as $file) {
            $relative = substr($file, strlen($this->root.'/src/'));
            $relative = str_replace('\\', '/', $relative);

            if (str_contains($relative, 'Database/Factories/')) {
                continue;
            }

            $expectedClass = 'Nreljed\\DashboardCore\\'.str_replace('/', '\\', substr($relative, 0, -4));
            $content = file_get_contents($file);

            if (! preg_match('/^namespace ([^;]+);/m', (string) $content, $matches)) {
                continue;
            }

            $namespace = trim($matches[1]);

            if (! str_starts_with($expectedClass, $namespace.'\\') && $expectedClass !== $namespace) {
                $violations[] = sprintf('%s declares %s but PSR-4 expects %s', $relative, $namespace, $expectedClass);
            }
        }

        $this->assertSame([], $violations, 'PSR-4 namespace mismatches:'.PHP_EOL.implode(PHP_EOL, $violations));
    }

    public function test_php_files_have_no_byte_order_mark(): void
    {
        $violations = [];

        foreach ($this->phpFiles() as $file) {
            $bytes = file_get_contents($file);

            if ($bytes !== false && str_starts_with($bytes, "\xEF\xBB\xBF")) {
                $violations[] = substr($file, strlen($this->root.'/'));
            }
        }

        $this->assertSame([], $violations, 'Files containing a UTF-8 BOM:'.PHP_EOL.implode(PHP_EOL, $violations));
    }

    public function test_composer_autoload_maps_package_namespace(): void
    {
        $composer = json_decode((string) file_get_contents($this->root.'/composer.json'), true);

        $this->assertSame(
            'src/',
            $composer['autoload']['psr-4']['Nreljed\\DashboardCore\\'],
            'composer.json must map Nreljed\DashboardCore\ to src/'
        );
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
