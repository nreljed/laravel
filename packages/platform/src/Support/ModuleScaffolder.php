<?php

declare(strict_types=1);

namespace Nreljed\Platform\Support;

use RuntimeException;

/**
 * Writes a module package skeleton to disk from the stubs in
 * `src/Console/stubs/module`.
 *
 * Deliberately container-free: the command passes an absolute root, and the
 * whole generation path is exercised from a test with a temporary directory and
 * no Laravel application.
 */
final class ModuleScaffolder
{
    /**
     * Stub => destination, in the order they are written. `null` means the file
     * is optional and only written when the matching flag is set.
     *
     * @var array<string, string|null>
     */
    private const FILES = [
        'composer.json.stub' => 'composer.json',
        'package.json.stub' => 'package.json',
        'ServiceProvider.php.stub' => 'src/{{ providerClass }}.php',
        'Controller.php.stub' => 'src/Http/Controllers/{{ controllerClass }}.php',
        'Routes/web.php.stub' => 'src/Routes/web.php',
        'pages.ts.stub' => 'src/Resources/js/pages.ts',
        'Page.tsx.stub' => 'src/Resources/js/Pages/Index.tsx',
        'gitignore.stub' => '.gitignore',
        'gitattributes.stub' => '.gitattributes',
        'README.md.stub' => 'README.md',
        'Migration.php.stub' => null,
        'tests/bootstrap.php.stub' => null,
        'phpunit.xml.stub' => null,
        'SmokeTest.php.stub' => null,
    ];

    public function __construct(private readonly string $stubsPath)
    {
        if (! is_dir($this->stubsPath)) {
            throw new RuntimeException("Module stubs directory [{$this->stubsPath}] does not exist.");
        }
    }

    /**
     * Scaffold the module and return the paths written, relative to its root.
     *
     * @return list<string>
     *
     * @throws RuntimeException when the target already exists and $force is false.
     */
    public function scaffold(
        ModuleDefinition $module,
        bool $withMigrations = false,
        bool $withTests = false,
        bool $force = false,
    ): array {
        $this->guardTarget($module->root, $force);

        $written = [];

        foreach (self::FILES as $stub => $destination) {
            if ($destination === null) {
                continue;
            }

            $written[] = $this->write($module, $stub, $destination, $force);
        }

        // The provider loads this directory unconditionally, so it must exist
        // even when no migration has been written yet.
        $written[] = $this->writeRaw(
            $module->root,
            'database/migrations/.gitkeep',
            '',
            $force,
        );

        if ($withMigrations) {
            $timestamp = gmdate('Y_m_d_His');

            $written[] = $this->write(
                $module,
                'Migration.php.stub',
                "database/migrations/{$timestamp}_create_{$module->table()}_table.php",
                $force,
            );
        }

        if ($withTests) {
            $written[] = $this->write(
                $module,
                'tests/bootstrap.php.stub',
                'tests/bootstrap.php',
                $force,
            );
            $written[] = $this->write($module, 'phpunit.xml.stub', 'phpunit.xml', $force);
            $written[] = $this->write(
                $module,
                'SmokeTest.php.stub',
                "tests/Feature/{$module->name}ModuleTest.php",
                $force,
            );
        }

        return array_values(array_filter($written, fn (?string $path) => $path !== null));
    }

    /**
     * Render one stub and write it under the module root.
     *
     * @return string the path written, relative to the module root
     */
    public function write(
        ModuleDefinition $module,
        string $stub,
        string $destination,
        bool $force = false,
    ): string {
        return (string) $this->writeRaw(
            $module->root,
            strtr($destination, $module->stubTokens()),
            $this->render($module, $stub),
            $force,
        );
    }

    /**
     * @return string|null the path written, or null when it was skipped
     */
    private function writeRaw(string $root, string $relative, string $contents, bool $force): ?string
    {
        $path = $root.'/'.ltrim($relative, '/');

        if (is_file($path) && ! $force) {
            return null;
        }

        $directory = dirname($path);

        if (! is_dir($directory) && ! mkdir($directory, 0777, true) && ! is_dir($directory)) {
            throw new RuntimeException("Unable to create directory [{$directory}].");
        }

        if (file_put_contents($path, $contents) === false) {
            throw new RuntimeException("Unable to write [{$path}].");
        }

        return $relative;
    }

    public function render(ModuleDefinition $module, string $stub): string
    {
        $path = $this->stubsPath.'/'.$stub;

        if (! is_file($path)) {
            throw new RuntimeException("Module stub [{$stub}] is missing from [{$this->stubsPath}].");
        }

        return strtr((string) file_get_contents($path), $module->stubTokens());
    }

    /**
     * Refuse to write into a non-empty directory unless the caller opted in,
     * so a typo can never overwrite an existing module.
     */
    private function guardTarget(string $root, bool $force): void
    {
        if (! is_dir($root)) {
            return;
        }

        $entries = array_values(array_diff(scandir($root) ?: [], ['.', '..']));

        if ($entries !== [] && ! $force) {
            throw new RuntimeException(
                "Target directory [{$root}] already exists and is not empty. ".
                'Pass --force to overwrite the generated files (other files are left alone).'
            );
        }
    }
}
