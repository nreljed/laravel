<?php

namespace Nreljed\Platform\Services;

use Closure;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

/**
 * Creates and prunes gzipped database dumps.
 *
 * Schema/driver aware: MySQL uses `mysqldump` (falling back to a pure PDO
 * dump when the binary is missing or locked down) and SQLite simply gzips a
 * copy of the database file. Dumps land in the configured backup directory
 * (default <storage>/app/backups) as `backup-<timestamp>-<driver>.gz` and are
 * pruned newest-first to the configured retention.
 *
 * The `$baseDir` and `$runner` constructor args allow framework-free tests:
 * callers that resolve through the container get storage_path() and the
 * Process facade automatically.
 */
class DatabaseBackupService
{
    private string $baseDir;

    private Closure $runner;

    public function __construct(?string $baseDir = null, ?Closure $runner = null)
    {
        $this->baseDir = $baseDir ?? $this->defaultBackupDir();
        $this->runner = $runner ?? static fn (string $command): int => Process::run($command)->exitCode();
    }

    /**
     * Absolute directory dumps are written to.
     */
    public function backupDirectory(): string
    {
        $path = rtrim($this->baseDir, '/\\');

        if (! is_dir($path)) {
            @mkdir($path, 0775, true);
        }

        return $path;
    }

    /**
     * List existing backup files, newest first.
     *
     * @return list<array{path: string, size: int, modified: int}>
     */
    public function backups(): array
    {
        $files = glob($this->backupDirectory().'/backup-*.gz') ?: [];

        $backups = array_map(static fn (string $path): array => [
            'path' => $path,
            'size' => (int) @filesize($path),
            'modified' => (int) @filemtime($path),
        ], $files);

        usort($backups, static fn (array $a, array $b) => $b['modified'] <=> $a['modified']);

        return $backups;
    }

    /**
     * Path of the most recent backup, or null when none exist.
     */
    public function latestBackup(): ?string
    {
        $backups = $this->backups();

        return $backups[0]['path'] ?? null;
    }

    /**
     * Create a fresh gzipped dump for the configured default connection.
     *
     * @param  list<string>  $output
     * @return array{path: string, size: int, name: string}
     *
     * @throws \RuntimeException when no dump strategy applies or the dump fails
     */
    public function createBackup(array &$output = []): array
    {
        $driver = config('database.default', 'sqlite');

        $stamp = now()->format('Ymd-His');
        $gzipPath = $this->backupDirectory()."/backup-{$stamp}-{$driver}.gz";

        $gzipped = match ($driver) {
            'mysql' => $this->dumpMysql($gzipPath, $output),
            'sqlite' => $this->dumpSqlite($gzipPath, $output),
            default => throw new \RuntimeException("Unsupported database driver '{$driver}' for backup."),
        };

        if (! $gzipped || ! File::exists($gzipPath)) {
            throw new \RuntimeException('Backup dump could not be generated.');
        }

        return [
            'path' => $gzipPath,
            'size' => File::size($gzipPath),
            'name' => basename($gzipPath),
        ];
    }

    /**
     * Delete backups beyond the retention window. Returns the count removed.
     */
    public function prune(?int $keep = null): int
    {
        $keep = max(1, $keep ?? (int) config('database_backup.keep', 7));
        $backups = $this->backups();

        if (count($backups) <= $keep) {
            return 0;
        }

        $stale = array_slice($backups, $keep);

        foreach ($stale as $backup) {
            @unlink($backup['path']);
        }

        return count($stale);
    }

    /**
     * Dump a MySQL database to a gzipped file.
     *
     * Primary path uses the mysqldump binary (streamed straight to gzip so
     * large dumps never live in PHP memory). Falls back to a PDO-based dump
     * when the binary is missing or exits non-zero.
     *
     * @param  list<string>  $output
     */
    private function dumpMysql(string $gzipPath, array &$output): bool
    {
        $config = config('database.connections.mysql');

        if (! is_array($config) || ! isset($config['database'], $config['username'])) {
            throw new \RuntimeException('MySQL connection is not configured.');
        }

        $command = $this->mysqldumpCommand($config);
        $exitCode = ($this->runner)($command.' | gzip > '.escapeshellarg($gzipPath));

        if ($exitCode === 0 && File::exists($gzipPath) && File::size($gzipPath) > 0) {
            $output[] = 'MySQL dump via mysqldump.';

            return true;
        }

        $output[] = 'mysqldump unavailable or failed — falling back to PDO dump.';

        $sqlPath = $gzipPath.'.sql';

        try {
            $this->dumpMysqlViaPdo($config, $sqlPath, $output);

            return $this->gzipFile($sqlPath, $gzipPath, $output);
        } finally {
            if (File::exists($sqlPath)) {
                File::delete($sqlPath);
            }
        }
    }

    /**
     * Build the mysqldump command line for the given connection config.
     *
     * `--single-transaction` gives a consistent snapshot without locking,
     * `--no-tablespaces` avoids the PROCESS privilege many shared hosts lack,
     * and `--quick` streams row-by-row instead of buffering.
     */
    private function mysqldumpCommand(array $config): string
    {
        $binary = (string) config('database_backup.mysqldump', 'mysqldump');
        $port = $config['port'] ?? 3306;

        $args = [
            '--single-transaction',
            '--quick',
            '--no-tablespaces',
            '--host='.$config['host'],
            '--port='.$port,
            '--user='.$config['username'],
        ];

        if (! empty($config['password'])) {
            $args[] = '--password='.$config['password'];
        }

        $args[] = $config['database'];

        return $binary.' '.implode(' ', array_map(static fn (string $arg): string => escapeshellarg($arg), $args));
    }

    /**
     * Pure-PDO fallback dump: schema (CREATE TABLE) + data (INSERT).
     *
     * Deliberately simple — the mysqldump path is the production one; this
     * only guarantees a recoverable dump exists on hosts without the binary.
     *
     * @param  list<string>  $output
     */
    private function dumpMysqlViaPdo(array $config, string $sqlPath, array &$output): void
    {
        $port = $config['port'] ?? 3306;
        $dsn = "mysql:host={$config['host']};port={$port};dbname={$config['database']};charset=utf8mb4";

        $pdo = new \PDO($dsn, $config['username'], $config['password'] ?? null, [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
        ]);

        $handle = fopen($sqlPath, 'w');
        if ($handle === false) {
            throw new \RuntimeException("Unable to write SQL dump to {$sqlPath}.");
        }

        try {
            fwrite($handle, "SET FOREIGN_KEY_CHECKS=0;\n");
            fwrite($handle, "SET NAMES utf8mb4;\n\n");

            foreach ($pdo->query('SHOW TABLES')->fetchAll(\PDO::FETCH_COLUMN) as $table) {
                $create = $pdo->query("SHOW CREATE TABLE `{$table}`")->fetch(\PDO::FETCH_ASSOC);
                fwrite($handle, ($create['Create Table'] ?? '').";\n\n");

                $rows = $pdo->query("SELECT * FROM `{$table}`");
                while ($row = $rows->fetch(\PDO::FETCH_ASSOC)) {
                    $values = array_map(fn ($value): string => $value === null ? 'NULL' : $pdo->quote((string) $value), $row);
                    fwrite($handle, "INSERT INTO `{$table}` VALUES (".implode(', ', $values).");\n");
                }

                fwrite($handle, "\n");
            }

            fwrite($handle, "SET FOREIGN_KEY_CHECKS=1;\n");
        } finally {
            fclose($handle);
        }

        $output[] = 'PDO dump written.';
    }

    /**
     * Gzip a copy of the SQLite database file.
     *
     * SQLite stores the whole schema + data in a single file, so gzipping a
     * consistent copy IS the backup (no intermediate SQL rendering needed).
     *
     * @param  list<string>  $output
     */
    private function dumpSqlite(string $gzipPath, array &$output): bool
    {
        $database = config('database.connections.sqlite.database');

        if (! is_string($database) || ! File::exists($database)) {
            throw new \RuntimeException('SQLite database file not found.');
        }

        $tmp = $gzipPath.'.sqlite';

        try {
            File::copy($database, $tmp);

            return $this->gzipFile($tmp, $gzipPath, $output);
        } finally {
            if (File::exists($tmp)) {
                File::delete($tmp);
            }
        }
    }

    /**
     * Stream-compress a plain file into a .gz sibling.
     *
     * @param  list<string>  $output
     */
    private function gzipFile(string $source, string $gzipPath, array &$output): bool
    {
        $input = @fopen($source, 'rb');
        $outputGz = @fopen('compress.zlib://'.$gzipPath, 'wb');

        if ($input === false || $outputGz === false) {
            if (is_resource($input)) {
                fclose($input);
            }
            if (is_resource($outputGz)) {
                fclose($outputGz);
            }

            return false;
        }

        $bytes = stream_copy_to_stream($input, $outputGz);

        fclose($input);
        fclose($outputGz);

        $output[] = 'Gzipped '.$bytes.' bytes.';

        return $bytes > 0 && File::exists($gzipPath);
    }

    private function defaultBackupDir(): string
    {
        $dir = (string) config('database_backup.dir', 'app/backups');

        if (str_starts_with($dir, DIRECTORY_SEPARATOR) || preg_match('/^[A-Za-z]:[\\\\\/]/', $dir) === 1) {
            return rtrim($dir, '/\\');
        }

        return rtrim(storage_path($dir), '/\\');
    }
}
