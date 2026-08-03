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
        $this->call(BlogContentSeeder::class);

        // Editor
        User::factory()->create([
            'username' => 'editor',
            'name' => 'Editor User',
            'email' => 'nreljed@outlook.com',
            'password' => Hash::make('azerty123/*'),
        ])->roles()->attach(
            Role::where('slug', 'editor')->first()->id
        );

        // Author
        User::factory()->create([
            'username' => 'author',
            'name' => 'Author User',
            'email' => 'neosavedme@gmail.com',
            'password' => Hash::make('azerty123/*'),
        ])->roles()->attach(
            Role::where('slug', 'author')->first()->id
        );

        // User
        User::factory()->create([
            'username' => 'user',
            'name' => 'User Test',
            'email' => 'nreljed@gmail.tn',
            'password' => Hash::make('azerty123/*'),
        ])->roles()->attach(
            Role::where('slug', 'user')->first()->id
        );

        // Admin
        User::factory()->create([
            'username' => 'nstudio',
            'name' => 'nstudio webdesign',
            'email' => 'nstudio.webdesign@gmail.com',
            'password' => Hash::make('azerty123/*'),
        ])->roles()->attach(
            Role::where('slug', 'admin')->first()->id
        );

        $this->call(SettingsSeeder::class);
    }
}
