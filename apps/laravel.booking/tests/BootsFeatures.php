<?php

namespace Tests;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Nreljed\BookingModule\Booking\BookingServiceProvider;
use Nreljed\BookingModule\Properties\PropertiesServiceProvider;
use Nreljed\Platform\Services\PermissionCacheService;

trait BootsFeatures
{
    /** @var array<int, class-string> */
    protected array $featureProviders = [
        PropertiesServiceProvider::class,
        BookingServiceProvider::class,
    ];

    protected function bootFeatures(): void
    {
        app(RolesAndPermissionsSeeder::class)->run();

        app('router')->getRoutes()->refreshNameLookups();

        // Collect permission slugs from all registered feature providers
        $featureSlugs = [];
        foreach ($this->featureProviders as $providerClass) {
            $provider = app()->getProvider($providerClass);
            if ($provider && method_exists($provider, 'permissions')) {
                foreach ($provider->permissions() as $perm) {
                    $featureSlugs[] = $perm['slug'];
                }
            }
        }

        // Create feature permissions in DB (not done by seeders)
        $existing = Permission::pluck('slug')->toArray();
        foreach ($featureSlugs as $slug) {
            if (! in_array($slug, $existing)) {
                Permission::create([
                    'name' => $slug,
                    'slug' => $slug,
                    'description' => '',
                    'guard_name' => 'web',
                ]);
                $existing[] = $slug;
            }
        }

        // Ensure manage_options permission exists
        if (! in_array('manage_options', $existing)) {
            Permission::create([
                'name' => 'Manage Options',
                'slug' => 'manage_options',
                'description' => 'Access application settings.',
                'guard_name' => 'web',
            ]);
        }

        $allPermIds = Permission::all()->pluck('id');
        $featurePermIds = Permission::whereIn('slug', $featureSlugs)->pluck('id');

        // Admin gets ALL permissions (production: Gate::before + permissions:sync)
        $adminRole = Role::where('slug', 'admin')->first();
        if ($adminRole) {
            $adminRole->permissions()->sync($allPermIds);
        }

        $this->clearPermissionCache();
    }

    protected function createUserWithRole(string $roleSlug): mixed
    {
        $user = User::factory()->create();
        $user->assignRole($roleSlug);
        $this->clearUserPermissionCache($user->id);

        return $user;
    }

    protected function clearPermissionCache(): void
    {
        app(PermissionCacheService::class)->flushAll();
    }

    protected function clearUserPermissionCache(int $userId): void
    {
        app(PermissionCacheService::class)->forgetUser($userId);
    }
}
