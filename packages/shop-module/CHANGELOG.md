# Changelog

All notable changes to `nreljed/shop-module` are documented in this file, based on conventional commits.

## [0.1.63]

### Fixed

- **The catalog price filter no longer drops variable and grouped products.** It compared the raw
  `price` column, which is 0 on both types, so they could never satisfy a price window: filtering
  `300-500` returned an empty catalog even for a product whose variations run 349-409. A product now
  matches when any price a shopper can pay for it falls inside the window — its own price, any of its
  variations, or any bundled item — so a range matches by *overlap* rather than containment. A parent
  price of 0 is treated as "no price" and never matches a window that starts at 0.

## [0.1.62]

### Fixed

- **A grouped product no longer opens at 0.00.** `ProductDetail` special-cased bundles onto the
  external-product branch and read `product.price`, which is 0, so the headline advertised the bundle
  as free while the items listed directly beneath it showed their real prices. Bundles now headline
  the range of their items (`29.00 - 39.00`) rather than a fake strike-through discount.
- **The product page's `priceRange` prop resolves for grouped products.** The non-variable fallback
  seeded it from `product.price`, so a bundle arrived with a `{0, 0}` range. It now reads the model's
  derived `price_range`.
- **The catalog price filter no longer starts at 0.00.** The slider bounds were aggregated from the
  raw `price` column, which is 0 on every variable and grouped parent, so the panel advertised a
  catalog running from 0. They now span every price a shopper can actually pay: simple product
  prices, cached variation ranges, and bundled item prices.

## [0.1.61]

### Fixed

- **The related-products rail and the wishlist grid no longer show 0.00.** Both render their own price
  markup instead of reusing `ProductCard`, so 0.1.60 fixed the homepage, featured and catalog grids
  but left the same raw `price` render on the product page's "You might also like" section and on
  `/store/wishlist`. All three surfaces now share the `displayPrice()` helper.
- **`relatedProducts()` and the wishlist query eager-load `groupedItems`.** Neither went through
  `baseQuery()`, so a grouped product in those lists resolved its price range with a query per row.

## [0.1.60]

### Fixed

- **Variable and grouped products no longer advertise themselves as 0.00 in storefront listings.**
  `ProductCard` rendered the raw `products.price` column, which is 0 for every variable and grouped
  product - their real money lives on the variations or the bundled items - so the homepage and
  catalog grids showed a free price while the product page displayed the correct amount. The card
  now renders the derived range (`349.00 - 409.00` for the Redmi Note 13 Pro, `29.00 - 39.00` for
  the Xiaomi Smart Life Bundle).
- **`price_range` is now derived for grouped products.** The accessor only ever looked at
  `product_variations`, which grouped products do not use, so it fell through to the parent price of
  0. It now falls back to the bundled items when a grouped product has no variation price.
- **`display_price` no longer returns 0.00 for grouped products** - it used the range for variable
  products only.
- **No N+1 from the appended `price_range`.** `price_range` is appended to the product payload, so
  the accessor now runs on every serialization. It answers simple, digital, service and external
  products straight from the parent price without touching the variations table, and the storefront
  catalog query eager-loads `groupedItems` so bundled prices are fetched in one extra query.

## [0.1.59]

### Fixed

- **`product_prices` migrations now run on MySQL (production outage, hotfix for 0.1.58).** The
  `price_scope` and `group_scope` generated columns were declared `STORED`, and MySQL refuses a
  foreign key on the base column of a `STORED` generated column — error 138, surfaced as `1215
  "Cannot add foreign key constraint"`. Because `product_prices` needs real cascade FKs on
  `product_variation_id` and `customer_group_id`, the migration could not apply on MySQL at all.
  Both columns are now `VIRTUAL`, which MySQL accepts and which behaves identically for the
  lookup unique index: a public row and a group override still coexist, a duplicate group row is
  still rejected, and deleting a group still cascades to its overrides. Verified on MySQL 8.0.35.
- **Migration 10C is now retry-safe.** A host that rejected the generated columns could be left
  with a partially created `product_prices_group_scope` staging table — MySQL commits DDL
  implicitly, so the failed `ALTER` did not roll the `CREATE` back. `up()` and `down()` now
  `dropIfExists` their staging table first, so re-running the migration starts clean.
- The pre-existing `2026_09_22` `create_product_prices_table` migration carried the same
  `STORED` + foreign key combination, so fresh MySQL installs were affected as well. Fixed there
  too.

### Added

- **`scripts/migration-check.php` can target another driver.** Set `MIGRATION_CHECK_DB_HOST` (with
  the optional `MIGRATION_CHECK_DB_PORT` / `_DATABASE` / `_USERNAME` / `_PASSWORD`) to run the
  real migrations against MySQL instead of in-memory sqlite. The sqlite-only check is what let
  0.1.58 ship green across 199 tests and 69 migration checks.
- **`GeneratedColumnForeignKeyTest`** encodes the invariant that a table with a foreign key must
  not use a `STORED` generated column, so the known case is caught even where no MySQL server is
  configured. A CI step runs the MySQL migration check when the `MIGRATION_CHECK_DB_*` repository
  variables are set.

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
