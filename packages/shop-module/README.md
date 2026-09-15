# Shop Module (`nreljed/shop-module`)

A self-contained e-commerce feature provider for the Laravel dashboard — products (simple /
variable / grouped / external / digital / service), inventory, orders, customers, coupons,
reviews, shipping, taxes, currencies, wishlist, abandoned-cart recovery, webhooks, and a
public storefront.

**Namespace:** `Nreljed\ShopModule\` (PSR-4 → `src/`)
**Composer package:** `nreljed/shop-module` (private GitHub repo, installed via VCS)
**Feature slug:** `shop`

## Docs

| Doc | Covers |
|-----|--------|
| [index.md](docs/index.md) | Overview, counts, feature checklist |
| [architecture.md](docs/architecture.md) | Package layout, provider, shared props, wiring |
| [data-model.md](docs/data-model.md) | All 29 models + variation system |
| [services.md](docs/services.md) | The 14 domain services |
| [controllers.md](docs/controllers.md) | Controllers keyed by route prefix |
| [routes.md](docs/routes.md) | All 11 self-loaded route files |
| [permissions.md](docs/permissions.md) | Permissions (16) + policies (9) |
| [workflows.md](docs/workflows.md) | Order lifecycle, hold stock, abandoned cart, checkout, coupons, webhooks |
| [settings.md](docs/settings.md) | DB-backed shop settings (`settings` table, group `shop`) |
| [frontend.md](docs/frontend.md) | React/Inertia pages, swatches, cart patterns |

## Requirements

| Requirement | Version |
|-------------|---------|
| PHP | ^8.4 |
| laravel/framework | ^13.0 |
| nreljed/dashboard-core | ^0.1 |
| barryvdh/laravel-dompdf | ^3.1 (order invoices) |
| stripe/stripe-php | ^20.0 (Stripe payments) |

## Installation

```bash
composer config repositories.shop vcs https://github.com/nreljed/shop-module.git
composer require nreljed/shop-module:^0.1
php artisan permissions:sync
php artisan migrate
```

1. **Composer:** declare the package as a VCS repository (private; requires a GitHub token,
   e.g. `COMPOSER_AUTH`).
2. **Provider:** register `Nreljed\ShopModule\ShopServiceProvider` in `bootstrap/providers.php`.
3. **Feature registration:** add the provider class to the `features` array in
   `config/core.php` so the core `FeatureRegistry` aggregates its nav, permissions, email
   templates, notification types, gallery config, and dashboard data.
4. **Migrations / views / config:** auto-loaded —
   `loadMigrationsFrom(__DIR__.'/../database/migrations')`,
   `loadViewsFrom(..., 'shop')` + `View::addLocation(...)`, and `mergeConfigFrom(..., 'shop')`.

### Frontend wiring (host)

- **Vite alias:** `@shop` → `vendor/nreljed/shop-module/src/Resources/js/`.
- **Page registration:** the host glob-imports `@shop/Pages/**/*.tsx` with
  `projectPrefix: "shop"`, so pages render under `shop/*` Inertia component keys.
- **Tailwind:** include the shop JS dir via `@source` — a missing `@source` silently strips
  every shop utility class from the built CSS.

## Development workflow

```bash
# After editing package PHP, re-install into the app:
composer update nreljed/shop-module --no-scripts

# Frontend: rebuild (the app runs the production build — no `npm run dev`)
npm run build

# Quality gate
vendor/bin/pint
php artisan permissions:sync
php artisan test
npm run typecheck
```
