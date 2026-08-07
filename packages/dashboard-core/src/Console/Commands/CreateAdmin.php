<?php

namespace Nreljed\DashboardCore\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Nreljed\DashboardCore\Models\Role;
use Nreljed\DashboardCore\Models\User;

class CreateAdmin extends Command
{
    protected $signature = 'app:create-admin
        {--email= : Admin email}
        {--password= : Admin password}
        {--name= : Admin display name}
        {--username= : Admin username (defaults to name)}';

    protected $description = 'Create or update the first admin user';

    public function handle(): int
    {
        if (! Schema::hasTable('users') || ! Schema::hasTable('roles')) {
            $this->error('Tables do not exist. Run migrations first.');

            return Command::FAILURE;
        }

        $email = $this->option('email') ?: config('admin.email');
        $password = $this->option('password') ?: config('admin.password');
        $name = $this->option('name') ?: config('admin.name');
        $username = $this->option('username') ?: config('admin.username') ?: $name;

        $existing = User::where('email', $email)->first();
        if ($existing && ! $password) {
            $this->info("Admin {$email} already exists, skipping.");

            return Command::SUCCESS;
        }

        if (! $password) {
            $this->error('Password is required.');

            return Command::FAILURE;
        }

        $role = Role::where('slug', 'admin')->first();
        if (! $role) {
            $this->error('admin role not found. Run php artisan db:seed --class=RolesAndPermissionsSeeder first.');

            return Command::FAILURE;
        }

        $user = User::updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'username' => $username,
                'password' => Hash::make($password),
            ]
        );

        $user->forceFill(['email_verified_at' => now()])->save();

        $user->roles()->syncWithoutDetaching([$role->id]);
        $user->invalidatePermissionCache();

        if ($user->wasRecentlyCreated) {
            $this->info("Admin user created: {$email}");
        } else {
            $this->info("Admin user updated: {$email}");
        }

        return Command::SUCCESS;
    }
}
