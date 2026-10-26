<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Nreljed\Platform\Models\Role;
use Tests\TestCase;

/**
 * `php artisan db:seed` must be safe to run more than once.
 *
 * It used to abort on the second run with a duplicate key error, because every
 * demo account was created unconditionally. That is exactly the situation a
 * deploy or a manual re-seed hits: the first run leaves rows behind, the
 * second one crashes and anyone watching the deploy thinks the seed step is
 * broken. The seeder now reuses existing accounts and only attaches the role
 * when it is missing - both directions are asserted here, plus the production
 * guard that keeps demo accounts out of a live database.
 */
class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<string> */
    private const DEMO_EMAILS = [
        'nstudio.webdesign@gmail.com',
        'shopowner@example.com',
        'shopmanager@example.com',
        'customer@example.com',
    ];

    public function test_the_seeder_can_run_twice_without_creating_duplicates(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(4, User::whereIn('email', self::DEMO_EMAILS)->count());

        foreach (self::DEMO_EMAILS as $email) {
            $user = User::where('email', $email)->firstOrFail();

            $this->assertSame(
                1,
                $user->roles()->count(),
                "{$email} must hold exactly one role after two seed runs.",
            );
        }
    }

    public function test_the_second_run_restores_a_missing_role_assignment(): void
    {
        $this->seed(DatabaseSeeder::class);

        $user = User::where('email', 'customer@example.com')->firstOrFail();
        $user->roles()->detach();

        $this->seed(DatabaseSeeder::class);

        $this->assertTrue(
            $user->fresh()->hasRole('customer'),
            'A re-seed must re-attach the role it owns.',
        );
    }

    public function test_demo_accounts_are_skipped_in_production(): void
    {
        $this->app['env'] = 'production';

        try {
            // `--force` mirrors how the deploy and the maintenance reset run
            // seeders in production; without it `db:seed` stops for a
            // "really run in production?" confirmation.
            $this->artisan('db:seed', ['--class' => DatabaseSeeder::class, '--force' => true]);
        } finally {
            $this->app['env'] = 'testing';
        }

        $this->assertSame(0, User::whereIn('email', self::DEMO_EMAILS)->count());

        // The non-demo baseline must still seed, otherwise a production
        // database would come out of `db:seed` without roles to assign.
        $this->assertGreaterThan(0, Role::count());
    }
}
