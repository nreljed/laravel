<?php

namespace App\Providers;

use App\Modules\ModuleLoader;
use App\Modules\ModuleRegistry;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class ModuleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ModuleRegistry::class, function () {
            return new ModuleRegistry();
        });

        $this->app->singleton(ModuleLoader::class, function ($app) {
            return new ModuleLoader($app[ModuleRegistry::class]);
        });
    }

    public function boot(): void
    {
        $loader = $this->app[ModuleLoader::class];
        $loader->load();

        if (!Schema::hasTable('modules')) {
            return;
        }

        $registry = $this->app[ModuleRegistry::class];
        $registry->bootEnabled();
        $registry->registerRoutes();

        foreach ($registry->getBooted() as $module) {
            foreach ($module->migrationPaths() as $path) {
                if (is_dir($path)) {
                    $this->loadMigrationsFrom($path);
                }
            }

            foreach ($module->permissions() as $permission) {
                Gate::define($permission['slug'], function ($user) use ($permission) {
                    return $user->hasPermission($permission['slug']);
                });
            }
        }
    }
}
