<?php

namespace Nreljed\DashboardCore\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;
use Nreljed\DashboardCore\Models\User;
use Nreljed\DashboardCore\Services\FeatureRegistry;
use Nreljed\DashboardCore\Services\PermissionCacheService;

class PermissionGateServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->registerPermissionGates();
    }

    protected function registerPermissionGates(): void
    {
        $permissions = $this->getAllPermissionSlugs();

        foreach ($permissions as $slug) {
            Gate::define($slug, fn (User $user) => $user->hasPermission($slug));
        }
    }

    protected function getAllPermissionSlugs(): array
    {
        try {
            $slugs = [];

            if ($this->app->resolved(FeatureRegistry::class)) {
                $registry = $this->app->make(FeatureRegistry::class);
                foreach ($registry->permissions() as $perm) {
                    $slugs[] = $perm['slug'];
                }
            }

            try {
                $slugs = array_merge($slugs, array_values(app(PermissionCacheService::class)->getAllPermissions()));
            } catch (\Throwable) {
            }

            return array_unique($slugs);
        } catch (\Throwable) {
            return [];
        }
    }
}
