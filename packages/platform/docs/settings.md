# Settings

DB-backed application configuration, shared across apps. The `settings` table
(`Setting` model) holds `group` + `key` + `value` rows; the `SettingsService` reads them and
caches per group.

## Service

`Nreljed\Platform\Services\SettingsService` (singleton):

| Method                                             | Purpose                                                         |
| -------------------------------------------------- | --------------------------------------------------------------- |
| `getGroup(string $group): array`                   | All settings for a group (merged over config defaults, cached). |
| `get(string $group, string $key, $default = null)` | Single value with cast.                                         |
| `set(string $group, string $key, $value)`          | Persist via `updateOrCreate`.                                   |
| `setGroup(string $group, array $values)`           | Bulk persist (used by the settings controller).                 |
| `clearCache()`                                     | Forget the group cache (called after every settings update).    |

Base groups (always included): `general`, `registration`, `notifications`, `security`,
`email`, `maintenance`, `formatting`, `audit_logs`. Feature providers add domain groups via
`core.settings.groups` (e.g. Shop adds `shop`).

## Controllers & UI

- `SettingsController` (admin) — renders the settings pages (`dashboard/admin/settings/*`)
  and `update()` validates + persists via `setGroup`.
- `EmailSettingsController` — mail transport settings + test send.
- The account page exposes per-user `notification` prefs (not group settings).

## Configuration

- `config/core.php` → `settings.groups` — extra groups.
- Publish with `php artisan vendor:publish --tag=core-config`.

## Consumers

- `MailerConfigurator` — mail transport from `email` group.
- `HandleInertiaRequests` — shares `settings` for the UI.
- Feature `ShopSettingsService` / `ShopSettingsController` — a domain group (`shop`) built on
  this exact service (see Shop package `docs/settings.md`).

## Related

- [Architecture](architecture.md) — boot-time wiring.
- Shop package `docs/settings.md` — example of a feature settings group.
