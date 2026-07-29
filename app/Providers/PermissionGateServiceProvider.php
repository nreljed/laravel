<?php

namespace App\Providers;

use App\Models\Permission;
use App\Models\User;
use App\Services\FeatureRegistry;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

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
            Gate::define($slug, function (User $user) use ($slug) {
                return $user->hasPermission($slug);
            });
        }
    }

    protected function getAllPermissionSlugs(): array
    {
        try {
            $corePermissions = [
                'manage-users',
                'manage-settings',
                'manage-roles',
                'view-system-health',
                'manage_options',
                'view-dashboard',
                'view-profile',
                'edit-profile',
                'delete-account',
                'view-notifications',
                'manage-notifications',
            ];

            $featureSlugs = [];

            if ($this->app->resolved(FeatureRegistry::class)) {
                $registry = $this->app->make(FeatureRegistry::class);
                foreach ($registry->permissions() as $perm) {
                    $featureSlugs[] = $perm['slug'];
                }
            }

            $dbSlugs = [];
            try {
                $dbSlugs = Permission::pluck('slug')->toArray();
            } catch (\Throwable) {
            }

            return array_unique(array_merge($corePermissions, $featureSlugs, $dbSlugs));
        } catch (\Throwable) {
            return [];
        }
    }
}
