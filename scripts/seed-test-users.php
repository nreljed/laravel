<?php

/**
 * Seed fresh test users for development/testing.
 *
 * Usage: php scripts/seed-test-users.php
 *
 * Creates:
 *   - nreljed@gmail.tn (user role, username: user, password: azerty123/*)
 *   - nreljed@outlook.com (editor role, username: editor, password: azerty123/*)
 *   - neosavedme@gmail.com (author role, username: author, password: azerty123/*)
 *   - nstudio.webdesign@gmail.com (admin role, username: nstudio, password: azerty123/*)
 */

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Hash;
use Nreljed\Platform\Models\Role;
use Nreljed\Platform\Models\User;

echo "=== Seeding Test Users ===\n\n";

$users = [
    ['name' => 'User Test', 'username' => 'user', 'email' => 'nreljed@gmail.tn', 'password' => 'azerty123/*', 'role' => 'user'],
    ['name' => 'Editor User', 'username' => 'editor', 'email' => 'nreljed@outlook.com', 'password' => 'azerty123/*', 'role' => 'editor'],
    ['name' => 'Author User', 'username' => 'author', 'email' => 'neosavedme@gmail.com', 'password' => 'azerty123/*', 'role' => 'author'],
    ['name' => 'nstudio webdesign', 'username' => 'nstudio', 'email' => 'nstudio.webdesign@gmail.com', 'password' => 'azerty123/*', 'role' => 'admin'],
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
            'username' => $data['username'] ?? $data['name'],
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
