<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Nreljed\Platform\Database\Seeders\SettingsSeeder;
use Nreljed\Platform\Models\Role;
use Nreljed\ShopModule\Database\Seeders\CurrencySeeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Demo accounts for local/testing environments. Production gets its admin
     * from `php artisan app:create-admin` (deploy) or the database maintenance
     * reset flow - never from this list.
     *
     * @var list<array{name: string, email: string, username: string, role: string}>
     */
    private const DEMO_USERS = [
        ['name' => 'nstudio', 'email' => 'nstudio.webdesign@gmail.com', 'username' => 'nstudio', 'role' => 'admin'],
        ['name' => 'shop_owner', 'email' => 'shopowner@example.com', 'username' => 'shop_owner', 'role' => 'shop_owner'],
        ['name' => 'shop_manager', 'email' => 'shopmanager@example.com', 'username' => 'shop_manager', 'role' => 'shop_manager'],
        ['name' => 'customer', 'email' => 'customer@example.com', 'username' => 'customer', 'role' => 'customer'],
    ];

    public function run(): void
    {
        $this->call(RolesAndPermissionsSeeder::class);

        $this->seedDemoUsers();

        $this->call(SettingsSeeder::class);
        $this->call(CurrencySeeder::class);

        // Shop demo catalog (brands, categories, tags, attributes, products,
        // prices, reviews). Must run before GenerateDemoData, which assumes
        // published products already exist.
        $this->call(ShopProductSeeder::class);

        // Comprehensive e-commerce demo data (orders, coupons, shipping, tax,
        // webhooks, stock history, blog posts).
        $this->call(DemoDataSeeder::class);

        // Marketing demo data (campaigns, contacts, segments, workflows,
        // social accounts/posts, plans, briefs, links).
        $this->call(MarketingDemoSeeder::class);

        // Sync shop customers into the marketing audience so campaigns can
        // target real buyers (not just manually-entered contacts).
        Artisan::call('marketing:sync-customers');

        // Process shop orders so order-placed workflows fire and purchase data
        // is recorded. Clear the cache first so orders handled by the real-time
        // observer are re-evaluated against the workflows created above.
        Cache::forget('marketing.last_processed_order_id');
        Artisan::call('marketing:process-orders');

        // Re-sync roles/permissions last: feature providers declare permissions
        // that may not all be registered during the first sync (fresh install).
        $this->call(RolesAndPermissionsSeeder::class);
    }

    /**
     * Seed the demo accounts idempotently.
     *
     * A seeder must be safe to re-run: `db:seed` used to fail with a duplicate
     * key error on the second run because every account was created
     * unconditionally. Existing accounts are now reused and their role is
     * attached only when missing.
     */
    private function seedDemoUsers(): void
    {
        if (app()->isProduction()) {
            $this->command?->warn('DatabaseSeeder: skipping demo users in production (use app:create-admin).');

            return;
        }

        $password = Hash::make('azerty123/*');

        foreach (self::DEMO_USERS as $spec) {
            $role = Role::where('slug', $spec['role'])->first();

            if (! $role) {
                $this->command?->warn("DatabaseSeeder: role [{$spec['role']}] is missing; skipping {$spec['email']}.");

                continue;
            }

            $user = User::firstOrCreate(
                ['email' => $spec['email']],
                [
                    'name' => $spec['name'],
                    'username' => $spec['username'],
                    'password' => $password,
                    'email_verified_at' => now(),
                ],
            );

            if (! $user->hasRole($spec['role'])) {
                $user->assignRole($spec['role']);
            }
        }
    }
}
