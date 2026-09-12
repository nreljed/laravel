<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Nreljed\Platform\Models\Role;
use Nreljed\Platform\Models\User;

/**
 * Demo role users used for local development/staging.
 *
 * This is a demo tier seeder — it is NOT part of the baseline reset that
 * production runs. Database resets in production only recreate the admin
 * account (via app:create-admin) plus core roles/permissions/settings.
 */
class DemoUsersSeeder extends Seeder
{
    public function run(): void
    {
        $roleUsers = [
            ['username' => 'editor', 'name' => 'Editor User', 'email' => 'nreljed@outlook.com', 'role' => 'editor'],
            ['username' => 'author', 'name' => 'Author User', 'email' => 'neosavedme@gmail.com', 'role' => 'author'],
            ['username' => 'user', 'name' => 'User Test', 'email' => 'nreljed@gmail.tn', 'role' => 'user'],
            ['username' => 'nstudio', 'name' => 'nstudio webdesign', 'email' => 'nstudio.webdesign@gmail.com', 'role' => 'admin'],
        ];

        foreach ($roleUsers as $user) {
            $model = User::firstOrCreate(
                ['username' => $user['username']],
                [
                    'name' => $user['name'],
                    'email' => $user['email'],
                    'password' => Hash::make('azerty123/*'),
                ],
            );

            $role = Role::where('slug', $user['role'])->first();
            if ($role && ! $model->roles()->where('roles.id', $role->id)->exists()) {
                $model->roles()->attach($role->id);
            }
        }
    }
}
