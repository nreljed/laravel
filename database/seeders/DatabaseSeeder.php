<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Nreljed\DashboardCore\Models\Role;
use Nreljed\DashboardCore\Models\User;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RolesAndPermissionsSeeder::class);

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

        $this->call(BlogContentSeeder::class);
        $this->call(SettingsSeeder::class);
    }
}
