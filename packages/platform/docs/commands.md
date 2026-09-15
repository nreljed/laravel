# Artisan Commands

Registered by `PlatformServiceProvider::loadConsoleRoutes()`.

| Command                     | Description                                                                                                                                                                                  |
| --------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `app:create-admin`          | Bootstrap or update the admin user. Reads `X-Admin-Email`, `X-Admin-Password`, `X-Admin-Name` headers on the deploy webhook (or CLI options). Skips gracefully when an admin already exists. |
| `app:prune-audit-logs`      | Prune old audit logs (`--days`).                                                                                                                                                             |
| `core:cleanup-orphan-media` | Remove unreferenced media from the public disk (`--dry-run` to preview). Scans `media.orphan_media` folder → model pairs; each model exposes `getReferencedPaths()`.                          |
| `core:version`              | Print the installed platform version (from the package version marker).                                                                                                                   |
| `deploy:auto`               | Server-side self-deploy: check for a new GitHub release, download + extract `deploy.zip`, run post-deploy steps. Scheduled every 5 minutes via the provider (see [Deploy](deploy.md)).       |
| `permissions:list`          | List all permissions (`--role` to filter).                                                                                                                                                   |
| `roles:create`              | Create a role with permissions.                                                                                                                                                              |
| `schedule:publish`          | Publish due scheduled content (Posts by `published_at`). Ships with the `nreljed/blog` package, which registers it every minute.                             |
| `notifications:purge`       | Purge old notifications (`--days`).                                                                                                                                                          |
| `feature:make`              | Scaffold a new feature provider (contract + trait + config registration).                                                                                                                    |

## Hosts

Host apps schedule the following in their own `routes/console.php` (NOT `deploy:auto` — that
one is owned by core):

```php
Schedule::command('notifications:purge')->daily();
Schedule::command('app:prune-audit-logs')->daily();
```

## Related

- [Deploy](deploy.md) — `deploy:auto` + the endpoints it drives.
- [Blog](https://github.com/nreljed/blog) — `schedule:publish` and other blog feature commands.
