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
        $this->line('  1. Add a "features" entry to config/core.php pointing at '.$namespace.'\\'.$className);
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
        $configPath = config_path('core.php');

        if (! is_file($configPath)) {
            $this->warn('config/core.php not found — skipping config registration.');

            return;
        }

        $config = file_get_contents($configPath);
        $fqcn = $namespace.'\\'.$className;

        if (str_contains($config, $fqcn)) {
            return;
        }

        // Append to the "features" array before the closing bracket if present.
        if (preg_match('/^(\s*)features\s*=>\s*\[/m', $config, $matches, PREG_OFFSET_CAPTURE)) {
            $indent = $matches[1][0] ?? '        ';
            $search = "{$indent}];";
            $replacement = "{$indent}    {$fqcn}::class,\n{$indent}];";

            if (str_contains($config, $search)) {
                $config = Str::replaceLast($search, $replacement, $config);
                file_put_contents($configPath, $config);
                $this->info("Registered [{$fqcn}] in config/core.php features array.");

                return;
            }
        }

        $this->warn('Could not auto-register the provider in config/core.php — add it manually to the features array.');
    }

    protected function makeDirectory(string $path): void
    {
        $directory = dirname($path);

        if (! is_dir($directory)) {
            mkdir($directory, 0777, true);
        }
    }
}
