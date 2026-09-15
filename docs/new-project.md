# Starting a New Project with the Nreljed Platform

This guide explains how to bootstrap a brand-new Laravel application that consumes
the Nreljed platform and any of its feature modules. Everything is manual and
explicit — there is no installer command yet. Follow the checklist per module.

## Prerequisites

- PHP 8.4+, Composer
- Node 22+, pnpm (workspace model) or npm (standalone model)
- Access to the private package repos (configured via `COMPOSER_AUTH` or an
  SSH key that can clone `github.com/nreljed/*`)

## Available Modules

| Package              | Composer pkg           | npm pkg                  | Page kit export(s)                                          | Feature slugs                          |
| -------------------- | ---------------------- | ------------------------ | ----------------------------------------------------------- | -------------------------------------- |
| platform (engine)    | `nreljed/platform`     | `@nreljed/platform`      | `registerCoreTemplates()` (`@nreljed/platform/dashboard`)  | public-site, ai (bundled)             |
| blog                 | `nreljed/blog`         | `@nreljed/blog`          | `registerBlogPages()` (`@nreljed/blog/pages`)              | posts                                  |
| shop-module          | `nreljed/shop-module`  | `@nreljed/shop-module`   | `registerShopPages()` (`@nreljed/shop-module/pages`)       | shop                                   |
| booking-module       | `nreljed/booking-module`| `@nreljed/booking-module`| `registerBookingPages()` (`.../booking/pages`) + `registerPropertiesPages()` (`.../properties/pages`) | bookings, properties |

## Two Consumption Models

### Model A — Standalone repo (recommended for a real product)

Each package resolves from **VCS** into `vendor/`. The app is its own git repo
with its own CI/CD.

```
composer.json        → VCS repos for every package
vendor/nreljed/*     → Composer installs
resources/js/        → app shell + page-config.ts
```

### Model B — Workspace monorepo (current dev model)

Packages are workspace members (`packages/*`), parents to consumer apps
(`apps/*`). PHP uses **path repos**, npm uses **pnpm workspace symlinks**.
See `../../AGENTS.md` "Workspace" section.

This guide covers Model A (standalone), since that is the new-project path.
For Model B, follow the existing `apps/*` apps as templates.

---

## Step 1 — Create the Laravel project

```bash
composer create-project laravel/laravel my-app
cd my-app
```

## Step 2 — Add the packages

Edit `composer.json`. Example for a **shop** product (platform + blog + shop):

```json
{
  "repositories": [
    { "type": "vcs", "url": "https://github.com/nreljed/platform.git" },
    { "type": "vcs", "url": "https://github.com/nreljed/blog.git" },
    { "type": "vcs", "url": "https://github.com/nreljed/shop-module.git" }
  ],
  "require": {
    "php": "^8.4",
    "laravel/framework": "^13.8",
    "inertiajs/inertia-laravel": "^3.3.3",
    "laravel/sanctum": "^4.3",
    "tightenco/ziggy": "^2.6.4",
    "intervention/image-laravel": "^4.0",
    "laravel/ai": "^0.10",
    "minishlink/web-push": "^11.0",
    "nreljed/platform": "^0.1",
    "nreljed/blog": "^0.1",
    "nreljed/shop-module": "^0.1"
  }
}
```

For a **booking** product, swap `nreljed/shop-module` for
`nreljed/booking-module` (and add `stripe/stripe-php` if payments are needed).

> **No private `repositories` block inside the packages.** Modules resolve their
> own `nreljed/platform` dependency from the app's repos. If Composer complains
> it cannot find any repositories, the app's repo config is what matters — the
> package files no longer carry their own.

```bash
composer update
```

## Step 3 — Register providers

Replace `bootstrap/providers.php` with the platform wiring. Shop example:

```php
<?php

use App\Providers\AppServiceProvider;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider;
use Nreljed\Blog\BlogServiceProvider;
use Nreljed\Platform\Ai\AiServiceProvider;
use Nreljed\Platform\PlatformServiceProvider;
use Nreljed\Platform\Providers\PermissionGateServiceProvider;
use Nreljed\Platform\PublicSite\PublicSiteServiceProvider;
use Nreljed\ShopModule\ShopServiceProvider;

return array_filter([
    PlatformServiceProvider::class,
    BlogServiceProvider::class,
    PublicSiteServiceProvider::class,
    AiServiceProvider::class,
    ShopServiceProvider::class,
    PermissionGateServiceProvider::class,
    AppServiceProvider::class,
]);
```

Booking example adds both booking providers instead of the shop one:

```php
use Nreljed\BookingModule\Booking\BookingServiceProvider;
use Nreljed\BookingModule\Properties\PropertiesServiceProvider;
// in the array:
    BookingServiceProvider::class,
    PropertiesServiceProvider::class,
```

## Step 4 — Configure features

Publish/create `config/features.php`:

```php
<?php

use Nreljed\Blog\BlogServiceProvider;
use Nreljed\Platform\Ai\AiServiceProvider;
use Nreljed\Platform\PublicSite\PublicSiteServiceProvider;
use Nreljed\ShopModule\ShopServiceProvider;

return [

    'providers' => [
        PublicSiteServiceProvider::class,
        BlogServiceProvider::class,
        AiServiceProvider::class,
        ShopServiceProvider::class,
    ],

    'enabled' => [
        'public-site' => true,
        'posts' => true,
        'ai' => true,
        'shop' => true,
    ],

];
```

Booking swaps the shop provider for the two booking providers and toggles
`bookings` / `properties`.

> Features are toggleable at runtime by an admin from
> `/dashboard/admin/settings/modules`. This config only sets the out-of-the-box
> state. Disabled features contribute no routes, nav, or permissions.

## Step 5 — Wire the frontend page kits

Create `resources/js/page-config.ts`:

```typescript
import type { FoundationAppConfig, PageImport } from "@nreljed/platform/app";
import { registerCoreTemplates } from "@nreljed/platform/dashboard";
import { registerBlogPages } from "@nreljed/blog/pages";
import { registerShopPages } from "@nreljed/shop-module/pages";

export const pageConfig: FoundationAppConfig = {
  basePages: {
    ...registerCoreTemplates(),
    ...registerBlogPages(),
    ...registerShopPages(),
  },
};
```

Booking example adds the two booking kits instead of shop:

```typescript
import { registerBookingPages } from "@nreljed/booking-module/booking/pages";
import { registerPropertiesPages } from "@nreljed/booking-module/properties/pages";
// spread both into basePages
```

Then point the entry point at it (see `resources/js/app.tsx` in existing apps):
the client entry imports `pageConfig` and calls `createFoundationApp()`.

## Step 6 — Vite aliases + refresh

`vite.config.js` — add one alias + refresh glob per package. Standalone apps
point at `vendor/`:

```javascript
resolve: {
  alias: {
    '@nreljed/platform': path.resolve(__dirname, 'vendor/nreljed/platform/resources/js'),
    '@nreljed/platform/*': path.resolve(__dirname, 'vendor/nreljed/platform/resources/js'),
    '@nreljed/blog': path.resolve(__dirname, 'vendor/nreljed/blog/src/Resources/js'),
    '@nreljed/blog/*': path.resolve(__dirname, 'vendor/nreljed/blog/src/Resources/js'),
    '@nreljed/shop-module': path.resolve(__dirname, 'vendor/nreljed/shop-module/src/Resources/js'),
    '@nreljed/shop-module/*': path.resolve(__dirname, 'vendor/nreljed/shop-module/src/Resources/js'),
  },
},
```

>`@nreljed/booking-module` scopes by sub-domain — two alias pairs, one per
>domain, both pointing into the same package:
>```javascript
>'@nreljed/booking-module/booking/*': path.resolve(__dirname, 'vendor/nreljed/booking-module/src/Booking/Resources/js'),
>'@nreljed/booking-module/properties/*': path.resolve(__dirname, 'vendor/nreljed/booking-module/src/Properties/Resources/js'),
>```

And extend the `laravel()` plugin's `refresh` array with the same package
directories so HMR picks up package edits.

## Step 7 — TypeScript paths

`tsconfig.json`:

```json
{
  "compilerOptions": {
    "paths": {
      "@nreljed/platform/*": ["vendor/nreljed/platform/resources/js/*"],
      "@nreljed/blog/*": ["vendor/nreljed/blog/src/Resources/js/*"],
      "@nreljed/shop-module/*": ["vendor/nreljed/shop-module/src/Resources/js/*"]
    }
  },
  "include": [
    "resources/js/**/*.ts",
    "resources/js/**/*.tsx",
    "resources/js/**/*.d.ts",
    "vendor/nreljed/platform/resources/js/**/*.d.ts",
    "vendor/nreljed/blog/src/Resources/js/**/*.d.ts",
    "vendor/nreljed/shop-module/src/Resources/js/**/*.d.ts"
  ]
}
```

## Step 8 — npm peer dependencies

Each package declares its runtime npm deps as `peerDependencies` in its
`package.json`. The app must install every dependency the packages it imports
need. The safest approach for a new app: copy the union of `peerDependencies`
from every used package into `devDependencies`/`dependencies`, then:

```bash
npm install
```

(Mismatches surface as duplicate-instance or missing-package errors at build
time. When unsure, mirror the `package.json` of an existing app that uses the
same modules, e.g. `apps/laravel.shop`.)

## Step 9 — Migrate + seed

```bash
php artisan migrate
php artisan db:seed --class=RolesAndPermissionsSeeder   # idempotent RBAC
php artisan app:create-admin                             # bootstrap your admin
npm run build
```

Then log in at `/login` with the created admin.

> **Alternative:** run the platform bootstrapper in one step — it verifies the
> wiring and, with `--migrate` / `--seed`, does the data work:
>
> ```bash
> php artisan platform:install --migrate --seed
> ```
>
> Without flags it is purely a read-only wiring report (package installed,
> providers registered, `config/features.php` published, tables migrated,
> roles seeded) and fails loudly if a `features.providers` entry is missing
> from `bootstrap/providers.php`. It never truncates or drops data.

---

## Module Activation Checklist (per module added later)

- [ ] `composer.json`: add `"nreljed/{module}": "^0.1"` to `require`
- [ ] `composer.json`: add VCS repo (standalone) or path repo (workspace)
- [ ] `bootstrap/providers.php`: add `{Module}ServiceProvider::class` (both
      providers for booking-module)
- [ ] `composer update nreljed/{module}`
- [ ] `config/features.php`: add the provider class to `providers[]`
- [ ] `config/features.php`: add `'{slug}' => true` to `enabled[]`
- [ ] `resources/js/page-config.ts`: import + spread `register{Name}Pages()`
- [ ] `vite.config.js`: add `@nreljed/{module}` aliases + refresh globs
- [ ] `tsconfig.json`: add `paths` + `include` globs for the module's `.d.ts`
- [ ] `package.json`: add the module's `peerDependencies` to your deps
- [ ] `npm install`
- [ ] `php artisan migrate`
- [ ] `php artisan db:seed --class=RolesAndPermissionsSeeder`
- [ ] `npm run build` and smoke-test the module's routes + pages

## Troubleshooting

| Symptom                                                | Cause                                                                 | Fix                                                                          |
| ------------------------------------------------------ | --------------------------------------------------------------------- | ---------------------------------------------------------------------------- |
| `Provider [...] not registered` at boot                | Feature provider enabled in `features.providers` but not in `bootstrap/providers.php` | Add the provider class to `bootstrap/providers.php`                           |
| Feature routes return 404                               | Feature disabled, or provider missing from `config/features.php`      | Enable the slug in `features.enabled` + confirm `permissions:view`           |
| `Unable to locate file in Vite manifest`                | `npm run build` not run, or `public/hot` present                       | Run `npm run build`; delete `public/hot`                                     |
| Pages render but styles/components missing             | Wrong alias target (vendor vs package path)                           | Fix the `@nreljed/*` alias in `vite.config.js`                               |
| Duplicate-React / hook errors at runtime               | Peer deps not installed as direct deps                                | Copy `peerDependencies` from each package into your `package.json`            |
| Composer: "No matching package found" for a module     | Module's `platform` dependency repo not reachable                     | Make sure the app's `repositories` include `nreljed/platform` and auth works  |