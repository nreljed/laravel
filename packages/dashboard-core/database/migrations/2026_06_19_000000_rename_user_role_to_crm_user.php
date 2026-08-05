<?php

use Illuminate\Database\Migrations\Migration;
use Nreljed\DashboardCore\Models\Permission;
use Nreljed\DashboardCore\Models\Role;
use Nreljed\DashboardCore\Services\FeatureRegistry;

return new class extends Migration
{
    public function up(): void
    {
        Role::where('slug', 'user')->update([
            'slug' => 'crm_user',
            'name' => 'CRM User',
            'description' => 'Standard user: modules + profile + notifications',
        ]);

        // Ensure crm_user role gets all module permissions
        if (app()->resolved(FeatureRegistry::class)) {
            $registry = app(FeatureRegistry::class);
            $crmUser = Role::where('slug', 'crm_user')->first();
            if ($crmUser) {
                $moduleSlugs = [];
                foreach ($registry->allProviders() as $module) {
                    foreach ($module->permissions() as $perm) {
                        $moduleSlugs[] = $perm['slug'];
                    }
                }
                $modulePerms = Permission::whereIn('slug', $moduleSlugs)->pluck('id');
                $existingIds = $crmUser->permissions()->pluck('permissions.id')->toArray();
                $newIds = $modulePerms->diff($existingIds)->values()->toArray();
                if (! empty($newIds)) {
                    $crmUser->permissions()->attach($newIds);
                }
            }
        }
    }

    public function down(): void
    {
        Role::where('slug', 'crm_user')->update([
            'slug' => 'user',
            'name' => 'User',
            'description' => 'Basic access: dashboard, profile, notifications',
        ]);
    }
};
