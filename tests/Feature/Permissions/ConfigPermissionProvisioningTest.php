<?php

namespace Tests\Feature\Permissions;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Nreljed\Platform\Models\Permission;
use Nreljed\Platform\Models\Role;
use Nreljed\Platform\Services\PermissionCacheService;
use Tests\Concerns\CreatesTestUsers;
use Tests\TestCase;

/**
 * A consumer deploy runs `migrate`, never the seeder, so a permission
 * introduced by a new core release has no row until somebody seeds by hand.
 *
 * `CheckPermission` reads an explicit slug list and never passes through
 * `Gate::before`, so admins get no wildcard bypass on route middleware — an
 * unprovisioned permission 403s the route for everyone, admins included. This
 * is exactly what happened to `manage-submissions`.
 */
class ConfigPermissionProvisioningTest extends TestCase
{
    use CreatesTestUsers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();
    }

    public function test_migrating_backfills_a_permission_the_seeder_never_ran(): void
    {
        $this->simulateAnUnseededEnvironment('manage-submissions');

        $admin = Role::where('slug', 'admin')->firstOrFail();
        $this->assertFalse($admin->hasPermission('manage-submissions'));

        $this->runProvisioningMigration();

        $this->assertDatabaseHas('permissions', ['slug' => 'manage-submissions']);
        $this->assertTrue($admin->hasPermission('manage-submissions'));
    }

    public function test_provisioning_is_idempotent(): void
    {
        $before = Permission::count();

        $this->runProvisioningMigration();
        $this->runProvisioningMigration();

        $this->assertSame($before, Permission::count());
    }

    public function test_provisioning_grants_permissions_to_every_config_role_that_wants_them(): void
    {
        $this->simulateAnUnseededEnvironment('posts.view');

        $this->runProvisioningMigration();

        $editor = Role::where('slug', 'editor')->firstOrFail();
        $admin = Role::where('slug', 'admin')->firstOrFail();

        $this->assertTrue($admin->hasPermission('posts.view'));
        $this->assertTrue($editor->hasPermission('posts.view'));
    }

    public function test_provisioning_revokes_an_excluded_permission_a_catch_all_role_still_carries(): void
    {
        $permission = Permission::where('slug', 'manage-contact')->firstOrFail();
        $editor = Role::where('slug', 'editor')->firstOrFail();

        $editor->permissions()->attach($permission->id);
        app(PermissionCacheService::class)->flushAll();

        $this->assertTrue($editor->hasPermission('manage-contact'));

        $this->runProvisioningMigration();

        $this->assertFalse($editor->fresh()->hasPermission('manage-contact'));
        $this->assertTrue(Role::where('slug', 'admin')->firstOrFail()->hasPermission('manage-contact'));
    }

    public function test_provisioning_honours_the_excluded_slugs_in_a_catch_all_role(): void
    {
        $this->simulateAnUnseededEnvironment('manage-submissions');

        $this->runProvisioningMigration();

        $editor = Role::where('slug', 'editor')->firstOrFail();

        $this->assertTrue($editor->hasPermission('posts.delete'));
        $this->assertFalse($editor->hasPermission('manage-submissions'));
        $this->assertFalse($editor->hasPermission('manage-contact'));
        $this->assertFalse($editor->hasPermission('manage-users'));
    }

    public function test_provisioning_never_detaches_a_grant_an_operator_added_by_hand(): void
    {
        $permission = Permission::where('slug', 'manage-users')->firstOrFail();

        $role = Role::create([
            'name' => 'Support',
            'slug' => 'support',
            'description' => 'Hand-made role outside the config catalog.',
            'guard_name' => 'web',
        ]);

        $role->permissions()->attach($permission->id);
        app(PermissionCacheService::class)->flushAll();

        $this->assertTrue($role->hasPermission('manage-users'));

        $this->runProvisioningMigration();

        $this->assertTrue($role->fresh()->hasPermission('manage-users'));
    }

    /**
     * A production install shipped the 2026_10_20 provisioning migration with
     * `manage-contact` still granted through `'*'`. Migrations run once, so
     * editing that file can never reach prod; the later sync migration is the
     * only thing that can revoke the stale grant.
     */
    public function test_the_catalog_sync_migration_revokes_a_stale_grant_on_an_already_migrated_install(): void
    {
        $permission = Permission::where('slug', 'manage-contact')->firstOrFail();
        $editor = Role::where('slug', 'editor')->firstOrFail();

        $editor->permissions()->attach($permission->id);
        app(PermissionCacheService::class)->flushAll();

        $this->assertTrue($editor->hasPermission('manage-contact'));

        $this->runCatalogSyncMigration();

        $this->assertFalse($editor->fresh()->hasPermission('manage-contact'));
        $this->assertTrue(Role::where('slug', 'admin')->firstOrFail()->hasPermission('manage-contact'));
    }

    public function test_the_catalog_sync_migration_is_idempotent(): void
    {
        $this->runCatalogSyncMigration();
        $this->runCatalogSyncMigration();

        $editor = Role::where('slug', 'editor')->firstOrFail();

        $this->assertFalse($editor->hasPermission('manage-contact'));
        $this->assertFalse($editor->hasPermission('manage-submissions'));
        $this->assertTrue($editor->hasPermission('posts.edit'));
    }

    /**
     * Consumers run their own database/migrations (load_migrations is false),
     * so the package migration only reaches a deployed app if it is copied in.
     */
    public function test_the_catalog_sync_migration_is_published_into_the_app_migration_path(): void
    {
        $this->assertFileExists(
            database_path('migrations/2026_10_26_000001_sync_catalog_role_permissions.php'),
        );
    }

    public function test_an_admin_can_open_the_submissions_inbox_after_provisioning(): void
    {
        $this->simulateAnUnseededEnvironment('manage-submissions');
        $this->runProvisioningMigration();

        $response = $this->actingAs($this->admin())
            ->get(route('dashboard.admin.submissions.index'));

        $response->assertOk();
    }

    /**
     * Drop the permission row and every grant for it, exactly as an
     * environment that only ever ran `migrate` before the release would look.
     */
    protected function simulateAnUnseededEnvironment(string $slug): void
    {
        Permission::where('slug', $slug)->delete();

        app(PermissionCacheService::class)->flushAll();
    }

    protected function runProvisioningMigration(): void
    {
        $this->runMigrationFile('2026_10_20_000001_provision_config_permissions_into_database.php');
    }

    protected function runCatalogSyncMigration(): void
    {
        $this->runMigrationFile('2026_10_26_000001_sync_catalog_role_permissions.php');
    }

    protected function runMigrationFile(string $filename): void
    {
        $path = database_path('migrations/'.$filename);

        $this->assertFileExists($path);

        /** @var Migration $migration */
        $migration = require $path;

        $migration->up();
    }
}
