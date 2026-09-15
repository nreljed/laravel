<?php

/**
 * Reset all non-admin user data for fresh testing.
 *
 * Usage: php scripts/reset-users-data.php
 *
 * - Preserves admin users
 * - Deletes all other users (cascades to properties, bookings, posts, carts, etc.)
 * - Deletes orphaned orders (user_id set to null by CASCADE)
 * - Nullifies products.created_by
 * - Clears session table
 */

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Nreljed\ShopModule\Models\Order;
use Nreljed\ShopModule\Models\Product;

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

// CASCADE handles: properties, bookings, posts, audit_logs,
// notification_preferences, role_user, permission_user, carts
$nonAdminQuery->delete();

// 3. Clean up orphaned orders (SET NULL user_ids)
$orphanOrders = Order::whereNull('user_id')->orWhereNull('processed_by')->delete();
echo "Deleted orphaned orders.\n";

// 4. Nullify products.created_by for deleted users
Product::whereNotNull('created_by')
    ->whereNotIn('created_by', function ($q) {
        $q->select('id')->from('users');
    })
    ->update(['created_by' => null]);
echo "Cleaned up products.created_by.\n";

// 5. Clear sessions
if (Schema::hasTable('sessions')) {
    DB::table('sessions')->truncate();
    echo "Cleared sessions table.\n";
}

echo "\nDone! Users remaining: ".User::count()."\n";
