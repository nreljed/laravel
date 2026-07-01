<?php

namespace App\Console\Commands;

use App\Models\Permission;
use App\Models\Role;
use App\Services\PermissionCacheService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class CreateRole extends Command
{
    protected $signature = 'roles:create
        {name : The display name of the role}
        {--permissions= : Comma-separated list of permission slugs to assign}
        {--description= : Description of the role}';

    protected $description = 'Create a new role with optional permissions';

    public function handle(PermissionCacheService $cache): int
    {
        if (! Schema::hasTable('roles')) {
            $this->error('Roles table does not exist. Run migrations first.');

            return Command::FAILURE;
        }

        $name = $this->argument('name');
        $slug = str($name)->slug('_')->toString();

        if (Role::where('slug', $slug)->exists()) {
            $this->error("Role with slug '{$slug}' already exists.");

            return Command::FAILURE;
        }

        $role = Role::create([
            'name' => $name,
            'slug' => $slug,
            'description' => $this->option('description'),
        ]);

        $this->info("Role '{$name}' created with slug '{$slug}'.");

        if ($perms = $this->option('permissions')) {
            $slugs = explode(',', $perms);
            $permissionIds = Permission::whereIn('slug', $slugs)->pluck('id')->toArray();

            if (count($permissionIds) !== count($slugs)) {
                $missing = array_diff($slugs, Permission::whereIn('slug', $slugs)->pluck('slug')->toArray());
                $this->warn('Some permissions not found: '.implode(', ', $missing));
            }

            $role->permissions()->sync($permissionIds);
            $this->info('Assigned '.count($permissionIds).' permission(s) to role.');
        }

        $cache->flushAll();

        return Command::SUCCESS;
    }
}
