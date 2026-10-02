# Shop App — Full Audit Report (v2, verified)

**Date:** 2026-08-09
**Scope:** Laravel 13 + Inertia.js + React 19 e-commerce application (core package `vendor/nreljed/dashboard-core` + `app/Shop` + `app/Blog` + storefront)
**Methodology:** Three parallel codebase audits (seeder architecture, frontend inventory, modern-shop feature matrix) followed by **direct source verification** of every disputed claim. All claims below are verified against source unless marked otherwise.

---

## 1. Executive Summary

The application is a **feature-complete e-commerce shop** with a solid modular foundation. After direct verification, several "gaps" from the initial agent report were **disproved**: Stripe payments, refunds, order state machine, PDF invoices, coupon stacking, barcode, order notes, and stock decrement on order creation are all implemented.

The genuine remaining gaps are concentrated in the **storefront experience / marketing / extensibility** layers, not the core commerce workflow.

| Priority | Gap | Impact |
|----------|-----|--------|
| 1 | **Storefront UX depth** — no hero/featured products, no brand/price/attribute filters, no sorting UI, no related products, no breadcrumbs | High — conversion |
| 2 | **Product SEO** — no meta fields, no `sitemap.xml`, no `robots.txt` | Medium — discoverability |
| 3 | ~~**Multi-currency at storefront**~~ — **DONE (2026-08-10)**: storefront switcher + display conversion via `useCurrency`, checkout conversion, Accept-Language auto-detect, admin cache invalidation. Remaining: rate-sync command, per-product `ProductPrice` fixed prices | Medium — global reach |
| 4 | **Public API** — read-only, unauth'd, products+orders only | Medium — ecosystem |
| 5 | **Guest cart merge on plain login** — only happens at checkout/account-creation | Low — UX edge case |

---

## 2. Verified Facts (corrected)

> Everything here was confirmed directly in source. This section supersedes v1's findings.

### Commerce workflow — all IMPLEMENTED

| Capability | Evidence |
|-----------|----------|
| **Stripe payments** (not COD-only!) | `app/Shop/Services/StripeService.php` (PaymentIntent create/retrieve/confirm, `refund()`, `verifyWebhook()`); `CheckoutController` injects it, validates `payment_method in:manual,stripe`, passes `stripeKey`, `createPaymentIntent()`; `StripeWebhookController` handles `payment_intent.succeeded/failed` + `charge.refunded`; route `POST /store/webhook/stripe`; `payment_intent_id` column on orders |
| **Refunds / partial refunds** | `OrderRefund` model + `OrdersController::refund()` (partial via `total_refunded`, sets `payment_status=refunded`, restocks via `InventoryService`), `OrderPolicy::refund`, frontend refund UI in `Orders/Show.tsx` |
| **Order state machine** | `app/Shop/Services/OrderStatusService.php` — 10 statuses (`draft/pending/on-hold/confirmed/processing/completed/cancelled/refunded/failed/trash`) + explicit `TRANSITIONS` map + `validateTransition()`, `getAllowedTransitions()` passed to frontend |
| **PDF invoice** | `OrdersController::invoice()` — `Pdf::loadView('email.templates.order-invoice')` + `$pdf->download('invoice-{order_number}.pdf')` |
| **Coupon stacking** | `applied_coupons` JSON on carts + orders (`migration ..._add_coupon_stacking_to_carts_and_orders`); `CouponService::validateStack()` + `calculateStackedDiscount()`; `CartController::applyCoupon/removeCoupon` |
| **Barcode** | `barcode` column on products + product variations |
| **Order notes** | `OrderNote` model + `OrdersController::storeNote()/destroyNote()` + `StoreOrderNoteRequest` + frontend timeline in `Orders/Show.tsx` |
| **Stock decrement on order creation** | `InventoryService::recordSaleForOrder()` called from `CheckoutService` (after order creation), `OrdersController` (storeAdmin, convertDraft, markAsPaid), with `StockAdjustment` audit trail; hold-stock + release |
| **Tax applied at checkout** | `CheckoutService` — `taxService->calculate($subtotal, $billingAddress)`, adds to `total`, stores `tax_total` on order; `TaxService` (percentage/fixed per country); `CheckoutController` passes `estimatedTax` |
| **Guest cart merge** | `app/Shop/Services/CartMergeService.php` — called in `CheckoutController` during account creation at checkout and register-from-order flow |
| **Order editing** | `OrdersController::updateAdmin()` edits line items, `recordRefundForOrder`/`recordSaleForOrder` on delta |
| **Status emails + notifications** | `order.status_changed` via `NotificationService::notifyViaPreferences`, webhook `dispatchOrderStatusChanged` |

### Addresses

- **No standalone `Address` model** — addresses are stored as `billing_address` / `shipping_address` **JSON columns** on `users`, `carts`, and `orders`.
- Customer account can save billing + shipping address (`UserAccountController::updateAddress`, route `dashboard.account.address`, full field validation).
- Displayed on `My/Orders/Show.tsx` and checkout.

### Seeding

| Path | Coverage | Notes |
|------|----------|-------|
| `php artisan db:seed` → `DatabaseSeeder` | 4 users + roles, settings, currencies | Users: nstudio (admin), shop_owner, shop_manager, customer — all `azerty123/*`; NOT idempotent (factory `create()`) |
| `php artisan shop:product-seed` → `ShopProductSeeder` | 2 brands, 6 categories, 6 tags, Color+Storage attributes, 8 products (simple/variable/grouped/external/digital/service), 6 variations, 2 downloads, 5 reviews, picsum fake images | ⚠️ **Not called by `DatabaseSeeder`** — runs via the dedicated `shop:product-seed` command (added 2026-08-10). Idempotent (`firstOrCreate` by slug) |
| `php artisan demo:generate` | 12 customers, 4 shipping methods, 5 tax rates, 6 coupons, 18 orders (all 8 statuses), 11 reviews, stock history, blog posts (commented) | **Requires products already seeded** (`Product::published()` lookup). Does NOT seed products/brands/categories/webhooks/watchlists |
| `php artisan permissions:sync` | Syncs shop + blog feature permissions, assigns them to `admin` + `shop_owner` | Run after role changes |

### Frontend architecture

- `app.tsx` merges core + module pages via Vite globs (`dashboard/*`, `shop/*`, `blog/*`).
- Adding a page = TSX in module `Resources/js/Pages/` + controller render; **no core changes required**.
- Page tree: full shop admin (Products, Orders, Categories, Brands, Tags, Attributes, Variations, Customers, Coupons, Reviews, Shipping, Tax, Reports, Webhooks, Back-in-Stock, Wishlist, StockHistory) + storefront (grid, detail, cart, checkout) + My Orders portal.
- Components: shadcn/ui + Radix + Lucide; shared (terms-selector, gallery-uploader, featured-image-selector, publish-section); shop (StarRating, VariationsManager, AttributeSelector); dashboard (KpiCard, Sparkline, RevenueChart, RecentOrdersTable, TopProductsTable).

---

## 3. Corrections to the Initial (v1) Audit

The feature-matrix agent report contained **multiple false negatives**. These were all corrected via direct verification:

| v1 Claim | Reality |
|----------|---------|
| ❌ "Payments — COD only, no gateway" | ✅ **Stripe fully integrated** (intents, webhook, refunds) + `manual` fallback |
| ❌ "Refunds/returns — completely missing" | ✅ **OrderRefund + partial refunds + restock + policy** |
| ❌ "No enforced order state machine" | ✅ **OrderStatusService with TRANSITIONS map** |
| ❌ "No PDF invoice" | ✅ **`invoice()` returns PDF download** |
| ❌ "No coupon stacking" | ✅ **`applied_coupons` JSON + validateStack** |
| ❌ "No barcode field" | ✅ **barcode column on products + variations** |
| ❌ "No order notes" | ✅ **OrderNote model + CRUD + timeline UI** |
| ❌ "Stock decrement unclear" | ✅ **`InventoryService::recordSaleForOrder`** |
| ❌ "Tax at checkout unclear" | ✅ **TaxService integrated into CheckoutService** |
| ❌ "`CartService.applyCoupon`" | ⚠️ **`CartService` doesn't exist** — coupon logic lives in `CartController` + `CouponService` |
| ⚠️ "ShopProductSeeder orphaned" | ✅ **Now wired** via the dedicated `shop:product-seed` command (created 2026-08-10). It is NOT called by `DatabaseSeeder` — it runs separately, before `demo:generate` |

**Lesson:** the agent's exploratory summaries were unreliable for feature status. Every "missing" claim that mattered was verified against source before being written into this report.

---

## 4. Modern-Shop Feature Matrix (verified)

| # | Requirement | Status | Evidence / Gap |
|---|-------------|--------|----------------|
| 1 | Product CRUD | ✅ | `ProductsController` + form |
| 2 | Category tree | 🟡 | `parent_id` + table UI; no drag-drop tree widget |
| 3 | Brands & tags | ✅ | Full CRUD + inline create from product form |
| 4 | Attributes | ✅ | Typed (`text/color/image/button` display), swatches, terms, per-product assignment |
| 5 | Variations | ✅ | Attribute combos, per-variation stock/price/sku/barcode, pivot-based |
| 6 | SKU / barcode | ✅ | Both on products + variations |
| 7 | Inventory / stock | ✅ | stock, low-stock threshold, hold-stock, incoming stock, StockAdjustment audit |
| 8 | Back-in-stock | ✅ | `BackInStockRequest` + admin page + mailable |
| 9 | Search | ✅ | Admin + storefront |
| 10 | Storefront filters | 🟡 | **No brand/price-range/attribute filter UI, no sort control on storefront grid** |
| 11 | Sale / compare-at price | 🟡 | `compare_at_price` exists; **no promo badge / % off display** in UI |
| 12 | Multi-currency | 🟡 | Models + admin CRUD; **no storefront switcher, no checkout conversion** |
| 13 | Tax | ✅ | Calculated at checkout, `tax_total` on order, per-country rates |
| 14 | Cart persistence | 🟡 | Session-keyed guests + user carts; **merge only at checkout/account-creation, not plain login** |
| 15 | Quantity limits | ✅ | min/max, sold_individually, stock validation |
| 16 | Checkout | ✅ | multi-step page, addresses, shipping, tax, Stripe/manual |
| 17 | Shipping | 🟡 | Zones + methods + override prices; **no carrier integration / live rates** |
| 18 | Coupons | ✅ | percentage/fixed/free_shipping, stacking, auto-discounts, limits |
| 19 | Guest checkout | ✅ | + register-from-order flow |
| 20 | Order creation + stock decrement | ✅ | Verified (see §2) |
| 21 | Order emails | ✅ | confirmation + status_changed + notifications + webhooks |
| 22 | Order states | ✅ | Full state machine (see §2) |
| 23 | Refunds / returns | 🟡 | **Refunds ✅; customer-initiated returns ❌** |
| 24 | Invoices | ✅ | PDF download + HTML view |
| 25 | Payments | ✅ | Stripe (intents + webhook + refund) + manual |
| 26 | Customer accounts / addresses | 🟡 | JSON billing/shipping per user; **no multi-address book** |
| 27 | Wishlist | ✅ | Controller + page + button |
| 28 | Reviews | ✅ | Approval workflow, rating cache, storefront display |
| 29 | Marketing coupons | ✅ | + scheduled sales, abandoned cart recovery |
| 30 | SEO | 🟡 | `<Head>` titles only; **no product meta, sitemap, robots** |
| 31 | Abandoned cart | ✅ | `ProcessAbandonedCarts` + recovery emails + admin |
| 32 | Admin dashboard | ✅ | ShopDashboard (stats, low stock, recent orders) |
| 33 | Storefront UX | 🟡 | **No hero/featured/related/breadcrumbs; thin homepage; no filters** |
| 34 | Public API | 🟡 | Read-only products+orders; **no auth/scopes/rate-limit** |
| 35 | Webhooks | ✅ | Signing, retries, deliveries, call log |
| 36 | Roles / permissions | ✅ | Policies + `permissions:sync` |
| 37 | Performance | 🟡 | Eager loading present; no query caching; index review recommended |
| 38 | Settings | ✅ | Core + shop + currencies |

---

## 5. Genuine Gaps (prioritized)

1. **Storefront UX depth** — hero/banners, featured products, new arrivals, category grid; product detail: gallery thumbnails, variations selector UX, reviews summary, related products, breadcrumbs; grid: brand/price/attribute filters + sorting. Highest conversion impact.
2. **Product SEO / marketing** — `meta_title`/`meta_description`/canonical/OG on products, `sitemap.xml`, `robots.txt`.
3. **Multi-currency storefront** — currency switcher + checkout conversion (models ready).
4. **Public API hardening** — auth (Sanctum/OAuth), scopes, rate limiting, OpenAPI docs, broader resource coverage.
5. **Guest cart merge on login** — hook `CartMergeService` into the login flow (core `AuthenticatedSessionController`), not just checkout.
6. **Customer-initiated returns** — returns vs refunds (refunds exist; a self-service returns request flow doesn't).
7. **Shipping carrier integration** — real-time rates / labels (Phase 36 scope).
8. **Multi-address book** — currently one billing + one shipping JSON per user.
9. **Marketing/bulk email campaigns** — transactional emails exist; no campaign tool.
10. **B2B / subscriptions / gift cards / POS** — the Phase 28–32 roadmap; larger efforts, deferrable.

### Minor code health items
- `OrderService` hardcodes `payment_method => 'cod'` for manual/admin orders — intentional default, but name it explicitly (`manual`) to avoid confusion with the gateway checkouts.

---

## 6. Recommendations

1. **Storefront conversion pass** (biggest ROI): homepage sections + product-detail polish + grid filters/sorting + related products + breadcrumbs.
2. **SEO layer**: product SEO columns + render meta tags + `sitemap.xml` + `robots.txt`.
3. **Multi-currency checkout**: wire existing `CurrencyService` into storefront display + checkout conversion.
4. **API hardening**: auth + scopes + rate limits + docs.
5. **Cart merge on login**: extend `CartMergeService` call to the login controller.

---

## 7. Verification Status

All v1 "TO VERIFY" items are now resolved:

- ✅ Guest cart merge — implemented, but checkout-only (gap for plain login)
- ✅ Tax applied at checkout — confirmed
- ✅ Address handling — JSON billing/shipping; no model (minor)
- ✅ Stock decrement on order creation — confirmed
- ✅ Stripe payments — confirmed (v1 wrongly said COD-only)
- ✅ Refunds, state machine, PDF invoice, coupon stacking, barcode, order notes — all confirmed
