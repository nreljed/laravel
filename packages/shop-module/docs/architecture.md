# Architecture

How the Shop module plugs into the `nreljed/platform` platform and a host app.

## Package Layout

```
shop-module/
├── composer.json                 # PSR-4: Nreljed\ShopModule\ → src/
├── config/shop.php               # merged as config('shop')
├── database/migrations/          # 63 migrations, auto-loaded
├── resources/views/email/templates/  # 7 blade templates (auto-location)
└── src/
    ├── Commands/                 # 5 artisan commands
    ├── Http/
    │   ├── Controllers/          # admin + storefront + api controllers
    │   │   └── Api/              # public REST controllers (Sanctum)
    │   ├── Resources/            # API resource transformers
    ├── Models/                   # 32 Eloquent models
    ├── Pipelines/                # context classes + ordered pipe steps (cart, cart_lines, checkout)
    ├── Policies/                 # 10 authorization policies
    ├── Requests/                 # FormRequests (validation)
    ├── Resources/js/Pages/       # Inertia React pages (@nreljed/shop-module/Pages/...)
    ├── Routes/                   # 12 route files, self-loaded
    ├── Services/                 # 18 domain services
    ├── Support/                  # Countries (ISO 3166 map)
    └── ShopServiceProvider.php
```

## Pipelines

Multi-step flows (cart add, cart-line build, checkout) are composed as Laravel
pipelines instead of controller/service recursion. `config/shop.php` `pipelines`
declares each ordered step list; a shared context class is passed through, and
each step mutates the context (or returns a value to short-circuit, e.g. an error).
See [Services](services.md) for the context shapes and the checkout step order.

## Registration

The host installs the package via Composer VCS (`nreljed/shop-module` private repo)
and does four things:

1. **Composer repository** — declared in the host `composer.json`:
   ```json
   { "type": "vcs", "url": "https://github.com/nreljed/shop-module.git" }
   ```
2. **Provider** — auto-discovered through Laravel's `extra.laravel.providers`
   package auto-discovery list (or registered in `bootstrap/providers.php`).
3. **Feature registration** — the provider class is added to the `providers` array
   in `config/features.php`, so the platform `FeatureRegistry` aggregates its nav,
   permissions, email templates, notification types, gallery config, shared data,
   and dashboard data.
4. **Frontend** — see below.

`ShopServiceProvider` implements
`Nreljed\Platform\Contracts\FeatureProvider` via `InteractsWithFeatures`.

### FeatureProvider capabilities

| Contract method | What Shop provides |
|-----------------|---------------------|
| `name()`, `slug()`, `version()`, `dependencies()` | `Shop` / `shop` / `1.0.0` |
| `permissions()` | 17 permissions (see [Permissions](permissions.md)) |
| `getNavItems()` | Full **Shop** group for managers, **My Account** group for customers |
| `getNavGroupLabel()` | Group heading "Manage Shop" / "My Account" |
| `getDashboardData()` | KPI block: products, published, orders, pending, low stock |
| `getEmailTemplates()` | Simulator samples: order-confirmation, order-shipped, low-stock-alert |
| `getNotificationTypes()` | 4 types (see [index](index.md)) |
| `getGalleryConfig()` | `product_gallery` (max 10 images, hidden from media library) |
| `getMetaFormattingConfig()` | `price_fields` formatted with currency |
| `getSettingsRoute()` | Route name `admin.shop.settings.index` |
| `hasSettings()` | Enables the settings tab |
| `getSharedData()` | Lazy-closure shared props (below) |
| `onUserDelete($userId)` | Orphans orders (`user_id` → null) instead of deleting |

The provider also: registers 10 policies via `Gate::policy()`, registers 5 artisan
commands, self-loads all routes (11 files — no host `routes/shop*.php`), and
registers `ShopService` as a singleton.

## Shared data

All storefront shared props come from `getSharedData()` as **lazy closures**. The
core `FeatureRegistry` aggregates them under the top-level `sharedData` prop
(wrapped in `Inertia\AlwaysProp` by the host middleware, so they survive partial
reloads). Frontend reads `props.sharedData.currencies`, `props.sharedData.cart`,
etc. — **never** call `Inertia::share()` from the provider; it bypasses registry
aggregation and breaks partial reloads.

> `cart` is shared at `props.sharedData.cart`. Page-level cart data (admin cart
> pages, checkout) is passed as a normal page prop from the controller — only the
> storefront header badge reads the shared prop.

## Frontend wiring (host)

- **Vite alias:** `@nreljed/shop-module` → `vendor/nreljed/shop-module/src/Resources/js/`
  (installed via Composer VCS; production alias points at the vendor copy; also in
  `tsconfig.json` paths).
- **Page registration:** the host imports `registerShopPages()` from
  `@nreljed/shop-module/pages` and spreads it into its page map under `shop/*`
  Inertia component keys. The legacy `@shop` glob scheme is superseded.
- **Tailwind:** the host's `resources/css/app.css` uses `@source` directives; the
  shop JS must be included (currently
  `@source "../../vendor/nreljed/shop-module/src/Resources/js/"`). Because Tailwind
  config uses `source(none)`, a missing `@source` silently drops every shop utility
  class.
- **TypeScript:** the package JS is excluded from the host `tsconfig` `include` for
  parity with the pre-extraction state; the `@nreljed/shop-module` path mapping is
  the future-proof entry point.

## Boot order

```
Host boot
  → config/core.php registers [ShopServiceProvider, ...]
  → ShopServiceProvider::register()
       ShopService singleton
  → ShopServiceProvider::boot()
       loadMigrationsFrom(database/migrations)
       loadViewsFrom(resources/views, 'shop') + View::addLocation(...)
       mergeConfigFrom(config/shop.php, 'shop')
       loadRoutesFrom(src/Routes/*.php)  ← 11 files
       Gate::policy(...)  × 9
       commands() → 5 commands
```

`schedule:publish` (scheduled products/posts/properties) lives in the
`nreljed/blog` package, not here.
