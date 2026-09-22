# Changelog

All notable changes to `nreljed/shop-module` are documented in this file, based on conventional commits.

## [Unreleased]

### Added

- **Shipments (0.3)** — `shipments` + `shipment_items` tables, `Shipment`/`ShipmentItem` models with `Order::shipments()` relation, `ShipmentService` (createForOrder / markShipped / markDelivered / updateTracking), `ShipmentsController` + `ShipmentPolicy`, `shop.shipments.manage` permission, `shipment.created` / `shipment.shipped` / `shipment.delivered` webhook events, and an admin Inertia page under `/dashboard/shop/orders/{order}/shipments`.
- **Queued webhooks (0.4)** — deliveries now run through a real `SendWebhook` queue job (`ShouldQueue` + unique per call, per-webhook `retry_attempts` / `retry_delay` backoff); `WebhookService::dispatch()` enqueues, `dispatchAndWait()` stays synchronous for the test button, `processPending()` re-queues lost deliveries. Webhook endpoints auto-deactivate after 10 consecutive failures (`webhooks.consecutive_failures` column).
- **Event catalog (0.4)** — `Events/WebhookEvents.php` centralizes the 16 event names; controller store/update validation whitelists against it; wired `order.placed` (checkout + manual order), `order.cancelled`, `product.created/updated/deleted`, `customer.created` (checkout account), and renamed the test ping to `webhook.test`.

### Changed

- Docs synced with the shipped behaviour (`workflows.md`, `services.md`, `data-model.md`, `architecture.md`, `routes.md`, `controllers.md`, `permissions.md`, `index.md`); counts updated to 32 models, 18 services, 10 policies, 17 permissions, 62 migrations.

## [0.1.44] - 2026-09-22

### Fixed

- **`product_prices` migration (0.1.43 regression)** - the `CREATE TABLE` emitted two identical `product_prices_currency_index` indexes (once from the `currency` column and once from the explicit `index()`), which aborted real `php artisan migrate` runs on SQLite ("index already exists") and MySQL ("Duplicate key name"). The redundant explicit index is removed.
- **`product_prices` backfill (0.1.43 regression)** - the seed `INSERT` wrote into the `price_scope` stored generated column, which SQLite and MySQL both reject. The backfill now omits `price_scope` and lets the generated column normalize it (`COALESCE(product_variation_id, 0)`).

### Added

- **Migration smoke check** - `scripts/migration-check.php` boots the package migrations against a real sqlite connection and enforces clean `up()` execution for all 62 migrations; wired into CI so migration regressions are caught before release.

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
