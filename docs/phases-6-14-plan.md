# Phase Plans 6–14 — Shop & Beyond

All phases assume: `php artisan test` stays green, `npm run typecheck` clean, production build passes.

---

## Phase 6 — Checkout Flow (7–10 days)

The cart is currently API-only and orders can't be created. Build a full checkout pipeline.

### 6.1 — Cart Frontend UI (2 days)

| # | Task | Files |
|---|------|-------|
| 6.1.1 | Create `Shop/Cart/Index.tsx` — cart items list with quantity +/- controls, remove button, per-item subtotals, cart total, coupon input (placeholder) | `Resources/js/Pages/Cart/Index.tsx` (NEW) |
| 6.1.2 | Add nav item "Cart" under Shop sidebar group | `ShopModule.php` |
| 6.1.3 | Register frontend page `dashboard.shop.cart.index` | `ShopModule.php` |
| 6.1.4 | Cart empty state with "Browse Products" CTA | `Cart/Index.tsx` |
| 6.1.5 | Wire cart quantity +/- to existing `PUT cart/items/{item}` endpoint | `Cart/Index.tsx` |

**Verification**: navigate to `/dashboard/shop/cart`, add/remove items, quantities update.

### 6.2 — Checkout Page (3 days)

| # | Task | Files |
|---|------|-------|
| 6.2.1 | Create `OrdersController::checkout()` — renders checkout form with cart items + address fields | `OrdersController.php` |
| 6.2.2 | Create `Shop/Orders/Checkout.tsx` — order summary panel, billing/shipping address forms, shipping method select (hardcoded), payment method select, place order button | `Resources/js/Pages/Orders/Checkout.tsx` (NEW) |
| 6.2.3 | Register checkout frontend page route | `ShopModule.php`, `Routes/web.php` |
| 6.2.4 | "Proceed to Checkout" button on cart page | `Cart/Index.tsx` |
| 6.2.5 | Shipping method model + migration (name, price, estimated_days, is_active) | `database/migrations/*_create_shipping_methods.php`, `Models/ShippingMethod.php` (NEW) |

**Verification**: cart → checkout page → address form renders → shipping options load.

### 6.3 — Place Order (2 days)

| # | Task | Files |
|---|------|-------|
| 6.3.1 | Create `OrdersController::placeOrder()` — validate cart not empty, create Order + OrderItems from cart items, save billing/shipping address from form, set status `pending`, payment_status `pending`, clear cart | `OrdersController.php` |
| 6.3.2 | Dispatch `order.placed` notification to customer + admins via `NotificationService::notifyViaPreferences` | `OrdersController.php` |
| 6.3.3 | Validation: stock availability check before placing order, backorder logic | `OrdersController.php` |
| 6.3.4 | Create `Shop/Orders/Confirmation.tsx` — "Thank you" page with order number, status, link to view order | `Resources/js/Pages/Orders/Confirmation.tsx` (NEW) |
| 6.3.5 | Register confirmation frontend page route | `ShopModule.php`, `Routes/web.php` |
| 6.3.6 | Flash success + redirect to confirmation after place order | `OrdersController.php` |

**Verification**: fill checkout → place order → order created in DB → cart cleared → confirmation page shown → notification sent.

### 6.4 — Guest Checkout (1–2 days)

| # | Task | Files |
|---|------|-------|
| 6.4.1 | Allow checkout without login: collect name + email + phone on checkout form | `Checkout.tsx` |
| 6.4.2 | If no logged-in user, create Order with `customer_email`, `customer_name`, `customer_phone` fields (no user_id) | `OrdersController::placeOrder()` |
| 6.4.3 | Ignore notifying user via in_app if no user_id (email-only for guests) | `OrdersController.php` |

**Verification**: guest checkout → order created with customer_email, no user_id → no 500 error.

### 6.5 — Manual Payment Confirmation (1 day)

| # | Task | Files |
|---|------|-------|
| 6.5.1 | Create payment method config in `ShopModule::getConfig()`: `['manual']`, define label + instructions | `ShopModule.php` |
| 6.5.2 | On order with manual payment, show payment instructions on confirmation page | `Confirmation.tsx` |
| 6.5.3 | Admin can mark payment as received from order show page → sets `paid_at`, `payment_status: paid` | `OrdersController.php`, `Orders/Show.tsx` |

**Verification**: place order with manual → instructions shown → admin clicks "Mark Paid" → payment_status changes.

### Files Summary (Phase 6)

```
NEW:
  app/Modules/Shop/Resources/js/Pages/Cart/Index.tsx
  app/Modules/Shop/Resources/js/Pages/Orders/Checkout.tsx
  app/Modules/Shop/Resources/js/Pages/Orders/Confirmation.tsx
  app/Modules/Shop/Models/ShippingMethod.php
  app/Modules/Shop/database/migrations/*_create_shipping_methods_table.php

MODIFIED:
  app/Modules/Shop/ShopModule.php         # nav items, frontend pages, config
  app/Modules/Shop/Routes/web.php              # checkout, place-order, payment routes
  app/Modules/Shop/Http/Controllers/OrdersController.php  # checkout, placeOrder, markPaid
  app/Modules/Shop/Http/Controllers/CartController.php    # clearCart on checkout
```

### Verification
- Cart page renders with items, quantity +/- works
- Checkout page shows order summary + address forms
- Place order creates Order + OrderItems, clears cart
- Confirmation page shows order details
- Guest checkout works without login
- Admin can mark manual payment as received

---

## Phase 7 — Admin Order Management (5–7 days)

Operators need to create/edit orders manually for phone/email orders and handle refunds.

### 7.1 — Admin Create Order (2 days)

| # | Task | Files |
|---|------|-------|
| 7.1.1 | Create `OrdersController::create()` — renders order form page | `OrdersController.php` |
| 7.1.2 | Create `OrdersController::storeAdmin()` — create order manually with product selection, quantity, customer lookup, address | `OrdersController.php` |
| 7.1.3 | Create `Shop/Orders/Form.tsx` — product search/select (with price + SKU), quantity input, customer select/autocomplete, billing/shipping address fields, line items table with subtotals, order total, notes field | `Resources/js/Pages/Orders/Form.tsx` (NEW) |
| 7.1.4 | Register route + frontend page | `Routes/web.php`, `ShopModule.php` |
| 7.1.5 | Nav item "Create Order" under Shop | `ShopModule.php` |
| 7.1.6 | Stock deduction on manual order creation, low stock check | `OrdersController` |

**Verification**: admin creates order → order in DB → product stock deducted → customer can see it in their orders.

### 7.2 — Order Edit (1–2 days)

| # | Task | Files |
|---|------|-------|
| 7.2.1 | Create `OrdersController::edit()` — load order with items | `OrdersController.php` |
| 7.2.2 | Create `OrdersController::updateAdmin()` — update line items (add/remove/change qty), recalculate totals, update addresses, update notes | `OrdersController.php` |
| 7.2.3 | Reuse `Orders/Form.tsx` for edit mode (like Products/Form does) | `Form.tsx`, `ShopModule.php` |
| 7.2.4 | Stock reconciliation on order edit (restore old qty, deduct new qty) | `OrdersController::updateAdmin()` |
| 7.2.5 | "Edit" button on order show page | `Orders/Show.tsx` |

**Verification**: edit order → line items update → stock recalculation correct → total recalculated.

### 7.3 — Refund Flow (1–2 days)

| # | Task | Files |
|---|------|-------|
| 7.3.1 | Create `OrdersController::refund()` — validate status is `completed` or `processing`, set status to `refunded`, restore stock, set `refunded_at` + `refund_amount` | `OrdersController.php` |
| 7.3.2 | Add `refunded_at` (nullable timestamp) + `refund_amount` (decimal) columns to orders table | Migration (NEW) |
| 7.3.3 | Add `refund_notes` column (nullable text) | Migration (NEW) |
| 7.3.4 | "Issue Refund" button on order show page with amount + reason dialog | `Orders/Show.tsx` |
| 7.3.5 | Dispatch `order.status_changed` notification on refund | `OrdersController` |
| 7.3.6 | Partial refund: allow entering custom refund amount < total, add refund method to track remaining balance | `OrdersController`, migration |

**Verification**: refund order → status changes → stock restored → notification sent → refunded_at set.

### 7.4 — Order Invoice View (1 day)

| # | Task | Files |
|---|------|-------|
| 7.4.1 | Add printable invoice section to `Orders/Show.tsx` with print CSS | `Orders/Show.tsx` |
| 7.4.2 | Invoice template: order number, date, customer info, billing/shipping address, line items table, totals, payment status | `Orders/Show.tsx` |
| 7.4.3 | "Print Invoice" button → `window.print()` | `Orders/Show.tsx` |
| 7.4.4 | Create `OrdersController::invoice()` → dedicated PDF view using Laravel's Blade? Or keep it simple with printable HTML | `OrdersController.php` (optional) |

**Verification**: click "Print Invoice" → printable layout appears with correct data.

### 7.5 — Order Activity Timeline (1 day)

| # | Task | Files |
|---|------|-------|
| 7.5.1 | Create `OrderAction` model + migration (order_id, action_type, old_value, new_value, user_id, notes) | `Models/OrderAction.php`, migration (NEW) |
| 7.5.2 | Create `OrderActionService` — log order status changes, refunds, edits, payments | `Services/OrderActionService.php` (NEW) |
| 7.5.3 | Wire logging into `OrdersController::updateStatus()`, `refund()`, `updateAdmin()`, markPaid() | `OrdersController.php` |
| 7.5.4 | Add timeline component to `Orders/Show.tsx` below order summary | `Orders/Show.tsx` |

**Verification**: change order status → timeline shows the change with timestamp + user.

### Files Summary (Phase 7)

```
NEW:
  app/Modules/Shop/Resources/js/Pages/Orders/Form.tsx
  app/Modules/Shop/Models/OrderAction.php
  app/Modules/Shop/Services/OrderActionService.php
  app/Modules/Shop/database/migrations/*_add_refund_columns_to_orders.php
  app/Modules/Shop/database/migrations/*_create_order_actions_table.php

MODIFIED:
  app/Modules/Shop/ShopModule.php
  app/Modules/Shop/Routes/web.php
  app/Modules/Shop/Http/Controllers/OrdersController.php
  app/Modules/Shop/Resources/js/Pages/Orders/Show.tsx
```

### Verification
- Admin can create order from scratch with product search + customer lookup
- Admin can edit existing orders with stock reconciliation
- Refund flow works (full + partial)
- Invoice view prints correctly
- Activity timeline logs all order changes

---

## Phase 8 — Email Templates & Notifications (3–5 days)

Notification types like `order.confirmation` and `order.shipped` are registered but blade templates may be missing or minimal. Wire up the full notification pipeline.

### 8.1 — Audit Existing Templates (0.5 day)

| # | Task | Files |
|---|------|-------|
| 8.1.1 | List all `email_template` values from `ShopModule`, `BookingModule`, `PropertiesModule` | grep across modules |
| 8.1.2 | Check which blade templates exist in `resources/views/email/` | glob |
| 8.1.3 | Report gaps: missing templates, incomplete templates | output |

### 8.2 — Create Missing Templates (2 days)

| # | Task | Files |
|---|------|-------|
| 8.2.1 | `resources/views/email/order-confirmation.blade.php` — order number, items table, totals, customer info, payment instructions | NEW |
| 8.2.2 | `resources/views/email/order-shipped.blade.php` — tracking info, shipped items, estimated arrival | NEW |
| 8.2.3 | Ensure all templates extend correct layout, use `SettingsService` for branding | Each template |
| 8.2.4 | Wire `MailService::send()` — verify it passes correct view data from notification | `MailService.php` review |

**Verification**: trigger test notification with email → email sent with correct HTML rendering.

### 8.3 — Wire up order.placed Event (1 day)

| # | Task | Files |
|---|------|-------|
| 8.3.1 | In `placeOrder()` (Phase 6), dispatch `order.placed` notification to customer + admins | `OrdersController::placeOrder()` |
| 8.3.2 | For guest checkouts, use email-only channel (no in_app) | `OrdersController` |
| 8.3.3 | Notification payload includes: orderNumber, total, items summary, link to order | `AppNotification` data |

### 8.4 — Template Preview & Test (1 day)

| # | Task | Files |
|---|------|-------|
| 8.4.1 | Extend existing `TemplateSimulationSection` with Shop templates if missing | `TemplateSimulationSection.tsx` |
| 8.4.2 | Verify "Send Test" from `/dashboard/notifications/test` works with Shop templates | `NotificationTestController` |
| 8.4.3 | Add Shop-specific test data (mock order, products) for preview | `NotificationTestController.php` |

### Files Summary (Phase 8)

```
NEW:
  resources/views/email/order-confirmation.blade.php
  resources/views/email/order-shipped.blade.php

MODIFIED:
  resources/js/Components/settings/sections/TemplateSimulationSection.tsx
  app/Http/Controllers/NotificationTestController.php
  app/Modules/Shop/Http/Controllers/OrdersController.php  # wire up order.placed
```

### Verification
- Trigger notification with email channel → email received with correct HTML
- Template preview renders correctly in admin
- order.placed fires on checkout

---

## Phase 9 — Discounts & Coupons (5–7 days)

### 9.1 — Coupon Model + Migration (1 day)

| # | Task | Files |
|---|------|-------|
| 9.1.1 | Create `Coupon` model: `code` (unique), `type` (percentage/fixed), `value` (decimal), `min_order_amount`, `max_discount`, `usage_limit`, `per_user_limit`, `used_count`, `is_active`, `starts_at`, `expires_at`, `created_by` | `Models/Coupon.php`, migration (NEW) |

### 9.2 — Coupon CRUD (Admin) (2 days)

| # | Task | Files |
|---|------|-------|
| 9.2.1 | `CouponsController` (index, store, update, destroy, toggle active) | `Controllers/CouponsController.php` (NEW) |
| 9.2.2 | `Coupons/Index.tsx` — table with code, type, value, usage, status, dates, actions | `Resources/js/Pages/Coupons/Index.tsx` (NEW) |
| 9.2.3 | `Coupons/Form.tsx` — create/edit with type select, value, restrictions, date range picker | `Resources/js/Pages/Coupons/Form.tsx` (NEW) |
| 9.2.4 | Routes + nav item + frontend page registration | `Routes/web.php`, `ShopModule.php` |
| 9.2.5 | Coupon `product_id` BelongsToMany (apply to specific products) + pivot table | Migration (NEW) |

### 9.3 — Coupon Validation + Application (2 days)

| # | Task | Files |
|---|------|-------|
| 9.3.1 | `CouponService::validate()` — check active, not expired, not exceeded limits, min order amount | `Services/CouponService.php` (NEW) |
| 9.3.2 | `CouponService::apply()` — calculate discount, store coupon_id on order, increment used_count | `CouponService.php` |
| 9.3.3 | Coupon input + apply button on cart page | `Cart/Index.tsx` |
| 9.3.4 | Discount line item on checkout summary | `Checkout.tsx` |
| 9.3.5 | Validation errors (expired, limit reached, min amount not met) shown inline | `Cart/Index.tsx` |
| 9.3.6 | Store `coupon_id` + `discount_total` on Order during checkout | `OrdersController::placeOrder()` |

### 9.4 — Usage Tracking + Admin Reports (1 day)

| # | Task | Files |
|---|------|-------|
| 9.4.1 | `CouponUsage` model + migration (coupon_id, order_id, user_id, discount_amount) | `Models/CouponUsage.php`, migration (NEW) |
| 9.4.2 | Track usage in `CouponService::apply()` | `CouponService.php` |
| 9.4.3 | Add coupon usage stats to coupon edit page (times used, total discount given, last used) | `Coupons/Form.tsx` |
| 9.4.4 | Per-user usage limit enforcement | `CouponService::validate()` |

### Files Summary (Phase 9)

```
NEW:
  app/Modules/Shop/Models/Coupon.php
  app/Modules/Shop/Models/CouponUsage.php
  app/Modules/Shop/Services/CouponService.php
  app/Modules/Shop/Http/Controllers/CouponsController.php
  app/Modules/Shop/Resources/js/Pages/Coupons/Index.tsx
  app/Modules/Shop/Resources/js/Pages/Coupons/Form.tsx
  app/Modules/Shop/database/migrations/*_create_coupons_table.php
  app/Modules/Shop/database/migrations/*_create_coupon_usages_table.php
  app/Modules/Shop/database/migrations/*_create_coupon_product_pivot_table.php

MODIFIED:
  app/Modules/Shop/ShopModule.php
  app/Modules/Shop/Routes/web.php
  app/Modules/Shop/Resources/js/Pages/Cart/Index.tsx
  app/Modules/Shop/Resources/js/Pages/Orders/Checkout.tsx
  app/Modules/Shop/Http/Controllers/OrdersController.php
  app/Modules/Shop/Models/Order.php  # coupon_id + discount_total fillable
```

### Verification
- Admin creates coupon → shows on coupons list
- Apply valid coupon on cart → discount reflected
- Apply expired/exhausted coupon → error message shown
- Coupon applied to order → coupon_id + discount_total saved
- Usage limits enforced per-user and globally

---

## Phase 10 — Product Reviews & Ratings (4–5 days)

### 10.1 — Review Model + Migration (1 day)

| # | Task | Files |
|---|------|-------|
| 10.1.1 | Create `ProductReview` model: `product_id`, `user_id`, `order_id` (nullable, verified purchase), `rating` (1-5), `title`, `content`, `is_approved`, `approved_at`, `approved_by` | `Models/ProductReview.php`, migration (NEW) |
| 10.1.2 | Product hasMany reviews, Review belongsTo product + user | `Product.php`, `ProductReview.php` |
| 10.1.3 | Add `average_rating` + `reviews_count` cached columns to products (updated on review save/delete) | Migration + `ProductReview` boot events |

### 10.2 — Frontend Review Form (1 day)

| # | Task | Files |
|---|------|-------|
| 10.2.1 | Add review form to `Products/Show.tsx` or create dedicated section | `Products/Show.tsx` (or NEW `Products/Reviews.tsx`) |
| 10.2.2 | Star rating input component (clickable stars, half-star support) | NEW component |
| 10.2.3 | Review list below form: rating stars, title, content, date, customer name | `Products/Show.tsx` |
| 10.2.4 | "No reviews yet" empty state | `Products/Show.tsx` |
| 10.2.5 | Only allow one review per user per product | `ProductReview` validation |

### 10.3 — Admin Moderation (1–2 days)

| # | Task | Files |
|---|------|-------|
| 10.3.1 | `ReviewsController` (admin) — index (pending/approved/rejected), approve, reject, delete | `Controllers/ReviewsController.php` (NEW) |
| 10.3.2 | `Reviews/Index.tsx` — table with rating stars, product name, author, date, status, actions | `Resources/js/Pages/Reviews/Index.tsx` (NEW) |
| 10.3.3 | Approve/reject buttons inline in table row | `Reviews/Index.tsx` |
| 10.3.4 | Nav item under Shop | `ShopModule.php` |
| 10.3.5 | Routes + frontend page registration | `Routes/web.php`, `ShopModule.php` |

### 10.4 — Verified Purchase Check (1 day)

| # | Task | Files |
|---|------|-------|
| 10.4.1 | When submitting review, check if user has a completed order containing this product | `ReviewsController::store()` |
| 10.4.2 | Show "Verified Purchase" badge on approved reviews from confirmed buyers | `Products/Show.tsx` |
| 10.4.3 | Allow reviews only from users with at least one completed order containing the product | Optional gate |

### Files Summary (Phase 10)

```
NEW:
  app/Modules/Shop/Models/ProductReview.php
  app/Modules/Shop/Http/Controllers/ReviewsController.php
  app/Modules/Shop/Resources/js/Pages/Reviews/Index.tsx
  app/Modules/Shop/database/migrations/*_create_product_reviews_table.php
  app/Modules/Shop/database/migrations/*_add_rating_columns_to_products.php
  resources/js/Components/shop/StarRating.tsx (NEW)

MODIFIED:
  app/Modules/Shop/ShopModule.php
  app/Modules/Shop/Routes/web.php
  app/Modules/Shop/Models/Product.php  # reviews() + average_rating + reviews_count
  app/Modules/Shop/Resources/js/Pages/Products/Show.tsx  # review form + list
```

### Verification
- Authenticated user submits review on product → pending moderation
- Admin approves/rejects on reviews page
- Approved review shows on product page with "Verified Purchase" if applicable
- Star rating input works (click to select, hover preview)
- Product average_rating + reviews_count update automatically

---

## Phase 11 — Shipping & Taxes (5–7 days)

### 11.1 — Shipping Methods CRUD (2 days)

| # | Task | Files |
|---|------|-------|
| 11.1.1 | `ShippingMethod` model: `name`, `description`, `price` (decimal), `free_above` (nullable decimal), `estimated_days_min`, `estimated_days_max`, `is_active`, `sort_order` | Already started in 6.2.5 |
| 11.1.2 | `ShippingMethodsController` (CRUD) | `Controllers/ShippingMethodsController.php` (NEW) |
| 11.1.3 | `ShippingMethods/Index.tsx` — table with name, price, free_above, estimate, toggle | `Resources/js/Pages/ShippingMethods/Index.tsx` (NEW) |
| 11.1.4 | Routes + nav item + frontend page | `Routes/web.php`, `ShopModule.php` |

### 11.2 — Shipping Zone Configuration (2 days)

| # | Task | Files |
|---|------|-------|
| 11.2.1 | `ShippingZone` model: `name`, `countries` (JSON array of country codes), `regions` (nullable JSON), `is_active` | `Models/ShippingZone.php`, migration (NEW) |
| 11.2.2 | Pivot `shipping_zone_method` (zone_id, method_id, override_price) | Migration (NEW) |
| 11.2.3 | `ShippingZonesController` (CRUD) | `Controllers/ShippingZonesController.php` (NEW) |
| 11.2.4 | `ShippingZones/Index.tsx` — table with countries, linked methods | `Resources/js/Pages/ShippingZones/Index.tsx` (NEW) |
| 11.2.5 | `ShippingZones/Form.tsx` — multi-select countries, assign methods with override prices | `Resources/js/Pages/ShippingZones/Form.tsx` (NEW) |

### 11.3 — Checkout Shipping Selection (1 day)

| # | Task | Files |
|---|------|-------|
| 11.3.1 | `ShippingService::getAvailableMethods(billing_address)` — match zone by country, return methods with prices | `Services/ShippingService.php` (NEW) |
| 11.3.2 | Radio button selector on checkout for available shipping methods | `Checkout.tsx` |
| 11.3.3 | Save `shipping_method_id` + `shipping_total` on order during checkout | `OrdersController::placeOrder()` |

### 11.4 — Tax Rates (1–2 days)

| # | Task | Files |
|---|------|-------|
| 11.4.1 | `TaxRate` model: `name`, `rate` (decimal, e.g., 20.00 for 20%), `type` (percentage/fixed), `country`, `state` (nullable), `is_active`, `is_default` | `Models/TaxRate.php`, migration (NEW) |
| 11.4.2 | `TaxRatesController` (CRUD) | `Controllers/TaxRatesController.php` (NEW) |
| 11.4.3 | `TaxRates/Index.tsx` — table with rate, country, status | `Resources/js/Pages/TaxRates/Index.tsx` (NEW) |
| 11.4.4 | `TaxService::calculate(order_subtotal, billing_address)` — apply matching tax rates | `Services/TaxService.php` (NEW) |
| 11.4.5 | Save `tax_total` on order during checkout | `OrdersController::placeOrder()` |
| 11.4.6 | "Is Taxable" per-product toggle already exists — use it in TaxService | `TaxService.php` |

### Files Summary (Phase 11)

```
NEW:
  app/Modules/Shop/Models/ShippingMethod.php (if not done in 6.2.5)
  app/Modules/Shop/Models/ShippingZone.php
  app/Modules/Shop/Models/TaxRate.php
  app/Modules/Shop/Services/ShippingService.php
  app/Modules/Shop/Services/TaxService.php
  app/Modules/Shop/Http/Controllers/ShippingMethodsController.php
  app/Modules/Shop/Http/Controllers/ShippingZonesController.php
  app/Modules/Shop/Http/Controllers/TaxRatesController.php
  app/Modules/Shop/Resources/js/Pages/ShippingMethods/Index.tsx
  app/Modules/Shop/Resources/js/Pages/ShippingZones/Index.tsx
  app/Modules/Shop/Resources/js/Pages/ShippingZones/Form.tsx
  app/Modules/Shop/Resources/js/Pages/TaxRates/Index.tsx
  app/Modules/Shop/database/migrations/*_create_shipping_zones_table.php
  app/Modules/Shop/database/migrations/*_create_shipping_zone_method_table.php
  app/Modules/Shop/database/migrations/*_create_tax_rates_table.php

MODIFIED:
  app/Modules/Shop/ShopModule.php
  app/Modules/Shop/Routes/web.php
  app/Modules/Shop/Models/Order.php  # shipping_method_id, shipping_total, tax_total
  app/Modules/Shop/Http/Controllers/OrdersController.php  # save shipping + tax
  app/Modules/Shop/Resources/js/Pages/Orders/Checkout.tsx  # shipping method selector
```

### Verification
- Admin creates shipping methods + zones with country mapping
- Checkout shows available methods based on billing address country
- Selected shipping method saved on order
- Admin creates tax rates → applied on checkout
- Tax + shipping totals correctly stored on order

---

## Phase 12 — CSV Import/Export (3–5 days)

### 12.1 — Product Export (1 day)

| # | Task | Files |
|---|------|-------|
| 12.1.1 | `ProductsController::export()` — stream CSV response with all product columns + categories + tags | `ProductsController.php` |
| 12.1.2 | Columns: name, slug, description, price, compare_price, cost_price, sku, barcode, stock_quantity, low_stock_threshold, track_stock, status, type, weight, categories (pipe-separated), tags (pipe-separated) | Same |
| 12.1.3 | "Export" button on products index page → downloads CSV | `Products/Index.tsx` |
| 12.1.4 | Route: `GET /products/export` | `Routes/web.php` |

### 12.2 — Product Import (2 days)

| # | Task | Files |
|---|------|-------|
| 12.2.1 | `ProductsController::import()` — parse uploaded CSV, validate rows, create/update products by SKU | `ProductsController.php` |
| 12.2.2 | `ImportProductRequest` — validate file mime type, max size, required columns | `Requests/ImportProductRequest.php` (NEW) |
| 12.2.3 | Queue import for large files via Laravel job | `Jobs/ImportProductsJob.php` (NEW) |
| 12.2.4 | Import results page: success count, error rows with messages, skipped duplicates | `Products/ImportResult.tsx` or inline flash |
| 12.2.5 | "Import" button + file picker on products index page | `Products/Index.tsx` |
| 12.2.6 | Route: `POST /products/import` | `Routes/web.php` |

### 12.3 — Order Export (1 day)

| # | Task | Files |
|---|------|-------|
| 12.3.1 | `OrdersController::export()` — stream CSV with order fields + line items | `OrdersController.php` |
| 12.3.2 | Columns: order_number, status, customer_name, customer_email, subtotal, shipping_total, tax_total, discount_total, total, payment_status, items (JSON or pipe-separated), created_at | Same |
| 12.3.3 | "Export" button on orders index | `Orders/Index.tsx` |
| 12.3.4 | Route: `GET /orders/export` | `Routes/web.php` |

### 12.4 — Customer Export (0.5 day)

| # | Task | Files |
|---|------|-------|
| 12.4.1 | `CustomersController::export()` — stream CSV with customer data + order stats | `CustomersController.php` |
| 12.4.2 | "Export" button on customers index | `Customers/Index.tsx` |
| 12.4.3 | Route: `GET /customers/export` | `Routes/web.php` |

### 12.5 — Import Progress UI (0.5 day)

| # | Task | Files |
|---|------|-------|
| 12.5.1 | Show import progress bar for queued imports using polling | `Products/Index.tsx` |
| 12.5.2 | Store import result in session or cache for display after redirect | `ProductsController::import()` |

### Files Summary (Phase 12)

```
NEW:
  app/Modules/Shop/Http/Requests/ImportProductRequest.php
  app/Modules/Shop/Jobs/ImportProductsJob.php
  resources/js/Pages/Products/ImportResult.tsx (optional)

MODIFIED:
  app/Modules/Shop/ShopModule.php
  app/Modules/Shop/Routes/web.php
  app/Modules/Shop/Http/Controllers/ProductsController.php
  app/Modules/Shop/Http/Controllers/OrdersController.php
  app/Modules/Shop/Http/Controllers/CustomersController.php
  app/Modules/Shop/Resources/js/Pages/Products/Index.tsx
  app/Modules/Shop/Resources/js/Pages/Orders/Index.tsx
  app/Modules/Shop/Resources/js/Pages/Customers/Index.tsx
```

### Verification
- Export products → valid CSV with correct columns
- Import CSV → products created/updated in DB
- Import with errors → error report shown, valid rows still imported
- Export orders → valid CSV
- Export customers → valid CSV

---

## Phase 13 — Posts Module Expansion (5–7 days)

### 13.1 — AJAX Image Upload for Posts (1 day)

| # | Task | Files |
|---|------|-------|
| 13.1.1 | Create `PostsController::uploadImage()` — handle file upload, store to `posts` disk, return JSON URL | `Controllers/PostsController.php` |
| 13.1.2 | Add image upload button in post form with preview + drag-and-drop zone | `Form.tsx` |
| 13.1.3 | Route: `POST /posts/upload-image` | `Routes/web.php` |

### 13.2 — Full Taxonomy UI (2 days)

| # | Task | Files |
|---|------|-------|
| 13.2.1 | Create `CategoriesController.php` for post categories (if not exists) | `Controllers/CategoriesController.php` (NEW) |
| 13.2.2 | Create `TagsController.php` for post tags | `Controllers/TagsController.php` (NEW) |
| 13.2.3 | `Categories/Index.tsx` — table with name, slug, post count, parent, actions | `Resources/js/Pages/Categories/Index.tsx` (NEW) |
| 13.2.4 | `Tags/Index.tsx` — table with name, slug, post count, actions | `Resources/js/Pages/Tags/Index.tsx` (NEW) |
| 13.2.5 | Nav items under Posts sidebar | `PostsModule.php` |
| 13.2.6 | Routes + frontend page registration | `Routes/web.php`, `PostsModule.php` |
| 13.2.7 | Category/tag selectors in post form | `Form.tsx` |

### 13.3 — Featured Image + Gallery (1 day)

| # | Task | Files |
|---|------|-------|
| 13.3.1 | Add `featured_image` column to posts table (string, nullable) | Migration (NEW) |
| 13.3.2 | Add `gallery` JSON column to posts (array of image URLs) + casts | Migration (NEW) |
| 13.3.3 | Featured image picker in post form (upload + preview + remove) | `Form.tsx` |
| 13.3.4 | Gallery uploader: add multiple images, reorder, remove | `Form.tsx` |
| 13.3.5 | Show featured image + gallery in post show/index pages | `Index.tsx`, `Show.tsx` |

### 13.4 — Scheduled Publishing (1 day)

| # | Task | Files |
|---|------|-------|
| 13.4.1 | Add `published_at` column to posts (nullable timestamp) | Migration (NEW) |
| 13.4.2 | Add `PublishScheduledPosts` artisan command (pattern: `PublishScheduledProperties` already exists) | `Commands/PublishScheduledPosts.php` (NEW) |
| 13.4.3 | Register command in `routes/console.php` (every minute) | `routes/console.php` |
| 13.4.4 | Post form: schedule publish date/time picker + "Publish" vs "Schedule" toggle | `Form.tsx` |
| 13.4.5 | Add `published` / `scheduled` / `draft` status scopes | `Post.php` model |

### 13.5 — Post Settings (1 day)

| # | Task | Files |
|---|------|-------|
| 13.5.1 | `PostsModule::getSettingsRoute()` — point to admin settings tab | `PostsModule.php` |
| 13.5.2 | Settings fields: posts per page, default post status, enable comments, allow guest comments | `PostsModule::getProfileFieldConfigs()` |

### Files Summary (Phase 13)

```
NEW:
  app/Modules/Posts/Http/Controllers/CategoriesController.php
  app/Modules/Posts/Http/Controllers/TagsController.php
  app/Modules/Posts/Resources/js/Pages/Categories/Index.tsx
  app/Modules/Posts/Resources/js/Pages/Tags/Index.tsx
  app/Modules/Posts/Console/Commands/PublishScheduledPosts.php
  app/Modules/Posts/database/migrations/*_add_image_columns_to_posts.php
  app/Modules/Posts/database/migrations/*_add_published_at_to_posts.php

MODIFIED:
  app/Modules/Posts/PostsModule.php  # nav items, frontend pages, settings
  app/Modules/Posts/Routes/web.php  # taxonomy routes, image upload
  app/Modules/Posts/Http/Controllers/PostsController.php  # uploadImage
  app/Modules/Posts/Resources/js/Pages/Posts/Form.tsx  # image upload, featured, gallery, schedule
  app/Modules/Posts/Resources/js/Pages/Posts/Index.tsx  # featured image display
  app/Modules/Posts/Resources/js/Pages/Posts/Show.tsx   # featured image + gallery
  routes/console.php  # schedule command
```

### Verification
- AJAX image upload works in post form
- Category/tag CRUD works with post counts
- Featured image + gallery saved and displayed
- Scheduled post auto-publishes at published_at time
- Post settings appear in admin settings page

---

## Phase 14 — Inventory Log (3–4 days)

### 14.1 — Stock Adjustment Model + Migration (1 day)

| # | Task | Files |
|---|------|-------|
| 14.1.1 | `StockAdjustment` model: `product_id`, `variation_id` (nullable), `type` (manual/sale/return/correction/refund), `quantity` (integer, signed — negative for reduction), `stock_before`, `stock_after`, `reason`, `reference_type` (nullable — order/adjustment), `reference_id` (nullable), `user_id` | `Models/StockAdjustment.php`, migration (NEW) |

### 14.2 — Stock Adjustment Tracking (1 day)

| # | Task | Files |
|---|------|-------|
| 14.2.1 | Create `InventoryService::adjust(product, type, quantity, reason, reference)` — creates StockAdjustment, updates product stock_quantity | `Services/InventoryService.php` (NEW) |
| 14.2.2 | Wire into existing flows: order creation (sale), order refund (refund), manual admin adjustment (manual), order edit (correction) | `OrdersController`, `ProductsController` |
| 14.2.3 | Use `InventoryService` in `OrdersController::placeOrder()` instead of manual deduction | `OrdersController.php` |
| 14.2.4 | Use `InventoryService` in `ProductsController::update()` if stock changes | `ProductsController.php` |

### 14.3 — Admin Stock UI (1 day)

| # | Task | Files |
|---|------|-------|
| 14.3.1 | Add "Stock History" tab/section to product edit page | `Products/Form.tsx` |
| 14.3.2 | `ProductsController::stockHistory(product)` — return paginated stock adjustments | `ProductsController.php` |
| 14.3.3 | Stock history table: date, type, qty change, before/after, reason, user | NEW component or inline |
| 14.3.4 | "Adjust Stock" button with dialog: type select (manual/correction), qty input, reason textarea | `Products/Form.tsx` |
| 14.3.5 | Route: `POST /products/{product}/adjust-stock` | `Routes/web.php` |

### 14.4 — Low Stock Alert Improvements (0.5 day)

| # | Task | Files |
|---|------|-------|
| 14.4.1 | Add configured `low_stock_email` setting (email address to receive low stock alerts) | `ShopModule::getConfig()` |
| 14.4.2 | BCC admins in low stock notification | `ProductsController::notifyLowStock()` |

### Files Summary (Phase 14)

```
NEW:
  app/Modules/Shop/Models/StockAdjustment.php
  app/Modules/Shop/Services/InventoryService.php
  app/Modules/Shop/database/migrations/*_create_stock_adjustments_table.php

MODIFIED:
  app/Modules/Shop/ShopModule.php  # low_stock_email config
  app/Modules/Shop/Routes/web.php  # adjust-stock route
  app/Modules/Shop/Http/Controllers/OrdersController.php  # use InventoryService
  app/Modules/Shop/Http/Controllers/ProductsController.php  # use InventoryService, stockHistory
  app/Modules/Shop/Resources/js/Pages/Products/Form.tsx  # stock history + adjust dialog
```

### Verification
- Adjust stock from product edit → StockAdjustment created → product stock updated
- Order placed → Sale-type adjustment created → stock deducted
- Order refunded → Refund-type adjustment created → stock restored
- Stock history shows complete timeline
- Low stock check fires correctly after adjustments

---

## Summary

| Phase | Focus | Duration | Dependencies |
|-------|-------|----------|--------------|
| 6 | Checkout Flow | 7–10 days | Phase 5 (done) |
| 7 | Admin Order Management | 5–7 days | Phase 6 (needs placeOrder) |
| 8 | Email Templates | 3–5 days | Phase 6 (needs order.placed) |
| 9 | Discounts & Coupons | 5–7 days | Phase 6 (needs checkout) |
| 10 | Product Reviews | 4–5 days | Phase 5 (needs products) |
| 11 | Shipping & Taxes | 5–7 days | Phase 6 (needs checkout) |
| 12 | CSV Import/Export | 3–5 days | Phase 5 (needs CRUD) |
| 13 | Posts Module Expansion | 5–7 days | Independent |
| 14 | Inventory Log | 3–4 days | Phase 5 (needs products) |

**Total estimated: 40–57 days**

Ordered by dependency: 6 → 7 → 8 → 9 → 11 are a chain (each depends on the previous). 10, 12, 13, 14 can run independently or in parallel.
