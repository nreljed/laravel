# nreljed/shop-module — Shop Feature Package

The **e-commerce feature** of the nreljed platform: products, orders, cart,
checkout, coupons and discounts, reviews, shipping, taxes, customer groups,
payments, webhooks and the storefront.

It is a **library**, not an application: there is no `artisan`, no `.env`, no
database. Consumers install it via Composer and wire the frontend through the
pnpm workspace.

## Consumers

| Repo                    | Role                                                       |
| ----------------------- | ---------------------------------------------------------- |
| `nreljed/laravel`       | pnpm workspace mono (`packages/shop-module` is a byte-identical mirror) |
| `nreljed/laravel.shop`  | Shop deploy target                                         |

Apps opt in with `registerShopPages()` from `@nreljed/shop-module/pages`
(rendered under `shop/*`). Never hand-edit a consumer's `vendor/` or
`packages/` mirror.

## Package layout

- `src/` — `Nreljed\ShopModule` (Commands, Contracts, Customers, Discounts,
  Payments, Pipelines, Search, Services, Tax, Statuses, Support, Http, Models,
  Observers, Policies, Requests, Routes, Resources). Provider:
  `ShopServiceProvider`; feature slug `shop`.
- `config/` — package config catalogs.
- `database/` — migrations and seeders.
- `resources/js/` — frontend kit, exported via `./shop-aliases`.
- `shop.aliases.js` — vite/tsconfig alias helper, alias `@shop`.
- `scripts/frontend-boundary-check.php` — boundary fence (CI-gated).
- `scripts/migration-check.php` — migration ordering/ownership check (CI-gated).
- `docs/` — shop notes.

## Release discipline

- `composer.json` **and** `package.json` each carry a `version`; the `release`
  CI job hard-fails if either disagrees with the git tag. Never bump one only.
- Tag `vX.Y.Z` on `main` → CI creates the GitHub Release automatically and
  dispatches `shop-updated` to `laravel.shop`. Do **not** hand-create the
  release.
- A package release *notifies* consumers; it never deploys anything.

## Verification

These are the exact commands CI runs (`ci.yml`). Run them before tagging.

```bash
composer install --prefer-dist --no-interaction --no-scripts --no-progress
find src tests -name "*.php" -print0 | xargs -0 -n1 php -l > /dev/null
composer validate --no-check-publish
composer audit --no-interaction
php scripts/frontend-boundary-check.php
php scripts/migration-check.php
php vendor/bin/phpunit
```

## Ownership boundaries

- Keep app-specific routes, migrations and pages out of this repo — anything an
  app owns belongs in the app.
- Migrations here are canonical; do not duplicate them in a consumer.
- The frontend is a kit, not a page set: consumers opt in to pages explicitly.

## Before you push

- [ ] `composer.json` and `package.json` versions agree
- [ ] `composer validate --no-check-publish` exits `0`
- [ ] `php scripts/frontend-boundary-check.php` passes
- [ ] `php scripts/migration-check.php` passes
- [ ] PHPUnit passes
- [ ] `composer.lock` is committed — it is the pin of record
