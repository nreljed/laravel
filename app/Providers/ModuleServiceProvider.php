<?php

namespace App\Providers;

use App\Modules\BookableRegistry;
use App\Modules\ModuleLoader;
use App\Modules\ModuleRegistry;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class ModuleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ModuleRegistry::class, function () {
            return new ModuleRegistry;
        });

        $this->app->singleton(ModuleLoader::class, function ($app) {
            return new ModuleLoader($app[ModuleRegistry::class]);
        });

        $this->app->singleton(BookableRegistry::class, function () {
            return new BookableRegistry;
        });
    }

    public function boot(): void
    {
        $loader = $this->app[ModuleLoader::class];
        $loader->load();

        // Load all discovered module migration paths regardless of DB state,
        // so that RefreshDatabase in tests can pick them up.
        foreach ($this->app[ModuleRegistry::class]->all() as $module) {
            foreach ($module->migrationPaths() as $path) {
                if (is_dir($path)) {
                    $this->loadMigrationsFrom($path);
                }
            }
        }

        if (! Schema::hasTable('modules')) {
            return;
        }

        $registry = $this->app[ModuleRegistry::class];
        $registry->bootEnabled();
        $registry->registerRoutes();
    }
}
