# Feature Provider

Self-contained features (Blog, Shop, Booking - each a separate package) are discovered through a
single contract aggregated by the `FeatureRegistry`. Adding a feature requires **no core
edits** - just register its service provider in `config/features.php` `providers`.

## Start here: `php artisan make:module`

Scaffolding a module by hand is five files of boilerplate plus five host-app edits. The generator
does both:

```bash
php artisan make:module Events                       # scaffold only, prints the activation checklist
php artisan make:module Events --activate            # scaffold + patch the host app in place
php artisan make:module Events --with-migrations --with-tests
php artisan make:module Events --path=packages/events --vendor=acme --slug=events
```

| Option              | Effect                                                                       |
| ------------------- | ---------------------------------------------------------------------------- |
| `--slug=`           | Feature slug / route + page prefix (default: slugified name)                 |
| `--namespace=`      | Root namespace (default: `<Vendor>\<Name>Module`)                            |
| `--vendor=`         | Composer + npm vendor (default: `nreljed`)                                   |
| `--path=`           | Target directory, relative to the app root or absolute (default: `modules`)  |
| `--nav-group=`      | Sidebar heading (default: headline of the name)                              |
| `--with-migrations` | Also write an example migration                                              |
| `--with-tests`      | Also write `phpunit.xml`, a bootstrap and a contract smoke test              |
| `--activate`        | Patch `composer.json`, `package.json`, `bootstrap/providers.php`, `config/features.php`, `page-config.ts` |
| `--force`           | Overwrite generated files in a non-empty target directory                    |

It emits a package shaped exactly like the shipped modules: `composer.json` + `package.json`, a
`{Name}ServiceProvider` implementing the minimal `Feature` contract plus only `HasNavigation` and
`HasPermissions`, dashboard routes under `dashboard/{slug}`, an Inertia page kit
(`register{Name}Pages()`), an example page, `.gitignore`/`.gitattributes` and a README.

`--activate` is text-patch based and idempotent: it preserves the host file's indentation and line
endings, reports `already:` on a second run, and reports `skipped:` (with the manual instruction)
rather than half-patching when a host file is missing or has an unexpected shape.

After activation:

```bash
php artisan migrate
php artisan db:seed --class=RolesAndPermissionsSeeder
php artisan route:list --path=dashboard/events
pnpm install && pnpm build
```

To grow it, implement the capability interfaces the module actually needs - the registry dispatches
on `instanceof`, so no core edit is involved.

## Contract

Since platform 0.3.0 the contract is split into a minimal root interface plus
opt-in capability interfaces:

- `Nreljed\Platform\Contracts\Feature` — identity + lifecycle only (`name`,
  `slug`, `description`, `version`, `getComposerPackage`, `dependencies`,
  `enabled`, `onDisable`, `onUserDelete`). Mandatory for every feature.
- `Nreljed\Platform\Contracts\Capabilities\Has*` — one interface per concern
  (navigation, permissions, settings, dashboard widgets, notifications, media,
  sitemap, health checks, admin resources, content type, content editor, data
  backup, profile extension, shared data, deactivation). Implement only the ones
  your feature actually supports — the registry adapts via `instanceof`.

`Nreljed\Platform\Contracts\FeatureProvider` is the **backwards-compatible
composite** of `Feature` + every capability. It is `@deprecated` since 0.3.0 and
is removed in 1.0.0. Existing providers keep working unchanged because
`InteractsWithFeatures` supplies a safe no-op default for every capability
method. Only `name()` and `slug()` are mandatory to implement yourself.

The contract is frozen at the **required-method-set** level (see
`scripts/contract-signature.php --diff <old-tag> <new-tag>`): adding a method to
an existing interface is a breaking change that needs a MAJOR bump, while adding
a whole new interface is additive and allowed.

| Method                             | Purpose                                                                     |
| ---------------------------------- | --------------------------------------------------------------------------- |
| `name(): string`                   | Human-readable feature name                                                 |
| `slug(): string`                   | Unique key used to namespace permissions, shared data, nav groups           |
| `description(): ?string`           | One-liner                                                                   |
| `version(): string`                | Feature version (shown in system health)                                    |
| `dependencies(): array`            | Other feature slugs this feature depends on                                 |
| `getNavGroupLabel(): ?string`      | Sidebar group heading (e.g. "Shop", "Content")                              |
| `getNavItems(): array`             | Sidebar items                                                               |
| `getDashboardData(): array`        | Keyed data normalized into dashboard KPI cards (`label`, `icon`, `total`)   |
| `getAnalyticsSeries(): array`      | Extra analytics series for dashboard charts                                 |
| `getSharedData(): array`           | **Lazy closures** — resolved per-request into the `sharedData` Inertia prop |
| `getSettingsRoute(): ?string`      | **Route name** (not URL) of the feature's settings page, or `null`          |
| `hasSettings(): bool`              | Whether the feature has a settings page                                     |
| `getMetaFormattingConfig(): array` | Meta/formatting config for the settings UI                                  |
| `getRepeaterConfig(): array`       | Repeater field definitions                                                  |
| `getGalleryConfig(): array`        | Gallery/media config                                                        |
| `getProfileFieldsConfig(): array`  | Extra account profile fields                                                |
| `getEmailTemplates(): array`       | Email template registry entries (sample data for the simulator)             |
| `getNotificationTypes(): array`    | Notification types with channels + defaults                                 |
| `permissions(): array`             | Permission slugs granted to roles by `RolesAndPermissionsSeeder`            |
| `onUserDelete(int $userId): void`  | Cleanup hook when a user is deleted                                         |

## FeatureRegistry

`Nreljed\Platform\Services\FeatureRegistry` (singleton) reads `config('features.providers')`
and exposes:

- `all()` / `get(string $slug)` — feature lookup
- `getAllNavItems()` — injects `groupLabel` per item from `getNavGroupLabel()`
- `getAllSharedData()` — aggregates every provider's lazy closures
- `permissions()` — flat permission list
- `getDashboardData()` / `getAnalyticsSeries()` — dashboard aggregation
- `getSettingsRoute()` resolution (lazy — a stale route name degrades to "no settings link")

Consumers: `HandleInertiaRequests`, `PermissionGateServiceProvider`, `DashboardService`,
`FormattingService`, `NotificationTypeRegistry`, `EmailSettingsController`, and host services.

## Authoring Checklist

A provider must pass both conformance tests (`FeatureProviderContractTest` in the package and
a mirror in the host):

1. Implement `Feature` (+ the capability interfaces you need) and use `InteractsWithFeatures`.
   New modules should not implement the deprecated `FeatureProvider` composite.
2. `getSharedData()` returns **lazy closures** — never call `Inertia::share()` from a provider
   (it bypasses registry aggregation and breaks partial reloads). Consumers read
   `props.sharedData.{key}`.
3. `getNavItems()` returns sidebar items; icons resolve by name via `lib/icons.ts`
   (`registerFeatureIcons`) with a generic fallback.
4. `getSettingsRoute()` returns a **route name**, not a URL.
5. **Permission slugs are scoped** to the feature prefix (e.g. `shop.orders.view`). Generic
   admin permissions (no dot) are reserved for core.
6. Register the provider class in `config/features.php` `providers` and run
   `php artisan db:seed --class=RolesAndPermissionsSeeder` (idempotent,
   config-catalog backed — it creates the provider's permissions and maps them
   onto the default roles).
7. Self-load own routes + migrations from the provider's `boot()` (see `BlogServiceProvider`).

## Routes

Core routes are loaded by `PlatformServiceProvider::loadRoutes()` (web) + the host loads
its own. Feature providers self-load their routes from their own `Routes/` directory:

- Blog: `BlogServiceProvider::boot()` → routes from `src/Routes/` (prefix
  `dashboard/posts`, names `dashboard.posts.*`) + public `blog` routes. Ships as
  [`nreljed/blog`](https://github.com/nreljed/blog).
- Shop: the package's `ShopServiceProvider` loads 11 route files grouped with `web` +
  `permission:shop.view` (see the Shop package docs).

## Related

- [Blog](https://github.com/nreljed/blog) — the reference standalone feature package.
- [Architecture](architecture.md) — how the registry feeds Inertia shared props.
- Shop package `docs/architecture.md` — a full external feature provider.
