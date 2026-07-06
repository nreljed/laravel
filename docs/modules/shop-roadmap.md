# Shop Module — Roadmap (Phases 21–38)

**Current baseline**: 189 tests passing, typecheck clean, build clean. All P0/P1 variation WC parity items done.

---

## Priority Summary

| Priority | Phase | Title | Impact | Effort | Depends On |
|----------|-------|-------|--------|--------|------------|
| **P0** | 21 | Order & Refund Expansion | HIGH | Medium | Core orders |
| **P1** | 22 | Discount Engine Expansion | MEDIUM | Medium | 21 (order integration) |
| **P1** | 23 | Storefront Enhancement | MEDIUM | Medium | 19, 20 |
| **P1** | 24 | Checkout & Cart Enhancement | MEDIUM | Medium | 20, 23 |
| **P2** | 25 | Shipping Depth | MEDIUM | Medium | 23 |
| **P2** | 26 | Tax Depth | LOW | Small | None |
| **P2** | 27 | Customer & Account Depth | MEDIUM | Medium | 21 |
| **P2** | 28 | B2B / Wholesale | HIGH | Large | 20, 22, 26, 27 |
| **P2** | 29 | Subscriptions | HIGH | Large | 19, 24 |
| **P3** | 30 | Gift Cards | MEDIUM | Medium | 19 |
| **P3** | 31 | Multi-Currency | HIGH | Medium | 24 |
| **P3** | 32 | POS | LOW | XL | 21, 27 |
| **P3** | 33 | Marketing & Analytics | MEDIUM | Medium | 21, 24 |
| **P4** | 34 | API & Developer Experience | HIGH | XL | All CRUD phases |
| **P4** | 35 | Multi-Language | LOW | XL | All UI phases |
| **P4** | 36 | Shipping Carrier Integration | HIGH | XL | 25 |
| **P4** | 37 | Purchase Orders & Suppliers | LOW | Large | 20 |
| **P4** | 38 | Migration & Go-Live | — | Medium | All phases |

---

## Phase 21 — Order & Refund Expansion (HIGH · Medium) ✅ Completed 2026-07-08

**Goal**: Bring order management to WooCommerce parity with partial refunds, order editing, draft orders, invoice, and order notes.

### Sub-tasks

| # | Task | Files | Lines Est. |
|---|------|-------|-----------|
| 21.1 | Add `on-hold`, `failed`, `trash` order statuses | `Order.php` (status enum), migration, `config/shop.php` | ~50 |
| 21.2 | Define status transition workflow (which → which allowed, with validation) | `OrderStatusService`, `OrderStatusRule` config | ~100 |
| 21.3 | **Partial refunds**: `OrderRefund` model, `items` JSON (line_item_id, qty, amount, tax), `shipping_refund`, `created_by` | `Models/OrderRefund.php`, migration `create_order_refunds_table`, `RefundController` rewrite | ~250 |
| 21.4 | Refund controller: accept line item breakdown, auto-restock per item via `InventoryService` | `OrdersController.php` — new `refund()` POST accepting items array | ~150 |
| 21.5 | **Order editing**: add/remove/change items, prices. Log changes to `OrderAction` | `OrderEditController`, `OrderAction` model | ~200 |
| 21.6 | **Draft orders**: manual admin order creation via form, status=draft, convert to pending on checkout | `DraftOrdersController`, `DraftOrderService` | ~250 |
| 21.7 | **Invoice PDF**: `laravel-dompdf` (or `barryvdh/laravel-snappy`), Blade template, download button | `InvoiceController`, `resources/views/pdf/invoice.blade.php`, composer require | ~100 |
| 21.8 | **Order notes**: `OrderNote` model with public/private flag, admin timeline UI | `Models/OrderNote.php`, migration, `OrderNoteController` (JSON), frontend timeline | ~150 |
| 21.9 | Frontend: refund dialog (line item checkboxes), edit order modal, notes timeline, invoice button | `Orders/Show.tsx`, `Orders/components/RefundDialog.tsx`, `Orders/components/OrderNotes.tsx`, `Orders/components/InvoiceButton.tsx` | ~400 |
| 21.10 | Permissions: `shop.orders.refund` — register in `ShopModule`, create `OrderPolicy` | `ShopModule.php:permissions()`, `app/Policies/OrderPolicy.php` | ~30 |

**Models new**: `OrderRefund`, `OrderNote`, `OrderAction`
**Services new**: `OrderStatusService`, `DraftOrderService`
**Controllers new**: `DraftOrdersController`, `OrderEditController`, `InvoiceController`, `OrderNoteController`
**Composer**: `barryvdh/laravel-dompdf`
**Tests**: ~20 test methods covering refund flow, status transitions, draft order lifecycle, invoice generation

---

## Phase 22 — Discount Engine Expansion (MEDIUM · Medium)

**Goal**: Add BOGOF, product-specific discounts, tiered/quantity breaks, and coupon stacking.

### Sub-tasks

| # | Task | Details |
|---|------|---------|
| 22.1 | BOGOF coupon type: `buy_x_get_y` with `free_product_id`, `buy_quantity`, `free_quantity` | Add to `Coupon::$fillable`, `Coupon::calculateDiscount()`, admin form type selector |
| 22.2 | Product-specific discount: `fixed_product` (fixed on specific products), `percentage_product` | New discount calculation in `CouponService`, `coupon_product` pivot already exists |
| 22.3 | Tiered / quantity-break discounts: `CartDiscount` model with threshold rules (`min_qty`, `discount_percent`) | New model, new service method, automatic application in cart |
| 22.4 | Coupon stacking: allow multiple coupons per order, validate stacking rules | Cart session change (array of codes), checkout re-validation, `CouponService::validateStack()` |
| 22.5 | Frontend: new coupon type config in Form.tsx, multiple coupon inputs in Cart, tiered display | `Coupons/Form.tsx`, `Storefront/ShoppingCart.tsx` |

**Models new**: `CartDiscount`
**Services modified**: `CouponService`
**Frontend modified**: Coupons form, cart page
**Tests**: ~10 test methods

---

## Phase 23 — Storefront Enhancement (MEDIUM · Medium)

**Goal**: Make the storefront product detail page shoppable with variations, gallery, and attribute filtering.

### Sub-tasks

| # | Task | Details |
|---|------|---------|
| 23.1 | Attribute-based filtering (color swatches, size dropdowns) using `AttributeTerm` | `StorefrontController::index()` with `attribute_{id}` query params, `Product::scopeByAttributeTerm()` |
| 23.2 | In-stock/backorder/out-of-stock badges per product card | `Storefront/ProductGrid.tsx` stock badge component (uses `active_price` + `stock_status`) |
| 23.3 | Sale badge with % off (calculated from `compare_price` or `sale_price` vs `price`) | `Storefront/ProductDetail.tsx` — use `on_sale` accessor, compute `(price - active_price) / price * 100` |
| 23.4 | Low stock indicator ("Only 3 left") | Already has `low_stock_threshold` — display when `stock_quantity <= low_stock_threshold && stock_quantity > 0` |
| 23.5 | Image gallery with lightbox (thumbnail → fullscreen carousel) | New `ProductGallery` component using Radix Dialog + `images` JSON array |
| 23.6 | Variation selector (radio buttons on detail, update price/image via JS) | New `VariationSelector` component, fetch variation data via `getDefaultVariation()` + JS event |
| 23.7 | Quantity selector with min/max enforcement | New `QtySelector` component using `min_quantity`/`max_quantity`/`sold_individually` |
| 23.8 | Related products carousel (same category, different product) | `StorefrontController::show()` passes `related` Collection, `ProductCard` horizontal scroll |
| 23.9 | Product tabs (Description / Additional Info / Reviews) | `Storefront/ProductDetail.tsx` — tab state or Radix Tabs |
| 23.10 | Upsells/cross-sells display | New pivot tables `product_upsells`/`product_cross_sells`, admin form selectors, storefront cards |

**Components new**: `ProductGallery`, `VariationSelector`, `QtySelector`, `ProductTabs`, `RelatedProducts`, `ImageLightbox`
**Controller modified**: `StorefrontController` — pass relations, price ranges, availability per variation
**Tests**: ~10 test methods

---

## Phase 24 — Checkout & Cart Enhancement (MEDIUM · Medium)

**Goal**: Make checkout a proper multi-step flow with shipping calculator, tax display, payment selection, and guest account creation.

### Sub-tasks

| # | Task | Details |
|---|------|---------|
| 24.1 | Cart persistence across sessions (guest cart token in localStorage + DB) | `Cart` model gets `token` column, `CartService::getOrCreateGuestCart()` via middleware |
| 24.2 | Shipping calculator in cart (estimate by country, shows methods + prices) | AJAX endpoint → `ShippingService::getAvailableMethods()`, frontend displays options |
| 24.3 | Estimated tax display in cart (based on billing address or geolocation) | AJAX → `TaxService::calculate()`, display breakdown lines |
| 24.4 | Checkout progress indicator (Cart → Shipping → Payment → Review) | `CheckoutProgress` component, multi-step state via `useState` in Checkout page |
| 24.5 | Payment gateway selection UI (Stripe, COD, bank transfer) | `PaymentMethodSelector` component, `Order.payment_method` saved on submit |
| 24.6 | Order summary review step (all items, totals, addresses, method) | `OrderReview` component, read-only display before final submit |
| 24.7 | Guest account creation during checkout (opt-in checkbox, creates User after order) | `AccountCreationCheckbox` + `CheckoutService` creates user after order |
| 24.8 | Cross-sell suggestions in cart ("You might also like...") | `Cart/Index.tsx` — products from same categories excluding cart items |

**Components new**: `CheckoutProgress`, `PaymentMethodSelector`, `OrderReview`, `ShippingCalculator`, `AccountCreationCheckbox`
**Controller modified**: `CheckoutController`, `CartController`
**Tests**: ~15 test methods

---

## Phase 25 — Shipping Depth (MEDIUM · Medium)

**Goal**: Add shipping classes, local delivery/pickup, and weight-based calculation.

### Sub-tasks

| # | Task | Details |
|---|------|---------|
| 25.1 | `ShippingClass` model: name, slug, description, sort_order | New model + migration, CRUD controller, permissions `shop.shipping.manage` |
| 25.2 | `shipping_class_id` on Product + ProductVariation | Migration + fillable update |
| 25.3 | Flat rate per class in ShippingMethod: per-class base cost + per-item cost | `ShippingMethod` gets `class_rates` JSON column, `ShippingService` calculates |
| 25.4 | Local delivery method: fixed cost, defined postal codes | New `local_delivery_zones` table or JSON column on method |
| 25.5 | Local pickup method: free, location address display | Display pickup address at checkout |
| 25.6 | Weight-based calculation: base rate + per-kg rate | `ShippingMethod` gets `weight_rate` columns |

**Models new**: `ShippingClass`
**Models modified**: `Product`, `ProductVariation`, `ShippingMethod`
**Services modified**: `ShippingService`
**Tests**: ~8 test methods

---

## Phase 26 — Tax Depth (LOW · Small)

**Goal**: Add tax classes, `tax_status` enum, and order-level tax exemption for B2B.

### Sub-tasks

| # | Task | Details |
|---|------|---------|
| 26.1 | `TaxClass` model: name, slug, description | New model + migration, CRUD |
| 26.2 | `tax_class_id` on TaxRate | Migration + foreign key |
| 26.3 | `tax_status` enum on Product: `taxable`/`shipping`/`none` replacing `is_taxable` bool | Migration (add column, drop `is_taxable`), fillable update, Form.tsx |
| 26.4 | `tax_exempt` at Order level for B2B | Migration column + checkout integration |

**Models new**: `TaxClass`
**Models modified**: `Product`, `Order`, `TaxRate`
**Tests**: ~5 test methods

---

## Phase 27 — Customer & Account Depth (MEDIUM · Medium)

**Goal**: Multiple addresses, customer groups, rich order history, downloadable products, wishlist.

### Sub-tasks

| # | Task | Details |
|---|------|---------|
| 27.1 | Multiple addresses: `UserAddress` model (type: billing/shipping, is_default) | New model + migration, `User::addresses()` hasMany, CRUD controller |
| 27.2 | Customer groups: `CustomerGroup` model (name, slug, description) | New model + migration, CRUD admin page, assign on user edit |
| 27.3 | Customer notes (private admin notes) | `CustomerNote` model, inline on Customers/Show.tsx |
| 27.4 | Rich order history page — search, filters (status/date), export | `Account/Orders/Index.tsx` + `Account/Orders/Show.tsx` |
| 27.5 | Downloadable products section in account | `Account/Downloads/Index.tsx` — purchased downloads with expiry |
| 27.6 | Wishlist enhancements | Wishlist exists — add bulk add/remove, share wishlist URL |
| 27.7 | Saved payment methods (Stripe SetupIntents) | `PaymentMethod` model, `UserPaymentMethodsController`, `Account/PaymentMethods.tsx` |

**Models new**: `UserAddress`, `CustomerGroup`, `CustomerNote`
**Controllers new**: `CustomerAddressController`, `CustomerGroupController`, `WishlistController`, `UserPaymentMethodsController`
**Tests**: ~12 test methods

---

## Phase 28 — B2B / Wholesale (HIGH · Large)

**Goal**: Company accounts with tiered pricing, net terms, quantity rules, and B2B checkout flow.

### Sub-tasks

| # | Task | Details |
|---|------|---------|
| 28.1 | `Company`: name, tax_id, email, phone, address, is_active | New model + migration |
| 28.2 | `CompanyLocation`: name, address, is_primary, belongsTo Company | New model + migration |
| 28.3 | `CompanyContact`: user_id, company_id, location_id, role (buyer/approver/admin) | New model + migration, pivot use |
| 28.4 | Tiered pricing: `PriceList` model, `PriceListEntry` (product_id, price) | Percentage markup/discount per product, applied at cart/checkout |
| 28.5 | Net terms: `PaymentTerm` (due_days, discount_percent, discount_days) assigned to Company | New model, checkout shows net terms invoice |
| 28.6 | B2B quantity rules: `min_b2b_quantity`, `max_b2b_quantity`, `b2b_price` on Product | Migration columns, validation in cart |
| 28.7 | B2B checkout flow: company context → tiered pricing → net terms → PO number | Company selector in user dropdown, applies to cart |
| 28.8 | Permissions: `shop.companies.view/manage`, `shop.price_lists.manage` | `ShopModule::permissions()`, policies |
| 28.9 | Frontend: B2B pricing in storefront, company selector, net terms display | Storefront shows both prices (retail + B2B), `CompanySelector.tsx` |

**Models new**: `Company`, `CompanyLocation`, `CompanyContact`, `PriceList`, `PriceListEntry`, `PaymentTerm`
**Controllers new**: `CompanyController`, `CompanyLocationController`, `PriceListController`, `CompanyContactController`
**Permissions**: 3 new
**Tests**: ~15 test methods

---

## Phase 29 — Subscriptions (HIGH · Large)

**Goal**: Recurring billing with plans, customer management, free trials, and webhook events.

### Sub-tasks

| # | Task | Details |
|---|------|---------|
| 29.1 | `SubscriptionPlan`: name, description, interval (day/week/month/year), interval_count, trial_days, price, signup_fee, is_active | New model + migration, admin CRUD |
| 29.2 | `Subscription`: user_id, plan_id, status (active/paused/cancelled/expired), start_date, next_billing, end_date, trial_ends_at | New model + migration |
| 29.3 | Recurring billing: console command generates orders for due subscriptions | `SubscriptionService::generateOrders()`, `shop:process-subscriptions` daily |
| 29.4 | Subscription management in account: view plans, upgrade/downgrade, cancel, pause | `Account/Subscriptions/Index.tsx`, `SubscriptionPlansController` (public) |
| 29.5 | Free trial support: `trial_days`, `trial_ends_at`, auto-convert to paid | Handled in `SubscriptionService::processTrials()` |
| 29.6 | Webhook events: `subscription.*` — created, updated, cancelled, expired | `WebhookService::dispatch()` calls in service |
| 29.7 | Permissions: `shop.subscriptions.view`, `shop.subscriptions.manage` | `ShopModule::permissions()` |

**Models new**: `SubscriptionPlan`, `Subscription`
**Services new**: `SubscriptionService`
**Controllers new**: `SubscriptionPlanController` (admin), `SubscriptionController` (customer), `AdminSubscriptionController`
**Console**: `shop:process-subscriptions`
**Tests**: ~12 test methods

---

## Phase 30 — Gift Cards (MEDIUM · Medium)

**Goal**: Sell gift cards via storefront, redeem at checkout, track balance.

### Sub-tasks

| # | Task | Details |
|---|------|---------|
| 30.1 | Gift card product type: extends virtual, sets `is_gift_card=1` | Product type selector, `Product::isGiftCard()` |
| 30.2 | `GiftCard` model: code (unique), initial_balance, remaining_balance, purchaser_id, recipient_email, message, expires_at, is_active | New model + migration |
| 30.3 | Gift card purchase flow: select amount → cart → checkout → email delivery to recipient | Integration with Checkout, `MailService::send('gift-card-purchased')` |
| 30.4 | Gift card redemption flow: apply code at checkout, validate remaining_balance | `CouponService`-like `GiftCardService::validateAndApply()` |
| 30.5 | Balance checking page (public): enter code, see remaining balance | `Storefront/GiftCardBalance.tsx` |
| 30.6 | `GiftCardTransaction` model: gift_card_id, order_id, amount, type (purchase/redeem/refund) | Audit trail migration |
| 30.7 | Permissions: `shop.gift_cards.manage` | `ShopModule::permissions()` |

**Models new**: `GiftCard`, `GiftCardTransaction`
**Services new**: `GiftCardService`
**Controllers new**: `GiftCardController` (public), `GiftCardsController` (admin)
**Tests**: ~10 test methods

---

## Phase 31 — Multi-Currency (HIGH · Medium)

**Note**: Currency model + admin CRUD + `CurrencyService` (convert/format/getDefault) + `CurrenciesController` + currencies migration **already done**.

### Sub-tasks

| # | Task | Details |
|---|------|---------|
| 31.1 | Currency detection (browser `Accept-Language`, IP geolocation, or saved preference) | Middleware `DetectCurrency`, stores in session/cookie |
| 31.2 | Currency switcher UI in store header | `StoreLayout.tsx` dropdown, posts to `CurrencySwitchController` |
| 31.3 | Wire auto-conversion at checkout: convert product prices × exchange rate | `CurrencyService::convert()` called in `StorefrontController`, `CartController`, `CheckoutController` |
| 31.4 | Per-product fixed prices in multiple currencies via `ProductPrice` (product_id, currency_id, price) | New model + migration, admin product form tab |
| 31.5 | Exchange rate sync command (optional: fetch from exchangerate-api.com) | `shop:sync-exchange-rates`, configurable in settings |

**Models new**: `ProductPrice`
**Services modified**: `CurrencyService`
**Controllers new**: `CurrencySwitchController`
**Console**: `shop:sync-exchange-rates`
**Tests**: ~8 test methods

---

## Phase 32 — POS (Point of Sale) (LOW · Extra Large)

**Goal**: Touch-optimized POS order creation page for retail stores.

**Note**: Deferrable until retail stores become a target market. Estimated at 500+ lines across backend + frontend.

### Sub-tasks (rough)

| # | Task |
|---|------|
| 32.1 | POS order creation page (optimized for touch/tablet) |
| 32.2 | Product lookup via search + barcode scanner |
| 32.3 | Cart builder with add/remove/qty (touch-friendly) |
| 32.4 | Customer lookup + attach to order |
| 32.5 | Payment capture: stripe terminal, cash, card |
| 32.6 | Receipt printing (browser print + thermal printer via ESC/POS) |
| 32.7 | Offline mode (queue orders locally, sync when online) |

---

## Phase 33 — Marketing & Analytics (MEDIUM · Medium)

**Goal**: Abandoned cart SMS, bulk email campaigns, conversion funnel analytics.

### Sub-tasks

| # | Task | Details |
|---|------|---------|
| 33.1 | Abandoned cart SMS recovery (via Twilio) | `AbandonedCartService` extends to SMS, config toggle |
| 33.2 | `Campaign` model: name, subject, content (Blade), sent_count, opened_count, clicked_count | New model + migration, admin CRUD |
| 33.3 | Campaign send via queue with open/click tracking (tracking pixel + redirect) | `CampaignSendJob` + `CampaignTrackerController` |
| 33.4 | Marketing analytics dashboard: subscribers, campaign stats, funnel | Admin dashboard tab |
| 33.5 | Conversion funnel analytics: page view → add to cart → checkout → purchase | `AnalyticsEvent` model, JS tracking snippet |
| 33.6 | Customer LTV calculation | `User::getLifetimeValue()` — sum of completed orders |

**Models new**: `Campaign`, `CampaignRecipient`, `AnalyticsEvent`
**Permissions**: `shop.marketing.manage`
**Tests**: ~10 test methods

---

## Phase 34 — API & Developer Experience (HIGH · Extra Large)

**Goal**: Public REST API for products, orders, customers, categories, reviews. Documentation.

### Sub-tasks

| # | Task | Details |
|---|------|---------|
| 34.1 | Public REST API routes under `/api/v1/` with Sanctum auth | `routes/api-v1.php`, `Api/V1/` controllers |
| 34.2 | Products CRUD API | `Api/V1/ProductsController`, `ProductResource`, pagination, filters |
| 34.3 | Orders CRUD API | `Api/V1/OrdersController` (read-only for customers, full for admin) |
| 34.4 | Customers CRUD API | `Api/V1/CustomersController` (admin-only) |
| 34.5 | Categories API | `Api/V1/CategoriesController` (read-only public) |
| 34.6 | Reviews API | `Api/V1/ReviewsController` (create, list) |
| 34.7 | API rate limiting (100 req/min per token) | `RateLimiter::for('api')` in `AppServiceProvider` |
| 34.8 | OpenAPI/Swagger documentation | `l5-swagger` or custom OpenAPI JSON |
| 34.9 | TypeScript module typecheck: add `app/Modules/*/Resources/js` to `tsconfig.json` include | One config change |
| 34.10 | Migration tests: `RefreshDatabase` + schema assertions per migration | `tests/Feature/MigrationTest.php` |
| 34.11 | Permissions: `shop.api.access` | `ShopModule::permissions()` |

**Controllers new**: `Api/V1/*Controller` (6+)
**Resources**: `ProductResource`, `OrderResource`, `CustomerResource`, etc.
**Permissions**: 1 new
**Tests**: ~20 test methods

---

## Phase 35 — Multi-Language (LOW · Extra Large)

**Goal**: i18n for storefront with translatable product/category content.

### Sub-tasks

| # | Task | Details |
|---|------|---------|
| 35.1 | Locale detection: cookie/session, `Lang` facade with custom loader | Middleware `DetectLocale` |
| 35.2 | Translation files for default strings (product, cart, checkout, account) | `resources/lang/{locale}/storefront.php` |
| 35.3 | `ProductTranslation` model: product_id, locale, name, description, short_description | New model + migration |
| 35.4 | `CategoryTranslation` model: category_id, locale, name, description | New model + migration |
| 35.5 | Language switcher in store header | `StoreLayout.tsx` dropdown |
| 35.6 | Admin UI: per-locale fields in product/category forms | `Form.tsx` tabs per enabled locale |

**Models new**: `ProductTranslation`, `CategoryTranslation`
**Tests**: ~8 test methods

---

## Phase 36 — Shipping Carrier Integration (HIGH · Extra Large)

**Goal**: Real-time rates, label generation, tracking from UPS/FedEx/DHL.

### Sub-tasks

| # | Task | Details |
|---|------|---------|
| 36.1 | Abstract `CarrierService` interface | Define contract (getRates, createLabel, track) |
| 36.2 | UPS API integration: SOAP/REST for rates, labels, tracking | `Carriers/UpsCarrier.php`, config for API keys |
| 36.3 | FedEx API integration | `Carriers/FedExCarrier.php` |
| 36.4 | DHL API integration | `Carriers/DhlCarrier.php` |
| 36.5 | Real-time rate calculation at checkout (display carrier rates when address entered) | AJAX → `ShippingService::getCarrierRates()`, frontend display |
| 36.6 | Tracking number management on Orders (input, auto-email customer) | `OrdersController::updateTracking()`, email trigger |
| 36.7 | Shipping label generation (download PDF from carrier) | Button in Order Show, downloads via carrier API |
| 36.8 | Config: carrier API keys stored in settings | `ShopSettingsController`, `shop` group |

**Services new**: `CarrierService` (abstract), `UpsCarrier`, `FedExCarrier`, `DhlCarrier`
**Permissions**: `shop.carriers.manage`
**Tests**: ~6 test methods (mocked HTTP)

---

## Phase 37 — Purchase Orders & Supplier Management (LOW · Large)

**Goal**: Supplier management, purchase orders, and stock reorder recommendations.

### Sub-tasks

| # | Task | Details |
|---|------|---------|
| 37.1 | `Supplier` model: name, contact, email, phone, address, lead_time_days | New model + migration, admin CRUD |
| 37.2 | `PurchaseOrder` model: supplier_id, status (draft/sent/confirmed/received/cancelled), expected_date, items JSON or HasMany | New model + migration, admin CRUD |
| 37.3 | Stock reorder recommendations: when stock < `low_stock_threshold` + lead_time consumption → suggest PO | `InventoryService::getReorderRecommendations()` |
| 37.4 | Receiving UI: mark PO received, auto-increment stock quantities | `PurchaseOrdersController::receive()`, calls `InventoryService` |

**Models new**: `Supplier`, `PurchaseOrder`, `PurchaseOrderItem`
**Permissions**: `shop.suppliers.view/manage`, `shop.purchase_orders.*`
**Tests**: ~8 test methods

---

## Phase 38 — Migration & Go-Live (— · Medium)

**Goal**: Finalize, audit, and optimize for production.

### Sub-tasks

| # | Task | Details |
|---|------|---------|
| 38.1 | Cumulative migration script for all new columns/tables | Review all migrations since baseline, ensure idempotent |
| 38.2 | Database seeders for all new permissions | `RolesAndPermissionsSeeder` update |
| 38.3 | Performance audit: index review, query profiling, eager loading audit | Check N+1 queries in storefront, admin list pages |
| 38.4 | Storefront caching strategy (full-page cache for product pages) | `Cache::remember()` in `StorefrontController`, TTL per product |
| 38.5 | Security audit: XSS, CSRF, SQL injection review across all new code | Manual review + automated (Larastan) |
| 38.6 | Documentation update: API docs, admin manual, developer guide | Update `docs/modules/shop.md` + new docs per phase |
| 38.7 | Final test suite verification: ensure all existing tests pass + new tests for each phase | Full `php artisan test` + `npm run typecheck` + `npm run build` |

---

## Dependency Graph

```
21 (Orders) ──────────────────────────────────────────────────────────────┐
  ├── 22 (Discounts) ── depends on 21 (checkout integration)              │
  ├── 24 (Checkout) ─── depends on 21 (order creation)                    │
  ├── 27 (Customers) ── depends on 21 (order history)                     │
  ├── 28 (B2B) ──────── depends on 21 (draft orders) + 22 (tiers)         │
  ├── 32 (POS) ──────── depends on 21 (order creation) + 27 (customers)   │
  └── 33 (Marketing) ── depends on 21 (order data for analytics)          │

19 (Product Types) ── depends on nothing
  ├── 23 (Storefront) ── depends on 19 (type display) + 20 (stock badges) │
  ├── 29 (Subscriptions) ── depends on 19 (product types) + 24 (checkout) │
  └── 30 (Gift Cards) ── depends on 19 (virtual type)                     │

20 (Stock Depth) ──── depends on 19
  ├── 23 (Storefront) ── uses stock badges                                 │
  ├── 24 (Checkout) ──── uses stock validation                             │
  ├── 28 (B2B) ──────── uses stock rules                                   │
  └── 37 (PO) ───────── uses stock thresholds                              │

23 (Storefront) ──────────────────────────────────────────────────────────┐
  └── 24 (Checkout) ──── storefront feeds into cart                        │
  └── 25 (Shipping) ──── storefront shows shipping options                 │

25 (Shipping) ────────────────────────────────────────────────────────────┐
  └── 36 (Carriers) ──── expands 25's rate calculation                     │

26 (Tax Depth) ──────────────────────────────────────────────────────┐
  └── 28 (B2B) ──────── tax exemption for B2B                           │

34 (API) ───────── depends on all CRUD phases being done               │
35 (I18n) ──────── depends on all UI phases                             │
38 (Go-Live) ───── depends on all phases                                │
```

## Recommended Execution Order

```
Wave 1 (P0 – HIGH impact, manageable effort):
  Phase 21 → Phase 22 → Phase 27

Wave 2 (P1 – Storefront & checkout experience):
  Phase 23 → Phase 24 → Phase 25

Wave 3 (P1-P2 – Revenue-driving features):
  Phase 29 (Subscriptions) → Phase 30 (Gift Cards) → Phase 31 (Multi-Currency)

Wave 4 (P2 – B2B enterprise):
  Phase 28 → Phase 26 (Tax for B2B) → Phase 33 (Marketing)

Wave 5 (P3-P4 – Scale & developer ecosystem):
  Phase 34 → Phase 36 → Phase 35

Wave 6 (P4 – Niche / polished):
  Phase 37 → Phase 32 → Phase 38
```

Each wave is independent and can be executed sequentially or by parallel teams. Within each wave, phases are ordered by dependency.

## Verification Gate

After every phase:
```bash
php artisan test
npm run typecheck
npm run build
vendor/bin/pint
```

Must pass before moving to the next phase. For phases with schema changes, also run:
```bash
php artisan migrate:fresh --seed
php artisan modules:sync-permissions
```
