---
description: Scaffold a new self-contained feature module (FeatureProvider + migrations + frontend pages + permissions) into this workspace
agent: build
---

# Add a feature module

A feature is self-contained: its own service provider, routes, models, migrations,
permissions, nav items and frontend pages. `FeatureRegistry` discovers providers
listed in `config/features.php`.

**First decide where it belongs.** If the feature is specific to one consumer (shop,
booking), it belongs in that consumer's package repo, not here. Only build it here
if it is genuinely shared engine functionality.

## 1. Read the contract first

```bash
cat vendor/nreljed/platform/src/Contracts/FeatureProvider.php
cat vendor/nreljed/platform/src/Services/FeatureRegistry.php
cat config/features.php
```

`FeatureRegistry` delegates to each provider and aggregates nav items and shared
data. `HandleInertiaRequests` calls `getAllNavItems()` / `getAllSharedData()`, so
nav and data reach the frontend without touching the platform.

## 2. Create the module

For a new module package (the usual case):

```text
packages/<module>/
  <Module>ServiceProvider.php     # or src/<Module>ServiceProvider.php
  src/
    Models/  Http/Controllers/  Http/Requests/  Routes/
    Resources/js/Pages/
  database/migrations/
  package.json                     # peerDependencies for every runtime npm import
  tests/
```

Copy the shape of `packages/blog` or `packages/shop-module` rather than inventing
one - they are the working references.

## 3. Register it

`config/features.php` - add to the `providers` array. Registration is the only
wiring step required; there is no central route file to edit.

## 4. Declare permissions

In the provider's `permissions()` method, return feature-scoped slugs using **dot**
notation (`posts.view`, `posts.create`, `posts.edit`, `posts.delete`). Kebab-case is
for global permissions (`manage-users`); snake_case is never correct.

Grant them to roles in `vendor/nreljed/platform/config/roles.php` (the catalog is
platform-owned; an app may publish its own override). Then seed:

```bash
php artisan db:seed --class="Nreljed\\Platform\\Database\\Seeders\\RolesAndPermissionsSeeder"
```

The seeder is idempotent and config-driven.

Gates auto-register via
`vendor/nreljed/platform/src/Providers/PermissionGateServiceProvider.php` from the
`FeatureRegistry::permissions()` output plus the `permissions` table.

## 5. Frontend pages

Register explicitly - CORE never auto-registers. In `resources/js/page-config.ts`:

- `basePages: registerCoreTemplates()` for shared platform pages,
- `projectPages` for namespaced project pages,
- `pageOverrides` to swap any CORE-owned page (applied last, wins).

Controllers render **lowercased** names (`Inertia::render('blog/index')`). Cross-package
imports use `@nreljed/<module>/*` and need a `tsconfig.json` `paths` entry - not a
`vite.config.js` alias. Runtime npm dependencies go in the package's
`peerDependencies` **and** as direct dependencies in every consuming app, then one
root `pnpm install`.

## 6. Verify

```bash
php artisan migrate --pretend
php artisan test --compact --filter=<Module>
npm run typecheck:packages
npm run lint
npm run build
```

Then confirm the boundaries still hold and mirrors are untouched:

```bash
npm run check:foundation
node scripts/check-platform-consistency.js
```

## Reporting rules

State which files were created, how the feature is registered, its permission slugs,
and the actual output of each verification command. If a step was skipped, say which
and why.