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

        // Admin
        $admin = User::factory()->create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
        ]);
        $admin->roles()->attach(Role::where('slug', 'admin')->first()->id);

        // Editor
        User::factory()->create([
            'name' => 'Editor User',
            'email' => 'editor@example.com',
        ])->roles()->attach(
            Role::where('slug', 'editor')->first()->id
        );

        // Author
        User::factory()->create([
            'name' => 'Author User',
            'email' => 'author@example.com',
        ])->roles()->attach(
            Role::where('slug', 'author')->first()->id
        );

        // User
        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ])->roles()->attach(
            Role::where('slug', 'user')->first()->id
        );

        User::factory()->create([
            'name' => 'Studio Admin',
            'email' => 'nstudio.webdesign@gmail.com',
            'password' => Hash::make('azerty123/*'),
        ])->roles()->attach(
            Role::where('slug', 'admin')->first()->id
        );

        $this->call(SettingsSeeder::class);
    }
}
