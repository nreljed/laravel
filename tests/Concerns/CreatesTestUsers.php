<?php

namespace Tests\Concerns;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

trait CreatesTestUsers
{
    protected function admin(): User
    {
        $user = User::factory()->create();
        $role = Role::where('slug', 'admin')->first();
        $user->roles()->attach($role);

        return $user;
    }

    protected function regularUser(): User
    {
        $user = User::factory()->create();
        $role = Role::where('slug', 'crm_user')->first();
        $user->roles()->attach($role);

        return $user;
    }

    protected function createUserWithRole(string $roleSlug): User
    {
        $user = User::factory()->create();
        $role = Role::where('slug', $roleSlug)->first();
        $user->roles()->attach($role);

        return $user;
    }

    protected function seedRolesAndPermissions(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
    }
}
