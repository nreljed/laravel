<?php

namespace Nreljed\Platform\Tests;

use Nreljed\Platform\Support\RoleCatalogSynchroniser;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Structural contract for catalog reconciliation.
 *
 * The rule these tests protect: an install that already ran a migration can
 * never pick up a changed body, so a catalog deny (`!slug`) has to ship as a
 * new dated migration. The shared synchroniser keeps both migrations honest.
 */
final class RoleCatalogSyncContractTest extends TestCase
{
    private const string PROVISIONING_MIGRATION = '2026_10_20_000001_provision_config_permissions_into_database.php';

    private const string SYNC_MIGRATION = '2026_10_26_000001_sync_catalog_role_permissions.php';

    public function test_the_synchroniser_exposes_a_single_entry_point(): void
    {
        $reflection = new ReflectionClass(RoleCatalogSynchroniser::class);

        $this->assertTrue($reflection->isFinal());
        $this->assertTrue($reflection->hasMethod('sync'));
        $this->assertSame('array', (string) $reflection->getMethod('sync')->getReturnType());
    }

    public function test_both_catalog_migrations_delegate_to_the_synchroniser(): void
    {
        foreach ([self::PROVISIONING_MIGRATION, self::SYNC_MIGRATION] as $migration) {
            $path = dirname(__DIR__).'/database/migrations/'.$migration;

            $this->assertFileExists($path);

            $source = (string) file_get_contents($path);

            $this->assertStringContainsString(
                'RoleCatalogSynchroniser::class',
                $source,
                "{$migration} must delegate to the shared synchroniser.",
            );
            $this->assertStringNotContainsString(
                'resolvePermissionIds',
                $source,
                "{$migration} must not duplicate the grant resolution.",
            );
        }
    }

    public function test_the_sync_migration_runs_after_the_provisioning_migration(): void
    {
        $this->assertGreaterThan(
            self::PROVISIONING_MIGRATION,
            self::SYNC_MIGRATION,
            'A migration that fixes an already-run migration must sort after it.',
        );
    }

    public function test_the_sync_migration_never_revokes_through_a_down_method(): void
    {
        $source = (string) file_get_contents(
            dirname(__DIR__).'/database/migrations/'.self::SYNC_MIGRATION,
        );

        $this->assertStringContainsString('public function down(): void', $source);
        $this->assertStringNotContainsString('detach', $source);
    }
}
