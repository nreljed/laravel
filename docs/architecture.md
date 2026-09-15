# Workspace Architecture Reference

The 30,000-foot view of how the monorepo is layered, how packages and apps
relate, and how data flows through the system. For operational details see the
root `AGENTS.md` (the source of truth) plus the per-area docs under `docs/`.

## Layer diagram

```
┌──────────────────────────────────────────────────────────────────────┐
│ CONSUMER APPS (thin shells — own routes/web.php, theme, pageConfig)  │
│                                                                      │
│  apps/laravel.shop      apps/laravel.booking     app/ (mono root)    │
│  (shop consumer)        (booking consumer)       (platform demo)     │
└───────────────┬───────────────────────┬──────────────────────────────┘
                │  Composer path repos  │  pnpm workspace symlinks
                │  (vendor/ in prod)    │  (@nreljed/* in node_modules)
┌───────────────┴───────────────┬───────┴──────────────────────────────┐
│ FEATURE MODULES (packages)    │                                      │
│                               │                                      │
│  packages/platform/           │  Nreljed\Platform — ENGINE           │
│  packages/blog/               │  Nreljed\Blog     — posts CMS         │
│  packages/shop-module/        │  Nreljed\ShopModule — e-commerce      │
│  packages/booking-module/     │  Nreljed\BookingModule — bookings +   │
│                               │      properties (2 providers)         │
│  each: FeatureProvider + page kit + migrations + config               │
└───────────────────────────────┴──────────────────────────────────────┘
                │
┌───────────────┴──────────────────────────────────────────────────────┐
│ PLATFORM ENGINE (ships inside packages/platform)                     │
│  PlatformServiceProvider → routes, middleware, gates, scheduler      │
│  FeatureRegistry         → discovers + aggregates all providers      │
│  HandleInertiaRequests   → shared props: auth, nav, flash, settings  │
│  RBAC (Role/Permission)  → permission middleware + gates             │
│  Deploy / Media / AI / Notifications / Settings                      │
└──────────────────────────────────────────────────────────────────────┘
```

## Package ↔ app dependency graph

```
packages/platform  ◄─ packages/blog ◄─ packages/booking-module
       ▲                      │                │
       │                      ▼                ▼
       └───────  consumed by: app/, apps/laravel.shop, apps/laravel.booking
```

- Every module `require`s `nreljed/platform`. Platforms are aggregated by the
  consumer's `config/features.php` + `bootstrap/providers.php`.
- Frontend mirrors the same graph via `peerDependencies` (`@nreljed/*`) and the
  consumer's `page-config.ts` spreads the page kits.

## Feature wiring (what a provider plumbed into

```php
# bootstrap/providers.php          # service providers register routes/models
# config/features.php              # FeatureProvider classes + enabled slugs
# resources/js/page-config.ts      # register{Name}Pages() spread into basePages
```

Then `FeatureRegistry` (platform) aggregates: nav items, dashboard data,
permissions (→ RBAC seeder + gates), health checks, sitemap URLs, scheduled
tasks, orphan-media targets, settings groups.

Frontend page resolution: Inertia resolves **lowercased** component names.
`page-config.ts` builds `basePages` (core + feature kits) + optional
`projectPages`/`projectPrefix` + `pageOverrides` (applied last, wins — used to
swap a core page for a marketing page without forking core).

## Route ownership

| Prefix              | Owner                     | Notes                                    |
| ------------------- | ------------------------- | ---------------------------------------- |
| `/` public, `/dashboard`, `/dashboard/admin`, `/deploy/*`, `/api/notifications`, auth, maintainance, csp-report | **platform** (`packages/platform/routes`) | platform-owned |
| `/dashboard/posts`, blog public | **blog** | blog service provider, gated on `enabled()` |
| `/dashboard/shop`, `/store`, webhooks | **shop-module** | shop service provider |
| `/dashboard/bookings`, `/dashboard/properties`, guest flow | **booking-module** | booking + properties providers |
| anything else       | **consumer app**          | `routes/web.php` (thin)                  |

Consumers never re-own a dependency's routes, migrations, or models — those are
canonical in the package. The app shell only adds app-specific routes.

## Auth / RBAC flow

1. Auth routes (platform) → session login via Laravel Breeze + Sanctum.
2. `middleware('permission:slug')` checks the request against the `permissions`
   table via the slug-based Role/Permission catalog (`config/roles.php`).
3. Gates auto-register from `FeatureRegistry::permissions()`
   (`PermissionGateServiceProvider`).
4. `HandleInertiaRequests` shares `auth.user`, `userCapabilities`,
   `featureNavItems`, `flash`, notification counts.

## Settings flow

`SettingsService` (platform) is a static helper over the `settings` table with
`Cache::remember`. Feature providers extend it by declaring `getSettingsGroups()`.
The admin settings page renders each feature's `getSettingsSection()` declarative
definition (tab, label, component, nonce).

## Deploy flow (both models share it)

```
push main → deploy.yml → ci.yml + deploy-package (deploy.zip) + GitHub Release
  → push-deploy → POST /deploy/env (token) → poll /deploy/check
  → /deploy/upload fallback → FTP fallback (DirectAdmin)
```

Modules release independently: `vX.Y.Z` tag → GitHub Release →
`{module}-updated` repository_dispatch → consumers open `composer update` PRs.

## Two consumption models (recap)

|                        | Model A — standalone           | Model B — workspace mono (default)  |
| ---------------------- | ------------------------------ | ----------------------------------- |
| PHP resolution         | Composer VCS → `vendor/`       | Composer path repos → `vendor/`     |
| npm resolution         | direct install (npm/pnpm root) | pnpm workspace symlinks             |
| App ownership          | own git repo + CI/CD           | tree owned by the mono              |
| Module iteration       | tag → update                   | edit package, migrate, HMR          |
| Use for               | real product deployments       | this dev monorepo, module dev loop  |

For a new project follow `docs/new-project.md` (Model A) and
`docs/module-development.md` (writing a new module).

## Where things live

- Engine + kit + platform pages: `packages/platform/`
- Feature docs: `docs/core/*`, `docs/module-development.md`, `docs/new-project.md`
- Archive of pre-module docs (the pre-2026-08 "dashboard-packaging" era):
  `docs/archive/`, `docs/core-dashboard-package*.md` — historical reference
  only; do not use as current spec.