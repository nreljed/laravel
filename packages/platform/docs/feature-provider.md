# Feature Provider

Self-contained features (Blog in-core, Shop as a separate package) are discovered through a
single contract aggregated by the `FeatureRegistry`. Adding a feature requires **no core
edits** — just register its service provider in `config/features.php` `providers`.

## Contract

`Nreljed\Platform\Contracts\FeatureProvider` — every provider implements it and combines
it with the `InteractsWithFeatures` trait (supplies safe no-op defaults for every capability
method). Only `name()` and `slug()` are mandatory to implement yourself.

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
| `permissions(): array`             | Permission slugs for `permissions:sync`                                     |
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

1. Implement `FeatureProvider` + use `InteractsWithFeatures`.
2. `getSharedData()` returns **lazy closures** — never call `Inertia::share()` from a provider
   (it bypasses registry aggregation and breaks partial reloads). Consumers read
   `props.sharedData.{key}`.
3. `getNavItems()` returns sidebar items; icons resolve by name via `lib/icons.ts`
   (`registerFeatureIcons`) with a generic fallback.
4. `getSettingsRoute()` returns a **route name**, not a URL.
5. **Permission slugs are scoped** to the feature prefix (e.g. `shop.orders.view`). Generic
   admin permissions (no dot) are reserved for core.
6. Register the provider class in `config/features.php` `providers` and run
   `php artisan permissions:sync`.
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
