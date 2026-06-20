<?php

namespace App\Console\Commands;

use App\Models\Permission;
use App\Models\Role;
use App\Modules\ModuleRegistry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class SyncModulePermissions extends Command
{
    protected $signature = 'modules:sync-permissions';

    protected $description = 'Sync module permissions into the permissions table and assign to crm_user role';

    public function handle(ModuleRegistry $registry): int
    {
        if (!Schema::hasTable('permissions')) {
            $this->error('Permissions table does not exist. Run migrations first.');

            return Command::FAILURE;
        }

        $existing = Permission::pluck('slug')->toArray();
        $synced = 0;

        foreach ($registry->all() as $module) {
            foreach ($module->permissions() as $perm) {
                if (!in_array($perm['slug'], $existing)) {
                    Permission::create([
                        'name' => $perm['name'],
                        'slug' => $perm['slug'],
                        'description' => $perm['description'] ?? '',
                        'guard_name' => 'web',
                    ]);
                    $this->line("  Created: {$perm['slug']}");
                    $synced++;
                }
            }
        }

        $this->info("Synced {$synced} new permission(s).");

        // Auto-assign all module permissions to crm_user role
        $crmUser = Role::where('slug', 'crm_user')->first();
        if ($crmUser) {
            $moduleSlugs = [];
            foreach ($registry->all() as $module) {
                foreach ($module->permissions() as $perm) {
                    $moduleSlugs[] = $perm['slug'];
                }
            }
            $modulePerms = Permission::whereIn('slug', $moduleSlugs)->pluck('id');
            $existingIds = $crmUser->permissions()->pluck('permissions.id')->toArray();
            $newIds = $modulePerms->diff($existingIds)->values()->toArray();

            if (!empty($newIds)) {
                $crmUser->permissions()->attach($newIds);
                $this->line('  Assigned ' . count($newIds) . ' module permission(s) to crm_user role.');
            } else {
                $this->line('  No new module permissions to assign to crm_user role.');
            }
        }

        return Command::SUCCESS;
    }
}
