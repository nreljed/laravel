<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * MySQL rejects identifiers longer than 64 characters.
 *
 * Laravel derives index and unique-index names by concatenating the table
 * name, the indexed columns and the index type. A long table name plus a
 * two-column index therefore produces a name SQLite happily accepts and
 * MySQL refuses, and the migration only fails once it runs against the
 * production database - after the release has already been unpacked.
 *
 * That is exactly how the marketing module broke production: several of its
 * tables paired long names with composite unique indexes, so `migrate` aborted
 * and every page that touched those tables returned 500. The fix was explicit
 * short index names; this test keeps the next long one from shipping.
 *
 * Migrations that pass an explicit name are skipped - the name is short by
 * construction, and the point here is the auto-generated ones.
 */
class MigrationIdentifierLengthTest extends TestCase
{
    private const MYSQL_IDENTIFIER_LIMIT = 64;

    public function test_auto_generated_index_names_fit_in_a_mysql_identifier(): void
    {
        $violations = [];

        foreach ($this->migrationFiles() as $file) {
            foreach ($this->longIndexNames($file) as $problem) {
                $violations[] = $problem;
            }
        }

        $this->assertSame(
            [],
            $violations,
            "MySQL rejects identifiers over 64 characters; give these indexes an explicit short name:\n"
            .implode("\n", $violations),
        );
    }

    /**
     * @return list<string>
     */
    private function migrationFiles(): array
    {
        $directories = [database_path('migrations')];

        foreach (glob(base_path('vendor/nreljed/*/database/migrations'), GLOB_ONLYDIR) ?: [] as $packageMigrations) {
            $directories[] = $packageMigrations;
        }

        $files = [];

        foreach ($directories as $directory) {
            foreach (glob($directory.'/*.php') ?: [] as $file) {
                $files[] = $file;
            }
        }

        return $files;
    }

    /**
     * @return list<string>
     */
    private function longIndexNames(string $file): array
    {
        $sql = (string) file_get_contents($file);
        $problems = [];
        $table = null;
        $offset = 0;

        // Statement-at-a-time, so multi-line index definitions are read whole.
        foreach (preg_split('/;(?![^(]*\))/', $sql) ?: [] as $statement) {
            $offset++;

            if (preg_match("/Schema::(?:create|table)\\('([^']+)'/", $statement, $create)) {
                $table = $create[1];
            }

            if ($table === null) {
                continue;
            }

            if (! preg_match_all(
                '/->(unique|index|fullText|spatialIndex)\(\s*(\[[^\]]*\]|[\'"][^\'"]+[\'"])\s*(,\s*[\'"]([^\'"]+)[\'"])?\s*\)/',
                $statement,
                $matches,
                PREG_SET_ORDER,
            )) {
                continue;
            }

            foreach ($matches as $match) {
                if (! empty($match[4])) {
                    continue; // explicit name
                }

                // `$table->string('x')->unique('short')` passes the index name
                // as a column modifier, not a column - the name is explicit.
                $prefix = substr($statement, 0, strpos($statement, $match[0]) ?: 0);

                if (! str_starts_with($match[2], '[') && preg_match('/\$table->[a-zA-Z]+\(/', $prefix)) {
                    continue;
                }

                $columns = array_values(array_filter(array_map(
                    static fn (string $column): string => trim(trim($column), "'\" "),
                    preg_split('/[\[\],]/', $match[2]) ?: [],
                )));

                if ($columns === []) {
                    continue;
                }

                $name = $table.'_'.implode('_', $columns).'_'.$match[1];

                if (strlen($name) > self::MYSQL_IDENTIFIER_LIMIT) {
                    $problems[] = sprintf(
                        '  - %s: %s (%d chars)',
                        $this->relativePath($file),
                        $name,
                        strlen($name),
                    );
                }
            }
        }

        return $problems;
    }

    private function relativePath(string $file): string
    {
        return str_replace('\\', '/', str_replace(base_path().DIRECTORY_SEPARATOR, '', $file));
    }
}
