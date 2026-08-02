<?php

namespace Tests\Concerns;

use Database\Seeders\RolesAndPermissionsSeeder;
use Nreljed\DashboardCore\Models\Role;
use Nreljed\DashboardCore\Models\User;

trait CreatesTestUsers
{
    protected function admin(): User
    {
        $user = User::factory()->create();
        $role = Role::where('slug', 'admin')->first();
        $user->roles()->attach($role);

        return $user;
    }

    protected function editor(): User
    {
        $user = User::factory()->create();
        $role = Role::where('slug', 'editor')->first();
        $user->roles()->attach($role);

        return $user;
    }

    protected function contributor(): User
    {
        $user = User::factory()->create();
        $role = Role::where('slug', 'author')->first();
        $user->roles()->attach($role);

        return $user;
    }

    protected function regularUser(): User
    {
        $user = User::factory()->create();
        $role = Role::where('slug', 'user')->first();
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
