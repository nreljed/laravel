# nreljed/marketing-module — Engine Package

The **marketing automation + social publishing** feature of the Nreljed platform,
packaged as a self-contained feature provider. It ships social-account OAuth,
contacts, tags, audience segments, the platform adapters that publish to them,
and the React page kit for the dashboard.

It is a **library**, not an application: no `artisan`, no `.env`, no database of
its own. Consumers install it via Composer and wire the frontend through the
pnpm workspace.

## Identity

| Thing | Value |
|-------|-------|
| Composer package | `nreljed/marketing-module` |
| npm package | `@nreljed/marketing-module` |
| Namespace | `Nreljed\MarketingModule\` (PSR-4 → `src/`) |
| Feature slug | `marketing` |
| Routes | `/dashboard/marketing/*` (named `dashboard.marketing.*`) and public `/m/{code}` (`marketing.links.*`) |

## Ownership boundaries

- `src/` — platform-facing module code: routes, controllers, services, models,
  policies, requests, resources, adapters.
- `src/Resources/js/` — the module's React kit. Consumers import
  `registerMarketingPages()`; they never fork the files.
- `config/marketing.php` — the canonical module config. Apps may override it;
  they do not fork the file.
- `database/migrations/` — the module's own schema. It ships with the package
  and runs through `loadMigrationsFrom()`.
- `src/Routes/public.php` — unauthenticated redirect routes loaded outside the
  dashboard group.
- `scripts/` — the structural guards CI runs (frontend boundary, migrations).

Do not add app-specific routes, migrations, or pages here. Anything an app owns
belongs in the app.

## Consumer wiring

1. `composer config repositories.marketing vcs https://github.com/nreljed/marketing-module.git`
   and `composer require nreljed/marketing-module:^0.5`.
2. Register `Nreljed\MarketingModule\MarketingServiceProvider` in
   `bootstrap/providers.php`.
3. Add the provider class to the `providers` array in `config/features.php` so
   the shared `FeatureRegistry` aggregates its nav, permissions, dashboard data,
   and admin resources.
4. Frontend: import `registerMarketingPages()` from `@nreljed/marketing-module/pages`
   and call `registerMarketingFeatureIcons()` once at app bootstrap.

## Release discipline

- `composer.json` **and** `package.json` each carry a `version`, and CI
  hard-fails if either disagrees with the git tag. Never bump one and forget
  the other.
- Tag `vX.Y.Z` on `main` → CI creates the GitHub Release automatically and
  fires the `marketing-updated` `repository_dispatch` to the consumer.
- A release *notifies* consumers; it never deploys anything.

## Verification

```bash
# PHP
find src tests -name "*.php" -print0 | xargs -0 -n1 php -l > /dev/null
composer validate --no-check-publish
composer install --prefer-dist --no-interaction --no-scripts --no-progress
php vendor/bin/phpunit
vendor/bin/pint --test
php scripts/frontend-boundary-check.php
php scripts/migration-check.php
```

## Project memory

- `docs/` — conventions, gotchas, and decisions as they are written down.
