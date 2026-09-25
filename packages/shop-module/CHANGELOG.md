# Changelog

All notable changes to `nreljed/shop-module` are documented in this file, based on conventional commits.

## [Unreleased]

### Added

- **Scheduled publishing (PR 10A)** - `products` gets `unpublish_at` (nullable timestamp, additive)
  via migration 66. `Product::published()` becomes time-aware (excludes `published` products whose
  `unpublish_at` has passed) and a matching `isVisibleOnStorefront()` instance gate covers the
  storefront show route. New `shop:publish-scheduled` command flips `future` → `published` when
  `publish_date` arrives (clearing `publish_date`, dropping expired `unpublish_at` only when it has
  passed) and auto-hides (`published` → `archived`) products whose `unpublish_at` has passed;
  wired into the consumer scheduler. Storefront listings/brand withCount/attribute filters/
  category withCount/featured/new arrivals/related/sitemaps and the wishlist now use the
  time-aware `published()` scope. Admin Product form gains an "Auto-Unpublish" section
  (Switch + DateTimePicker, default +7 days) and the Products index shows `scheduled {date}` /
  `unpublishes {date}` status cues. `StoreProductRequest` accepts `unpublish_at` (nullable date).
  Contract suite grew to 132 tests / 322 assertions (new `ScheduledPublishingContractTest`).

## [0.1.46] - 2026-09-23

### Added

- **Discount scope on coupons** - `coupons` gets `customer_group_id` (nullable FK to `customer_groups`,
  additive) and `exclude_sale_items` (boolean) via migration 63, backed by fillable/casts on `Coupon`
  and `registerOrUpdateCoupons` passed through `CouponService::validateAndApply`/`getAutoDiscounts`.
- **DiscountManager + type drivers** - `DiscountManager` (singleton via `ShopServiceProvider`,
  `DiscountManager::defaults()`) resolves driver pipelines from `config/shop.php` `discounts` with
  driver filter/calc gates. Drivers under `src/Discounts/Types` implement `DiscountType` and share
  line-item scoping in `LineItemType`: `PercentageDiscount` (inherent/contextual), `FixedDiscount`,
  `FreeShippingDiscount`, `BuyXGetYDiscount` (incl. stacked `max_discount`), `FixedProductDiscount`,
  `PercentageProductDiscount`, `TieredDiscount` (bracket stepping), `ProductScopedDiscount`
  (price-scoped product coupons).
- **Coupon validation contracts** - generated validation
  messages via `registerValidatorFacade()` in the golden test (DB-free, container-agnostic), customer
  group + exclude-sale matching, sale-item exclusion in line-item drivers, currency-aware config
  defaults (hero subtitle + `config/shop.php`) and Form labels.
- **PR 6 scope + coverage** - `DiscountManager` singleton wiring, contract tests for 8 drivers,
  pipeline order continuity (`cart`/`cart_lines` untouched), migration count 63 in docs, 27-test
  discount/contract suite green (122 total), `no hardcoded EUR` in Form when currency prop present.

## [0.1.46] - 2026-09-23

## [0.1.45] - 2026-09-23

### Added

- **Cart + Cart Lines pipelines** — `config/shop.php` `pipelines.cart` (`ValidateVariant → ResolveUnitPrice → ValidatePurchaseQuantity → PersistLine`) and `pipelines.cart_lines` (`BuildOrderItemAttributes`), with `CartContext` / `CartLineContext` and pipe classes under `src/Pipelines/Cart` and `src/Pipelines/CartLines`. `CartController::addItem` now runs the cart pipeline (validation, unit price, quantity limits, persist) instead of inline logic; exact 422 behaviours and 404s are preserved.
- **Checkout pipeline** — `config/shop.php` `pipelines.checkout` (`ValidateStock → ResolveShippingMethod → BuildQuote → CreateOrder → RecordCouponUsage → CreateOrderItems → RecordInventory → ClearCart`) with `CheckoutContext` and pipe classes under `src/Pipelines/Checkout`. `CheckoutService` is now a stateless orchestrator (no constructor): `processCheckout()` runs the pipeline, then the order transaction (`validateStock`, `findOrFail`) moved inside `DB::transaction`.
- **Pipeline golden/contract tests** — `PipelinesContractTest` (config order, class existence, context shapes) plus `CartPipelineGoldenTest` and `CheckoutPipelineGoldenTest` covering all cart 422 branches, unit-price resolution, persist (create/update), stock-message localization, build-quote arg capture, coupon usage order, and order-item attribute assembly.

## [0.1.44] - 2026-09-22

### Fixed

- **`product_prices` migration (0.1.43 regression)** - the `CREATE TABLE` emitted two identical `product_prices_currency_index` indexes (once from the `currency` column and once from the explicit `index()`), which aborted real `php artisan migrate` runs on SQLite ("index already exists") and MySQL ("Duplicate key name"). The redundant explicit index is removed.
- **`product_prices` backfill (0.1.43 regression)** - the seed `INSERT` wrote into the `price_scope` stored generated column, which SQLite and MySQL both reject. The backfill now omits `price_scope` and lets the generated column normalize it (`COALESCE(product_variation_id, 0)`).

### Added

- **Migration smoke check** - `scripts/migration-check.php` boots the package migrations against a real sqlite connection and enforces clean `up()` execution for all 62 migrations; wired into CI so migration regressions are caught before release.

## [0.1.43] - 2026-09-22

### Added

- **Shipments (0.3)** — `shipments` + `shipment_items` tables, `Shipment`/`ShipmentItem` models with `Order::shipments()` relation, `ShipmentService` (createForOrder / markShipped / markDelivered / updateTracking), `ShipmentsController` + `ShipmentPolicy`, `shop.shipments.manage` permission, `shipment.created` / `shipment.shipped` / `shipment.delivered` webhook events, and an admin Inertia page under `/dashboard/shop/orders/{order}/shipments`.
- **Queued webhooks (0.4)** — deliveries now run through a real `SendWebhook` queue job (`ShouldQueue` + unique per call, per-webhook `retry_attempts` / `retry_delay` backoff); `WebhookService::dispatch()` enqueues, `dispatchAndWait()` stays synchronous for the test button, `processPending()` re-queues lost deliveries. Webhook endpoints auto-deactivate after 10 consecutive failures (`webhooks.consecutive_failures` column).
- **Event catalog (0.4)** — `Events/WebhookEvents.php` centralizes the 16 event names; controller store/update validation whitelists against it; wired `order.placed` (checkout + manual order), `order.cancelled`, `product.created/updated/deleted`, `customer.created` (checkout account), and renamed the test ping to `webhook.test`.

### Changed

- Docs synced with the shipped behaviour (`workflows.md`, `services.md`, `data-model.md`, `architecture.md`, `routes.md`, `controllers.md`, `permissions.md`, `index.md`); counts updated to 32 models, 18 services, 10 policies, 17 permissions, 62 migrations.

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
