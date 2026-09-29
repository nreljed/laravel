# PLAN — Shop Module & Platform: reaching & exceeding Filament/LunarPHP

**Status:** In execution — Phases 0–1 complete · Phase 2 at 6/6 · Phase 3 3/4 (schema kit + adminResources in platform **v0.1.64**; 3.3 generator in **v0.1.65**; 5.2 wiring in **v0.1.66**; 5.3 first pass — relation field + serialized row actions + descriptor validation — in **v0.1.67**, second pass — searchable `searchKeys` columns, currency-aware `money`, media-picker parity — in **v0.1.68**) · Phase 4 0/3 · Phase 5 3/4 · Phase 6 2/5 with 6.1 waves 1–11 shipped: TaxRates → Currencies → ShippingMethods → Categories/Tags → Brands → Coupons (full, incl. relations) → Webhooks → Reviews → Customers (index) → Orders (index) → ShippingZones (nested method rows, platform **v0.1.70** lazy-options `select`) → Attributes (nested term rows); only Products deferred (largest form) · **20/21 (95%)** · **Repos owned by this doc:** `nreljed/platform` (shared kernel) + `nreljed/shop-module` (primary) · **Plan date:** 2026-09-22 · **Log reconciled:** 2026-09-29

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
- 3.1 **(SHIPPED platform v0.1.64, 2026-09-28)** `resources/js/lib/resource-schema.ts` (`defineResource({table, form, relations})`) + `ResourceIndex`/`ResourceForm` runtime + field-type registry (`text, textarea, number, money, select, boolean, date, datetime, slug, json, media, password, hidden, dynamic, …`) + declared bulk/row actions wired to platform `DataTable` + `relation()` tables (generalizes `VariationsManager`). Lives in **platform** so blog/booking reuse it — shipped in `680c714 feat(admin)` + `259eb3a`, contained in tag v0.1.64, with tests (`resource-schema.test.ts`, `fields.test.tsx`, `ResourceForm.test.tsx`, `ResourceIndex.test.tsx`). Remaining gap: no modal-actions primitive (row/bulk actions are covered — closed by §6b 5.3).
- 3.2 Convert shop resources lowest-risk first: TaxRates → Currencies → ShippingMethods/Zones → Categories/Tags/Brands → Webhooks → Reviews → Coupons → OrderStatuses → Customers → Orders → Products. **(executed as §6c 6.1 — conversions need the shipped kit, so they land in Phase 6)**
- 3.3 `make:resource {Module} {Model}` generator (platform) + `make:shop-resource` alias. **(SHIPPED via §6b 5.1, platform v0.1.65)**
- 3.4 **(SHIPPED platform v0.1.64, 2026-09-28)** FeatureProvider gains optional `getAdminResources(): array` (+ trait default `[]` — non-breaking, passed the contract-frozen CI gate; memoized `FeatureRegistry::getAllAdminResources()` aggregation). Renamed from the plan's `adminResources` to match the interface's accessor style (documented in-code).

## 6. Phase 4 — Hardening (tenancy parked — decision 5)
- 4.1 PHPStan (level 5, after Phase 0) both repos; keep Pint.
- 4.2 Indexes `(status, created_at)`, `(currency, min_quantity)`; dashboard KPIs to SQL aggregates (Customer count replaces `Order::distinct('user_id')`).
  - The price-ledger index was written here as `(currency_id, min_quantity)`, but `product_prices` has no `currency_id` — the column is `currency` (char 3). Corrected 2026-09-27; as originally written the index cannot be created.
- 4.3 `UPGRADING.md` per shop-module release (backfill migrations flagged run-on-deploy).
- 4.4 Tenancy satchel (Filament's scoping lessons) — **no work now**.

## 6b. Phase 5 — Kernel foundation: the platform modules extend *(the "solid foundation" phase, added 2026-09-28)*

The schema kit (3.1) and the `getAdminResources()` contract (3.4) shipped in platform v0.1.64 are the
seed this phase grows. Its job: make the **platform** the single place any module's admin, permissions,
nav and settings are *declared*, and give module authors a generator that turns a model into a resource
in minutes. Vertical (per-domain) growth stays in the module repos; horizontal (shared-admin) growth
lives here and compounds across blog/booking/shop and every future store — §8.4's promise, made a
*mechanism*.

- 5.1 **(SHIPPED platform v0.1.65, PR #6, 2026-09-28)** `make:resource {Module} {Model}` generator + `make:shop-resource` alias — completes 3.3.
  Scaffolds the descriptor's `definition()` (integration-safe: string route names, camelCase `searchKey`),
  the controller, the descriptor's `routes()` keys and a stub test, so a new module's CRUD is generated,
  not copied. Covered by `MakeResourceContractTest`.
- 5.2 **(SHIPPED 2026-09-28, platform v0.1.66)** Wire `getAdminResources()` descriptors to the dashboard: route binding from each descriptor's
  `routes` keys, per-resource permission gating (reuse the RBAC catalog, e.g. `shop.products.view`),
  nav-item emission, and the registry getter consumed by `HandleInertiaRequests` — so a module's admin
  UI is *declared once and rendered everywhere*. Shipped as the kernel's `ResourceRenderer`
  (`renderIndex`/`renderForm` resolve each declared route name in the registry and fail loud on an
  unregistered one; edit/update/destroy bind the literal `record` param the generic pages resolve
  client-side via Ziggy), the `dashboard/admin/resources/{index,form,inventory}` kernel pages
  (`inventory` behind `dashboard.admin.resources.index`, admin + settings), per-descriptor `permissions`
  (view/create/edit/delete → RBAC slugs), and `adminResources` in shared props. Proven green by the
  shop-module's 6.1 conversions (**waves 1–9:** TaxRates → Currencies → ShippingMethods →
  Categories/Tags → Brands → Coupons → Webhooks → Reviews → Customers (index) → Orders (index)) + their contract tests.
- 5.3 Schema-kit completeness: close the escape hatches so the kit covers every shop form without
  hand-rolled pages — modal-action primitive, currency-aware number input, media-picker parity,
  list/relation editing via `relation()` (the `VariationsManager` generalisation), searchable columns.
  Acceptance: every current shop admin page is expressible in the schema (the 3.2 conversion list).
  **(SHIPPED first pass, platform v0.1.67, 2026-09-28: a declarative `relation` field type —
  multi-select chips control, value = array of scalars, static `options` or lazy `optionsUrl`
  (route name → URL in `toResource`) — plus serialized `table.rowActions` (`routeName`/`method`
  get|post|put|delete/`confirm`) translated to record-bound verbs by the bridge, and PHP
  `ResourceDefinition` validation for both (relation requires an options source; row-action shape is
  enforced). Modal-action primitive subsumed by verbs on row actions. Proven by the Webhooks
  conversion (relations + row actions) and Reviews (row-action-only moderation surface). **Second
  pass, platform v0.1.68, 2026-09-29: searchable columns (`table.searchKeys` — ORs across any listed
  accessor, single-column `searchKey` still supported) plus the generic currency `money` field and
  media-picker parity — unblocking the Customers index (`searchKeys` + `record`-bound View row
  action) and Orders index (`searchKeys` + status `filters` + View row action). Nested-shape
  editing remains.)**
- 5.4 **(SHIPPED 2026-09-28)** Module-shipping contract test (kernel guarantee): a structural test asserting every enabled module
  declares `resources + permissions + nav + settings` through FeatureProvider — the enforced, documented
  "no module drifts off the kernel" rule (mirror of `PackageLockParityTest`), so the fleet stays
  upgradeable and no module silently forks the shared admin surface. Shipped as two halves: the kernel
  enforces `ResourceDefinition` descriptors in **platform v0.1.65** (`getAllAdminResources()` fails
  loud with provider-slug attribution + unique resource keys; `validate()` surfaces issues as
  `high`/`critical`), and shop-module's `ModuleResourceContractTest` proves a drifting module
  (raw arrays, schema drift, key collision) is caught — green against v0.1.65 (5 tests, 12 assertions).

## 6c. Phase 6 — Shop coverage: compete on functions *(the "not shy vs the others" phase, added 2026-09-28)*

Shop-specific wave that (a) finishes the original plan (2.6 Search, 3.2 conversions) and (b) closes the
functional gaps buyers benchmark against LunarPHP/Filament/Shopify/WooCommerce/Medusa. Scope discipline
from decision 5 and the "steal ideas, not dependencies" rule still applies; every line gets a
build / build-next / defer decision in the coverage canvas (6.4).

- 6.1 **Finish 3.2 — every shop admin page on the schema kit**, lowest-risk first (TaxRates → Currencies →
  ShippingMethods/Zones → Categories/Tags/Brands → Webhooks → Reviews → Coupons → OrderStatuses →
  Customers → Orders → Products). The largest block, and the payoff of Phase 5: each conversion is a
  `defineResource()` declaration now the kit ships. **(In execution — waves 1–9 shipped 2026-09-29:
  TaxRate, Currency, ShippingMethod, Category, Tag, Brand (waves 1–5, platform v0.1.66); Webhooks
  (first relation field, first serialized row actions) + Reviews (moderation-only descriptor, `store`
  stays the customer submission route) as wave-6 on platform v0.1.67; **wave-7** Coupon relations
  upgrade (product restrictions as the first lazy `optionsUrl` relation → `/products/options`),
  **wave-8** Customers index (searchKeys + `record`-bound View row action into the retained show
  page), **wave-9** Orders index (searchKeys + status filters + View row action; custom create/form
  and show stay hand-rolled) on platform v0.1.68 (searchKeys second pass); **OrderStatuses
  de-scoped** — order statuses are a code catalog (`OrderStatusService::STATUSES` +
  `OrderStatusManager` + per-status transition types, surfaced in the Orders status dropdown), not a
  DB entity, so there is no admin resource to convert. Remaining: Products — deferred, the largest
  form (variations manager, price ledger, custom fields, SEO, media gallery, related products) — and
  the nested-shape surfaces that are still gated on nested editing (ShippingZones `methods` pivot
  with per-method override price, Attributes terms + reorder).)**
- 6.2 **(SHIPPED shop-module, 2026-09-28)** **Finish 2.6 — Search driver**: `ProductSearch` service behind a
  driver contract (`database` now, `scout` later), wired to the storefront search + admin product list;
  typo-tolerant matching. `ProductSearchDriver` contract tree + `Search/` namespace + config-driven
  engine selection, harness-tested LIKE-escape / case / clamp semantics (`ProductSearchContractTest`).
- 6.3 **Customer lifecycle competitors advertise**: abandoned-cart → recovery flow (`AbandonedCartService`
  exists; wire to notification/email + audience stats), review moderation UI + storefront review
  summaries (models exist), wishlist share, product compare, order emails + storefront order-tracking page.
- 6.4 **Competitive coverage canvas** (`docs/competitive-coverage.md`): tracked matrix vs
  LunarPHP/Filament/Shopify/WooCommerce/Medusa — every capability gets `current` / `build-next` / `defer`
  (+ why). Anything a prospect benchmarks must have a row; deferrals carry the decision-5 or
  no-dependency reason, so "not covered" is always a *conscious* answer, never a gap.
- 6.5 **B2B depth (differentiator, not parity)**: quote-to-order (`QuoteService`), payment terms +
  net-day invoice flows, order-approval workflow, self-service groups/addresses (portal exists) — the
  cohort most off-the-shelf shops handle poorly.

**Explicitly deferred (kept from earlier decisions; rows live in 6.4):** tenancy (4.4), group-gated
catalog, carrier APIs, the Scout-vs-database engine choice, POS/marketplaces.

## 7. PR slicing (execution order)
Track A (shop-module domain): 1 QuoteService+money snapshots → 2 product_prices+PriceResolver+server-side currency → 3 PaymentManager+provisional-order → 4 shipments+queued webhooks+docs → 5 pipelines (golden tests first) → 6 DiscountType extraction → 7 statuses+numbering → 8 tax v2 → 9 Customer entity → 10 publishing+fields+group pricing → 11 catalog extras → search driver (2.6 → §6c 6.2).
Track B (platform admin): **12 schema kit → SHIPPED platform v0.1.64** (also carried 3.4 `adminResources()`); **13 generator (5.1) → SHIPPED platform v0.1.65** + **5.4 module contract test** (kernel + consumer halves green); 14–N resource conversions → §6c 6.1 (generator now available); last: PHPStan (4.1) + indexes (4.2) + UPGRADING.md (4.3).

Tests: contract-style (existing pattern) + `QuoteService` golden matrix (stacking × tax-post-discount × multi-currency) + vitest for schema kit.

## 8. SaaS architecture impact check (one-store-per-app fleet)

**Verdict: beneficial — with three hard guardrails.** Many independently-deployable stores consuming a shared, versioned `nreljed/platform` + `nreljed/shop-module`.

1. **Contract safety = fleet safety.** The one contract change (3.4, shipped as `getAdminResources()` in platform v0.1.64) was trait-defaulted and optional — passed the contract-frozen CI gate without a MAJOR bump. No consumer app broke on upgrade; the fleet pattern is proven.
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

### Status snapshot (verified against code + tags, 2026-09-28)

Not taken from the ticks below — from `git tag --contains`, the released `vendor` tree and the
absence of the artefacts. **18 of 21 items (86%)** on the original list; Phases 5–6 (§6b/§6c) are the
post-plan roadmap and are not part of that count.

| Phase | Items | State | Evidence |
|---|---|---|---|
| 0 money/payments/webhooks | 0.1–0.5 | ✅ 5/5 | migrations 1–62, `SendWebhook`, `WebhookEvents` |
| 1 extensibility core | 1.1–1.4 | ✅ 4/4 | `src/Pipelines/` 13 pipes, 7 discount types, statuses tables, tax v2 |
| 2 domain depth | 2.1–2.6 | ✅ 6/6 | + catalog extras (hierarchy/aliases/relations/barcodes, v0.1.72); 2.6 `ProductSearch` search driver (database) |
| 3 admin resource layer | 3.1–3.4 | 🟡 3/4 | 3.1 + 3.4 SHIPPED platform v0.1.64 (`resource-schema.ts`, `ResourceIndex`/`ResourceForm`/`fields.tsx` + tests; `getAdminResources()` trait-defaulted + `getAllAdminResources()`); 3.3 (→5.1) SHIPPED platform v0.1.65; 3.2 (→6.1) absent |
| 4 hardening | 4.1–4.3 | ❌ 0/3 | no `phpstan.neon`, no `UPGRADING.md`, no `(status, created_at)` index, `Order::distinct('user_id')` still live |
| 5 kernel foundation | 5.1–5.4 | 🟡 3/4 | 5.1 generator + 5.4 module contract test SHIPPED (platform v0.1.65 + shop-module `ModuleResourceContractTest` green); 5.2 wiring SHIPPED platform v0.1.66 (`ResourceRenderer` + generic kernel pages + per-resource permissions, proven by 6.1 waves 1–5); 5.3 first pass SHIPPED platform v0.1.67 (relation field + serialized row actions + descriptor validation — unblocked Webhooks/Reviews; currency-aware number input, media-picker parity, searchable columns and nested editing remain) |
| 6 shop coverage | 6.1–6.5 | 🟡 1/5 | 6.2 search driver SHIPPED (database); 6.1 conversions **waves 1–9 SHIPPED in shop-module** — TaxRates, Currencies, ShippingMethods, Categories, Tags, Brands, Coupons, Webhooks, Reviews, Customers (index), Orders (index) on the kernel path (`{Resource}Resource` descriptor + `{record}` routes + `ResourceRenderer` controller + per-wave contract test green vs kernel 0.1.66→0.1.68; app-level render smoke pending a shop-module release); remaining conversions (Products form; ShippingZones/Attributes nested shapes) block on nested-shape kit work; 6.3 lifecycle + 6.4 canvas + 6.5 B2B pending (see §6c) |

**Verified absent (not merely unticked), original 21:** 3.2 conversions (→6.1) — waves 1–9 shipped (TaxRates, Currencies, ShippingMethods, Categories, Tags, Brands, Coupons incl. relations, Webhooks, Reviews, Customers index, Orders index); Products (variations/price-ledger/custom-fields/SEO/media form — deferred, own focused wave) + ShippingZones/Zones `tiers` (methods pivot with per-method override price) + Attributes nested terms + reorder — the nested-shape surfaces, still gated on nested editing; 4.1 `phpstan.neon` (both
repos), 4.2 indexes `(status, created_at)` + `(currency, min_quantity)` + KPI SQL
(`Order::distinct('user_id')` still live at `ShopService.php:22`), 4.3 `UPGRADING.md`. (Both 2.6 search
and 3.3 generator shipped this reconciliation — dropped from the absent list.)

**Shipped since the last snapshot:** the 2.5 artefacts (`url_aliases` + 301 middleware,
`product_relations`, hierarchical storefront category navigation, `mpn`/`global_unique_id`) with v0.1.72
(PR 11); PR 10D exposure hardening with v0.1.70; in **platform v0.1.64** the schema kit (3.1) + the
adminResources contract (3.4) — so the previous "not in **either** repo" Phase-3 claim is stale; and in
**platform v0.1.65** (`2a8a227`, PR #6) the `make:resource` generator (5.1) + `ResourceDefinition`
descriptor enforcement (5.4 kernel half: fail-loud `getAllAdminResources()`, schema-mirror validator,
`validate()` health issues), with shop-module's `ModuleResourceContractTest` (5.4 consumer half) green
against it. **platform v0.1.66** (`1809d31`, PR #7) shipped 5.2: `ResourceRenderer` (route-binding
fails loud), generic kernel pages `dashboard/admin/resources/{index,form,inventory}`, per-resource
`permissions` slots, and `adminResources` shared props — production already runs platform 0.1.64
(sha `6b3ef13`); 0.1.65/0.1.66 reach consumers via `composer update nreljed/platform`.

**Local, unreleased (platform clone, 2026-09-28):** §5.3 first pass — a declarative `relation` field
(multi-select chips control; static `options` or lazy `optionsUrl` resolved from a route name by
`toResource`), serialized `table.rowActions` (`routeName` + `method` get|post|put|delete +
`confirm` → record-bound verbs in the bridge; `ResourceRenderer`-side resources translate the same
way), and PHP `ResourceDefinition` validation for both (relation requires an options source,
row-action shape enforced). TS strict-gate + kit vitest + PHPUnit all green; unblocks the Webhooks
(multi-select `events`), Reviews (per-row actions) and Coupon-relations conversions.

**Corrections this reconciliation (2026-09-28) made:** 9B.3/10B/10C said "ship step pending" but had shipped
(v0.1.53 / v0.1.57 / v0.1.58); the 4.2 index spec named a `currency_id` column that does not exist; the
2026-09-27 snapshot listed all of 2.5 as absent, which PR 11 (v0.1.72) then shipped; **Phase 3 was not
0/4** — the schema kit + adminResources landed in **platform v0.1.64** on 2026-09-28 (`680c714` +
`259eb3a`, tag at `6b3ef13`, production already running it); PR 10D shipped as **v0.1.70**; PR 12 shipped
as platform v0.1.64. Net: Phase 2 → 5/6, Phase 3 → 2/4, total 16/21 (76%). **Second pass same day:**
2.6 `ProductSearch` shipped (driver + harness tests), 3.3/5.1 generator + 5.4 kernel/consumer contract
shipped as platform v0.1.65 (PR #6, tag `2a8a227`) with `ModuleResourceContractTest` green. Net now:
Phase 2 → 6/6, Phase 3 → 3/4, Phase 5 → 2/4, Phase 6 → 1/5, total 18/21 (86%).

### Next up

The earlier argument for PR 12 is moot — **12 shipped in platform v0.1.64, and with it 3.4**; the
**search driver (2.6 → §6c 6.2)**, the **generator (5.1)** and the **module contract test (5.4)** landed
2026-09-28 (shop-module local + platform v0.1.65). Remaining execution order, each step landing on the
now-shipped foundation:

1. **§6b 5.2 — shipped (platform v0.1.66)**, **5.3 first pass — shipped (platform v0.1.67)**: relation field + serialized row actions (Webhooks/Reviews proven). Close the remaining kit gaps (currency-aware number input, media-picker parity, searchable columns, nested terms), then continue 6.1 waves.
2. **§6c 6.1 — the 3.2 conversions**, wave by wave, on the shipped kit + generator.
3. **§6c 6.3 / 6.4 / 6.5 — coverage**: lifecycle flows, competitive canvas, B2B depth.
4. **Phase 4 — hardening** (4.1 PHPStan, 4.2 indexes + KPI SQL, 4.3 UPGRADING.md) — small, independent,
   high-confidence; can run in parallel with 6.3+.

**Rule of the road:** foundation before coverage (§6b before §6c) — the kit and generator are the
"solid platform" ask, 6.x is the "not shy vs the others" ask. Both are cheap now that the Phase 0–2
money/pipeline/status/tax/catalog cores exist.

**PR 10D** (exposure hardening) shipped as v0.1.70 before any new feature work — box ticked above.

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
  - PR 9B.3 (local, browser-verified): portal `/dashboard/my/addresses` — Eloquent `Customer`/`CustomerAddress`/`CustomerGroup`/`CustomerUser` models, `MyAddressController` (index/store/update/destroy/set-primary, inline validation, 403-gated), routes `dashboard.my.addresses.*`, React `Pages/My/Addresses/Index.tsx` (auto-registered `shop/my/addresses/index`, AuthenticatedLayout, Card/Empty/Badge/NativeSelect).      Browser: login as Emma → 18 backfilled addresses list, add (Rue de la Liberté), set-primary (clears prior primaries), delete all green; DB restored (18/18/9 primaries); 0 console errors. Also fixed pre-existing consumer `tsc` break: `StoreHeader`/`currency.ts` usePage prop interfaces now `extends PageProps`. PR 9 box ticked. **SHIPPED v0.1.53** (log corrected 2026-09-27: the tail previously still read "ship step pending"; `git tag --contains` puts 9B.2/9B.3 in v0.1.53).
- [x] PR 10 — Scheduled publishing + product fields + group pricing
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
     **SHIPPED v0.1.57** (log corrected 2026-09-27: the tail previously still read "ship step pending").
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
     -> re-added line re-persisted at 899; `shop:verify-price-ledger` reports the ledger in step. **SHIPPED v0.1.58**
     (log corrected 2026-09-27: the tail previously still read "ship step pending"). PR 10 box ticked.
- [x] **PR 10D — Storefront & order-surface exposure hardening (unplanned; required, not optional)**
  Not in the original plan. Found by a full sweep of every public surface, and it is a correctness
  fix in the same class as Phase 0 rather than a nice-to-have: the shop published merchant data and
  accepted ids it should have refused. Kept here so the plan reflects the work actually done.
  - **Leaked cost data.** `Product` declares no `$hidden` (the admin form has to bind `cost_price`),
    and the public cart serialised `items.product` straight off the relation — so one cart line was a
    complete dump of the merchant's own figures. `CartItemResource`/`CartResource` now allowlist the
    public shape; all six cart responses plus checkout go through them.
  - **Confirmation page published its own credential.** `confirmation_token` is the emailed credential
    for opening the page, and it was rendered into that page's payload. Replaced with
    `StorefrontOrderResource` (also drops payment intent, notes, refund notes, processor fields).
  - **Order ownership.** `GET /api/orders/{order}` bound any order off the URL and returned it, so any
    signed-in customer could read another customer's order. `OrderPolicy` + controller now scope it.
  - **Withdrawn products stayed buyable.** `addItem()` took a bare `product_id` and never checked the
    shop still offered the product, so a draft/hidden product could be added by id and reach an order;
    `updateItem()` bypassed the pipeline entirely. Fixed at add, update and checkout.
  - **Id oracle.** Missing and withdrawn products behaved differently (404 vs 422), so the cart was a
    product-id enumeration oracle. Both are now the same JSON 404. Back-in-stock gained the same
    treatment for inactive/foreign variations.
  - **Shipping tampering.** Checkout did a bare `ShippingMethod::findOrFail()` on the posted id, so a
    disabled or out-of-zone method could be submitted and priced. `ResolveShippingMethod` now resolves
    out of the set the address was *offered*, which also preserves the zone `override_price` a fresh
    lookup silently dropped.
  - **Orders could not say which carrier.** `shipping_total` recorded the amount but not the method, so
    "which carrier went wrong" was unanswerable once a method was deleted or repriced. Adds a nullable
    `shipping_method_id` FK (`nullOnDelete` — a catalogue tidy-up must never delete a sale) plus a
    `shipping_method_name` snapshot that outlives the row.
  - Tests: 3 new resources, 2 new package suites (`CustomerOrderAndCartExposureTest`,
    `StorefrontSurfaceVisibilityTest`), 6 new pipeline tests, and route-level coverage in the app
    (`StorefrontSurfaceExposureTest`, `OrderShippingMethodRecordingTest`) because a route is the only
    thing that can say whether a request is a 403, a 404 or a 200 carrying someone else's address.
    Suite 444 green (1046 assertions); migration-check 72/72.
  - **Release ordering matters.** These specs are red against any release that predates them. The
    package must ship first; the consumer's test commit follows, or CI is red on the consumer.
  - **SHIPPED v0.1.70** (tag `v0.1.70` verified 2026-09-28; box ticked).
- [x] PR 11 — Catalog extras (hierarchy, aliases, relations, barcodes)
  - PR 11A (SHIPPED v0.1.72): URL aliases — `url_aliases` morph table (migration 73), `UrlAlias` model +
    `UrlAliasService` (`remember`/`resolve`/`forgetTarget`/`basePath`), `SyncsUrlAliases` observer on
    Product + ProductCategory (a slug rename records the old path; a delete drops the target's aliases),
    `RedirectLegacyUrlAliases` on the guest storefront group (301 a legacy `/store/{slug}` /
    `/store/category/{slug}` to the target's current path; self-referencing aliases and non-GETs pass
    through). `UrlAliasesTest` (12 tests, 20 assert). Suite 470 green. migration-check 73/73. Consumer
    tsc+build green. **SHIPPED v0.1.72.**
  - PR 11B: product relations — `product_relations` pivot (migration 74, unique target/related/type,
    `sort_order`), `ProductRelation` model, `Product::crossSales()/upSales()/curatedRelations()`,
    `ProductsController::syncRelations()` (store/update, wholesale rebuild, self-reference + invalid
    type rejected), `StoreProductRequest` rules, storefront `relatedProducts()` prefers the curated set
    (published + browseable, deduped by id) before same-category fill, and a "Related Products" editor
    (search picker, cross-sell/up-sell selector, reorder, remove) in the product form.
    `ProductRelationsTest` (8 tests). **SHIPPED v0.1.72.**
  - PR 11C: barcodes/identifiers — `products.mpn` + `product_variations.global_unique_id`/`mpn` (migration
    75; the variation's `barcode` column had existed since 2026-07-03), admin fields (product MPN,
    variation GTIN/MPN), CSV export columns appended (positional, backward compatible), `ProductResource`
    exposes `barcode`/`global_unique_id`/`mpn` on the public API (storefront allowlists unchanged).
    `BarcodeAndIdentifierContractTest` (3 tests) + extended public-API contract. **SHIPPED v0.1.72.**
  - PR 11D: hierarchical category nav — storefront `category()` now ships `breadcrumbs` (root → itself,
    inactive ancestors skipped) and browseable `children` rails (active + listable-product counts), and
    `renderCatalog`'s `categories` is a `parent_id`-nested tree (a child whose parent lists nothing is a
    top-level root). `ProductGrid.tsx` renders the recursive sidebar (active page highlighted via
    `activeCategory`, fixing the `?category=`-only highlight that never fired on category pages),
    breadcrumb nav and children chips. `CategoryHierarchyNavTest` (6 tests). **SHIPPED v0.1.72.** Full
    suite 481 green (1119 assert); migration-check 75/75; boundary check OK; consumer tsc clean. PR 11
    box ticked. 2.6 Search shipped with the search-driver commit — Search box below.
- [x] PR 12 — Platform schema kit (ResourceIndex/ResourceForm/field registry) — **SHIPPED platform v0.1.64** (also shipped 3.4 adminResources)
- [ ] PR 13–N — Resource conversions wave by wave (§6c 6.1; generator 5.1 now shipped). **Waves 1–9 converted in shop-module** (TaxRates, Currencies, ShippingMethods, Categories, Tags, Brands, Coupons incl. product-restrictions relation, Webhooks, Reviews, Customers index, Orders index — each `{Resource}Resource` descriptor + `{record}` routes where the generic pages render + `ResourceRenderer`-rendered kernel pages + per-wave contract test green vs kernel 0.1.66→0.1.68). Wrap-up waves remaining: the Products form (biggest admin surface) and the nested-shape ShippingZones/Attributes conversions, which still need nested editing in the kernel. App-level render smoke awaiting the shop-module release.
- [x] Search — `ProductSearch` driver (2.6 → §6c 6.2): `ProductSearch` service behind the `ProductSearchDriver` contract (database backend), harness tests green (LIKE-escape/case/clamp)
- [ ] Foundation — 5.2 wired SHIPPED platform v0.1.66 (ResourceRenderer + generic kernel pages + per-resource permissions); 5.3 first pass SHIPPED platform v0.1.67 (relation field + serialized row actions + descriptor validation; proven by 6.1 waves 1–6 — see §6b)
- [ ] Coverage — 6.3 lifecycle + 6.4 competitive canvas + 6.5 B2B depth (shop-module)
- [ ] Final — PHPStan (4.1) + indexes/KPI (4.2) + UPGRADING.md (4.3)

