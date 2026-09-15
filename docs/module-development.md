# Developing a Nreljed Feature Module

A **module** is a self-contained Composer + npm package that a consumer app
installs to add a feature to its dashboard platform. This guide documents the
contract a module must implement — the `FeatureProvider` interface, the
frontend **page kit** convention, the composer/npm file shape, and the release
process. The platform package itself is a module (PublicSite, Ai) and so are
Blog, Shop and Booking modules.

## Package layout (standard)

```
{module}/
  composer.json
  package.json
  src/
    {Namespace}\
      {Name}ServiceProvider.php        # FeatureProvider + routes/migrations
      Controllers/ Models/ Policies/ ...
      Resources/js/                    # page kit lives here
        pages.ts                       # exports register{Name}Pages()
        Pages/**/*.tsx                 # module-owned frontend
  database/migrations/                 # CANONICAL — consumers must not re-own these
  config/                              # per-module config files
  tests/                               # structural/contract suite (module CI)
```

## composer.json (standardized)

```json
{
    "name": "nreljed/{module}",
    "description": "{One-line} feature for the Nreljed dashboard platform ...",
    "version": "0.1.x",
    "type": "library",
    "license": "proprietary",
    "authors": [{ "name": "Nreljed" }],
    "require": {
        "php": "^8.4",
        "laravel/framework": "^13.0",
        "nreljed/platform": "^0.1"
    },
    "require-dev": {
        "mockery/mockery": "^1.6",
        "nunomaduro/collision": "^8.6",
        "phpunit/phpunit": "^13.0"
    },
    "autoload": {
        "psr-4": { "Nreljed\\{Namespace}\\": "src/" }
    },
    "autoload-dev": {
        "psr-4": { "Nreljed\\{Namespace}\\Tests\\": "tests/" }
    },
    "extra": {
        "laravel": { "providers": ["Nreljed\\{Namespace}\\{Name}ServiceProvider"] }
    },
    "scripts": {
        "test": "phpunit"
    },
    "config": { "allow-plugins": { "php-http/discovery": true } },
    "minimum-stability": "stable",
    "prefer-stable": true
}
```

Rules:

- **No `repositories` block.** Modules resolve `nreljed/platform` from the
  *consumer's* repo config (VCS in standalone apps, a path repo in the
  workspace mono). A stale inline VCS repo caused the workspace's modules to
  bypass local path installs and pull the tagged dist zip instead.
- `version` is explicit and bumped in lockstep with the npm `version` — the
  two must never drift (release automation writes both).
- Provider auto-discovery: `extra.laravel.providers`. A multi-domain module
  (e.g. booking-module: `Properties` + `Booking`) lists **both** providers.
- Every module `require`s `nreljed/platform`; extra deps only if actually used
  (shop: `stripe/stripe-php`, `barryvdh/laravel-dompdf`; booking: none).

## The FeatureProvider contract

Implement on the service provider, combine with `InteractsWithFeatures`:

```php
<?php

namespace Nreljed\ShopModule;

use Illuminate\Support\ServiceProvider;
use Nreljed\Platform\Concerns\InteractsWithFeatures;
use Nreljed\Platform\Contracts\FeatureProvider;

final class ShopServiceProvider extends ServiceProvider implements FeatureProvider
{
    use InteractsWithFeatures;

    public function register(): void
    {
        // container bindings, mergeConfigFrom, singletons ...
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'shop');
        $this->loadRoutesFrom(__DIR__.'/../Routes/web.php'); // gate on enabled()

        if ($this->enabled()) {
            $this->loadRoutesFrom(__DIR__.'/../../routes/web.php');
        }
    }

    public function name(): string
    {
        return 'Shop';
    }

    public function slug(): string
    {
        return 'shop';
    }

    public function getComposerPackage(): string
    {
        return 'nreljed/shop-module';
    }

    public function permissions(): array
    {
        return ['shop.view', 'shop.manage'];
    }

    public function getNavGroupLabel(): ?string
    {
        return 'Commerce';
    }

    public function getNavItems(): array
    {
        return [
            ['label' => 'Products', 'href' => route('shop.products.index'), 'permission' => 'shop.view'],
        ];
    }

    public function getHealthChecks(): array
    {
        return ['shop_products_table' => Schema::hasTable('products')];
    }
}
```

| Method | Status            | Purpose                                                                    |
| ------ | ----------------- | -------------------------------------------------------------------------- |
| `name()`  | **required**      | Human label shown in nav, module manager, health page                      |
| `slug()`  | **required**      | Unique feature id — persisted settings key, permission prefix, route prefix |
| everything else | optional | `InteractsWithFeatures` supplies safe no-op defaults; override what you support |

### `enabled()` resolution

1. **Persisted override** — `FeatureSettingsService` (settings table, group
   `core`, key `feature_enabled.<slug>`), writes from the module-management
   admin page.
2. **Config default** — `features.enabled.<slug>` in the consumer's
   `config/features.php`.
3. **Fail-closed** — no entry anywhere → disabled.

A disabled feature contributes **no routes, nav, dashboard data, settings, or
permissions**. `hourly:cleanup`-style state is preserved so toggling back on is
non-destructive (see `onDisable()` for opt-in cleanup).

### FeatureRegistry

`Nreljed\Platform\Services\FeatureRegistry` reads `config('features.providers')`,
resolves each class with `app()->getProvider($class)`, and **throws a
`RuntimeException` at boot** if a listed provider is not registered in
`bootstrap/providers.php` or does not implement `FeatureProvider`. A provider
listed but disabled is skipped by the aggregators (nav, permissions, etc.).

## Frontend page kit

Each module ships its pages as an opt-in `register{Name}Pages()` that a consumer
spreads into its `pageConfig.basePages`:

```ts
// src/Resources/js/pages.ts
export function registerShopPages(): Record<string, PageImport> {
  const pages = import.meta.glob<PageImport>("./Pages/**/*.tsx");
  return Object.fromEntries(
    Object.entries(pages).map(([path, importer]) => {
      const name = path
        .replace(/^\.\/Pages\//, "")
        .replace(/\.tsx$/, "")
        .toLowerCase();
      return [`shop/${name}`, importer];
    }),
  );
}
```

Conventions:

- **Prefix namespaces are lowercase**: core pages are unprefixed, blog uses
  `posts/`, shop `shop/`, booking `bookings/` + `properties/`. Inertia resolves
  components by lowercased name, so controllers render
  `Inertia::render('shop/product')`.
- **Scoped exports for multi-domain modules**: booking-module exposes two entry
  points, `@nreljed/booking-module/booking/pages` and
  `.../properties/pages`, each registering its own prefix. One `pages.ts` per
  sub-domain keeps per-domain file trees independent.
- The kit path is just a file convention — `import.meta.glob` is a Vite
  feature and works regardless of how the module is installed (workspace
  symlink or `vendor/`), because the consumer's vite alias rewrites the package
  import to the real resource directory.

`package.json` exports map (convention):

```json
{
  "name": "@nreljed/{module}",
  "version": "0.1.x",
  "peerDependencies": {
    "react": "^19.2",
    "react-dom": "^19.2",
    "@inertiajs/react": "^3.0.0",
    "@base-ui/react": "latest",
    "lucide-react": "latest",
    "tailwind-merge": "latest",
    "clsx": "latest"
  },
  "exports": {
    "./pages": "./src/Resources/js/pages.ts",
    "./icons": "./src/Resources/js/icons.ts",   // optional, only if shipped
    "./*": "./src/Resources/js/*"
  }
}
```

Every runtime package a module imports must be declared as a `peerDependency`
AND installed as a direct dependency by consuming apps (duplicate-React/
missing-package build failures are the symptom of a gap). Keep `./pages` as the
required public entry; `./icons` and the wildcard are optional.

## Routes

Route loading is the module provider's job — `loadRoutesFrom` in `boot()`,
gated to `if ($this->enabled())`. Modules do not touch the core package's
`routes/web.php` (that file is platform-owned and registers public/dashboard/
admin/deploy/auth routes). A module adds its own prefix group, e.g.
`dashboard/shop` with `Route::middleware(['auth', 'verified'])->prefix('dashboard/shop')->name('shop.')`.

## Consumer-side registration (per consumer app)

See `docs/new-project.md` — the module activation checklist (13 steps). The
irreducible triple is:

1. `bootstrap/providers.php` → provider class
2. `config/features.php` → provider + `enabled` slug
3. `resources/js/page-config.ts` → spread `register{Name}Pages()` + vite/ts
   aliases + npm peer deps

## Release process

1. Edit the module in this workspace (or its own repo clone).
2. Bump BOTH `composer.json` `version` and `package.json` `version` to the same
   `vX.Y.Z`.
3. Tag `vX.Y.Z` → module CI creates the GitHub Release and fires the
   `{module}-updated` repository_dispatch to consumers.
4. Consumers update: `composer update nreljed/{module}` and re-install npm.

**Workspace consumers** (the mono apps) additionally keep the *path repo* active
during module development: `php artisan migrate` picks up new migrations
immediately, and Vite HMR picks up page-kit edits through the workspace symlink.
Flip to VCS only for the final push of a release.

## Testing

Modules ship a structural/contract suite (provider shape, `register{Name}Pages`
exports, migration presence) ORM-neutral enough to run in module CI; consumers
keep their own integration tests (`tests/Feature/{Module}/`). Never duplicate
module-owned migrations in consumer `database/migrations/` — the migration that
creates a table belongs next to the model in the module.