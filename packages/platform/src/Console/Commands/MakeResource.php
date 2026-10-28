<?php

namespace Nreljed\Platform\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

class MakeResource extends Command
{
    protected $signature = 'make:resource
        {name : The name of the resource, e.g. "Product"}
        {--slug= : The resource key (defaults to a Kebab-case plural name)}
        {--model= : The model the resource persists, for the docblock (optional)}
        {--path=app/Resources : Directory where the descriptor class is created}
        {--namespace=App\\Resources : Namespace of the descriptor class}';

    protected $description = 'Scaffold a declarative admin resource descriptor returned by a FeatureProvider\'s getAdminResources()';

    public function handle(): int
    {
        $name = $this->argument('name');
        $slug = $this->option('slug') ?: $this->defaultSlug($name);
        $model = $this->option('model');
        $basePath = (string) $this->option('path');
        $namespace = rtrim((string) $this->option('namespace'), '\\');
        $className = Str::studly($name).'Resource';
        $relativePath = str_replace('\\', '/', $namespace).'/'.$className.'.php';

        $path = base_path($basePath.'/'.$relativePath);

        if (is_file($path)) {
            $this->error("Resource descriptor already exists at [{$path}].");

            return Command::FAILURE;
        }

        $this->makeDirectory($path);

        $stub = $this->buildStub($namespace, $className, $name, $slug, $model);

        file_put_contents($path, $stub);

        $this->info("Resource descriptor [{$className}] created at [{$path}].");

        $this->components->twoColumnDetail('Key', $slug);
        $this->components->twoColumnDetail('Namespace', $namespace);

        $this->info('To complete setup:');
        $this->line('  1. Add the descriptor to your FeatureProvider\'s getAdminResources() alongside the existing resources:');
        $this->line('     return ['.$namespace.'\\'.$className.'::definition()];');
        $this->line('  2. Wire the routes each `routes` entry names in the owning feature provider.');
        $this->line('  3. Run "php artisan permissions:list" to assign the resource\'s permissions to roles.');

        return Command::SUCCESS;
    }

    protected function defaultSlug(string $name): string
    {
        $plural = str_ends_with($name, 's') ? $name : Str::plural($name);

        return Str::kebab($plural);
    }

    /**
     * @return non-empty-string
     */
    protected function buildStub(string $namespace, string $className, string $name, string $slug, ?string $model): string
    {
        $nameEscaped = str_replace("'", "\\'", $name);
        $modelComment = $model !== null && $model !== ''
            ? "\n * Persists {@see \\{$model}} on its own routes and controllers.\n"
            : '';

        return <<<PHP
        <?php

        namespace {$namespace};

        use Nreljed\\Platform\\Support\\ResourceDefinition;

        /**
         * Declarative admin resource for {$nameEscaped}.{$modelComment}
         * The table/form/route keys match `resources/js/lib/resource-schema.ts`
         * verbatim, so a key that is wrong here renders a wrong column on
         * purpose instead of the runtime guessing at what you meant.
         *
         * Return the descriptor from the owning feature provider's
         * `getAdminResources()`:
         *
         *     public function getAdminResources(): array
         *     {
         *         return [
         *             {$className}::definition(),
         *         ];
         *     }
         */
        final class {$className}
        {
            public static function definition(): ResourceDefinition
            {
                return new ResourceDefinition(
                    key: '{$slug}',
                    label: '{$nameEscaped}',
                    singular: '{$nameEscaped}',
                    table: [
                        'columns' => [
                            ['name' => 'id', 'header' => 'ID'],
                            ['name' => 'name', 'header' => 'Name'],
                            ['name' => 'created_at', 'header' => 'Created'],
                        ],
                        /**
                         * Keys mirror `resources/js/lib/resource-schema.ts`
                         * verbatim — camelCase here, `toColumnDef()` over
                         * there — so a wrong key is a wrong column on purpose.
                         */
                        'searchKey' => 'name',
                    ],
                    form: [
                        [
                            'title' => 'Details',
                            'fields' => [
                                ['name' => 'name', 'type' => 'text', 'label' => 'Name', 'required' => true],
                                ['name' => 'slug', 'type' => 'slug', 'label' => 'Slug'],
                            ],
                        ],
                    ],
                    routes: [
                        'index' => 'dashboard.{$slug}.index',
                        'create' => 'dashboard.{$slug}.create',
                        'store' => 'dashboard.{$slug}.store',
                        'edit' => 'dashboard.{$slug}.edit',
                        'update' => 'dashboard.{$slug}.update',
                        'destroy' => 'dashboard.{$slug}.destroy',
                    ],
                );
            }
        }

        PHP;
    }

    protected function makeDirectory(string $path): void
    {
        $directory = dirname($path);

        if (! is_dir($directory)) {
            mkdir($directory, 0777, true);
        }
    }
}
