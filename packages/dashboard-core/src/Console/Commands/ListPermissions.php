<?php

namespace Nreljed\DashboardCore\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Nreljed\DashboardCore\Models\Permission;
use Nreljed\DashboardCore\Models\Role;

class ListPermissions extends Command
{
    protected $signature = 'permissions:list
        {--role= : Filter by role slug}
        {--group= : Filter by group prefix (e.g. "posts")}
        {--format=table : Output format: table or json}';

    protected $description = 'List all permissions with role assignments';

    public function handle(): int
    {
        if (! Schema::hasTable('permissions')) {
            $this->error('Permissions table does not exist.');

            return Command::FAILURE;
        }

        $query = Permission::with('roles')->orderBy('slug');

        if ($roleSlug = $this->option('role')) {
            $role = Role::where('slug', $roleSlug)->first();
            if (! $role) {
                $this->error("Role '{$roleSlug}' not found.");

                return Command::FAILURE;
            }
            $query->whereHas('roles', fn ($q) => $q->where('roles.id', $role->id));
            $this->info("Permissions for role: {$role->name} ({$role->slug})");
        }

        if ($group = $this->option('group')) {
            $query->where('slug', 'like', "{$group}.%");
        }

        $permissions = $query->get();

        if ($this->option('format') === 'json') {
            $this->line($permissions->toJson(JSON_PRETTY_PRINT));

            return Command::SUCCESS;
        }

        if ($permissions->isEmpty()) {
            $this->warn('No permissions found.');

            return Command::SUCCESS;
        }

        $headers = ['ID', 'Name', 'Slug', 'Description', 'Roles'];
        $rows = $permissions->map(fn ($p) => [
            $p->id,
            $p->name,
            $p->slug,
            $p->description ?? '',
            $p->roles->pluck('slug')->implode(', '),
        ])->toArray();

        $this->table($headers, $rows);
        $this->line("Total: {$permissions->count()} permission(s).");

        return Command::SUCCESS;
    }
}
