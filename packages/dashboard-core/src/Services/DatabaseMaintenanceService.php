<?php

namespace Nreljed\DashboardCore\Services;

use Closure;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Nreljed\DashboardCore\Enums\AuditAction;
use Nreljed\DashboardCore\Exceptions\DatabaseMaintenanceException;
use Nreljed\DashboardCore\Models\AuditLog;
use Nreljed\DashboardCore\Models\User;
use Nreljed\DashboardCore\Notifications\DatabaseMaintenanceNotification;

/**
 * Centralizes destructive database operations (reset + reseed) behind a single
 * implementation shared by the admin UI (local/staging) and the token-protected
 * deploy endpoint (production).
 */
class DatabaseMaintenanceService
{
    private const string LOCK_FILE = '.maintenance-lock';

    private const int LOCK_TTL = 900;

    private readonly Closure $runner;

    public function __construct(?Closure $runner = null)
    {
        $this->runner = $runner ?? static fn (string $command, array $params = []): int => Artisan::call($command, $params);
    }

    /**
     * Whether the destructive UI is allowed to run in this environment.
     *
     * The admin-set `database_maintenance_enabled` setting takes precedence
     * when present; otherwise it falls back to the DB_MAINTENANCE_ENABLED env
     * flag (defaults to enabled everywhere except production).
     */
    public function isEnabled(): bool
    {
        try {
            if (Schema::hasTable('settings')) {
                $setting = SettingsService::get('database_maintenance_enabled');

                if ($setting !== null && $setting !== '') {
                    return (bool) $setting;
                }
            }
        } catch (\Throwable) {
            // Fall back to config when the settings store is unavailable.
        }

        return (bool) config('database_maintenance.enabled', false);
    }

    /**
     * Current maintenance snapshot rendered by the UI page.
     */
    public function status(): array
    {
        $lastReset = AuditLog::query()
            ->where('action', AuditAction::DatabaseReset->value)
            ->latest('id')
            ->first();

        return [
            'enabled' => $this->isEnabled(),
            'environment' => app()->environment(),
            'is_production' => app()->isProduction(),
            'database_driver' => config('database.default'),
            'table_count' => $this->tableCount(),
            'locked' => $this->isLocked(),
            'lock_path' => $this->lockPath(),
            'media_disk_path' => storage_path('app/public'),
            'last_reset_at' => $lastReset?->created_at?->toISOString(),
            'last_reset_by' => $lastReset?->user?->name,
            'reseed_groups' => array_values(array_map(
                fn (string $key, array $group) => [
                    'key' => $key,
                    'label' => $group['label'] ?? $key,
                    'destructive' => (bool) ($group['destructive'] ?? false),
                ],
                array_keys($this->seederGroups()),
                $this->seederGroups(),
            )),
        ];
    }

    /**
     * Run a destructive database reset: migrate:fresh, baseline seeders
     * (roles/permissions + settings), then app:create-admin.
     *
     * Demo content seeders (users, blog, ...) are skipped unless `$demo` is
     * true, so a production reset always yields a clean baseline environment.
     *
     * @param  bool  $purgeMedia  additionally empty the public media disk
     * @param  int|null  $userId  actor id used for the audit trail
     * @param  bool  $demo  also run demo-tier seeders after the baseline
     *
     * @throws DatabaseMaintenanceException
     */
    public function resetDatabase(bool $purgeMedia = false, ?int $userId = null, bool $demo = false): array
    {
        if ($this->isLocked()) {
            throw new DatabaseMaintenanceException('Another database maintenance operation is already running. Please wait a few minutes and try again.');
        }

        $this->acquireLock();
        $output = [];

        try {
            @set_time_limit(0);

            $this->enableMaintenanceMode($output);

            $exitCode = $this->run('migrate:fresh', ['--force' => true], $output);
            if ($exitCode !== 0) {
                throw new DatabaseMaintenanceException('migrate:fresh failed.');
            }

            foreach ($this->seedersForReset($demo) as $key => $class) {
                $exitCode = $this->run('db:seed', ['--force' => true, '--class' => $class], $output);
                if ($exitCode !== 0) {
                    throw new DatabaseMaintenanceException("Seeder '{$key}' failed (migrate:fresh).");
                }
            }

            $exitCode = $this->run('app:create-admin', [
                '--no-interaction' => true,
                '--email' => config('admin.email', ''),
                '--password' => config('admin.password', ''),
                '--name' => config('admin.name', 'Admin'),
            ], $output);
            if ($exitCode !== 0) {
                throw new DatabaseMaintenanceException('app:create-admin failed. Check the ADMIN_EMAIL and ADMIN_PASSWORD environment variables.');
            }

            if ($purgeMedia) {
                $this->purgeMediaFiles($output);
            }

            $this->disableMaintenanceMode($output);

            try {
                AuditService::log(
                    AuditAction::DatabaseReset,
                    $userId ?? 0,
                    ['purge_media' => $purgeMedia, 'demo' => $demo],
                );
            } catch (\Throwable) {
                // Best-effort — a failed audit write must never fail a completed reset.
            }

            $this->notifyAdmins(AuditAction::DatabaseReset, $purgeMedia);

            return ['success' => true, 'output' => $output];
        } catch (DatabaseMaintenanceException $e) {
            $this->disableMaintenanceMode($output);

            throw $e;
        } catch (\Throwable $e) {
            $this->disableMaintenanceMode($output);

            throw new DatabaseMaintenanceException($e->getMessage(), 0, $e);
        } finally {
            $this->releaseLock();
        }
    }

    /**
     * Re-run only the selected seeder groups (no destructive wipe).
     *
     * @param  list<string>  $groupKeys  seeder group slugs (see config 'database_maintenance.seeders')
     * @param  int|null  $userId  actor id used for the audit trail
     *
     * @throws DatabaseMaintenanceException
     */
    public function reseedDatabase(array $groupKeys, ?int $userId = null): array
    {
        $classes = $this->seedersForGroups($groupKeys);

        if ($classes === []) {
            throw new DatabaseMaintenanceException('No seeders were selected to re-run.');
        }

        if ($this->isLocked()) {
            throw new DatabaseMaintenanceException('Another database maintenance operation is already running. Please wait a few minutes and try again.');
        }

        $this->acquireLock();
        $output = [];

        try {
            @set_time_limit(0);

            $this->enableMaintenanceMode($output);

            foreach ($classes as $key => $class) {
                $exitCode = $this->run('db:seed', ['--force' => true, '--class' => $class], $output);
                if ($exitCode !== 0) {
                    throw new DatabaseMaintenanceException("Seeder '{$key}' failed.");
                }
            }

            $this->disableMaintenanceMode($output);

            try {
                AuditService::log(
                    AuditAction::DatabaseReseed,
                    $userId ?? 0,
                    ['seeders' => array_keys($classes)],
                );
            } catch (\Throwable) {
                // Best-effort — a failed audit write must never fail a completed reseed.
            }

            $this->notifyAdmins(AuditAction::DatabaseReseed, false);

            return ['success' => true, 'output' => $output];
        } catch (DatabaseMaintenanceException $e) {
            $this->disableMaintenanceMode($output);

            throw $e;
        } catch (\Throwable $e) {
            $this->disableMaintenanceMode($output);

            throw new DatabaseMaintenanceException($e->getMessage(), 0, $e);
        } finally {
            $this->releaseLock();
        }
    }

    public function isLocked(): bool
    {
        if (! File::exists($this->lockPath())) {
            return false;
        }

        return (time() - File::lastModified($this->lockPath())) < self::LOCK_TTL;
    }

    protected function lockPath(): string
    {
        return base_path(self::LOCK_FILE);
    }

    private function acquireLock(): void
    {
        File::put($this->lockPath(), (string) getmypid());
    }

    private function releaseLock(): void
    {
        if (File::exists($this->lockPath())) {
            File::delete($this->lockPath());
        }
    }

    /**
     * @param  list<string>  $output
     */
    private function run(string $command, array $params, array &$output): int
    {
        $exitCode = ($this->runner)($command, $params);
        $line = strtok(trim(Artisan::output()), "\n") ?: 'ok';
        $output[] = $exitCode === 0 ? "$command: $line" : "$command: FAILED (exit $exitCode)";

        return $exitCode;
    }

    /**
     * @param  list<string>  $output
     */
    private function enableMaintenanceMode(array &$output): void
    {
        try {
            SettingsService::set('maintenance_mode', '1');
            $output[] = 'Maintenance mode enabled.';
        } catch (\Throwable $e) {
            $output[] = 'Maintenance mode: skipped ('.$e->getMessage().')';
        }
    }

    /**
     * @param  list<string>  $output
     */
    private function disableMaintenanceMode(array &$output): void
    {
        try {
            SettingsService::set('maintenance_mode', '0');
            $output[] = 'Maintenance mode disabled.';
        } catch (\Throwable $e) {
            $output[] = 'Maintenance mode: skipped ('.$e->getMessage().')';
        }
    }

    /**
     * Empty every upload folder on the public disk (posts, avatars, logos,
     * favicons, feature folders). The disk root and its .gitignore survive.
     *
     * @param  list<string>  $output
     */
    private function purgeMediaFiles(array &$output): void
    {
        $disk = Storage::disk('public');
        $deletedDirectories = 0;
        $deletedFiles = 0;

        foreach ($disk->allDirectories() as $directory) {
            if ($disk->deleteDirectory($directory)) {
                $deletedDirectories++;
            }
        }

        foreach ($disk->allFiles() as $file) {
            if ($disk->delete($file)) {
                $deletedFiles++;
            }
        }

        $output[] = "Purged media: $deletedDirectories directorie(s), $deletedFiles file(s).";
    }

    private function notifyAdmins(AuditAction $action, bool $purgeMedia): void
    {
        try {
            $admins = User::query()
                ->whereHas('roles', fn ($query) => $query->where('slug', 'admin'))
                ->get();

            $isReset = $action === AuditAction::DatabaseReset;
            $notification = new DatabaseMaintenanceNotification(
                $action->value,
                $isReset ? 'Database reset completed' : 'Database reseed completed',
                $isReset
                    ? 'The database was reset to a clean baseline. Media purge: '.($purgeMedia ? 'yes' : 'no').'.'
                    : 'Selected seeders were re-run. No data was wiped.',
            );

            $notificationService = app(NotificationService::class);

            foreach ($admins as $admin) {
                $notificationService->notifyViaPreferences($admin, 'database.maintenance', $notification, ['in_app']);
            }
        } catch (\Throwable) {
            // Best-effort — a notification failure must never block the reset.
        }
    }

    /**
     * @return array<string, array{class: class-string, label?: string, destructive?: bool, baseline?: bool}>
     */
    private function seederGroups(): array
    {
        return (array) config('database_maintenance.seeders', []);
    }

    /**
     * Baseline groups always run on reset; demo groups only when requested.
     *
     * @return array<string, class-string>
     */
    private function seedersForReset(bool $demo): array
    {
        $groups = $this->seederGroups();

        $selected = array_filter($groups, static fn (array $group) => (bool) ($group['baseline'] ?? false));

        if ($demo) {
            $selected += array_filter($groups, static fn (array $group) => ! ($group['baseline'] ?? false));
        }

        return array_map(static fn (array $group): string => $group['class'], $selected);
    }

    /**
     * @param  list<string>  $groupKeys
     * @return array<string, class-string>
     *
     * @throws DatabaseMaintenanceException
     */
    private function seedersForGroups(array $groupKeys): array
    {
        $groups = $this->seederGroups();
        $classes = [];

        foreach ($groupKeys as $key) {
            if (! isset($groups[$key])) {
                throw new DatabaseMaintenanceException("Unknown seeder group '{$key}'.");
            }

            $classes[$key] = $groups[$key]['class'];
        }

        return $classes;
    }

    private function tableCount(): int
    {
        try {
            return count(Schema::getTableListing());
        } catch (\Throwable) {
            return 0;
        }
    }
}
