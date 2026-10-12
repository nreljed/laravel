---
name: feature-architecture
description: "Feature-based module architecture for the nreljed SaaS platform. Activate when adding new features, creating FeatureServiceProviders, registering features in config('features.providers'), implementing the shared FeatureProvider contract, wiring frontend module page kits (registerCoreTemplates / registerBlogPages), or when the user mentions feature architecture, feature registration, or self-contained features. Covers the provider contract, registration, the frontend module page kits, and the shipped platform modules (PublicSite, Ai) + external module packages (blog, shop-module, booking-module)."
license: MIT
metadata:
  author: laravel
---

# Feature Architecture

## Concept

Each domain (PublicSite, Ai, blog, shop, booking, future features) is a
self-contained module with its own routes, controllers, models, migrations,
and frontend pages. Modules plug into the platform by implementing the
`Nreljed\Platform\Contracts\FeatureProvider` contract and registering via the
FeatureRegistry. The kernel provider (`PlatformServiceProvider`) stays
feature-agnostic.

Three kinds of modules exist:

- **Platform modules** live in the core package under
  `C:\Users\nrelj\Local Sites\platform\src\{Name}/` (e.g. `Ai`, `PublicSite`),
  released by tag, consumers pull with `composer update nreljed/platform`.
  Never hand-edit vendor (or the `packages/*` mirrors — those are synced
  one-way from the standalone repo).
- **Module packages** are sibling Composer packages that consume the platform
  contract: `nreljed/blog` (`C:\Users\nrelj\Local Sites\blog`), `nreljed/shop-module`,
  `nreljed/booking-module`. They implement the SAME `FeatureProvider` contract
  and register themselves in the consuming app's `config('features.providers')`.
- **Project modules** live in the app under `app/{Name}/` and are discovered
  the same way.

## Directory Structure

Platform module (inside the platform repo):

```
src/{Name}/                                # platform modules in nreljed/platform
  {Name}ServiceProvider.php                # FeatureProvider (routes, permissions, policy)
  Controllers/                             # Module controllers
  Models/                                  # Module models
  Routes/
    web.php                                # Module routes (public and/or auth-gated)
  Resources/js/                            # React pages (optional) - NOT used by platform (see JS kit)
  database/migrations/                     # Module migrations
```

Module package (e.g. nreljed/blog):

```
src/BlogServiceProvider.php                # FeatureProvider (uses Nreljed\Platform\Concerns\InteractsWithFeatures)
src/{Controllers,Models,Routes,...}/       # Module code, PSR-4 Nreljed\Blog\
src/Resources/js/                          # Frontend page kit - Pages/ + components
src/Resources/js/pages.ts                  # export registerBlogPages(): Record<string, PageImport>
```

## The FeatureProvider Contract

`Nreljed\Platform\Contracts\FeatureProvider` declares the capability surface
(slug, nav, dashboard data, shared data, settings, permissions, sitemap URLs,
media subdirectory, content description, health checks, scheduled tasks,
backup capabilities, `onUserDelete`, `dependencies`, etc.). Providers
`use Nreljed\Platform\Concerns\InteractsWithFeatures` (no-op defaults for
every optional method) and implement only what they use. Only `name()` and
`slug()` are **mandatory**.

```php
<?php

namespace Nreljed\Blog;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Nreljed\Platform\Concerns\InteractsWithFeatures;
use Nreljed\Platform\Contracts\FeatureProvider;

class BlogServiceProvider extends ServiceProvider implements FeatureProvider
{
    use InteractsWithFeatures;

    public function name(): string { return 'Posts'; }

    public function slug(): string { return 'posts'; }

    public function boot(): void
    {
        if (! $this->enabled()) {
            return;  // disabled features contribute no routes/nav/permissions
        }

        Route::middleware(['web', 'auth', 'verified', 'throttle:60,1'])
            ->prefix('dashboard/posts')
            ->name('dashboard.posts.')
            ->group(__DIR__.'/Routes/web.php');

        Route::middleware('web')
            ->prefix('blog')
            ->group(__DIR__.'/Routes/public.php');

        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
    }
}
```

For module anchors anchored inside a larger group (e.g. the Ai module's
account routes), keep the group prefix + route-name prefix here so final route
names stay identical to what they were before the module extraction:

```php
Route::middleware(['web', 'auth', 'maintenance'])
    ->prefix('/dashboard/account')
    ->name('dashboard.account.ai-keys.')   // + bare route names below → dashboard.account.ai-keys.index
    ->group(__DIR__.'/Routes/web.php');
```

## Registration (two steps — both required)

### 1. `bootstrap/providers.php` (consuming app)

Laravel doesn't auto-discover library-internal providers. Every feature
provider (platform module AND module package) must be listed for the
`FeatureRegistry` to resolve it (via `app()->getProvider()`):

```php
use Nreljed\Platform\PublicSite\PublicSiteServiceProvider;
use Nreljed\Platform\Ai\AiServiceProvider;
use Nreljed\Blog\BlogServiceProvider;

return [
    PlatformServiceProvider::class,
    PublicSiteServiceProvider::class,
    AiServiceProvider::class,
    BlogServiceProvider::class,   // module package
    // project modules, app/{Name}/...
];
```

### 2. `config('features.providers')`

The registry reads `config('features.providers', [])`. The package's default
list ships the platform modules; a published/app `config/features.php`
(publish with `php artisan vendor:publish --tag core-config`) **replaces** the
list, so apps must list every module they want:

```php
'providers' => [
    PublicSiteServiceProvider::class,
    AiServiceProvider::class,
    Nreljed\Blog\BlogServiceProvider::class,   // module package
    App\TestPosts\TestPostsServiceProvider::class, // project module
],
```

`FeatureRegistry` (`Nreljed\Platform\Services\FeatureRegistry`) resolves each
entry with `app()->getProvider($class)` and throws if it isn't both a
registered provider and a `FeatureProvider`. Feature enablement is two-level:
(1) persisted override (settings table, key `feature_enabled.<slug>`), else
(2) config default `features.enabled.<slug>`; a disabled provider contributes
no routes, nav, dashboard data, settings or permissions (aggregators use
`enabledProviders()`).

## Frontend Module Kit (hybrid auto-discovery)

Backend modules auto-discover via `config('features.providers')`; frontend
pages are **explicit but modular**. The platform ships `registerCoreTemplates()`
(from `resources/js/dashboard.ts`) — the control-panel page map (dashboard,
admin, account, auth, error). Module packages ship their own `pages.ts`
exporting `register{Name}Pages()` (a `Record<string, PageImport>` of lowercase
Inertia keys), e.g. nreljed/blog `src/Resources/js/pages.ts` →
`registerBlogPages()`.

Consuming apps import each kit once in their `resources/js/page-config.ts`
and spread it into `basePages` next to `registerCoreTemplates()`:

```ts
import { registerCoreTemplates } from "@nreljed/platform/dashboard";
import { registerBlogPages } from "@nreljed/blog/pages";

export const pageConfig: FoundationAppConfig = {
  basePages: { ...registerCoreTemplates(), ...registerBlogPages() },
  // pageOverrides: project theme wins, applied last
};
```

Frontend page *keys* must be lowercase (Inertia lowercases controller component
names). Template/marketing pages are a **theme concern**: the app ships forked
theme templates under its own `resources/js/frontend/pages/` and declares them in
`pageOverrides` (applied last). No starter theme package is auto-registered.

## Shipped Platform Modules

| Module     | Provider                                      | Routes                                     | Notes         |
| ---------- | --------------------------------------------- | -------------------------------------------| ------------- |
| PublicSite | `...\PublicSite\PublicSiteServiceProvider`    | `/`, `/about`, `/services`, `/contact`     | —             |
| Ai         | `...\Ai\AiServiceProvider`                    | `/dashboard/account/ai-keys` (+ policy)    | BYOK key resolution via `provider()` method |
| (blog)     | `Nreljed\Blog\BlogServiceProvider` (package)  | `/dashboard/posts`, `/blog`                | slug `posts`; packs the Lexical editor |

The blog module is a **separate Composer package** (`nreljed/blog`, namespace
`Nreljed\Blog`) — the Lexical post editor lives in
`blog/src/Resources/js/Components/editor/`, NOT in the platform package.

**Deploy is NOT a FeatureProvider** (token-protected server infrastructure).
Its routes live in `src/Deploy/Routes/web.php`, required from the kernel
provider's `routes/web.php`.

## Permissions

Feature permissions are declared in `permissions()` and are picked up
automatically by `FeatureRegistry::permissions()`, the
`PermissionGateServiceProvider`, and the shared seeder — no seeder edits
needed. Convention: dot-namespaced for features (`posts.view`), kebab-case for
global permissions (`manage-users`). Grant to roles via `config/roles.php`.

## Adding a New Feature

1. Platform module: edit the core clone `C:\Users\nrelj\Local Sites\platform`
   → `src/{Name}/` (tag → release → composer update). Module package: edit its
   standalone repo (e.g. `C:\Users\nrelj\Local Sites\blog`). Project module:
   `app/{Name}/`. **Keep `packages/*` mirrors in sync** via
   `scripts/sync-package-mirror.mjs` (commit new files in the standalone repo
   first — `git ls-files` drives the sync).
2. Create `{Name}ServiceProvider.php` implementing the platform
   `FeatureProvider` contract + use `InteractsWithFeatures`; implement
   `name()`/`slug()` and whatever else you need; register routes/policies in
   `boot()`. (Or run `php artisan make:feature`.)
3. Register the provider in the app `bootstrap/providers.php` **and**
   `config('features.providers')`.
4. Declare feature permissions in `permissions()`; grant them to roles via
   `config/roles.php`.
5. Ship the frontend kit (`resources/js/pages.ts` → `register{Name}Pages()`)
   and spread it in the app `page-config.ts`.
6. Run: `php artisan db:seed --class=RolesAndPermissionsSeeder`.

## Key Rules

- Module must be self-contained — no cross-module dependencies (module
  packages may depend on the platform package only)
- Group prefixes/names stay put when extracting routes from the kernel so
  existing routes keep their names (tests + Ziggy depend on them)
- Permissions follow `{feature}.action`; global permissions are kebab-case
- `onUserDelete()` must clean up all module data
- Migrations must be reversible
- Never hand-edit `packages/*` mirrors — they are one-way copies of the
  standalone repos; edit the standalone repo and run the sync script