<?php

declare(strict_types=1);

namespace Nreljed\Platform\Console\Commands;

use Illuminate\Console\Command;
use Nreljed\Platform\Support\ModuleActivator;
use Nreljed\Platform\Support\ModuleDefinition;
use Nreljed\Platform\Support\ModuleScaffolder;
use RuntimeException;

/**
 * Scaffold a complete feature module package.
 *
 * Produces the same shape the shipped modules (blog, shop-module,
 * booking-module, marketing-module) use: a Composer package with a service
 * provider that implements the minimal `Feature` contract plus only the
 * capabilities it needs, dashboard routes, an Inertia page kit and an optional
 * migration/test suite.
 *
 * The five activation edits (composer path repo, npm link, bootstrap provider,
 * config/features.php, page-config.ts) are the only irreducible steps; they are
 * emitted as a checklist and, with `--activate`, applied in place.
 */
class MakeModule extends Command
{
    protected $signature = 'make:module
        {name : The module name, e.g. "Events"}
        {--slug= : The feature slug (defaults to a slugified name)}
        {--namespace= : Root namespace (default: <vendor>\\<Name>Module)}
        {--vendor=nreljed : Composer/npm vendor}
        {--path=modules : Directory to generate the module into, relative to the app root (or absolute)}
        {--nav-group= : Sidebar heading (defaults to a headline of the name)}
        {--with-migrations : Also write an example migration}
        {--with-tests : Also write a phpunit.xml, bootstrap and contract smoke test}
        {--activate : Patch the host app (composer.json, package.json, providers, features config, page config)}
        {--force : Overwrite generated files in a non-empty target directory}';

    protected $description = 'Scaffold a complete feature module package (provider, routes, pages, optional migrations/tests)';

    public function handle(): int
    {
        try {
            $module = ModuleDefinition::for(
                name: (string) $this->argument('name'),
                root: $this->resolveRoot((string) $this->option('path')),
                slug: $this->stringOption('slug'),
                namespace: $this->stringOption('namespace'),
                navGroup: $this->stringOption('nav-group'),
                vendor: $this->stringOption('vendor') ?? 'nreljed',
            );
        } catch (\InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $withMigrations = (bool) $this->option('with-migrations');
        $withTests = (bool) $this->option('with-tests');
        $activate = (bool) $this->option('activate');
        $force = (bool) $this->option('force');

        $this->components->info("Scaffolding {$module->name} module in {$module->root}");

        try {
            $written = (new ModuleScaffolder(__DIR__.'/../stubs/module'))
                ->scaffold($module, $withMigrations, $withTests, $force);
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        foreach ($written as $path) {
            $this->components->task($path, fn (): bool => true);
        }

        $this->newLine();
        $this->components->twoColumnDetail('Composer package', $module->package());
        $this->components->twoColumnDetail('npm package', $module->npmPackage());
        $this->components->twoColumnDetail('Namespace', $module->namespace);
        $this->components->twoColumnDetail('Feature slug', $module->slug);
        $this->components->twoColumnDetail('Routes', $module->routePrefix().' ('.$module->routeNamePrefix().'*)');
        $this->components->twoColumnDetail('Inertia page', $module->pageName());

        if ($activate) {
            $this->newLine();
            $this->components->info('Activating in the host application');

            foreach ((new ModuleActivator)->activate($module, $this->appRoot()) as $step => $status) {
                $this->components->twoColumnDetail($step, $status);
            }
        } else {
            $this->newLine();
            $this->components->info('Activation checklist (re-run with --activate to apply)');
            $this->renderChecklist($module);
        }

        $this->newLine();
        $this->components->info('Next steps');
        $this->line('  php artisan migrate');
        $this->line('  php artisan db:seed --class=RolesAndPermissionsSeeder');
        $this->line('  php artisan route:list --path='.$module->routePrefix());
        $this->line('  pnpm install && pnpm build   (or: npm install && npm run build)');

        return self::SUCCESS;
    }

    /**
     * Print the five edits a module needs in the host app, in dependency order.
     */
    protected function renderChecklist(ModuleDefinition $module): void
    {
        $relative = $module->relativeRoot($this->appRoot());

        $this->line('  1. composer.json - register the path repository and require the package:');
        $this->line('       "repositories": [{ "type": "path", "url": "'.$relative.'" }]');
        $this->line('       "require": { "'.$module->package().'": "@dev" }');
        $this->line('     then: composer update '.$module->package());
        $this->newLine();

        $this->line('  2. package.json - link the page kit:');
        $this->line('       "'.$module->npmPackage().'": "link:'.$relative.'"');
        $this->line('     then: pnpm install   (or: npm install)');
        $this->newLine();

        $this->line('  3. bootstrap/providers.php - add:');
        $this->line('       '.$module->providerFqcn().'::class,');
        $this->newLine();

        $this->line('  4. config/features.php - register and enable the feature:');
        $this->line('       \'providers\' => [ /* ... */ '.$module->providerClass().'::class ],');
        $this->line('       \'enabled\'   => [ /* ... */ \''.$module->slug.'\' => true ],');
        $this->newLine();

        $this->line('  5. resources/js/page-config.ts - spread the page kit into basePages:');
        $this->line('       import { '.$module->pagesFunction().' } from "'.$module->npmPackage().'/pages";');
        $this->line('       basePages: { ...registerCoreTemplates(), ...'.$module->pagesFunction().'() }');
    }

    /**
     * Absolute directory the module is generated into. A relative `--path` is
     * resolved against the app root; an absolute one is used verbatim so a
     * module can be scaffolded straight into its own standalone checkout.
     */
    protected function resolveRoot(string $path): string
    {
        $path = trim($path);

        if ($path === '') {
            $path = 'modules';
        }

        $path = str_replace('\\', '/', $path);

        if (preg_match('/^([A-Za-z]:\/|\/)/', $path) === 1) {
            return rtrim($path, '/');
        }

        return rtrim($this->appRoot(), '/').'/'.trim($path, '/');
    }

    protected function appRoot(): string
    {
        return str_replace('\\', '/', base_path());
    }

    /**
     * An option as a trimmed non-empty string, or null.
     */
    protected function stringOption(string $key): ?string
    {
        $value = $this->option($key);

        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
