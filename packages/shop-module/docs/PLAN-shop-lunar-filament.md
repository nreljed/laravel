# PLAN — Shop Module & Platform: reaching & exceeding Filament/LunarPHP

**Status:** Approved in principle · **Repos owned by this doc:** `nreljed/platform` (shared kernel) + `nreljed/shop-module` (primary) · **Date:** 2026-09-22

## 1. Context & decisions

Study of Filament + LunarPHP vs our stack (summary):

| | Us | Filament | Lunar |
|---|---|---|---|
| Storefront | ✓ React 19 | ✗ | ✗ (new starter kit) |
| RBAC | ✓ custom slug catalog + feature-folded | addon (Shield) | thin |
| Domain depth (money/currencies/shipments) | partial | n/a | ✓ leader |
| Extensibility (pipelines/discount types) | monolithic services | plugin contract + schema builders | ✓ pipelines/discount types |
| Admin velocity (schema-driven UI) | hand-rolled pages | ✓ leader | via Filament |

**Confirmed decisions:**
1. **Per-currency prices** — true `product_prices` rows per currency (ledger-grade), not client-side conversion.
2. **Customer/Account entity** — real `customers` + `customer_group` + `customer_addresses`, multi-user per account (B2B).
3. **Single storefront + scheduled publishing** — no multi-channel tables; `publish_at/unpublish_at` only.
4. **Own declarative admin resource layer** — our React/Filament-equivalent, shared in platform.
5. **Fleet stays one-store-per-app** — tenancy work parked (P4.4), not now.

Hard rule throughout: **steal ideas, not dependencies** — no Filament, no Livewire, no Lunar code. Everything implemented on our stack (Laravel + Inertia/React + custom RBAC + FeatureProvider).

## 2. Phase 0 — Correctness: money, payments, webhooks *(critical, first)*
- 0.1 `product_prices` (+ `PriceResolver`, backfill default-currency rows, kill client-side currency math, `carts.currency`, `orders.currency`+`exchange_rate_snapshot`, `order_items.currency/base_unit_price`, fix `'USD'` fallbacks, `syncPriceRange` scoped to default currency).
- 0.2 `PaymentDriver` contract + `PaymentManager` registry (Stripe/Manual); fix two-order Stripe flow → one provisional `draft` order; extract `QuoteService` (single source of truth, removes duplicated stacking math in `CheckoutController::index` vs `processCheckout`).
- 0.3 Shipments: `shipments` + `shipment_items`, `ShipmentService`, admin routes, `shipment.*` webhook events.
- 0.4 Queued webhooks: real `SendWebhook` job (unique, backoff), full event catalog (`order.placed`, product/customer CRUD), auto-deactivate after 10 failures (aligns docs with code).
- 0.5 Docs alignment (`workflows.md`, `architecture.md`, counts).

## 3. Phase 1 — Extensibility core (Lunar pipelines, our implementation)
- 1.1 Pipelines `shop.pipelines.cart|cart_lines|checkout` (plain Laravel Pipeline; host-overridable via config). `CheckoutService` becomes orchestrator.
- 1.2 Discount engine: `DiscountType` contract + `DiscountManager` registry; extract 7 types from `Coupon::calculateDiscount`; stacking uses `reducesSubtotal()` not hardcoded list; add `customer_group_id`, `exclude_sale_items`; fix hardcoded `' EUR'`.
- 1.3 DB statuses: `order_statuses` + `order_transitions` tables (seeded from map), cached read in `OrderStatusService`; configurable order-number generator replacing random `ORD-`+8.
- 1.4 Tax v2: `tax_classes` (+ `products.tax_class_id` — field already dormant), `tax_zones` (country/state/postcode), per-line tax (`order_items.tax_amount/tax_rate_snapshot`), and the **tax-base bug fix** (post-discount) — shipped through `QuoteService` so display & persist stay in lockstep.

## 4. Phase 2 — Domain depth
- 2.1 **Customer entity** (largest): `customers`, `customer_user`, `customer_addresses`, `customer_groups`; backfill from `orders.user_id/customer_email`; convert orders/carts/wishlists/reviews/subscriptions; portal `/dashboard/my/addresses`; address picker at checkout; `onUserDelete` extended; B2B multi-user accounts.
- 2.2 Scheduled publishing: `publish_at/unpublish_at`, `Product::published()` scope, `shop:publish-scheduled`, admin status column.
- 2.3 Custom product fields (no-migration): `product_fields` + `product_field_values`, admin manager + storefront Specs section.
- 2.4 Customer-group pricing: `product_prices.customer_group_id` with precedence resolution in `PriceResolver`. (Product-level group gating deferred.)
- 2.5 Catalog extras: hierarchical categories (`parent_id`), `url_aliases` (+301 middleware), `product_relations` (cross/up-sell), barcodes (GTIN/MPN/UPC) exposed in admin/API.
- 2.6 Search: driver contract (`database` now, `scout` later) via extracted `ProductSearch` service.

## 5. Phase 3 — Admin: our declarative resource layer *(the Filament-killer)*
- 3.1 `resources/js/lib/resource-schema.ts` (`defineResource({table, form, relations})`) + `ResourceIndex`/`ResourceForm` runtime + field-type registry (`text, number, money, select, boolean, date, media, slug, json, dynamic, …`) + declared bulk/row actions wired to platform `DataTable` + modal actions + `relation()` tables (generalizes `VariationsManager`). Lives in **platform** so blog/booking reuse it.
- 3.2 Convert shop resources lowest-risk first: TaxRates → Currencies → ShippingMethods/Zones → Categories/Tags/Brands → Webhooks → Reviews → Coupons → OrderStatuses → Customers → Orders → Products.
- 3.3 `make:resource {Module} {Model}` generator (platform) + `make:shop-resource` alias.
- 3.4 FeatureProvider gains optional `adminResources(): array` (+ trait default, memoized registry getter) — **non-breaking**; verify against contract-frozen CI gate.

## 6. Phase 4 — Hardening (tenancy parked — decision 5)
- 4.1 PHPStan (level 5, after Phase 0) both repos; keep Pint.
- 4.2 Indexes `(status, created_at)`, `(currency_id, min_quantity)`; dashboard KPIs to SQL aggregates (Customer count replaces `Order::distinct('user_id')`).
- 4.3 `UPGRADING.md` per shop-module release (backfill migrations flagged run-on-deploy).
- 4.4 Tenancy satchel (Filament's scoping lessons) — **no work now**.

## 7. PR slicing (execution order)
Track A (shop-module domain): 1 QuoteService+money snapshots → 2 product_prices+PriceResolver+server-side currency → 3 PaymentManager+provisional-order → 4 shipments+queued webhooks+docs → 5 pipelines (golden tests first) → 6 DiscountType extraction → 7 statuses+numbering → 8 tax v2 → 9 Customer entity → 10 publishing+fields+group pricing → 11 catalog extras.
Track B (platform admin): starts after PR 1; 12 schema kit → 13–N resource conversions → last: generator + `adminResources()` + PHPStan + indexes.

Tests: contract-style (existing pattern) + `QuoteService` golden matrix (stacking × tax-post-discount × multi-currency) + vitest for schema kit.

## 8. SaaS architecture impact check (one-store-per-app fleet)

**Verdict: beneficial — with three hard guardrails.** Many independently-deployable stores consuming a shared, versioned `nreljed/platform` + `nreljed/shop-module`.

1. **Contract safety = fleet safety.** Only contract change (3.4 `adminResources()`) is trait-defaulted and optional — survives the contract-frozen CI gate without a MAJOR bump. No consumer app breaks on upgrade.
2. **Additive migrations only.** Every new table/column is additive + versioned backfill (per-currency prices, customers, shipments, statuses). Each store upgrades on its own schedule; `UPGRADING.md` per release makes rollouts deterministic.
3. **Per-store domain model fits the fleet model.** One store per app → one Customer universe per app. No cross-store concern introduced; nothing presupposes a tenant column (tenancy off roadmap, decision 5).
4. **Shared-kernel work pays off fleet-wide.** Schema kit (Phase 3) and `QuoteService`/pipeline core live in shared packages — blog/booking and every store benefit. Build once in the kernel, deploy to N stores for free.
5. **Net new value per store:** money integrity, real payments, shipments, queued webhooks, pipelines, DB statuses, tax zones, customer accounts, admin velocity.

**Residual risks & mitigations:**
- **Refactor regression (1.1/1.2).** Mitigation: golden `QuoteService` tests before pipeline extraction; behavior-preserving PRs.
- **Customer backfill (2.1) is the riskiest migration.** Mitigation: additive columns + backfill, keep `order.user_id` intact, dry-run command.
- **Scope creep.** Group-gated catalog, Scout driver, carrier APIs explicitly deferred/optional.
- **Juggling two repos.** Mitigation: PR 12 scheduled right after PR 1 so Track B unblocks early.

**Net:** significantly strengthens SaaS value — hardens the money/payment core every store depends on, adds domain depth (customers/groups/tax/shipments) that turns a shop-simulator into a shop *platform*, and adds admin-velocity assets that compound across future stores. No needless multi-tenancy entanglement; respects the fleet's load-bearing rules (contract-frozen compatibility, additive-only migrations).

## 9. Execution log
- [x] Plan reviewed & approved (decisions 1–5 confirmed)
- [x] PR 1 — `QuoteService` extraction + order/cart currency snapshots + tax-base correction
- [x] PR 2 — `product_prices` + `PriceResolver` + server-side storefront currency
- [x] PR 3 — `PaymentManager` + single-provisional-order Stripe flow
- [x] PR 4 — Shipments + queued webhooks + event catalog + docs sync
- [x] PR 5 — Cart/checkout pipelines (golden tests first) — `config/shop.php` `pipelines` (`cart`/`cart_lines`/`checkout`), `src/Pipelines/` contexts + 13 pipes, `CheckoutService` → stateless orchestrator, `CartController::addItem` wired through `pipelines.cart`; contract + golden tests (96 green), docs synced.
- [x] PR 6 — DiscountType contract + 7 types extracted
- [x] PR 7 — order_statuses/transitions tables + numbering generator
- [x] PR 8 — Tax zones/classes v2 + per-line snapshots
  - PR 8A (SHIPPED v0.1.48): tax class/zone contract tree — `TaxClassContract`/`TaxClassType` + 3 types (Standard/Reduced/Zero), `TaxZoneContract`/`TaxZoneType`, `TaxManager` registry, composer 0.1.48, tag + GH release + mono mirror (7 tracked, lint-clean) + consumer require 0.1.48. Green.
  - PR 8B (SHIPPED v0.1.49): per-line snapshots — `PerLineTaxSnapshot` VO, `TaxClassManager`/`TaxZoneManager` registries, `Zones/{Eu,Us,Nz}`, `PerLineTaxResolver` (country/state → zone, basis-point rounding, class/zone rate precedence, migration adds `tax_class_code`/`tax_zone_code`/`tax_rate_snapshot`), golden test green (4 tests), full suite 126 green (310 assertions), composer 0.1.49, tag + GH release + mono mirror + consumer require 0.1.49. Green.
- [x] PR 9 — Customer/Group/Address entities + backfill + portal addresses
  - PR 9A (SHIPPED v0.1.50): customer contract subtree seed - `CustomerContract`/`CustomerType` + composer 0.1.50, tag v0.1.50 + GH release + GH release # + mono mirror + consumer require 0.1.50. CONTRACT SEED ONLY; entities/backfill + portal addresses = PR 9B (NOT shipped), PR 9 box stays unticked. Green.
  - PR 9B.1 (local): `CustomerManager` registry + `RegisterCustomer`/`ResolveCustomer` contracts + golden test green (v0.1.52 marker). Green.
  - PR 9B.2 (local): customer entity package — `RegisteredCustomer`/`WholesaleCustomerGroup`/`CustomerAddressEntity` entities + address `code()` contract (+ `label()` type-fix commit) + migration `2026_09_24_000000_create_customer_tables_and_backfill` (`customers`, `customer_user`, `customer_addresses`, `customer_groups` + wholesale seed, backfills 18 orders → 2 customers/2 pivots/36 addresses, additive). migration-check 65/65, suite 126 green (313 assert), browser-verified storefront (0 console errors). v0.1.53 marker.
  - PR 9B.3 (local, browser-verified): portal `/dashboard/my/addresses` — Eloquent `Customer`/`CustomerAddress`/`CustomerGroup`/`CustomerUser` models, `MyAddressController` (index/store/update/destroy/set-primary, inline validation, 403-gated), routes `dashboard.my.addresses.*`, React `Pages/My/Addresses/Index.tsx` (auto-registered `shop/my/addresses/index`, AuthenticatedLayout, Card/Empty/Badge/NativeSelect). Browser: login as Emma → 18 backfilled addresses list, add (Rue de la Liberté), set-primary (clears prior primaries), delete all green; DB restored (18/18/9 primaries); 0 console errors. Also fixed pre-existing consumer `tsc` break: `StoreHeader`/`currency.ts` usePage prop interfaces now `extends PageProps`. PR 9 box ticked; ship step pending (composer bump + tag + GH release + mono mirror + consumer require — confirm with user).
- [ ] PR 10 — Scheduled publishing + product fields + group pricing
  - PR 10A (SHIPPED v0.1.56): scheduled publishing — `products.unpublish_at` (migration 66, additive)
    + time-aware `Product::published()` scope + `isVisibleOnStorefront()` storefront show gate
    + `shop:publish-scheduled` command (future→published on publish_date, published→archived on unpublish_at)
    + scheduler entry + storefront/wishlist/sitemap gates switched to `published()` + Auto-Unpublish
    Section (Switch + DateTimePicker, default +7d) + `scheduled {date}`/`unpublishes {date}` index cues.
    `ScheduledPublishingContractTest` (5 tests), suite 132 green (322 assert), migration-check 66/66,
    boundary check OK, consumer tsc+build green, browser-verified (form section + storefront 404 gate +
    command promotion). Consumer scheduler entry added (`shop:publish-scheduled` -> everyMinute). Shipped
    v0.1.56 (tag + GH release + consumer require 0.1.56 + mono mirror; prod HTTP 200).
  - PR 10B (local): custom product fields — `product_fields` + `product_field_values` (additive migrations 67+68, unique per product/field, cascades)
    + `ProductField`/`ProductFieldValue` models with shared `normalizeValue()`/`valueError()` type contract
    + `ProductFieldsController` + `ProductFieldPolicy` + `StoreProductFieldRequest` (after() hook: select needs options, unique option values, typed defaults)
    + admin manager page `Pages/ProductFields/Index.tsx` (dnd reorder, type-aware options/default editor, active/required/storefront switches)
    + product form Custom Fields section (prefilled from `default_value`, per-field type inputs, required/type errors surfaced)
    + storefront Specifications accordion (`StorefrontController` `specifications` prop, storefront-flagged filled values only).
    `ProductFieldsContractTest` (21 tests, incl. migration up/down + unique index, duplicate-slug rejection,
    remote-image rule), suite 155 green (378 assert), migration-check 68/68, boundary check OK (61 files).
    Browser pass fixed 3 defects: duplicate name 500 (derived-slug collision now a `name` validation error),
    stale manager list (localData re-syncs from the refreshed prop), and a pre-existing
    `images.*.path` `required_with` rule that blocked saving any product with remote/seeded images.
    Ship step pending (composer/package bump + tag + GH release + consumer require + mono mirror).
  - PR 10C (local): customer-group pricing — `product_prices.customer_group_id` (migration 69, table rebuild) + precedence in `PriceResolver`.
    + `PriceLedgerService` is the single writer for public + group ledger rows; `SyncsPriceLedger` observer on Product/ProductVariation
      (`created()` for inserts, `updated()` + `wasChanged(PRICE_ATTRIBUTES)` for updates) so no write path can drift the ledger.
    + Group rows are gated by `CustomerGroup::allows_business_pricing` and reach shoppers through the `customer_user` pivot;
      group rows cascade on group deletion, the public row (`group_scope = 0`) is excluded from that cascade.
    + Precedence is currency-first: group requested -> public requested -> group default -> public default -> legacy columns.
      Sale windows are evaluated at read time from `sale_from`/`sale_to`, so denormalized `active_price` writes stay out of the ledger.
    + Admin: "Customer Group Prices" section in the product form (per-group price/sale/compare/min-qty, group picker, repeat sync,
      duplicates prevented) + `StoreProductRequest` business-pricing validation. Product-level only; variable/external/grouped are gated out.
    + Runtime: `ResolveUnitPrice` routes cart pricing through `activePrice()`/`CustomerPricingContext`, and `PersistLine` refreshes an
      existing line. Storefront + wishlist hydration pass the shopper's group. Cart pages read the persisted `unit_price` (currency-converted only),
      so a price change lands on a cart line at its next re-persist — pre-existing, price-type-agnostic behavior, deliberately unchanged here.
    `CustomerGroupPricingContractTest` (44 tests, 83 assert), suite 199 green (461 assert), migration-check 69/69, boundary check OK (61 files).
    Request-level coverage runs the real `StoreProductRequest` rules and `after()` hooks, so `distinct`, the business-pricing gate and the
    `sale_price` form round-trip are all asserted rather than assumed; each was mutation-checked. Doing that surfaced a latent isolation leak —
    booting `ProductField` leaked its slug-deriving `creating` hook into the next test class — fixed with a booted-model reset, and the `products`
    fixture gained the `min_variation_price`/`max_variation_price`/`price_hash` columns that `ProductVariation`'s `saved` hook really writes.
    Suite verified order-independent over repeated `--order-by=random` runs.
    Browser-verified end to end: authored 700/899 as Wholesale -> storefront + cart resolved 700 -> removed the row -> storefront back to 899
    -> re-added line re-persisted at 899; `shop:verify-price-ledger` reports the ledger in step. Ship step pending (tag + release).
- [ ] PR 11 — Catalog extras (hierarchy, aliases, relations, barcodes)
- [ ] PR 12 — Platform schema kit (ResourceIndex/ResourceForm/field registry)
- [ ] PR 13–N — Resource conversions wave by wave
- [ ] Final — generator + `adminResources()` + PHPStan + indexes

