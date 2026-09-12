<?php

/**
 * Reset all non-admin user data for fresh testing.
 *
 * Usage: php scripts/reset-users-data.php
 *
 * - Preserves admin users
 * - Deletes all other users (cascades to posts, audit_logs, etc.)
 * - Clears session table
 */

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Nreljed\Platform\Models\User;

echo "=== User Data Reset ===\n\n";

// 1. Find admin user IDs to preserve
$adminIds = DB::table('role_user')
    ->whereIn('role_id', function ($q) {
        $q->select('id')->from('roles')->where('slug', 'admin');
    })
    ->pluck('user_id')
    ->toArray();

echo 'Preserving '.count($adminIds)." admin user(s)...\n";

// 2. Delete all non-admin users
$nonAdminQuery = User::whereNotIn('id', $adminIds);
$count = $nonAdminQuery->count();
echo "Deleting {$count} non-admin user(s)...\n";

// CASCADE handles: posts, audit_logs, notification_preferences, role_user, permission_user
$nonAdminQuery->delete();

// 3. Clear sessions
if (Schema::hasTable('sessions')) {
    DB::table('sessions')->truncate();
    echo "Cleared sessions table.\n";
}

echo "\nDone! Users remaining: ".User::count()."\n";
