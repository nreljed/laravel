# Changelog

All notable changes to `nreljed/shop-module` are documented in this file, based on conventional commits.

## [0.1.72]

### Added

- **URL aliases (PR 11A) — a renamed product or category keeps answering at its old URL.** On the web,
  renaming a slug is either a broken link or a trap for a deprecated bookmark; the storefront now does
  neither. `SyncsUrlAliases` observes `Product` and `ProductCategory` and records the old path in the new
  `url_aliases` table whenever a slug changes, and `RedirectLegacyUrlAliases` runs on the guest storefront
  group, 301-redirecting a legacy `/store/{slug}` or `/store/category/{slug}` request to the target's
  current canonical path. A self-referencing alias (a rename back to the same slug) and any non-GET pass
  through rather than loop, and a deleted target's aliases are dropped with it — a 301 to a 404 is worse
  than a plain 404. Aliases are observer-managed, deliberately not admin-editable: an alias is a record
  of a rename, not a routing rule.
- **Product relations (PR 11B) — merchants can curate cross-sells and up-sells.** New `product_relations`
  pivot (unique per target/related/type, `sort_order`-sorted) with `Product::crossSales()/upSales()/
  curatedRelations()`, and a "Related Products" editor on the product form: search for a product, mark it
  a cross-sell or up-sell, and reorder or remove it. The storefront preference is now properly paired with
  the curation — the exact list the merchant picked comes first in its `sort_order` (still gated on being
  published and browseable, so a withdrawn product never 404s the rail), and the same-category fill only
  tops the rail up when curation leaves it short.
- **Barcodes & identifiers (PR 11C) — a variation can now carry the same identifier set as a product.**
  Products gain an `mpn`, and variations gain `global_unique_id` (GTIN/UPC/EAN/ISBN) and `mpn` — a
  variation previously held a barcode column but no way to carry the identifiers a product had, so the
  admin form, CSV export and public product API all exposed product identifiers a variation could not
  hold. Editor fields, CSV export (columns appended at the end, so older importers keep reading the same
  positions) and the public API resource now expose all three.
- **Hierarchical category navigation (PR 11D) — the storefront taxonomy is a tree.** A category page now
  shows a breadcrumb trail from its root down to itself (inactive ancestors dropped), offers its
  browseable sub-categories as rails beneath its heading (active children whose own grid can list
  something, with counts that match it), and the catalog sidebar renders the full taxonomy nested by
  `parent_id` — a child whose parent lists nothing is still offered, at the top level. The sidebar also
  fixes a highlight that never fired: it keyed the current page off a `?category=` query parameter that a
  category-page URL never carries, so browsing a category showed no active link; it now uses the page's
  `activeCategory` too. A parent page still lists only its own products — descendants are navigated to,
  not silently merged in.

## [0.1.71]

### Fixed

- **The shipping snapshot added in 0.1.70 could not be read back by anyone.** `CreateOrder` records
  `shipping_method_name` so that "which carrier went wrong" stays answerable after a method is deleted
  or renamed, but no read surface exposed it: the admin order resource, the orders CSV export and the
  outbound webhook payload all reported `shipping_total` and none reported the method. The column was
  written, never shown — invisible, because a write-only field breaks nothing. `OrderResource` now
  carries both the snapshot and the method's current name (so a rename is distinguishable from a
  deletion), the export gained a `shipping_method` column paired at matching header and row positions,
  and `WebhookService` sends `shipping_method` so an integration receiving `shipping_total` can tell a
  slow carrier from a cheap one. The admin order page now shows the method and displays the shipping
  row for free-shipping orders too — previously the row was hidden on `shipping_total > 0`, which is
  exactly where an operator most wants to confirm the service that was chosen.

## [0.1.70]

### Fixed

- **The public cart was a dump of the merchant's own figures.** `Product` declares no `$hidden` — the
  admin form has to bind `cost_price` — and the cart serialised `items.product` straight off the
  relation, so one cart line carried `cost_price`, `price_hash` and the rest. `CartItemResource` and
  `CartResource` now allowlist the public shape, and all six cart responses plus the checkout page go
  through them instead of serialising models.
- **The confirmation page published the credential that opens it.** `confirmation_token` is the
  emailed token for that very page, and it was rendered into the page's own payload — as were the
  payment intent, order notes, refund notes and `processed_by`. Replaced with `StorefrontOrderResource`.
  The exclusions are asserted on the token-only guest path too, since a guest order has no account and
  the token is the only thing standing between that shopper and a stranger.
- **`GET /api/orders/{order}` returned any order to any signed-in customer.** It bound the order off
  the URL and returned it, exposing names, email, phone, both addresses and every line. Ownership is
  now enforced in `OrderPolicy` and the controller.
- **The shared `cart` Inertia prop was a second, unversioned copy of the same leak**, hand-built
  inline in the service provider. It is now the same minimal allowlist, and the user-scoped lookup
  behind it is unchanged.
- **A withdrawn product stayed buyable.** `addItem()` took a bare `product_id` and never checked that
  the shop still offered the product, so a draft or hidden product could be added by id, sit in the
  basket and reach an order; `updateItem()` bypassed the pipeline entirely. Refused at add, at update
  and at checkout. A `search`-only product stays buyable — it is reachable, and the shopper is on it.
- **The cart was a product-id enumeration oracle.** A missing product and a withdrawn one behaved
  differently (422 vs 404), which is enough to enumerate the catalog. Both are now the same JSON 404.
  Back-in-stock gained the same treatment for inactive, foreign and unpriced variations.
- **Checkout accepted a shipping method it was never offered.** It did a bare
  `ShippingMethod::findOrFail()` on the posted id, so a disabled or out-of-zone method could be
  submitted and then priced by `QuoteService`. `ResolveShippingMethod` now resolves out of the same
  collection the checkout page rendered, which also preserves the zone pivot's `override_price` that a
  fresh lookup silently dropped.

### Added

- **Orders record which shipping method they were bought with.** `shipping_total` said how much was
  charged but not what was bought, so "which carrier went wrong" was unanswerable once a method was
  deleted or repriced. Adds a nullable `shipping_method_id` foreign key — `nullOnDelete`, because a
  catalogue tidy-up must never take a sale with it — plus a `shipping_method_name` snapshot that
  outlives the row. Both are nullable: orders placed before this have neither, and `nullOnDelete`
  produces the same shape.

### Notes

- These specs are red against every earlier release by design. The package ships first; the
  consumer's test commit follows, or consumer CI is red.

## [0.1.69]

### Fixed

- **`catalog_visibility` now means what its four values say.** `search` was written to the database
  and offered by the admin form, then filtered out of every storefront query by a list that named
  only `visible` and `catalog` - so a product set to it was unlistable *and* unfindable, which is the
  opposite of the value. The storefront now widens to the search set only when a search term is
  actually present, so a search-only product is findable by name and still cannot be browsed by
  category or paged through. A browse with no term keeps the listing set.
- **A `hidden` product is gone, not merely unlisted.** `StorefrontController::show()` gated on
  publication state alone, so the catalog hid the product while its own page still rendered its name,
  price, stock and reviews to anyone holding the link. `isVisibleOnStorefront()` - the predicate the
  show route, the API's show endpoint and the purchasability rule now share - refuses `hidden`, which
  404s the page. A search-only product is still reachable by direct link, because a shopper who
  followed a link has to land somewhere.
- **Related products, the homepage rails and the price bounds all carry a visibility rule.**
  `relatedProducts()` used `Product::published()` with no visibility filter at all, so a hidden
  product could be advertised on another product's page; the two homepage rails and the four price
  -bound subqueries each repeated the literal list. All of them now read the model's `inListing()`.
  The price bounds matter on their own: drawn from a hidden product priced at 5000, the slider
  advertised a range with nothing in it.
- **A withdrawn product can no longer be bought through the public cart or checkout.** `addItem()`
  takes a bare `product_id` from an anonymous caller, and the cart pipeline checked the variant, the
  price and the stock but never whether the shop still offered the product - so a draft or hidden
  product could be added by id, sit in the basket and reach an order. `updateItem()` bypasses the
  pipeline entirely, so the rule is restated there too, outside the removal branch. At checkout a
  new `ValidateAvailability` step re-checks the basket, because a cart outlives the product in it;
  stock was already re-checked there for the same reason.
- **The public API and the storefront no longer disagree about the same product.** The API held its
  own, narrower copy of the list and 404'd a search-only product the shop happily rendered, while the
  storefront served a hidden one the API refused. One predicate now answers both, and the API's
  listing takes the same search rule.
- **`CatalogPriceFilterTest`'s hand-rolled `products` table gained the `catalog_visibility` column.**
  A column missing from a hand-built schema does not fail loudly - the visibility scopes matched
  nothing, the bounds collapsed to zero, and the test guarding them reported a number instead of a
  schema gap.

### Changed

- **The admin product form describes what each value does.** The options were labelled "Visible
  everywhere", "Catalog only", "Search only" and "Hidden", and two of the four were wrong about their
  own product under the rules the storefront applied: `catalog` was in search results, and `search`
  was reachable by direct link. They are now "Shop and search", "Shop, but not in search", "Search
  only, not listed" and "Hidden everywhere", with a line under the select spelling out the
  consequences - including that a hidden product cannot be bought.
- **The staff product pickers (order create, order edit, product search) drop hidden products.**
  `OrdersController`'s write path is deliberately left alone: recording a phone order for a withdrawn
  product is real work, and refusing it needs a policy and an override rather than a query scope.
  `WishlistController` takes the new `reachable()` set for the same reason the pickers do - a saved
  list keeps a search-only product the customer chose, and loses a hidden one that would otherwise
  link to a 404.

### Added

- **`Product` owns the vocabulary:** the four value constants, the `LISTING_VISIBILITIES` and
  `SEARCH_VISIBILITIES` sets, and the `inListing()`, `inSearchResults()` and `reachable()` scopes.
  Every surface that has to answer "may this shopper see this product?" read a private copy of the
  list; a value added to one and not another disappeared from the shop with no error anywhere.
- **`tests/CatalogVisibilityTest.php`** holds the four-value contract on the real schema - the
  listing set, the search set, direct-link reachability, and that visibility never overrides
  publication state - plus the two new pipeline steps, the config order they run in, and source
  assertions that no surface keeps a private copy of the list or adds a `published()` query without
  one. `PublicCatalogApiTest`'s "the API is stricter than the storefront" test is replaced by one
  that pins the two endpoints agreeing, including the search-only and hidden cases that used to
  differ.

## [0.1.68]

### Fixed

- **Product SEO generation passes a product instance to the update gate.** `ProductPolicy::update()`
  takes the model, and the controller was handed the class name, so the gate resolved
  `ProductPolicy::update` as a static call on the policy and the request failed with a 500 on every
  product form.

## [0.1.67]

### Fixed

- **The orders and customers Export buttons work.** Both were registered *below* the
  `/orders/{order}` and `/customers/{user}` wildcards. Laravel matches routes in declaration order, so
  `GET /dashboard/shop/orders/export` resolved as an order lookup for the literal string "export",
  bound nothing and returned 404 - as did the customers export. The products export had been placed
  above `/products/{product}` and worked, which is why the two dead ones read as fine: `route:list`
  reports every registered route with its controller and listed all three. Both literals are now
  declared ahead of their wildcard.
- **Five staff endpoints no longer ride the route group's permission alone.** The category list, the
  attribute list, the raw attribute-term JSON, the product image upload and the SEO generation each
  wrote or exposed something - a Media row, a product's meta, or a call on the caller's own AI key -
  with no check of their own, relying entirely on the `/dashboard/shop` group's `shop.manage` gate.
  Nearly every sibling action re-checks a policy, so a Shop Manager (which holds `shop.manage` and
  `shop.products.edit` but not `shop.products.create`) could reach all five. They now authorise
  individually: the taxonomy reads and the upload take `create` on Product, matching the other three
  category and attribute actions, and the SEO generation takes `update`.

### Changed

- **`refund()` holds a row lock for the re-check it already performs.** The transaction re-read the
  order with `refresh()` and nothing held a write lock, so under read-committed two refunds could read
  the same `total_refunded`, both pass the "does this exceed the remaining total" check and both write
  - leaving the order over-refunded with the status merely flipping to `refunded`. The row is now
  locked, so the loser waits and re-reads the committed total. It is a no-op on SQLite, which
  serialises writers anyway; the guarantee matters on the MySQL production database.
- **The storefront review form shows why a submission was rejected.** The controller rejects with a
  form-level `review` error (already reviewed, or no qualifying order) and the page reported a generic
  "Failed to submit review." The first error message is now surfaced in the form and in the toast,
  matching the pattern the other staff forms already use.

### Added

- **`tests/StaffRouteOrderAndAuthorizationTest.php` guards both faults.** It reads
  `src/Routes/web.php` and asserts that no literal segment is declared after a wildcard that would
  swallow it - comparing verbs as well as paths, because a `PUT /attributes/{attribute}/terms/{term}`
  above a `POST .../terms/reorder` looks like a swallow and is not - and that each of the five staff
  actions above carries its own `authorize()` call. Route-source assertions rather than HTTP requests,
  because the package's own suite runs on a bare kernel where controller bodies never execute; the
  behavioural half lives in the deploy app's `ShopStaffExportAndGateTest`.
- **`RefundIntegrityTest` asserts the lock is taken before the amount is read**, and that the unlocked
  `refresh()` it replaces is gone - keeping both would read as protection that does not exist.

## [0.1.66]

### Added

- **Storefront review submission.** A shopper can now post a review from the product page.

## [0.1.65]

### Fixed

- **The storefront published `cost_price`.** Follow-up to the cart leak: the product-facing resources
  serialised the model rather than an allowlist.
- **The public API served unpublished products** from its own narrower copy of the publication list,
  so the API and the storefront disagreed about the same product.
- **Order confirmation needed proof.** The confirmation page accepted an order on the URL alone.
- **Stripe payments were confirmed without server-side verification.**
- **Cart line lookups were not scoped to the caller's own cart**, so a line could be addressed by id.
- **The shop admin area was gated on the wrong permission** — now a dedicated `shop.manage`.
- **Refunds were uncapped and restocked the wrong rows.** Refunds are now capped against the order and
  restock only what was actually refunded.
- **The orders module reached for a `User` relationship the package does not own.**
- Five previously unexecuted checkout pipes now run against the real schema in tests.

## [0.1.64]

### Fixed

- **A variable product with an unsynced price range is no longer invisible to the price filter.**
  The filter read only the cached `min/max_variation_price` columns, which are resynced by the
  variation model events and the scheduled-sales command. Any bulk price update that bypasses those
  left the cache missing or stale, and a product with no trustworthy cache matched no window at all -
  it silently vanished from every price filter. The filter now falls back to the live active
  variations, so the cached columns are a fast path rather than a single point of failure.
- **The catalog price bounds no longer depend on that same cache.** The slider advertised a range
  derived only from the cached variation columns, so an unsynced product could sit outside the
  advertised bounds and become impossible to reach with the filter. Active variation prices are now
  counted alongside them.
- **The scheduled-sales command no longer discards the range it just computed.** It called
  `syncPriceRange()` and then immediately nulled `price_hash` on the same products, undoing the
  freshness marker it had just set.

### Added

- **`tests/CatalogPriceFilterTest.php` exercises the catalog price SQL against a real database.** The
  price tests so far asserted against source text, which cannot detect a query that is wrong at
  runtime: an earlier revision of `effectivePriceBounds()` type-hinted a closure as the Eloquent
  builder while `DB::table()` returns the query builder, so every catalog request returned a 500 while
  the suite stayed green. These tests build the tables, insert products, variations, and bundles, and
  assert on the ids the filter actually returns - covering overlap, the 0-price sentinel, open-ended
  windows, inactive variations, and unsynced caches.

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
