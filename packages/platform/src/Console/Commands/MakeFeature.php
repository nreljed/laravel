<?php

namespace Nreljed\Platform\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

class MakeFeature extends Command
{
    protected $signature = 'make:feature
        {name : The name of the feature, e.g. "Forum"}
        {--slug= : The feature slug (defaults to a slugified name)}
        {--path=app/FeatureProviders : Directory where the provider class is created}
        {--namespace=App\\FeatureProviders : Namespace of the provider class}';

    protected $description = 'Scaffold a FeatureProvider class that is discoverable by the FeatureRegistry';

    public function handle(): int
    {
        $name = $this->argument('name');
        $slug = $this->option('slug') ?: Str::slug($name);
        $basePath = (string) $this->option('path');
        $namespace = rtrim((string) $this->option('namespace'), '\\');
        $className = Str::studly($name).'ServiceProvider';
        $relativePath = str_replace('\\', '/', $namespace).'/'.$className.'.php';

        $path = base_path($basePath.'/'.$relativePath);

        if (is_file($path)) {
            $this->error("Feature provider already exists at [{$path}].");

            return Command::FAILURE;
        }

        $this->makeDirectory($path);

        $stub = $this->buildStub($namespace, $className, $name, $slug);

        file_put_contents($path, $stub);

        $this->info("Feature provider [{$className}] created at [{$path}].");

        $this->registerInConfig($className, $namespace);

        $this->components->twoColumnDetail('Slug', $slug);
        $this->components->twoColumnDetail('Namespace', $namespace);

        $this->info('To complete setup:');
        $this->line('  1. Confirm config/features.php lists '.$namespace.'\\'.$className.'::class in its providers array');
        $this->line('  2. Implement any optional capability methods your feature supports');
        $this->line('  3. Run "php artisan permissions:list" and "php artisan permissions:sync" to sync permissions');

        return Command::SUCCESS;
    }

    protected function buildStub(string $namespace, string $className, string $name, string $slug): string
    {
        $nameEscaped = str_replace("'", "\\'", $name);

        return <<<PHP
        <?php

        namespace {$namespace};

        use Nreljed\\Platform\\Contracts\\FeatureProvider;
        use Nreljed\\Platform\\Concerns\\InteractsWithFeatures;

        class {$className} extends \Illuminate\Support\ServiceProvider implements FeatureProvider
        {
            use InteractsWithFeatures;

            public function boot(): void
            {
                // Register routes, migrations, policies, commands, etc.
                // $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
            }

            public function name(): string
            {
                return '{$nameEscaped}';
            }

            public function slug(): string
            {
                return '{$slug}';
            }

            public function description(): ?string
            {
                return '{$nameEscaped} feature module.';
            }

            public function version(): string
            {
                return '1.0.0';
            }

            public function getNavItems(): array
            {
                return [
                    // [
                    //     'title' => '{$name}',
                    //     'url' => route('dashboard.{$slug}.index'),
                    //     'icon' => 'FileText',
                    // ],
                ];
            }

            public function getSharedData(): array
            {
                return [
                    // Share props consumed by Inertia pages, e.g.:
                    // 'categories' => [...],
                ];
            }

            public function getSettingsRoute(): ?string
            {
                return null;
            }

            public function hasSettings(): bool
            {
                return false;
            }

            public function permissions(): array
            {
                return [
                    // '{$slug}.view',
                    // '{$slug}.create',
                    // '{$slug}.edit',
                    // '{$slug}.delete',
                    // '{$slug}.manage',
                ];
            }
        }

        PHP;
    }

    protected function registerInConfig(string $className, string $namespace): void
    {
        $configPath = config_path('features.php');

        if (! is_file($configPath)) {
            $this->warn('config/features.php not found — skipping config registration.');

            return;
        }

        $fqcn = $namespace.'\\'.$className;

        if (! $this->insertProvider($configPath, $fqcn)) {
            $this->warn('Could not auto-register the provider in config/features.php — add it manually to the providers array.');

            return;
        }

        $this->info("Registered [{$fqcn}] in config/features.php providers array.");
    }

    /**
     * Insert the provider class into the `providers` array of a features config
     * file. The FeatureRegistry only discovers providers listed there, so the
     * generator has to write to that array — not to config/core.php, which
     * holds engine gates and has no features list at all.
     *
     * Returns false when the file has no `providers` array to extend.
     */
    protected function insertProvider(string $configPath, string $fqcn): bool
    {
        $config = (string) file_get_contents($configPath);

        if (str_contains($config, $fqcn)) {
            return true;
        }

        $lines = preg_split('/\r\n|\r|\n/', $config);

        $start = null;

        foreach ($lines as $index => $line) {
            if (preg_match("/'providers'\s*=>\s*\[/", $line)) {
                $start = $index;

                break;
            }
        }

        if ($start === null) {
            return false;
        }

        $indent = $this->indentOf($lines[$start]);
        $entry = $indent.'    '.$fqcn.'::class,';

        // An empty array lives on one line: expand it before inserting.
        if (preg_match("/'providers'\s*=>\s*\[\s*\],/", $lines[$start])) {
            $lines[$start] = $indent."'providers' => [\n".$entry."\n".$indent.']';

            file_put_contents($configPath, implode(PHP_EOL, $lines));

            return true;
        }

        $depth = 0;
        $end = null;

        for ($index = $start; $index < count($lines); $index++) {
            $depth += substr_count($lines[$index], '[') - substr_count($lines[$index], ']');

            if ($depth <= 0) {
                $end = $index;

                break;
            }
        }

        if ($end === null) {
            return false;
        }

        array_splice($lines, $end, 0, [$entry]);

        file_put_contents($configPath, implode(PHP_EOL, $lines));

        return true;
    }

    protected function indentOf(string $line): string
    {
        preg_match('/^\s*/', $line, $matches);

        return $matches[0] ?? '';
    }

    protected function makeDirectory(string $path): void
    {
        $directory = dirname($path);

        if (! is_dir($directory)) {
            mkdir($directory, 0777, true);
        }
    }
}
