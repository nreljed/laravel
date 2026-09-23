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
- [ ] PR 5 — Cart/checkout pipelines (golden tests first)
- [ ] PR 6 — DiscountType contract + 7 types extracted
- [ ] PR 7 — order_statuses/transitions tables + numbering generator
- [ ] PR 8 — Tax zones/classes v2 + per-line snapshots
- [ ] PR 9 — Customer/Group/Address entities + backfill + portal addresses
- [ ] PR 10 — Scheduled publishing + product fields + group pricing
- [ ] PR 11 — Catalog extras (hierarchy, aliases, relations, barcodes)
- [ ] PR 12 — Platform schema kit (ResourceIndex/ResourceForm/field registry)
- [ ] PR 13–N — Resource conversions wave by wave
- [ ] Final — generator + `adminResources()` + PHPStan + indexes

## 10. Operations log — blog v0.1.3 idempotency fix (2026-09-23)
- **Prod wedge (pre-existing drift):** `migrate --force` aborted on duplicate column `post_tags.order` (`1060`) before shop-module `product_prices` ever applied — blog migrations were kernel-era, so prod's `migrations` table never recorded `2026_07_26_000003_add_order_…` while the column already existed.
- **Fix:** all 11 `nreljed/blog` migrations made idempotent (`hasTable`/`hasColumn`/`hasIndex` guards + guarded `dropColumn`s) in **v0.1.3** (commit `25d440e`). Same-source mirrors synced into this repo.
- **Deploy reality (now documented):** `release-install` flips symlinks **without** running migrations — post-flip the app 500s (missing `product_prices`) and the release-install run's "Wait for app health" step fails by design. Run the `migrate` action right after every release-install that has pending migrations; `/deploy/migrate` runs `Artisan::call('migrate --force')` in-process and returns `{"status":"ok",…}`.
- **Repair already applied on prod:** v0.14.1 active, `migrate` run OK, homepage HTTP 200, runner status OK. Seeded products do not exist on prod (seeders only run on fresh `migrate:fresh`) — storefront product URLs correctly 404 until a future seed.