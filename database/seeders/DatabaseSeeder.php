<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RolesAndPermissionsSeeder::class);
        $this->call(ModuleSeeder::class);
        $this->call(SettingsSeeder::class);
        $this->call(PropertyTermSeeder::class);

        $user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $user->roles()->attach(
            Role::where('slug', 'crm_user')->first()->id
        );

        User::factory()->create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
        ])->roles()->attach(
            Role::where('slug', 'admin')->first()->id
        );

        User::factory()->create([
            'name' => 'Studio Admin',
            'email' => 'nstudio.webdesign@gmail.com',
            'password' => Hash::make('azerty123/*'),
        ])->roles()->attach(
            Role::where('slug', 'admin')->first()->id
        );
    }
}
