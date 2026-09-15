# Changelog

All notable changes to `nreljed/shop-module` are documented in this file, based on conventional commits.

## [0.1.8] - 2026-09-07

### Changed

- `ShopServiceProvider::boot()` now gates route loading on the feature `enabled()` state. When the Shop feature is disabled (via the core module-management page), its `/dashboard/shop/*` and storefront routes 404 instead of loading. Migrations, views, policies and commands still load so the feature can be re-enabled non-destructively.
  - Depends on `nreljed/dashboard-core` >= `0.1.23` (adds the `InteractsWithFeatures::enabled()` / `onDisable()` contract).

## [0.1.0] - 2026-08-14

Initial release of the shop module as a standalone Composer package (extracted from the `laravel.shop` host app).

### Added
- Full e-commerce domain: products (simple / variable / grouped / external / digital / service), inventory, orders, customers, coupons, reviews, shipping, taxes, currencies, wishlist, abandoned-cart recovery, webhooks, and a public storefront.
- Product taxonomy: categories, tags, brands, attributes (with text/color/image/button swatches), variations (pivot-backed), downloadable files, grouped products.
- Discount engine: percentage / fixed / free_shipping / BOGOF / product-scoped / tiered coupons, automatic discounts, stacking, per-user + usage limits.
- Order workflow: 10 statuses with validated transitions, partial refunds with line-item breakdown, order notes (public/private), timeline, invoice PDF.
- Checkout: guest + authenticated, zone-based shipping, tax calculation, coupon folding, stock holds with auto-release, consistency-verified totals.
- Storefront: product grid/detail/category pages, cart with quantity controls, checkout, wishlist, back-in-stock notifications.
- Inventory: `InventoryService` (sale/refund/adjust with audit trail), hold stock, incoming stock, low-stock + stock-changed webhooks/notifications.
- Abandoned-cart recovery: 3-step email sequence (1h/24h/72h) with signed recovery URLs.
- Webhooks: HMAC-SHA256 signed outbound dispatch with retry + auto-deactivation, Stripe webhook listener.
- Multi-currency: `CurrencyService` with Accept-Language detection and exchange rates.
- Shop settings: DB-backed settings page (`/admin/shop/settings`) with store profile, selling locations, currency & pricing, tax options, inventory, checkout, units, and feature toggles; typed access via `ShopSettingsService` + ISO 3166 `Countries`.
- 57 migrations, 30 models, 9 policies, 16 permissions, 5 artisan commands, 7 email templates, 11 self-loaded route files, 13 domain services.

### Changed
- Host app updated to consume the package via Composer path repository (`symlink: false`); provider auto-discovered through the Laravel package auto-discovery list and registered as a `FeatureProvider` in `config/core.php`.

### Fixed
- Checkout summary totals now match backend calculations exactly (shipping `free_above` + `free_shipping` folding, tax including shipping when configured, unconditional auto-discount folding, general error banner for top-level errors).

[0.1.0]: https://github.com/nreljed/shop-module/releases/tag/v0.1.0
