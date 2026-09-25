<?php

namespace Nreljed\ShopModule\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Regression guard for the MySQL-only migration failure shipped in v0.1.58.
 *
 * `product_prices` needs a real foreign key on `product_variation_id` and on
 * `customer_group_id` so group overrides are removed with their parent. MySQL
 * refuses a foreign key on the base column of a STORED generated column
 * (error 138, reported as 1215 "Cannot add foreign key constraint"), so the
 * scope columns that normalise a nullable parent id to 0 have to be VIRTUAL.
 *
 * Both are computed on read and only ever used as index keys, so VIRTUAL
 * carries no cost. The full 199-test suite and 69 migration checks all pass on
 * sqlite, which is why this shipped green and only failed on the production
 * deploy; this test encodes the invariant so a future edit cannot reintroduce
 * it. `scripts/migration-check.php` runs the real migrations against MySQL when
 * MIGRATION_CHECK_DB_HOST is set.
 */
class GeneratedColumnForeignKeyTest extends TestCase
{
    private const FORBIDDEN = 'storedAs';

    /**
     * Columns that a foreign key points at, so their generated scope columns
     * must stay VIRTUAL.
     */
    private const FKEY_BASE_COLUMNS = ['product_variation_id', 'customer_group_id'];

    public function test_price_scope_columns_are_virtual_not_stored(): void
    {
        $migrations = glob(dirname(__DIR__).'/database/migrations/*.php') ?: [];
        $this->assertNotEmpty($migrations, 'no migrations found');

        $offenders = [];

        foreach ($migrations as $file) {
            $source = (string) file_get_contents($file);
            if (! str_contains($source, self::FORBIDDEN)) {
                continue;
            }

            // A STORED generated column only breaks MySQL when a foreign key
            // depends on the same table. Flag the combination and let a human
            // confirm; a STORED column on a table with no foreign keys is fine.
            foreach (self::FKEY_BASE_COLUMNS as $column) {
                if (str_contains($source, $column)) {
                    $offenders[] = sprintf(
                        '%s declares a foreign key on %s and uses %s(); use virtualAs()',
                        basename($file),
                        $column,
                        self::FORBIDDEN
                    );
                }
            }
        }

        $this->assertSame(
            [],
            $offenders,
            "MySQL rejects a foreign key on the base column of a STORED generated column.\n".implode("\n", $offenders)
        );
    }

    public function test_every_scope_column_is_declared_virtual(): void
    {
        $files = [
            '2026_09_22_000000_create_product_prices_table.php',
            '2026_09_26_000200_add_customer_group_id_to_product_prices_table.php',
        ];

        foreach ($files as $name) {
            $path = dirname(__DIR__).'/database/migrations/'.$name;
            $this->assertFileExists($path);

            $source = (string) file_get_contents($path);

            preg_match_all("/unsignedBigInteger\('(\w*(?:price|group)_scope)'\)->(\w+)\(/", $source, $matches, PREG_SET_ORDER);

            $this->assertNotEmpty($matches, "no generated scope columns found in {$name}");

            foreach ($matches as $match) {
                $this->assertSame(
                    'virtualAs',
                    $match[2],
                    "{$name}: {$match[1]} must use virtualAs(); STORED breaks the MySQL foreign key"
                );
            }
        }
    }
}
