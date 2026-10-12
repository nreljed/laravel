---
name: console-commands
description: "Artisan commands and scheduling for Cost Breaker 360. Activate when working with console commands, app:create-admin, roles:create, permissions:list, schedule:publish, notifications:purge, app:prune-audit-logs, core:cleanup-orphan-media, core:version, the task scheduler, or when user mentions artisan commands, cron, scheduled tasks, or CLI. Covers command signatures, the scheduler, and command test patterns."
license: MIT
metadata:
  author: laravel
---

# Console Commands

## Command Inventory

Commands are **not** in this app. They ship in the packages (`vendor/nreljed/platform/src/Console/Commands/` and `vendor/nreljed/blog/src/Console/Commands/`); this repo has no `app/Console/Commands/`. To add a command, edit the **owning package repo**, tag, then `composer update` — never the app or the `packages/**` mirror.

| Command                    | Class                     | Package  | Purpose                                                     |
| -------------------------- | ------------------------- | -------- | ----------------------------------------------------------- |
| `app:create-admin`         | `CreateAdmin`             | platform | Create/update the first admin user                          |
| `roles:create`             | `CreateRole`              | platform | Create a role with optional permission slugs                |
| `permissions:list`         | `ListPermissions`         | platform | List permissions (`--role`, `--group`, `--format`)          |
| `app:prune-audit-logs`     | `PruneAuditLogs`          | platform | Delete audit logs older than retention (`--days=`)          |
| `notifications:purge`      | `PurgeNotifications`      | platform | Purge old notifications (`--days=90` default)               |
| `core:cleanup-orphan-media`| `CleanupOrphanMedia`      | platform | Remove media files not referenced by models (`--dry-run`)   |
| `core:version`             | `CoreVersion`             | platform | Show installed platform name, version, and commit           |
| `deploy:auto`              | `DeployAuto`              | platform | CI-fallback self-deploy from the server                     |
| `database:backup`          | `DatabaseBackup`          | platform | Gzipped DB dump + prune old backups (`--keep=`)             |
| `platform:install`         | `PlatformInstall`         | platform | Verify a consumer is wired to the platform                  |
| `make:feature`             | `MakeFeature`             | platform | Scaffold a `FeatureProvider` beside a feature               |
| `make:resource`            | `MakeResource`            | platform | Scaffold an admin resource descriptor                       |
| `schedule:publish`         | `PublishScheduledContent` | blog     | Publish posts with a due `published_at`                     |
| `posts:seed` / `posts:clean` | `PostsSeed` / `PostsClean` | blog   | Seed/remove demo posts (`--group=blog` marker)              |

The `core:` prefix marks package-shipped commands; a couple of platform commands keep a legacy `app:` prefix (`app:create-admin`, `app:prune-audit-logs`).

## Signature Styles

Two styles are used — match the neighboring file:

```php
// Classic property style (CreateAdmin, CreateRole, ListPermissions, PurgeNotifications, PublishScheduledContent)
protected $signature = 'app:create-admin
    {--email= : Admin email}
    {--password= : Admin password}
    {--name= : Admin display name}
    {--username= : Admin username (defaults to name)}';

protected $description = 'Create or update the first admin user';

// PHP 8.4 attribute style (PruneAuditLogs, CleanupOrphanMedia, DeployAuto, DatabaseBackup)
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Attributes\Description;

#[Signature('app:prune-audit-logs {--days= : Override the retention days from settings}')]
#[Description('Delete audit log entries older than the configured retention period')]
class PruneAuditLogs extends Command
```

`CreateAdmin` falls back to `config('admin.*')` values when options are omitted — these are injected as CI secrets on deploy.

## Scheduler

Two registration points:

- **App** (`routes/console.php`, owned by this repo):
  ```php
  Schedule::command('notifications:purge', ['--days' => 90])->daily();
  Schedule::command('app:prune-audit-logs')->daily();
  Schedule::command('queue:work', ['--stop-when-empty' => true])->everyMinute()->withoutOverlapping();
  ```
- **Package** (`BlogServiceProvider::boot()`): `schedule:publish` runs `everyMinute()->withoutOverlapping()`. Feature-owned schedules live in their provider, never in `routes/console.php`.

The host cron runs `<home>/current/artisan schedule:run` every minute — it must target the `current` symlink, not a legacy root `artisan` (see the deploy docs).

## Testing Commands

Tests live in `tests/Feature/Console/` (e.g. `CreateAdminCommandTest`, `ListPermissionsCommandTest`, `PublishScheduledContentCommandTest`, `CleanupOrphanMediaCommandTest`).

```php
$this->artisan('app:create-admin', [
    '--email' => 'admin@example.com',
    '--password' => 'secret',
    '--name' => 'Admin',
])
    ->expectsOutput('...')   // assert on output
    ->assertExitCode(0);
```

Patterns:

- Seed roles/permissions (`seedRolesAndPermissions()`) before commands that touch them
- `CreateAdmin` also asserts `email_verified_at` is set
- `schedule:publish`: create a post with `future()` published_at state → run → assert `published`
- `notifications:purge` / `app:prune-audit-logs`: create rows with old `created_at`, assert deletion + `--days` override
- `core:cleanup-orphan-media`: create an unreferenced file on the public disk, assert removal; `--dry-run` asserts it is kept

## Key Rules

- Add new commands in the **owning package** (platform/blog) and schedule recurring work in `routes/console.php` (app-level) or the feature's service provider
- Prefer the attribute style for new commands (PHP 8.4 convention), but match existing files
- `app:create-admin` must be runnable from CI after deploy (creates/updates idempotently)
- Never rely on tinker for command-level behavior — write a `tests/Feature/Console/` test
