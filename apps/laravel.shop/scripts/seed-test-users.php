<?php

/**
 * Seed fresh test users for development/testing.
 *
 * Usage: php scripts/seed-test-users.php
 *
 * Creates:
 *   - test@example.com (shop_owner role, password: password)
 *   - admin@example.com (admin role, password: password)
 *   - nstudio.webdesign@gmail.com (admin role, password: azerty123/*)
 */

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Hash;
use Nreljed\Platform\Models\Role;

echo "=== Seeding Test Users ===\n\n";

$users = [
    ['name' => 'Test User', 'email' => 'test@example.com', 'password' => 'password', 'role' => 'shop_owner'],
    ['name' => 'Admin User', 'email' => 'admin@example.com', 'password' => 'password', 'role' => 'admin'],
    ['name' => 'Studio Admin', 'email' => 'nstudio.webdesign@gmail.com', 'password' => 'azerty123/*', 'role' => 'admin'],
];

foreach ($users as $data) {
    $role = Role::where('slug', $data['role'])->first();
    if (! $role) {
        echo "  SKIP: Role '{$data['role']}' not found. Run seeder first.\n";

        continue;
    }

    $user = User::firstOrCreate(
        ['email' => $data['email']],
        [
            'name' => $data['name'],
            'username' => $data['name'],
            'password' => Hash::make($data['password']),
            'email_verified_at' => now(),
        ]
    );

    if ($user->wasRecentlyCreated) {
        $user->roles()->sync([$role->id]);
        echo "  Created: {$data['email']} ({$data['role']})\n";
    } else {
        $user->roles()->sync([$role->id]);
        echo "  Updated: {$data['email']} → ({$data['role']})\n";
    }
}

echo "\nDone! Total users: ".User::count()."\n";
