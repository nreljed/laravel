# dashboard-core

Shared dashboard core for the Cost Breaker 360 product family — the master
app (Cost Breaker 360), Shop, and Booking. The package is the source of
truth for the dashboard, admin panel, auth, account, notifications, settings,
email, blog, and AI (BYOK) features. Applications keep only domain-specific
code on top (public pages, domain features, custom dashboard pages).

## Installation

### Development (path repository)

```json
{
  "repositories": [
    {
      "type": "path",
      "url": "../laravel/packages/dashboard-core"
    }
  ]
}
```

```bash
composer require nreljed/dashboard-core:@dev
```

### Production (GitHub Packages)

```json
{
  "repositories": [
    {
      "type": "composer",
      "url": "https://nuget.pkg.github.com/nreljed/"
    }
  ]
}
```

```bash
composer config http-basic.nuget.pkg.github.com <username> <token>
composer require nreljed/dashboard-core
```

The provider is auto-discovered via `extra.laravel.providers`.

## Configuration

Publish the config to override defaults:

```bash
php artisan vendor:publish --tag=core-config
```

| Key                    | Default                        | Purpose                                                                                                     |
| ---------------------- | ------------------------------ | ----------------------------------------------------------------------------------------------------------- |
| `core.load_migrations` | `true`                         | Set `false` when the app already ships the equivalent migrations (e.g. migrated from a monolith).           |
| `core.features`        | `[BlogServiceProvider::class]` | Registered feature providers discovered by the `FeatureRegistry`.                                           |
| `core.orphan_media`    | `['posts' => Post::class]`     | Folder → model pairs scanned by `core:cleanup-orphan-media`. Each model must expose `getReferencedPaths()`. |

### Frontend

All dashboard frontend ships as TypeScript source in `resources/js/`. Add a
`@dashboard` alias to consume it:

- `vite.config.js`: `alias: { '@dashboard': 'vendor/nreljed/dashboard-core/resources/js' }`
- `tsconfig.json`: `paths: { "@dashboard/*": ["vendor/nreljed/dashboard-core/resources/js/*"] }`
- `tailwind.config.js`: add `vendor/nreljed/dashboard-core/resources/js` to `@source`
- `resources/js/app.tsx`: call `registerDashboard()` instead of page globbing

See `package.json` for the peer-dependency contract (shipped for reference —
there is no separate npm package).

## Commands

| Command                     | Description                                                              |
| --------------------------- | ------------------------------------------------------------------------ |
| `core:cleanup-orphan-media` | Remove unreferenced media from the public disk (`--dry-run` to preview). |
| `app:create-admin`          | Bootstrap or update the admin user.                                      |
| `roles:create`              | Create a role with permissions.                                          |
| `permissions:list`          | List all permissions (`--role` to filter).                               |
| `notifications:purge`       | Purge old notifications.                                                 |
| `app:prune-audit-logs`      | Prune audit logs (`--days`).                                             |
| `schedule:publish`          | Publish scheduled content.                                               |

## Extension Layer

Add a self-contained feature by creating a service provider and registering
it in `config('core.features')`. The provider contract is satisfied via the
methods used by `FeatureRegistry`:

```php
public function slug(): string;
public function getNavItems(): array;
public function getSharedData(): array;
public function permissions(): array;
```

Optional hooks: `getDashboardData()`, `getAnalyticsSeries()`,
`getNotificationTypes()`, `getEmailTemplates()`, `getGalleryConfigs()`,
`onUserDelete(int $userId)`.

No core edits are required to add a feature — just register the provider.

## Tests

```bash
vendor/bin/phpunit -c packages/dashboard-core/phpunit.xml.dist
```

The bootstrap resolves the monorepo autoloader (or the package's own
`vendor/autoload.php` when installed standalone).
