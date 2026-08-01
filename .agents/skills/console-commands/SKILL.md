---
name: console-commands
description: "Artisan commands and scheduling for Cost Breaker 360. Activate when working with console commands, app:create-admin, roles:create, permissions:list, schedule:publish, notifications:purge, app:prune-audit-logs, app:cleanup-orphan-media, the task scheduler, or when user mentions artisan commands, cron, scheduled tasks, or CLI. Covers command signatures, the scheduler, and command test patterns."
license: MIT
metadata:
  author: laravel
---

# Console Commands

## Command Inventory

All commands live in `app/Console/Commands/`.

| Command                    | Class                     | Purpose                                                     |
| -------------------------- | ------------------------- | ----------------------------------------------------------- |
| `app:create-admin`         | `CreateAdmin`             | Create/update the first admin user                          |
| `roles:create`             | `CreateRole`              | Create a role with optional permission slugs                |
| `permissions:list`         | `ListPermissions`         | List permissions with role assignments (`--role` filter)    |
| `schedule:publish`         | `PublishScheduledContent` | Publish posts with a future `published_at`                  |
| `notifications:purge`      | `PurgeNotifications`      | Purge old notifications (`--days=90` default)               |
| `app:prune-audit-logs`     | `PruneAuditLogs`          | Delete audit logs older than retention (`--days=`)          |
| `app:cleanup-orphan-media` | `CleanupOrphanMedia`      | Remove storage files not referenced by models (`--dry-run`) |

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

// PHP 8.4 attribute style (PruneAuditLogs, CleanupOrphanMedia)
#[Signature('app:prune-audit-logs {--days= : Override the retention days from settings}')]
#[Description('Delete audit log entries older than the configured retention period')]
class PruneAuditLogs extends Command
```

`CreateAdmin` falls back to `config('admin.*')` values when options are omitted — these are injected as CI secrets on deploy.

## Scheduler

`routes/console.php` defines the schedule:

```php
Schedule::command('schedule:publish')->everyMinute();
Schedule::command('notifications:purge', ['--days' => 90])->daily();
Schedule::command('app:prune-audit-logs')->daily();
```

## Testing Commands

Tests live in `tests/Feature/Console/` (e.g. `CreateAdminCommandTest`, `ListPermissionsCommandTest`, `PublishScheduledContentCommandTest`).

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
- `app:cleanup-orphan-media`: create an unreferenced file on the public disk, assert removal; `--dry-run` asserts it is kept

## Key Rules

- Add new commands to `app/Console/Commands/` and schedule recurring work in `routes/console.php`
- Prefer the attribute style for new commands (PHP 8.4 convention), but match existing files
- `app:create-admin` must be runnable from CI after deploy (creates/updates idempotently)
- Never rely on tinker for command-level behavior — write a `tests/Feature/Console/` test
