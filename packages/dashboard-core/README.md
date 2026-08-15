# dashboard-core

Shared dashboard core platform — the source of truth for the dashboard,
admin panel, auth, account, notifications, settings, email, blog, and AI
(BYOK) features across Laravel applications (master app, Shop, Booking).
Applications keep only domain-specific code on top (public pages, domain
features, custom dashboard pages).

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
- `resources/css/app.css`: `@import` the shared stylesheet (see its header comment for per-app token overrides)

#### Page inventory (opt-in)

Core never auto-registers pages. Each app's `app.tsx` builds an explicit page
map — spread the standard kit, add namespaced project pages, and shadow any
core page:

```ts
import { createFoundationApp, type FoundationAppConfig } from "@dashboard/app";
import { registerCoreTemplates } from "@dashboard/dashboard";

const config: FoundationAppConfig = {
  basePages: registerCoreTemplates(), // the standard CORE kit
  projectPages: import.meta.glob("./Pages/**/*.tsx"),
  projectPrefix: "shop", // namespaced project pages
  pageOverrides: {
    // swap CORE pages, wins last
    "public/about": () => import("./Pages/Public/About"),
  },
};

createFoundationApp(config);
```

Routes + controllers stay in core (they render `public/*`, `dashboard/*`, …) —
only the page component swaps.

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

### Module-authoring checklist

Every feature provider must pass both conformance tests
(`packages/dashboard-core/tests/FeatureProviderContractTest.php` in this repo
and `tests/Feature/FeatureProviderContractTest.php` in the host):

- **Implement `FeatureProvider`** and use the `InteractsWithFeatures` trait
  (safe no-op defaults for every capability method). Only `name()` and
  `slug()` are mandatory to implement yourself.
- **`getSharedData()`** returns an array of **lazy closures**, not eagerly
  resolved values. The core middleware wraps the aggregated result in
  `Inertia\AlwaysProp` and exposes it as the top-level `sharedData` prop, so
  nested closures resolve per-request and survive partial reloads. Consumers
  read `props.sharedData.{key}` — never call `Inertia::share()` from a
  provider (it bypasses registry aggregation and breaks partial reloads).
- **`getNavItems()`** returns sidebar items; group headings come from
  `getNavGroupLabel()` (injected by `FeatureRegistry::getAllNavItems()`).
  Feature icons are resolved by name via `lib/icons.ts` (`registerFeatureIcons`)
  and fall back to a generic icon in `nav-main.tsx`.
- **`getSettingsRoute()`** returns a **route name** (not a URL) for the
  feature's settings page, or `null`. The registry resolves it lazily so a
  stale name degrades to "no settings link". Optional `getSettingsNav()`
  provides settings-category nav items rendered under `featureSettingsNav`.
- **Permission slugs are scoped** to the feature prefix (e.g. `shop.orders.view`).
  Generic admin permissions (no dot) are allowed but reserved for core.
- **`getDashboardData()`** returns a keyed array; the registry normalizes it
  (`label`, `icon`, `total`) for the dashboard KPI cards.
- Register the provider class in `config('core.features')`. Run
  `php artisan permissions:sync` after adding permissions.

## Tests

```bash
vendor/bin/phpunit -c packages/dashboard-core/phpunit.xml.dist
```

The bootstrap resolves the monorepo autoloader (or the package's own
`vendor/autoload.php` when installed standalone).
