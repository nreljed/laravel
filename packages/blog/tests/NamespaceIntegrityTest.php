<?php

namespace Nreljed\Blog\Tests;

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

    public function test_no_foreign_namespace_references_remain(): void
    {
        $violations = [];

        foreach ($this->phpFiles() as $file) {
            $relative = str_replace('\\', '/', substr($file, strlen($this->root.'/')));

            // The frozen FeatureProvider contract copy intentionally keeps the
            // Nreljed\Platform\Contracts namespace (byte-identical reference).
            if (str_starts_with($relative, 'src/Contracts/')) {
                continue;
            }

            $content = file_get_contents($file);

            if (preg_match('/(?<!Blog)(?<!Platform)App\\\\[A-Za-z_]/', (string) $content)) {
                $violations[] = $file;
            }
        }

        $this->assertSame([], $violations, 'Files still referencing the host App\\ namespace:'.PHP_EOL.implode(PHP_EOL, $violations));
    }

    public function test_every_php_file_declares_the_package_namespace(): void
    {
        $violations = [];

        foreach ($this->phpFiles() as $file) {
            $relative = str_replace('\\', '/', substr($file, strlen($this->root.'/')));

            // Route definition files, anonymous-class migrations and the
            // frozen contract copy are intentionally outside the package root
            // namespace.
            if (preg_match('#(^|/)Routes/|/database/migrations/#', $relative) || str_starts_with($relative, 'src/Contracts/')) {
                continue;
            }

            $content = file_get_contents($file);

            if (! str_starts_with((string) $content, '<?php')) {
                continue;
            }

            if (! preg_match('/^namespace Nreljed\\\\Blog(\\\\\w+)*;/m', (string) $content)) {
                $violations[] = $relative;
            }
        }

        $this->assertSame([], $violations, 'Files not declaring the Nreljed\Blog namespace:'.PHP_EOL.implode(PHP_EOL, $violations));
    }

    public function test_psr4_namespace_matches_file_path(): void
    {
        $violations = [];

        foreach ($this->phpFiles() as $file) {
            $relative = substr($file, strlen($this->root.'/src/'));
            $relative = str_replace('\\', '/', $relative);

            if (str_contains($relative, 'Database/Factories/') || str_starts_with($relative, 'Contracts/')) {
                continue;
            }

            $expectedClass = 'Nreljed\\Blog\\'.str_replace('/', '\\', substr($relative, 0, -4));
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
            $composer['autoload']['psr-4']['Nreljed\\Blog\\'],
            'composer.json must map Nreljed\Blog\ to src/'
        );
    }

    public function test_frozen_contract_copy_matches_the_kernel_contract(): void
    {
        $installed = $this->root.'/vendor/nreljed/platform/src/Contracts/FeatureProvider.php';

        if (! is_file($installed)) {
            $this->markTestSkipped('platform not installed in vendor (run composer install)');
        }

        $local = (string) file_get_contents($this->root.'/src/Contracts/FeatureProvider.php');
        $remote = (string) file_get_contents($installed);

        $this->assertSame(
            hash('sha256', $remote),
            hash('sha256', $local),
            'src/Contracts/FeatureProvider.php must stay byte-identical to the installed platform contract.'
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
