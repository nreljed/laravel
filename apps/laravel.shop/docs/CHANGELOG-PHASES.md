# CHANGELOG — Phase History

> Historical, phase-by-phase implementation notes originally embedded in AGENTS.md.
> These record what shipped when. For CURRENT architecture and conventions, see AGENTS.md.
> Note: many file paths below reference the legacy `app/Modules/*`, `app/Shop/`, `app/Blog/`
> trees that no longer exist in the host — they live in the nreljed/dashboard-core and
> nreljed/shop-module packages now.

## Recent Changes (Phase 3 — Webhooks)

### Phase 3.1 — Webhook Model & Migration
- `Webhook` model — `url`, `secret`, `events` (JSON array of event types), `is_active`, `last_sent_at`, `last_response_code`, `last_error`, `failure_count`.
- Migration `create_webhooks_table` with all fields + timestamps.
- `WebhookLog` model — `webhook_id`, `event`, `payload` (JSON), `response_code`, `response_body`, `success`, `duration_ms`.
- Migration `create_webhook_logs_table` with foreign key to webhooks.

### Phase 3.2 — WebhookService
- `WebhookService::dispatch(string $event, array $payload): void` — finds all active webhooks subscribed to the event, dispatches each to a queued job `WebhookSendJob`.
- `WebhookSendJob` — implements `ShouldQueue`, sends POST with HMAC-SHA256 signature in `X-Webhook-Signature` header, logs result to `WebhookLog`, increments `failure_count` on non-2xx, auto-deactivates after 10 consecutive failures.
- Implements `ShouldBeUniqueUntilProcessing` to prevent duplicate sends.

### Phase 3.3 — Controllers
- `WebhooksController` — full CRUD (index, store, show, update, destroy) + `test()` endpoint that fires a `webhook.test` event for manual testing. Authorized via `shop.webhooks.manage` permission.
- `StripeWebhookController` — `handle()` method verifies Stripe signature, handles `checkout.session.completed` and `charge.refunded`, fires internal `order.placed` / `order.refunded` events.

### Phase 3.4 — Event Types
- Registered events: `order.placed`, `order.updated`, `order.cancelled`, `order.refunded`, `order.status_changed`, `product.created`, `product.updated`, `product.deleted`, `customer.created`, `customer.updated`, `webhook.test`
- Each fired from appropriate controller action (OrdersController, ProductsController, CustomersController, WebhooksController).

### Phase 3.5 — Routes & Frontend
- Routes: `dashboard.shop.webhooks.*` (CRUD), `POST .../webhooks/{webhook}/test`, `POST stripe/webhook` (no CSRF/middleware).
- Frontend: `Webhooks/Index.tsx` — table with toggle active, test button, copy URL, copy secret, delete dialog.
- Nav item registered in `ShopModule`.

### Phase 3.6 — Tests
- `ApiAndWebhookTest.php` — 17 tests covering webhook CRUD, dispatch, test endpoint, Stripe signature verification, Storefront index/show/hide, Sanctum token management, API products listing.
- Tests use `Http::fake()` to prevent actual outbound requests.

## Recent Changes (Phase 4 — Storefront)

### Phase 4.1 — Controller + Routes
- `StorefrontController` — `index()` (paginated product grid with search, category filter, min/max price, sort options), `show()` (product detail with categories, reviews, user review), `category()` (products filtered by category slug).
- Routes prefixed with `/store` (guest-accessible), loaded via new `loadPublicRoutes()` on `HasRoutes` trait.
- `ShopModule::routes()` — registers `Shop/Routes/public-store.php` with `'store.'` route name prefix.

### Phase 4.2 — Product Grid Page
- `Storefront/Products/Index.tsx` — product grid (responsive: 1→2→3→4 columns), product cards with image, name, price, rating stars, category badge. Search input, category filter chips, price range, sort dropdown. Empty state with "No products found" illustration.
- Uses `ProductCard` inline component with hover scale effect and category badge.

### Phase 4.3 — Product Detail Page
- `Storefront/Products/Show.tsx` — product image + details layout, price display with stock status, category badge, reviews section (star rating + list), "Add to Cart" button placeholder.
- Responsive: image above content on mobile, side-by-side on desktop.

### Phase 4.4 — Category Browsing
- `StorefrontController::category()` — filters products by category slug via `whereHas('categories')`.
- Frontend: `usePage().props.categories` shared for filter chips.

### Phase 4.5 — Layout
- `StoreLayout.tsx` — minimal guest layout with "Store" header link to `/store`, cart icon placeholder.
- `app.tsx` updated with guest store routes pattern for glob import.

### Phase 4.6 — Guest Cart & Checkout Fixes
- **Created `StoreLayout.tsx`** — dedicated guest store layout with sticky header, cart badge (with item count), mobile hamburger menu, and sign-in link. Replaces `PublicLayout` for storefront pages.
- **Created public cart routes** (`store-cart.php`) — mirrors all `CartController` methods (addItem, updateItem, removeItem, clear, applyCoupon, removeCoupon) under `/store/cart/` with `store.cart.*` route names. No auth middleware required.
- **Created `Storefront/ShoppingCart.tsx`** — guest cart page using `StoreLayout` with quantity controls, coupon input, order summary, and "Proceed to Checkout" link to the existing guest checkout flow.
- **Fixed "Add to Cart" button** on product detail page — route name changed from `cart.add-item` (non-existent public route) to `store.cart.add-item`.
- **Registered guest cart pages** in `ShopModule::getFrontendPages()` and `routes()`.
- **Wired price range slider** in `Storefront/ProductGrid.tsx` — uses `Slider` component from shadcn/ui, filters products by min/max price. Backend already sent `priceRange` prop but frontend didn't render it.
- **Cleaned up unused imports** in `Storefront/ProductGrid.tsx` (removed `ArrowUpDown`, `Grid3X3`, `List`).

### Phase 4.7 — PSR-4 Autoloader Fixes
- Fixed `ProductCategory::products()` pivot table — was using default `product_product_category`, corrected to `'product_category_product'`.
- Fixed `ProductTag::products()` pivot table — was using default `product_product_tag`, corrected to `'product_tag_product'`.
- Moved controllers from `Http/Controllers/` to `Controllers/` for Properties, Booking, and Posts modules (14 files) to match PSR-4 namespace declarations.
- `HasRoutes` trait updated with `loadApiRoutes()` method (for future use).
- Controller file paths in Phase 3-4 file listings use `Controllers/` (without `Http/`) to match the corrected PSR-4 structure.

### Files Changed (Phase 3 — Webhooks)
```
NEW:
  app/Modules/Shop/Models/Webhook.php
  app/Modules/Shop/Models/WebhookLog.php
  app/Modules/Shop/Services/WebhookService.php
  app/Modules/Shop/Services/WebhookDispatcherServiceProvider.php
  app/Modules/Shop/Http/Controllers/WebhooksController.php
  app/Modules/Shop/Http/Controllers/StripeWebhookController.php
  app/Modules/Shop/Routes/webhooks.php
  app/Modules/Shop/database/migrations/2026_07_02_070000_create_webhooks_table.php
  app/Modules/Shop/database/migrations/2026_07_02_070001_create_webhook_logs_table.php
  app/Modules/Shop/Resources/js/Pages/Webhooks/Index.tsx
  tests/Feature/modules/shop/ApiAndWebhookTest.php

MODIFIED:
  app/Modules/Shop/ShopModule.php         # +webhooks nav, routes, permissions, event types
  app/Modules/Shop/Routes/web.php              # +webhook CRUD routes
  app/Modules/Shop/Http/Controllers/OrdersController.php     # +webhook dispatch
  app/Modules/Shop/Http/Controllers/ProductsController.php   # +webhook dispatch
  app/Modules/Shop/Http/Controllers/CustomersController.php  # +webhook dispatch
  app/Providers/AppServiceProvider.php               # +WebhookDispatcherServiceProvider registration
  app/Providers/ModuleServiceProvider.php
  app/Http/Controllers/AdminController.php           # +webhook admin nav
```

### Files Changed (Phase 4 — Storefront)
```
NEW:
  app/Modules/Shop/Http/Controllers/StorefrontController.php
  app/Modules/Shop/Routes/storefront.php
  app/Modules/Shop/Routes/store-cart.php
  app/Modules/Shop/Resources/js/Pages/Storefront/ProductGrid.tsx
  app/Modules/Shop/Resources/js/Pages/Storefront/ProductDetail.tsx
  app/Modules/Shop/Resources/js/Pages/Storefront/ShoppingCart.tsx
  resources/js/Layouts/StoreLayout.tsx

MODIFIED:
  app/Modules/Shop/ShopModule.php         # +storefront routes, cart routes, frontend pages, categories shared prop
  app/Modules/Traits/HasRoutes.php                   # +loadPublicRoutes(), +loadApiRoutes()
  resources/js/app.tsx                               # +guest store routes glob
  app/Modules/Shop/Models/ProductCategory.php   # +pivot table fix
  app/Modules/Shop/Models/ProductTag.php        # +pivot table fix
```

### Phase 5.0 — Core Fixes
- Added `$this->authorize()` calls to ProductsController and OrdersController
- Removed stale `Http/Routes/` duplicate files
- Fixed frontend route name `dashboard.admin.shop.settings.update` → `admin.shop.settings.update`

### Phase 5.1 — Product Tags
- Migration `create_product_tags_table.php` + pivot `product_tag_product`
- `ProductTag` model with `active` scope and slug auto-generation
- Updated `Product` model with `tags()` BelongsToMany
- Tags sync in ProductsController store/update
- Tags toggle-button selector in Form.tsx

### Phase 5.2 — Attributes
- Migrations for `attributes`, `attribute_terms`, pivot tables
- `Attribute` model (hasMany terms, BelongsToMany products)
- `AttributeTerm` model (BelongsTo attribute, BelongsToMany products)
- `AttributesController` (CRUD for attributes + terms, `listTerms` JSON)
- Frontend `Attributes/Index.tsx` with create/edit/delete modals
- Nav item + frontend page registration

### Phase 5.3 — Variations
- Migration `create_product_variations_table.php`
- `ProductVariation` model (JSON attributes cast)
- `ProductVariationsController` (CRUD + cartesian product generate)
- `VariationsManager.tsx` component embedded in Form.tsx
- Sets product type to `variable` when variations exist

### Phase 5.4 — Full Category CRUD
- `CategoriesController` (index, store, update, destroy with parent_id)
- `Categories/Index.tsx` frontend page (table, parent select, dialogs)
- Routes + nav item + frontend page registration

### Phase 5.5 — Customer Detail Page
- `CustomersController::show()` with order history + stats
- `Customers/Show.tsx` frontend page (stats cards, order history table, pagination)
- Route + nav item + frontend page registration

### Phase 5.6 — Reports
- `ReportsController` (sales summary, sales-over-time, top products by range)
- `Reports/Index.tsx` frontend page (CSS bar chart, summary cards, top products table)
- Date range filter (7/30/90/365 days)
- Route + nav item + frontend page registration

### Phase 5.7 — Billing/Shipping Address
- Migration: added `billing_address` and `shipping_address` JSON columns to `users` table
- Updated User model fillable + casts
- `AddressSection.tsx` component with billing + shipping address forms
- `AccountController::updateAddress()` with validation
- Route `PUT /dashboard/account/address`

### Phase 5.8 — Notification Integration
- `OrdersController::updateStatus()` dispatches `order.status_changed` to customer via `NotificationService::notifyViaPreferences`
- `ProductsController::update()` checks `wasChanged` for stock fields + dispatches `low_stock` to admins
- `ProductsController::notifyLowStock()` helper method

### Phase 5.9 — Tests
- `tests/Feature/modules/shop/ShopCrudTest.php`: 21 PHPUnit tests covering all Shop CRUD (products, orders, categories, attributes, attribute terms, customers, reports)
- Fixed `ModuleServiceProvider::boot()` to load module migrations even when `modules` table doesn't exist (enables `RefreshDatabase` in tests)

### Files Changed (Phase 5)
```
NEW:
  app/Modules/Shop/Http/Controllers/AttributesController.php
  app/Modules/Shop/Http/Controllers/CategoriesController.php
  app/Modules/Shop/Http/Controllers/ProductVariationsController.php
  app/Modules/Shop/Http/Controllers/ReportsController.php
  app/Modules/Shop/Models/ProductTag.php
  app/Modules/Shop/Models/Attribute.php
  app/Modules/Shop/Models/AttributeTerm.php
  app/Modules/Shop/Models/ProductVariation.php
  app/Modules/Shop/Resources/js/Pages/Attributes/Index.tsx
  app/Modules/Shop/Resources/js/Pages/Categories/Index.tsx
  app/Modules/Shop/Resources/js/Pages/Customers/Show.tsx
  app/Modules/Shop/Resources/js/Pages/Reports/Index.tsx
  app/Modules/Shop/Resources/js/components/VariationsManager.tsx
  app/Modules/Shop/database/migrations/2026_07_01_160005_create_product_tags_table.php
  app/Modules/Shop/database/migrations/2026_07_01_160006_create_attributes_table.php
  app/Modules/Shop/database/migrations/2026_07_01_160007_create_attribute_terms_table.php
  app/Modules/Shop/database/migrations/2026_07_01_160008_create_product_variations_table.php
  app/Modules/Shop/database/migrations/2026_07_01_160009_create_pivot_tables.php # inline in respective migrations
  database/migrations/2026_07_01_150000_add_address_to_users_table.php
  resources/js/Components/account/sections/AddressSection.tsx
  tests/Feature/modules/shop/ShopCrudTest.php

MODIFIED:
  app/Providers/ModuleServiceProvider.php          # Load migrations before modules table check
  app/Models/User.php                              # billing_address/shipping_address fillable + casts
  app/Http/Controllers/AccountController.php       # updateAddress() method, address in edit()
  routes/web.php                                   # PUT /dashboard/account/address
  app/Modules/Shop/ShopModule.php         # Nav items, frontend pages, notification types
  app/Modules/Shop/Routes/web.php             # All new routes (categories, attributes, variations, reports, customers)
  app/Modules/Shop/Http/Controllers/OrdersController.php     # notifications on status change
  app/Modules/Shop/Http/Controllers/ProductsController.php   # low stock notification
  app/Modules/Shop/Resources/js/Pages/Products/Form.tsx       # VariationsManager integration
  resources/js/Pages/Account/Edit.tsx               # AddressSection integration
```

## Recent Changes (Phase 4 — Guest Portal & Booking Parity)

### Booking PHP Services (Phase 1)
- `app/Modules/Booking/Models/BookingAction.php`: Eloquent model for booking action log
- `database/migrations/*_create_booking_actions_table.php`: Migration for `booking_actions` table
- `app/Modules/Booking/Services/BookingActionService.php`: Records and retrieves booking actions
- `app/Modules/Booking/Services/BookingActivityDashboardService.php`: Admin dashboard activity feed
- `app/Modules/Booking/Services/BookingHistoryService.php`: Booking history/audit trail
- `app/Modules/Booking/Support/BookingMeta.php`: Constants class with 65+ field definitions
- `config/booking-meta.php`: Configuration array for all booking meta fields

### Property Module Improvements
- **Scheduled publishing**: `published_at` column on `properties` table, `PublishScheduledProperties` artisan command (every minute via `routes/console.php`), model scopes + controller form data transformation
- **Term image support**: `image` column on `property_types` and `cities` tables, URL input + thumbnail display in Types.tsx and Cities.tsx
- **Taxonomy seed data**: 14 property types, 8 statuses, 4 feature groups with 48 features, 200+ Tunisian cities
- **Property fixes**: bulk action route, route method mismatch (PATCH→PUT), user count cache, cascade delete for bookings, `updateStatus` future status validation

### Guest Portal Parity (Phase 4)
- **API routes** (`app/Modules/Booking/Routes/api.php`):
  - `POST api/booking/check-availability`
  - `GET api/booking/cache-version/{propertyId}`
  - `POST api/booking/modify/{bookingId}`
  - `POST api/booking/cancel/{bookingId}`
- **Check-in/check-out** (BookingGuestController):
  - `guestCheckIn()` / `guestCheckOut()` — token-based guest auth
  - `loggedInCheckIn()` / `loggedInCheckOut()` — session-based auth
  - Routes in `guest.php`, `my-bookings.php`, `web.php` (admin guest portal)
- **Frontend check-in/check-out buttons**:
  - `GuestView.tsx` — card with "I've Arrived" / "I'm Leaving" buttons (token auth)
  - `MyBookings.tsx` — dropdown actions in DataTable (session auth)
  - `MyBookingDetail.tsx` — card with same buttons (session auth)
  - All respect `Confirmed→CheckIn` / `CheckedIn→CheckOut` transition rules

### Files Changed
```
NEW:
  app/Modules/Booking/Models/BookingAction.php
  app/Modules/Booking/Services/BookingActionService.php
  app/Modules/Booking/Services/BookingActivityDashboardService.php
  app/Modules/Booking/Services/BookingHistoryService.php
  app/Modules/Booking/Support/BookingMeta.php
  app/Modules/Booking/Routes/api.php
  config/booking-meta.php
  database/migrations/*_create_booking_actions_table.php
  database/migrations/*_add_published_at_to_properties_table.php
  database/migrations/*_add_image_to_term_tables.php
  app/Console/Commands/PublishScheduledProperties.php

MODIFIED:
  app/Modules/Booking/BookingModule.php            # route loading + api routes
  app/Modules/Booking/Controllers/BookingGuestController.php  # check-in/check-out methods
  app/Modules/Booking/Routes/guest.php             # check-in/check-out routes
  app/Modules/Booking/Routes/my-bookings.php       # check-in/check-out routes
  app/Modules/Booking/Routes/web.php               # admin guest portal routes
  app/Modules/Booking/Resources/js/Pages/GuestView.tsx
  app/Modules/Booking/Resources/js/Pages/MyBookings.tsx
  app/Modules/Booking/Resources/js/Pages/MyBookingDetail.tsx
  app/Modules/Booking/Services/BookingActionService.php (lint fix)
  app/Modules/Booking/Services/BookingActivityDashboardService.php (lint fix)
  app/Modules/Booking/Services/BookingHistoryService.php (lint fix)
  config/booking-meta.php (lint fix)
  routes/console.php                                # schedule registration
```

## Recent Changes (Phase 8-11 — Shop Feature Parity: Email, Coupons, Reviews, Shipping & Taxes)

### Phase 8 — Email Templates

**Files:**
- `resources/views/email/templates/order-confirmation.blade.php` — Order placed email with fallback variables (`$customerName`, `$orderNumber`, `$orderTotal`, `$orderStatus`, `$orderUrl`)
- `resources/views/email/templates/order-shipped.blade.php` — Status change email for any status transition (dynamic `$newStatus`, conditional `$trackingNumber`)

**Key Patterns:**
- Both templates use null coalescing (`??`) for all variable fallbacks
- `$siteName`, `$userName`, `$notificationLink` are merged globally by `AppNotification::toArray()`
- `ShopModule::getEmailTemplates()` registers sample data for the template simulator

**Controllers:**
- `CheckoutController::store()` — enriches notification data with `customerName`, `orderTotal`, `orderStatus`, `orderUrl`. Guest checkout sends email directly via `MailService::send()` (no `User` notifiable)
- `OrdersController::updateStatus()` + `refund()` — enriched with complete template data. Auto-sets `shipped_at` when status transitions to `completed` or `shipped`

### Phase 9 — Discounts & Coupons

**Models:**
- `Coupon` — `code` (unique, uppercased), `type` (percentage/fixed), `value`, `min_order_amount`, `max_discount`, `usage_limit`, `per_user_limit`, `used_count`, `is_active`, `starts_at`, `expires_at`, `created_by`. Methods: `isValid(float $subtotal): bool`, `calculateDiscount(float $subtotal): float`. Relations: `products()` (BelongsToMany via `coupon_product`), `usages()` (HasMany), `creator()` (BelongsTo User)
- `CouponUsage` — `coupon_id`, `order_id`, `user_id`, `discount_amount`. Relations: `coupon()`, `order()`, `user()`

**Migrations:**
- `create_coupons_table.php` — core table with all coupon fields
- `coupon_product_pivot` — pivot for coupon-product assignment
- `create_coupon_usages_table.php` — usage tracking table

**Controllers:**
- `CouponsController` — full CRUD (index, create, store, edit, update, destroy) + `toggleActive()`. Authorized via `CouponPolicy` (view: `shop.view`, manage: `shop.coupons.manage`)

**Services:**
- `CouponService`:
  - `validateAndApply(string $code, float $subtotal, ?int $userId): Coupon` — validates active/dates/limits/per-user, throws `ValidationException` with specific messages
  - `calculateDiscount(Coupon $coupon, float $subtotal): float` — delegates to model
  - `recordUsage(Coupon $coupon, int $orderId, ?int $userId, float $discountAmount): void` — creates usage record + increments `used_count`

**Integration:**
- `CartController::applyCoupon()` / `removeCoupon()` — JSON endpoints, validates via `CouponService`, stores code + discount on cart
- `CheckoutController::store()` — re-validates coupon at checkout via `CouponService::validateAndApply()` (including per-user limits), records usage
- `ShopModule::permissions()` — `shop.coupons.manage` registered
- `Cart/Index.tsx` — coupon input with apply/remove, discount line display

**Frontend Pages:**
- `Coupons/Index.tsx` — table with usage count, copy-to-clipboard code, toggle active, delete
- `Coupons/Form.tsx` — type selector (percentage/fixed), value, min/max, date pickers, usage stats (times used, total discount, last used)

### Phase 10 — Product Reviews & Ratings

**Models:**
- `ProductReview` — `product_id`, `user_id`, `order_id`, `rating` (1-5), `title`, `content`, `is_approved`, `approved_at`, `approved_by`. Scopes: `approved()`, `pending()`. Boot events: `saved`/`deleted` call `$review->product->updateRatingCache()`
- `Product` — added `average_rating` and `reviews_count` columns. `updateRatingCache()` uses single aggregate query (single `SELECT COUNT, AVG`). Relations: `reviews()`, `approvedReviews()`

**Migrations:**
- `create_product_reviews_table.php`
- `add_rating_columns_to_products.php`

**Controllers:**
- `ProductsController::show()` — loads product with categories, reviews (with user). Returns `userReview` for current user
- `ReviewsController`:
  - `index()` — filterable by status (pending/approved) and search (product/user/title). `$this->authorize('viewAny', ProductReview::class)`
  - `store()` — one review per user enforced, `$orderId` from single query, `is_approved: false`. `$this->authorize('create', ProductReview::class)`
  - `approve()` / `reject()` — sets `approved_at` + `approved_by`. `$this->authorize('approve', $review)`
  - `destroy()` — `$this->authorize('delete', $review)`
- `ShopModule::permissions()` — `shop.reviews.moderate` registered

**Components:**
- `resources/js/Components/shop/StarRating.tsx` — reusable star rating: `value`, `onChange?`, `size` (sm/md/lg), `readonly`, `maxStars`. Filled: `fill-amber-400 text-amber-400`. Empty: `fill-none text-muted-foreground/30`

**Frontend Pages:**
- `Products/Show.tsx` — product detail with rating display, review form (title + content + star picker), review list with verified purchase badge (`BadgeCheck`) and pending badge
- `Reviews/Index.tsx` — admin moderation: table with product/customer/rating/review/date/status, approve/reject/delete actions with dialogs, filter by status + search

### Phase 11 — Shipping & Taxes

**Models:**
- `ShippingMethod` — `name`, `description`, `price`, `free_above`, `estimated_days_min/max`, `is_active`, `sort_order`. Scope: `active()`
- `ShippingZone` — `name`, `countries` (JSON array of ISO2), `regions` (JSON array), `is_active`. Relations: `methods()` (BelongsToMany via `shipping_zone_method` with `override_price` pivot). Scope: `active()`
- `TaxRate` — `name`, `rate`, `type` (percentage/fixed), `country` (ISO2), `state`, `is_active`, `is_default`. Scopes: `active()`, `forCountry(?string $country)`

**Migrations:**
- `create_shipping_zones_table.php` — zones with JSON countries/regions
- `shipping_zone_method_pivot` — pivot with `override_price`
- `create_tax_rates_table.php` — per-country tax rates

**Controllers:**
- `ShippingMethodsController` — CRUD + toggleActive. `$this->authorize()` with `ShippingMethodPolicy`
- `ShippingZonesController` — CRUD. `create()`/`edit()` pass `methods` for assignment. Stores pivot with `override_price`. `$this->authorize()` with `ShippingZonePolicy`
- `TaxRatesController` — CRUD + toggleActive. `$this->authorize()` with `TaxRatePolicy`
- All policies registered in `AppServiceProvider` via `Gate::policy()`
- Permissions: `shop.shipping.manage`, `shop.taxes.manage`

**Services:**
- `ShippingService:getAvailableMethods(?array $billingAddress): Collection` — matches country to zone via `whereJsonContains('countries', $country)`, returns zone methods with override prices. Falls back to all active methods if no zone match
- `TaxService:calculate(float $subtotal, ?array $billingAddress, bool $isTaxable): array` — finds rates matching billing address country, falls back to `is_default`. Returns `['tax_total' => float, 'breakdown' => [...]]` with per-rate breakdown

**Integration:**
- `CheckoutController::index()` — uses `ShippingService::getAvailableMethods($billingAddress)` instead of `ShippingMethod::active()->get()`
- `CheckoutController::store()` — calls `TaxService::calculate()`, stores `tax_total` on Order
- `ShopModule` — nav items for Shipping Methods, Shipping Zones, Tax Rates. Frontend page registrations for all CRUD pages

**Frontend Pages:**
- `ShippingMethods/Index.tsx` — dialog-based CRUD with name, description, price, free_above, estimated days, sort_order, toggle active
- `ShippingZones/Index.tsx` — zone list with countries + method names
- `ShippingZones/Form.tsx` — create/edit with multi-select country dropdown, method assignment with override price per method
- `TaxRates/Index.tsx` — dialog CRUD with type (percentage/fixed), rate, country (ISO2 with display name), state, default toggle, active toggle

### Code Audit — Phase 8-11

**Audit Date:** 2026-07-01
**Methodology:** Manual code review of all models, services, controllers, frontend components, routes, migrations, and permissions against Laravel 13 / Inertia.js 3 / React 19 best practices and the project's existing code style.

**What's Solid:**
- Constructor dependency injection for all services (Laravel container pattern ✅)
- `$request->validate()` for server-side validation (project convention ✅)
- Inertia `useForm` for all form state (project convention ✅)
- Route model binding for all models (Laravel convention ✅)
- Null coalescing operators in Blade templates for safe fallbacks ✅
- Boot events on Eloquent models for side effects (rating cache, order number) ✅
- `saveQuietly()` to prevent infinite observer loops ✅
- Scopes on models for clean query filtering ✅
- Pagination with `withQueryString()` on all list endpoints ✅
- Frontend components use `cn()` from `@/lib/utils` ✅
- Routes via Ziggy `route()` helper, no hardcoded URLs ✅
- Flash messages via `toast()` from `sonner` ✅
- `DB::transaction()` for atomic operations ✅
- Services separated from controllers for business logic ✅
- Proper route naming convention (`dashboard.shop.{resource}.{action}`) ✅
- Frontend pages registered in `ShopModule::getFrontendPages()` ✅

**Issues Found & Fixed:**
| Issue | File | Severity | Fix |
|-------|------|----------|-----|
| No `$this->authorize()` calls in CouponsController | `CouponsController.php` | High | Added `CouponPolicy` + authorize calls |
| No `$this->authorize()` calls in ReviewsController | `ReviewsController.php` | High | Added `ProductReviewPolicy` + authorize calls |
| No `$this->authorize()` calls in ShippingMethodsController | `ShippingMethodsController.php` | High | Added `ShippingMethodPolicy` + authorize calls |
| No `$this->authorize()` calls in ShippingZonesController | `ShippingZonesController.php` | High | Added `ShippingZonePolicy` + authorize calls |
| No `$this->authorize()` calls in TaxRatesController | `TaxRatesController.php` | High | Added `TaxRatePolicy` + authorize calls |
| Missing permissions `shop.coupons.manage`, `shop.reviews.moderate`, `shop.shipping.manage`, `shop.taxes.manage` | `ShopModule.php` | High | Added to `permissions()` |
| Missing Gate registrations for 5 new policies | `AppServiceProvider.php` | High | Added `Gate::policy()` registrations |
| CheckoutController bypassed CouponService for per-user limit check | `CheckoutController.php` | Medium | Changed to use `CouponService::validateAndApply()` |
| ReviewsController `$hasPurchased` query duplicated | `ReviewsController.php` | Low | Single `value('id')` replaces exists + value |
| `Product::updateRatingCache()` used two queries | `Product.php` | Low | Single `selectRaw('COUNT, AVG')` |

**Shopify Competitive Analysis (Feature Gap Matrix):**

| Feature Area | Our Implementation | Shopify | Gap |
|-------------|-------------------|---------|-----|
| **Products** | CRUD, categories, tags, attributes, variations, types (simple/variable/digital/service), stock tracking, SEO meta | Everything + bundles, kits, custom product builder | Minor — no bundles/kits |
| **Orders** | CRUD, status workflow, refund, mark paid, invoice, timeline, search | Same + draft orders, order editing | None critical |
| **Cart/Checkout** | Cart CRUD, coupon apply/remove, checkout with billing/shipping, guest checkout, email confirmation, tax calculation, zone-based shipping | 15% higher conversion checkout, Shop Pay, installments, multi-currency, scripts/functions | Payment gateway (Shop Pay), installments, multi-currency |
| **Discounts** | Coupons (percentage/fixed), per-user limits, usage limits, date ranges, min order, max discount, product targeting | Same + automatic discounts, BOGOF, free shipping, buy X get Y, discount stacking (up to 5 codes), Shopify Functions | No BOGOF/stacking/free-shipping coupon type, no automatic discounts |
| **Reviews** | Star rating, text reviews, verified purchase badge, approval workflow | Via apps (Loox, Judge.me, Yotpo) — photos/videos, Q&A, social sharing | No photo/video in reviews, no Q&A |
| **Shipping** | Methods with flat/free, zones with country matching, zone-specific override prices | Carrier-calculated rates, real-time labels, SFN fulfillment, weight/dimension calculation, local delivery, pickup | No carrier API integration (UPS/FedEx/DHL), no real-time rates, no weight calc |
| **Taxes** | Per-country rates, flat/percentage, default fallback | Auto tax calculation via Avalara/TaxJar, multi-jurisdiction support | No Avalara/TaxJar integration, no auto calculation |
| **Multi-currency** | — | Shopify Markets with auto-currency conversion | Not implemented |
| **Multi-language** | — | Shopify Markets with Liquid translations | Not implemented |
| **POS** | — | Shopify POS (retail + inventory sync) | Not implemented |
| **Marketing** | — | Built-in SEO, Shopify Email (10k/mo), customer segments, abandoned cart recovery | Not implemented |
| **Analytics** | Basic revenue reports | Real-time dashboards, sales reports, finance reports, fraud analysis | Basic compared to Shopify |
| **B2B/Wholesale** | — | Company profiles, tiered pricing, net terms, quantity rules | Not implemented |
| **Subscriptions** | — | Via Shopify Subscriptions app | Not implemented |
| **Gift Cards** | — | Native gift card support | Not implemented |
| **Fulfillment** | — | Shopify Fulfillment Network, carrier integration, label printing | Not implemented |
| **API/Developer** | Inertia pages + JSON endpoints | GraphQL Storefront API, Admin API, Hydrogen, Functions, Webhooks, 8000+ apps | No public API (internal only), no webhooks |

**Key Recommendations for Next Phases:**
1. **Authorization** — All new controllers must include `$this->authorize()` calls with corresponding policies registered in `AppServiceProvider`. New permissions must be added to `ShopModule::permissions()`.
2. **Services** — Business logic should live in service classes (e.g., `CouponService`, `ShippingService`, `TaxService`), not in controllers or models.
3. **Transactions** — All order/checkout mutations must use `DB::transaction()`.
4. **Competitive Priorities** — Based on the Shopify gap analysis, the most impactful features to add next are: multi-currency support, abandoned cart recovery, automatic discounts, and carrier-integrated shipping.

**Tests:** 140/140 passing, typecheck clean, production build succeeds.

## Shop Data Flow

```
Products CRUD → Catalog (categories, tags, attributes, variations)
       ↓
Cart (Inertia page + JSON API) ← Coupon (apply/remove)
       ↓
Checkout (billing/shipping address, shipping method, tax calc → Order)
       ↓
Order (status workflow, refund, mark paid, invoice, timeline, email)
       ↓
Reviews (product detail page submit → admin moderation)
```

```
Guest Checkout Flow:
  Cart (session) → Checkout (guest fields) → Order (no user) → Email (MailService::send)
```

```
Admin Order Flow:
  Create Order (search products + customers) → Invoice → Update Status → Notify Customer → Refund
```

## File Index (Phase 8-11)

### New Files
```
app/Policies/CouponPolicy.php
app/Policies/ProductReviewPolicy.php
app/Policies/ShippingMethodPolicy.php
app/Policies/ShippingZonePolicy.php
app/Policies/TaxRatePolicy.php
app/Modules/Shop/Models/Coupon.php
app/Modules/Shop/Models/CouponUsage.php
app/Modules/Shop/Models/ProductReview.php
app/Modules/Shop/Models/ShippingZone.php
app/Modules/Shop/Models/TaxRate.php
app/Modules/Shop/Services/CouponService.php
app/Modules/Shop/Services/ShippingService.php
app/Modules/Shop/Services/TaxService.php
app/Modules/Shop/Http/Controllers/CouponsController.php
app/Modules/Shop/Http/Controllers/ReviewsController.php
app/Modules/Shop/Http/Controllers/ShippingMethodsController.php
app/Modules/Shop/Http/Controllers/ShippingZonesController.php
app/Modules/Shop/Http/Controllers/TaxRatesController.php
app/Modules/Shop/Resources/js/Pages/Coupons/Index.tsx
app/Modules/Shop/Resources/js/Pages/Coupons/Form.tsx
app/Modules/Shop/Resources/js/Pages/Products/Show.tsx
app/Modules/Shop/Resources/js/Pages/Reviews/Index.tsx
app/Modules/Shop/Resources/js/Pages/ShippingMethods/Index.tsx
app/Modules/Shop/Resources/js/Pages/ShippingZones/Index.tsx
app/Modules/Shop/Resources/js/Pages/ShippingZones/Form.tsx
app/Modules/Shop/Resources/js/Pages/TaxRates/Index.tsx
app/Modules/Shop/database/migrations/*_create_coupons_table.php
app/Modules/Shop/database/migrations/*_coupon_product_pivot.php
app/Modules/Shop/database/migrations/*_create_coupon_usages_table.php
app/Modules/Shop/database/migrations/*_create_product_reviews_table.php
app/Modules/Shop/database/migrations/*_add_rating_columns_to_products.php
app/Modules/Shop/database/migrations/*_create_shipping_zones_table.php
app/Modules/Shop/database/migrations/*_shipping_zone_method_pivot.php
app/Modules/Shop/database/migrations/*_create_tax_rates_table.php
resources/js/Components/shop/StarRating.tsx
```

### Modified Files
```
app/Providers/AppServiceProvider.php                   # 5 policy registrations + imports
app/Modules/Shop/ShopModule.php              # Nav items, frontend pages, permissions
app/Modules/Shop/Routes/web.php                   # All new routes
app/Modules/Shop/Http/Controllers/CheckoutController.php  # CouponService re-validation, TaxService integration, ShippingService
app/Modules/Shop/Http/Controllers/OrdersController.php    # Enriched notification data, auto shipped_at
app/Modules/Shop/Http/Controllers/CartController.php      # applyCoupon/removeCoupon
app/Modules/Shop/Http/Controllers/ProductsController.php  # show() method with reviews
app/Modules/Shop/Models/Product.php               # avg_rating/reviews_count, updateRatingCache(), reviews()
app/Modules/Shop/Http/Controllers/CouponsController.php   # $this->authorize() added
app/Modules/Shop/Http/Controllers/ReviewsController.php   # $this->authorize() + single query optimization
app/Modules/Shop/Http/Controllers/ShippingMethodsController.php  # $this->authorize() added
app/Modules/Shop/Http/Controllers/ShippingZonesController.php   # $this->authorize() added
app/Modules/Shop/Http/Controllers/TaxRatesController.php        # $this->authorize() added
resources/views/email/templates/order-confirmation.blade.php  # Robust fallbacks
resources/views/email/templates/order-shipped.blade.php       # Dynamic status + conditional tracking
```

## Recent Changes (Phase 12 — CSV Import/Export)

### Phase 12.1 — Product Export
- `ProductsController::export()` — streams CSV with BOM for Excel compatibility. Columns: name, slug, description, price, sku, stock, status, type, categories (pipe-separated), tags (pipe-separated). Uses `fputcsv()` with no external dependencies.
- `OrdersController::export()` — streams CSV with order items inline. Status-filterable via query param. Columns: order_number, customer, status, total, items, date.
- `CustomersController::export()` — streams CSV with order count + total spent. Columns: name, email, orders_count, total_spent, registered_date.
- Export buttons on Products/Index.tsx, Orders/Index.tsx, Customers/Index.tsx.
- Routes: `GET /products/export`, `GET /orders/export`, `GET /customers/export`.

### Phase 12.2 — Product Import
- `ProductsController::import()` — parses CSV, validates each row with `Validator::make()` (name required, price numeric, sku unique, status/type enum). Returns success count + first 5 errors via flash message.
- Import button on Products/Index.tsx with hidden file input, posts via `router.post()` with `preserveScroll`.
- Route: `POST /products/import`.

## Recent Changes (Phase 14 — Inventory Log)

### Phase 14.1 — Stock Adjustment Model & Migration
- `StockAdjustment` model — tracks every stock change: `product_id`, `type` (manual/sale/return/correction/refund), `quantity` (signed), `stock_before`, `stock_after`, `reason`, `reference_type/ID`, `user_id`.
- Migration `create_stock_adjustments_table` with foreign keys to products and users.

### Phase 14.2 — InventoryService
- `InventoryService::adjust()` — core method: calculates new stock quantity (capped at 0 min), updates product, creates StockAdjustment record.
- `recordSale()` — records sale (negative quantity) for track_stock products.
- `recordRefund()` — records refund (positive quantity) for track_stock products.
- `manualAdjust()` — manual stock adjustment with reason.
- `getHistory()` — paginated history for a product with user eager-loading.

### Phase 14.3 — Orders Integration
- `OrdersController::updateStatus()` — records sale when transitioning to `completed`, records refund when transitioning to `cancelled`.
- `OrdersController::refund()` — uses `InventoryService::recordRefund()` instead of raw `increment()`.

### Phase 14.4 — Admin UI
- `StockHistory.tsx` — full-page history table with type badges, quantity coloring (red/green), reason column, user attribution. Includes "Adjust Stock" dialog with +/- buttons and reason textarea.
- Products/Index.tsx — each product card shows a "History" link (TrendingUp icon) next to stock count for track_stock products.
- Frontend page registered: `Shop/Products/StockHistory`.

### New Files (Phase 12 + 14)
```
app/Modules/Shop/Models/StockAdjustment.php
app/Modules/Shop/Services/InventoryService.php
app/Modules/Shop/Resources/js/Pages/Products/StockHistory.tsx
app/Modules/Shop/database/migrations/*_create_stock_adjustments_table.php
```

### Modified Files (Phase 12 + 14)
```
app/Modules/Shop/Http/Controllers/ProductsController.php  # +import(), +adjustStock(), +stockHistory(), InventoryService DI
app/Modules/Shop/Http/Controllers/OrdersController.php    # InventoryService DI, recordSale/Refund in updateStatus/refund
app/Modules/Shop/Http/Controllers/CustomersController.php # +export() method
app/Modules/Shop/Routes/web.php                          # Export/import/adjust-stock/stock-history routes
app/Modules/Shop/ShopModule.php                      # StockHistory frontend page registration
app/Modules/Shop/Resources/js/Pages/Products/Index.tsx    # Import button + Stock History link
app/Modules/Shop/Resources/js/Pages/Customers/Index.tsx   # Export button
```

## Inventory Log Data Flow

```
Product Stock Change (sale/refund/adjustment)
       ↓
InventoryService::adjust() — records StockAdjustment, updates product.stock_quantity
       ↓
StockAdjustment table — full audit trail (who, what, when, why)
       ↓
StockHistory UI — admin reviews history per product, can manually adjust
       ↓
Low stock notification — automatic when stock < threshold
```

## Recent Changes (Phase 13 — Posts Module Expansion)

### Phase 13.1 — Content Blocks
- Migration `create_content_blocks_table` — `post_id`, `type` (rich_text/image/gallery/embed/code/separator/heading), `content`, `media_url`, `media_alt`, `settings` (JSON), `order`.
- `ContentBlock` model with `BelongsTo Post`, casts `settings` as array.
- Content block syncing in `PostController::syncContentBlocks()` — deletes removed blocks, updates existing via `id`, creates new ones.

### Phase 13.2 — Post Categories
- Migration `create_post_categories_table` — `name`, `slug` (unique), `description`, `parent_id` (self-referencing), `order`.
- `PostCategory` model with `children()`/`parent()` relations, `hasMany posts`, auto-slug on create.
- `PostCategoryController` — CRUD with `$this->authorize('viewAny', Post::class)` / `$this->authorize('create', Post::class)`.
- `Posts/Categories/Index.tsx` — table with inline edit/delete dialogs, parent select, post count.
- Nav item + frontend page registered in `PostsModule`.

### Phase 13.3 — Post Tags
- Migration `create_post_tags_table` — `name`, `slug` (unique).
- Pivot migration `post_tag_post` — composite PK `[post_tag_id, post_id]`.
- `PostTag` model with `belongsToMany Post`, auto-slug on create.
- `PostTagController` — JSON endpoints for tag management.
- Tags selected via multi-select dropdown in form.

### Phase 13.4 — SEO Fields
- Migration `add_seo_columns_to_posts_table` — `meta_title` (varchar 70), `meta_description` (varchar 160), `og_image`.
- Dropped the old `category` string column.
- SEO tab in Form.tsx with character counters, image upload buttons.

### Phase 13.5 — Controller & Frontend Improvements
- `PostController` — added `$this->authorize()` to all 6 actions using `PostPolicy`.
- `index()` — search by title/excerpt, filter by status and category_id, paginated with `withQueryString()`.
- `create()`/`edit()` — pass `categories` and `tags` for select dropdowns.
- `store()`/`update()` — handle `category_id`, `tag_ids` (sync), `content_blocks`, `meta_title`, `meta_description`, `og_image`, `featured_image`.
- `uploadImage()` / `deleteImage()` — image upload endpoints.
- `Index.tsx` — search input, status filter, category filter, pagination, clear filters.
- `Form.tsx` — tabbed interface (Content / Blocks / SEO), featured image upload with preview, tag selector (existing tags dropdown + manual entry), category select, content block editor with add/move/delete/type-change capabilities.
- `ContentBlockEditor.tsx` — reusable component with block-type selector, ordering arrows, dynamic field rendering per type, add-block dropdown.

### New Files (Phase 13)
```
app/Modules/Posts/Models/ContentBlock.php
app/Modules/Posts/Models/PostCategory.php
app/Modules/Posts/Models/PostTag.php
app/Modules/Posts/Controllers/PostCategoryController.php
app/Modules/Posts/Controllers/PostTagController.php
app/Modules/Posts/Resources/js/components/ContentBlockEditor.tsx
app/Modules/Posts/Resources/js/Pages/Categories/Index.tsx
app/Modules/Posts/database/migrations/*_create_content_blocks_table.php
app/Modules/Posts/database/migrations/*_create_post_categories_table.php
app/Modules/Posts/database/migrations/*_create_post_tags_table.php
app/Modules/Posts/database/migrations/*_create_post_tag_post_table.php
app/Modules/Posts/database/migrations/*_add_seo_columns_to_posts_table.php
```

### Modified Files (Phase 13)
```
app/Modules/Posts/Models/Post.php                     # +category(), +tags(), +contentBlocks() relations, SEO fillable
app/Modules/Posts/Controllers/PostController.php      # Full rewrite: $this->authorize(), search/filter, content blocks, tags, SEO, image upload
app/Modules/Posts/Routes/web.php                      # +categories routes, +tags routes, +image upload routes
app/Modules/Posts/PostsModule.php                     # +categories nav item, +categories frontend page, +migrationPaths()
app/Modules/Posts/Resources/js/Pages/Index.tsx         # Full rewrite: search, filters, pagination
app/Modules/Posts/Resources/js/Pages/Form.tsx          # Full rewrite: tabs, blocks editor, SEO panel, category/tag select, featured image
```

## Recent Changes (Phase 15 — Dashboard Analytics Overhaul)

### Phase 15.1 — Backend Analytics Service
- `DashboardService::getAnalytics()` — cached (5-min TTL) comprehensive analytics:
  - **KPIs**: Revenue Today, Orders Today, Avg Order Value, Conversion Rate, Revenue This Month — each with period-over-period % change
  - **Revenue Over Time**: Daily revenue/order count for past 14 days
  - **Recent Orders**: Last 5 orders with customer name, total, status, items count, relative time
  - **Top Products**: Top 5 by revenue (product name, qty sold, revenue)
  - **Order Status Distribution**: Count per status (pending/confirmed/processing/completed/cancelled/refunded)
  - **Totals**: Total revenue, orders, products, customers
- Revenue comparison: today vs yesterday for daily KPIs, this month vs last month for monthly KPIs
- Conversion rate: completed+processing orders / total orders within period
- `clearStatsCache()` now also clears `dashboard.analytics` cache

### Phase 15.2 — Dashboard Frontend Components
All new components under `resources/js/Components/dashboard/`:

| Component | Purpose |
|-----------|---------|
| `KpiCard.tsx` | KPI card with value, Lucide icon, % change indicator (up/down/neutral with color), optional SVG sparkline |
| `Sparkline.tsx` | Tiny inline SVG polyline sparkline (no external dep) |
| `RevenueChart.tsx` | 14-day CSS bar chart (revenue bars + order count label per day) |
| `RecentOrdersTable.tsx` | Compact list with order number link, customer, items count, total, status badge, time ago |
| `TopProductsTable.tsx` | Ranked list with product name link, qty sold, revenue |

### Phase 15.3 — Dashboard Page Overhaul (`Dashboard/Index.tsx`)
- Welcome greeting with "Updated just now" indicator
- **5 KPI cards** in responsive grid (1→2→3→5 columns): Revenue Today, Orders Today, AOV, Conversion Rate, Revenue This Month — each with % change arrow
- **Module stat cards** row (from `DashboardService::getStats()`) — backwards compatible
- **Revenue Chart + Order Status** side-by-side
- **Recent Orders + Top Products** side-by-side  
- **Quick Actions** card (Add Product, Create Order, View Reports)
- **Platform Summary** card (total revenue, orders, products, customers)

## Recent Changes (Phase 16 — Abandoned Cart Recovery)

### Phase 16.1 — Database Migration
- Added columns to `carts` table: `last_activity_at`, `abandoned_at`, `recovered_at`, `recovery_email_sent_at`, `recovery_email_count` (default 0), `converted_to_order_id` (FK to orders, nullable)

### Phase 16.2 — AbandonedCartService
- `identifyAbandonedCarts(int $hoursSinceActivity = 1): Collection` — finds carts with items, no conversion, no abandonment mark, activity older than threshold
- `markAbandoned(Cart $cart): void` — sets `abandoned_at` timestamp
- `needsSequenceStep(Cart $cart, int $stepHours): bool` — checks if enough time has passed since abandonment for the next email step
- `sendRecoveryEmail(Cart $cart, int $sequenceStep): bool` — sends the appropriate email template based on step (1=1h, 2=24h, 3=72h), generates signed recovery URL via `URL::signedRoute()`
- `handleRecovery(Cart $cart): string` — marks as recovered, clears abandoned_at, returns redirect URL (dashboard cart for auth users, guest checkout for guests)

### Phase 16.3 — Console Command
- `app/Console/Commands/ProcessAbandonedCarts.php` — `shop:abandoned-cart-process`
  - Step 1: Identify newly abandoned carts (no activity for 1h), mark them as abandoned
  - Step 2: For each sequence step (1h/24h/72h), find carts needing that step's email based on `recovery_email_count` and elapsed time, send the recovery email
- Scheduled via `routes/console.php` to run hourly

### Phase 16.4 — Controller & Routes
- `AbandonedCartRecoveryController::recover()` — accepts signed URL, validates signature, calls `handleRecovery()`, redirects to cart with success flash
- Routes: public GET `/store/cart/recover/{cart}` (signed middleware), loaded via `ShopModule::routes()`

### Phase 16.5 — Cart Lifecycle Integration
- `CartController` — all mutation methods (addItem, updateItem, removeItem, clear, applyCoupon, removeCoupon) now call `touchLastActivity()` to update `last_activity_at`
- `CheckoutController::store()` — after creating order, sets `converted_to_order_id` on the cart (inside the transaction)

### Phase 16.6 — Email Templates
- `cart-recovery-1h.blade.php` — "You left items in your cart!" (friendly reminder)
- `cart-recovery-24h.blade.php` — "Still thinking about it?" (gentle nudge)
- `cart-recovery-72h.blade.php` — "Your cart is still waiting!" (last chance urgency)

### Phase 16.7 — Configuration & Dashboard
- `config/shop.php` — abandoned_cart settings (enabled, hours_until_abandoned: 1, sequence_hours: [1, 24, 72])
- `DashboardService::getAnalytics()` now includes `abandoned_carts` and `recovered_carts` counts

## Recent Changes (Phase 17 — Discount Engine Expansion)

### Phase 17.1 — New Coupon Types
- Added `free_shipping` coupon type — sets `shipping_total = 0` at checkout when applied
- Added `is_automatic` boolean flag — coupons applied without a code (auto-discounts)
- Added `auto_discount_label` field — display label shown in cart for auto-discounts
- Migration adds columns to `coupons` table

### Phase 17.2 — Coupon Model & Service Updates
- `Coupon::isValid()` — skips `min_order_amount` check for `free_shipping` type (always valid regardless of subtotal)
- `Coupon::calculateDiscount()` — accepts optional `?float $shippingCost`, returns shipping cost for `free_shipping` type
- `CouponService::getAutoDiscounts(float $subtotal): Collection` — finds all active automatic coupons valid for the given subtotal
- `CouponService::calculateDiscount()` — passes through shipping cost for free_shipping

### Phase 17.3 — Checkout Integration
- `CheckoutController::store()` — checks if applied coupon is `free_shipping`, sets `shippingTotal = 0`
- `CartController::applyCoupon()` — stores `discount = 0` for `free_shipping` coupons (actual benefit calculated at checkout time)

### Phase 17.4 — Auto-Discount Display
- `CartController::page()` — loads auto-discounts via `CouponService::getAutoDiscounts()`, passes them to the cart frontend with label, type, and applied status
- `CartController::index()` — (auto-discounts returned for JSON clients)

### Phase 17.5 — Frontend Updates
- **Coupon Form** (`Coupons/Form.tsx`): Added `free_shipping` type option (Truck icon), `is_automatic` toggle (Sparkles icon), conditional `auto_discount_label` field, value field hidden for free_shipping type
- **Coupons Index** (`Coupons/Index.tsx`): Shows Truck icon for free_shipping, Sparkles icon for auto-discounts, displays `"Free Shipping"` label text

## Recent Changes (Publish/Scheduling Workflow & Product Form Revamp)

### Phase 18 — Product Form & Scheduling Overhaul

**Date:** 2026-07-02

**Files Created:**
- `resources/js/Components/shared/featured-image-selector.tsx` — Drag-and-drop featured image upload with preview, overlay, and remove support
- `resources/js/Components/shared/terms-selector.tsx` — Searchable multi-select dropdown with chips, inline-add dialog support
- `resources/js/Components/shared/gallery-uploader.tsx` — react-dropzone-based multi-upload with progress, grid preview, set-cover, delete confirmation
- `app/Console/Commands/PublishScheduledContent.php` — Unified `schedule:publish` artisan command handling Products, Posts, and Properties scheduled publishing
- `app/Modules/Shop/database/migrations/*_add_scheduling_and_sale_columns_to_products_table.php` — Adds `publish_date`, `sale_price`, `sale_from`, `sale_to` columns

**Files Modified:**
- `resources/js/Components/ui/publish-section.tsx` — Updated to use `DateTimePicker` instead of basic `<Input type="datetime-local">`; internal state sync; formatted scheduled date display
- `app/Modules/Shop/Resources/js/Pages/Products/Form.tsx` — **Complete rewrite** with CRM-dashboard-style 2/3 + 1/3 grid layout (General, Pricing with sale scheduling, Gallery, Categories, Tags, Inventory, Shipping in left; Product Type, PublishSection, FeaturedImageSelector, Featured toggle in right sidebar)
- `app/Modules/Shop/Http/Controllers/ProductsController.php` — Added `applyScheduleStatus()` helper; validation accepts `future` status + `publish_date` (required_if:future); auto-switches to `published` if publish_date is in the past; `sale_price`, `sale_from`, `sale_to` validation
- `app/Modules/Shop/Models/Product.php` — Added `sale_price`, `sale_from`, `sale_to`, `publish_date` to fillable + casts
- `app/Modules/Posts/Resources/js/Pages/Form.tsx` — **Complete rewrite** with 2/3 + 1/3 grid; PublishSection sidebar; FeaturedImageSelector; Category & Tags card; submit button in sidebar
- `app/Modules/Posts/Controllers/PostController.php` — Added `applyPostScheduleStatus()` helper; validation accepts `future` status + `publish_date` (required_if:future); `published_at` set to now() when published, to publish_date when future, null for draft/archived
- `routes/console.php` — Replaced `properties:publish-scheduled` with unified `schedule:publish` (runs every minute)
- `resources/js/Components/shared/gallery-uploader.tsx` — Removed unused `disabled` prop from destructuring (TS fix)
- `resources/js/Components/shared/terms-selector.tsx` — Removed unused `Separator` import (TS fix)

### Publish/Schedule Data Flow

```
Product/Post/Property Form
  ├── PublishSection (radio: publish | draft | schedule)
  │     ├── "Publish" → status = "published", publish_date = null
  │     ├── "Draft"   → status = "draft", publish_date = null
  │     └── "Schedule" → status = "future", publish_date = DateTimePicker value
  │           ├── If publish_date ≤ now → status = "published" (immediate)
  │           └── If publish_date > now → status = "future" (scheduled)
  └── Save → Controller
        ↓
Cron (every minute): schedule:publish
  ├── Product::where('status','future')->where('publish_date','<=',now()) → published
  ├── Post::where('status','future')->where('published_at','<=',now()) → published
  └── Property::scheduled() → published
```

### Key Architectural Decisions
- **Unified scheduling logic** via `applyScheduleStatus()` / `applyPostScheduleStatus()` helpers in controllers (not services) — keeps it close to the form validation. If more modules need scheduling, extract to a shared `ScheduleService`.
- **CRM-dashboard form layout** (2/3 + 1/3 grid) applied to both Products and Posts forms. Properties form already had PublishSection imported.
- **No external drag-drop library** for GalleryUploader — uses existing `react-dropzone` dependency. Simpler than the 1012-line beta reference.
- **`schedule:publish` replaces `properties:publish-scheduled`** — single command handles all three entity types, runs every minute.


## Recent Changes — Hold Stock & Inventory Defaults (Stage 1)

**Date:** 2026-07-04

Implemented hold stock system and refactored `InventoryService` to support variations and centralized stock mutation.

### Stage 1.1 — Migration & Config
- Migration adds `hold_stock_minutes` (nullable int) and `incoming_stock` (int, default 0) to `products` table
- `config/shop.php` — added `hold_stock` (enabled, default_minutes) and `inventory` (default_low_stock_threshold) sections

### Stage 1.2 — InventoryService Refactor
- `adjust()` now accepts `Product|ProductVariation` — checks `$entity instanceof ProductVariation` to determine table
- `recordSaleForOrder(Order $order)` — iterates order items, calls `recordSale()` on each, respects `track_stock` flag
- `recordRefundForOrder(Order $order)` — same pattern for refunds
- `StockAdjustment` model — added `variation_id` FK and `variation()` BelongsTo relation
- `OrderItem` model — added `variation()` BelongsTo relation

### Stage 1.3 — Order Integration
- `CheckoutService::checkout()` — injects `InventoryService`, calls `recordSaleForOrder()` after order creation (prevents overselling)
- `OrdersController::storeAdmin()` — uses `recordSaleForOrder()` instead of raw `$product->decrement()`
- `OrdersController::updateAdmin()` — uses `recordRefundForOrder()` + `recordSaleForOrder()` instead of raw increment/decrement
- `OrdersController::updateStatus()` — removed duplicate `recordSale()` on completed; calls `recordRefundForOrder()` on cancelled
- `OrdersController::refund()` — uses `recordRefundForOrder()` instead of raw `increment()`

### Stage 1.4 — Expired Hold Release
- `ReleaseExpiredHolds` command — finds pending unpaid orders older than `hold_stock.default_minutes`, refunds stock, cancels them
- `routes/console.php` — registers `shop:release-expired-holds` every 5 minutes

### Files Changed
```
NEW:
  app/Console/Commands/ReleaseExpiredHolds.php
  app/Modules/Shop/database/migrations/*_add_hold_stock_and_incoming_stock_to_products.php

MODIFIED:
  app/Modules/Shop/Services/InventoryService.php   # Variation support, recordSaleForOrder, recordRefundForOrder
  app/Modules/Shop/Models/StockAdjustment.php      # +variation_id FK, variation() relation
  app/Modules/Shop/Models/OrderItem.php             # +variation() relation
  app/Modules/Shop/Services/CheckoutService.php     # Inject InventoryService, recordSaleForOrder
  app/Modules/Shop/Http/Controllers/OrdersController.php  # All stock mutations via InventoryService
  config/shop.php                                   # hold_stock + inventory config sections
  routes/console.php                                     # Register release-expired-holds command
```

## Recent Changes — Shop Settings UI (Stage 2)

**Date:** 2026-07-04

Refactored the Shop settings from a self-contained local-state component to a proper props-driven section wired to the database-backed settings system.

### Backend
- `ShopSettingsController::update()` — validates 14 fields (added `hold_stock_enabled`, `hold_stock_minutes`, `backorder_default`, `inventory_enabled`), stores with `group\ =\ 'shop'` via `Setting::updateOrCreate()`, booleans cast to `"1"`/`"0"` for DB
- `SettingsService::clearCache()` — added `'shop'` to group cache clear list

### Frontend
- `ShopSection.tsx` — refactored to accept `data`/`onChange`/`errors`/`loading`/`onSubmit` props (matching `PropertiesSection` pattern). Added 4 accordion sections: Product Defaults (status + backorder), Currency & Units, Inventory Management (hold stock toggle + minutes, low stock threshold, inventory tracking toggle), Feature Toggles (show_out_of_stock, reviews, wishlist, auto-complete)
- `settings.tsx` — added `ecomFormData`/`ecomErrors`/`ecomLoading` state + `useEffect` sync from `settings.Shop` + `handleEcomSubmit` handler posting to `admin.shop.settings.update` + wired `<ShopSection>` with proper props

### Files Changed
```
MODIFIED:
  app/Modules/Shop/Http/Controllers/ShopSettingsController.php  # New fields, group='shop' storage
  resources/js/Components/settings/sections/ShopSection.tsx           # Props-driven rewrite with 4 accordion sections
  resources/js/Pages/dashboard/admin/settings.tsx                          # Shop state + submit handler
  app/Services/SettingsService.php                                         # +Shop in clearCache
```

## Recent Changes — Flexible Shop Settings (Full Set + Settings UI)

**Date:** 2026-08-14

Replaced the small embedded `ShopSection` with a full **dedicated Shop Settings page** (`/admin/shop/settings`, page key `shop/settings/index`) covering store profile, selling locations, currency & pricing, tax options, checkout behaviour, plus the existing inventory/features toggles.

### New Backend
- `packages/shop-module/src/Support/Countries.php` — ISO 3166 alpha-2 map (`all()`, `name()`, `exists()`), served as `countries` prop.
- `packages/shop-module/src/Services/ShopSettingsService.php` — typed getters + defaults over `settings` table `group\ =\ 'shop'` (`baseCountry()`, `guestCheckoutEnabled()`, `requireCompanyField()`, `requireTerms()`, `checkoutDefaultCountry()`, `pricesIncludeTax()`, `taxBasedOn()`, `taxShipping()`, `taxDisplay()`, `currencyFormat()`, `isCountryAllowed()`, `clearCache()`).
- `ShopSettingsController::index()` — renders `shop/settings/index` with `settings`/`countries`/`currencies`; `update()` validates all groups (base_country `size:2`, selling_locations_mode `all|specific|except`, currency_position 4-way, separators, decimals, tax options, checkout toggles, units).
- Routes (`Routes/admin.php`): `GET /settings` → `settings.index`, `PUT /settings` → `settings.update` (both `admin.shop.*` prefix).
- **Service wiring**: `TaxService` (country by `tax_based_on` billing/shipping, inc-tax back-calc, `tax_shipping`), `ShippingService` (base-country fallback), `CheckoutService` (tax computed after coupon loop with shipping address + shipping total), `CurrencyService::format()` (4-way symbol position + separators/decimals via `currencyFormat()`).
- **Checkout guards** (`CheckoutController`): guest checkout redirect/login when disabled; `billing_address.company` required when `require_company_field`; `accepted_terms` accepted when `require_terms`; selling-locations policy enforced on billing + shipping country (`ValidationException`).
- **Shared props** (`ShopServiceProvider`): `shopSettings` (full array), `currencyFormat` (position/separators/decimals). Admin-only "Settings" nav item → `admin.shop.settings.index`.
- `config/shop.php` — `checkout`, `tax`, `selling_locations`, `formatting`, `store` default arrays.

### Frontend
- `Pages/Settings/Index.tsx` — accordion sections: Store Profile, Selling Locations, Currency & Pricing, Tax Options, Inventory, Checkout, Units of Measure, Features & Automation; typed `SettingsForm`; "None" country mapping for checkout default; PUT to `admin.shop.settings.update`.
- `Pages/Orders/Checkout.tsx` — `cart.discount` line, terms checkbox (when `require_terms`), company label, default country prefill, `orderTotal = cart.total + shippingPrice + taxTotal`.
- `lib/currency.ts` — rewritten to consume `currencyFormat` (thousands/decimal separators, 4-way position).

### Tests
- `tests/Feature/Modules/Shop/ShopSettingsTest.php` — 8 tests: page access, persistence via `ShopSettingsService`, validation, guest-checkout-off, require_terms, require_company_field, selling-locations block. **Note:** don't use `->component()` in Inertia assertions for shop pages (page files aren't resolvable in tests) — assert props only.

### Files Changed
```
NEW:
  packages/shop-module/src/Support/Countries.php
  packages/shop-module/src/Services/ShopSettingsService.php
  packages/shop-module/src/Resources/js/Pages/Settings/Index.tsx
  tests/Feature/Modules/Shop/ShopSettingsTest.php

MODIFIED:
  packages/shop-module/src/Http/Controllers/ShopSettingsController.php  # index() + extended update()
  packages/shop-module/src/Routes/admin.php                              # GET/PUT settings routes
  packages/shop-module/src/Http/Controllers/CheckoutController.php       # guest/company/terms/locations guards, auto-discount folding
  packages/shop-module/src/Services/TaxService.php, ShippingService.php, CheckoutService.php, CurrencyService.php
  packages/shop-module/src/ShopServiceProvider.php                       # Settings nav + shopSettings/currencyFormat shared props
  packages/shop-module/config/shop.php                                   # new settings defaults
  packages/shop-module/src/Resources/js/Pages/Orders/Checkout.tsx        # discount line, terms, company, default country
  packages/shop-module/src/Resources/js/lib/currency.ts                  # currencyFormat-aware formatting
```


## Recent Changes — Variation Attribute Pivot Table

**Date:** 2026-07-04

Refactored variation attribute storage from a JSON column to a proper relational pivot table, following WooCommerce's architecture but without its `wp_postmeta` performance pitfalls.

### Architecture

**Before:** Variation attributes stored as JSON in `product_variations.attributes` column:
```
[{attribute_id: 1, attribute_term_id: 2, name: "Red"}]
```
- Not queryable via SQL
- No referential integrity (orphaned term IDs possible)
- PHP-side looping for default variation matching

**After:** Two-layer storage:
1. **Pivot table `product_variation_attribute_term`** — authoritative source, fully indexed:
   ```
   product_variation_id | attribute_id | attribute_term_id
   ```
   Enables direct SQL filtering (`WHERE EXISTS` -> join-free) and FK constraints.

2. **JSON `attributes` column** — read-only cache for API responses (frontend receives same shape as before)

### Key Changes

| File | Change |
|------|--------|
| `.../2026_07_04_150000_create_product_variation_attribute_terms_table.php` | New migration: creates pivot table + migrates existing JSON data |
| `ProductVariationAttributeTerm.php` | New model: `variation()`, `attribute()`, `term()` BelongsTo |
| `ProductVariation.php` | Added `attributeTerms()` BelongsToMany via pivot, `syncAttributes()` (writes pivot + cache atomically), `scopeWhereAttributeTerm()` |
| `AttributeTerm.php` | Added `variations()` BelongsToMany for reverse lookup |
| `ProductVariationsController.php` | All 5 methods use `syncAttributes()` instead of raw JSON writes; `generate()` deduplicates via pivot |
| `Product.php` | `getDefaultVariation()` uses `whereHas('attributeTerms', count)` — no more PHP looping. Added `scopeWhereVariationAttributeTerm()` |

### Queryable Filtering

```php
// Find all variations that are Red
ProductVariation::whereAttributeTerm($redTermId)->get();

// Find all products with a Red, Size M variation
Product::whereVariationAttributeTerm($redTermId)
    ->whereVariationAttributeTerm($sizeMTermId)
    ->get();
```

This mirrors WooCommerce's lookup table approach but with native SQL joins on properly indexed columns — no key-value JOIN degradation.

### Data Model

```
attributes ──╮
             │
attribute_terms ──╮
                  │
product_variation_attribute_term ── product_variations ── products
       │                                                        │
       └── attribute_id (FK)  ──────────────── attribute_id ────┘
       └── attribute_term_id (FK)
       └── product_variation_id (FK)
```

### Files Changed

```
NEW:
  app/Modules/Shop/Models/ProductVariationAttributeTerm.php
  app/Modules/Shop/database/migrations/2026_07_04_150000_create_product_variation_attribute_terms_table.php

MODIFIED:
  app/Modules/Shop/Models/ProductVariation.php    # +attributeTerms(), +syncAttributes(), +scopeWhereAttributeTerm()
  app/Modules/Shop/Models/AttributeTerm.php        # +variations() BelongsToMany
  app/Modules/Shop/Models/Product.php              # getDefaultVariation() uses pivot, +scopeWhereVariationAttributeTerm()
  app/Modules/Shop/Http/Controllers/ProductVariationsController.php  # All methods use syncAttributes()
```

## Recent Changes — Brands & Product Taxonomy Expansion (2026-07-05)

### Backend — Tags

- **TagsController** — full CRUD (`index`, `store`, `update`, `destroy`), authorized via `ProductPolicy` (`viewAny/create`). All mutation endpoints return JSON `{ id, name }` when `$request->wantsJson()` (i.e., called with `Accept: application/json` header) — enables inline creation from the product form's TermsSelector.
- **ProductTag** model already existed from Phase 5.1; `withCount('products')` on index.
- **Permissions**: `shop.tags.manage` registered in `ShopModule::permissions()`, synced to DB via `permissions:sync`.

### Backend — Brands

- **ProductBrand** model — `name`, `slug` (auto-generated), `description`, `image` (URL), `sort_order`, `is_active`. Relations: `products()` (BelongsToMany via `product_product_brand` pivot). Scope: `active()`.
- **BrandsController** — full CRUD (same pattern as TagsController: JSON-when-wantsJson, ProductPolicy authorization, image/logo field support).
- **Migrations**: `create_product_brands_table` (brands table + pivot), `add_vendor_to_products_table` (`vendor` nullable string column).
- **Permissions**: `shop.brands.manage` registered + synced.
- **Product model** — `brands()` BelongsToMany relation added, `vendor` added to `$fillable`, all price casts changed from `decimal:2` to `float` (fixes storefront `.toFixed()` crash).
- **ProductVariation model** — price casts `decimal:2` → `float` (same fix).
- **StoreProductRequest** — `brands` validation rules and `vendor` field added (fixes brand not persisting on save).
- **ProductsController** — passes `ProductBrand::active()->get()` as `$brands` to index/create/edit views, syncs brands on store/update, eager loads `brands` in index.

### Frontend — Tags Admin

- `Shop/Tags/Index.tsx` — table with name, description, products count, toggle active, edit/delete dialogs.

### Frontend — Brands Admin

- `Shop/Brands/Index.tsx` — table with name, image/logo (thumbnail preview), description, products count, toggle active, edit/delete dialogs.

### Frontend — Product Form

- Brand `TermsSelector` with inline creation (`canAdd={true}` + `handleCreateBrand` fetch).
- Vendor free-text input field.
- All inline creation fetch handlers (`handleCreateCategory`, `handleCreateBrand`, `handleCreateTag`) now include both `"Content-Type": "application/json"` and `"Accept": "application/json"` headers — required because `$request->wantsJson()` checks Accept header.

### Frontend — Product Index

- Added **Categories** (col-span, max 2 badges + `+N` overflow), **Tags** (xl, max 2 badges + `+N` overflow), **Brands** (xl, all badges) columns between Type and Price.

### Frontend — TermsSelector Refactor

- **TermsSelector** (`@/Components/shared/terms-selector.tsx`) refactored from CSS absolute-positioned dropdown to **Radix `Popover` + `Portal`** — eliminates clipping by parent Card `overflow-hidden`.

### Posts Module — Tags Parity

- **PostTagController** — `update()` method added, returns JSON when wantsJson().
- Posts routes — `PUT /tags/{postTag}` registered.
- Posts Form.tsx — `addTag()` now POSTs to `dashboard.posts.tags.store` when tag doesn't exist.
- Posts Tags admin page at `Posts/Tags/Index.tsx` (create/edit/delete with posts count).
- Posts Tags nav item + frontend page registered in PostsModule.

### Key Bug Fixes

| Bug | Root Cause | Fix |
|-----|-----------|-----|
| `s.price.toFixed is not a function` on storefront | `decimal:2` cast returns string in Laravel 11 | Changed to `float` cast in Product + ProductVariation |
| Inline creation returns 302 instead of JSON | `$request->wantsJson()` checks Accept header; only Content-Type was set | Added `"Accept": "application/json"` to all fetch calls |
| Brand selection not persisting on save | `brands` field missing from `StoreProductRequest::$rules` | Added validation rules for `brands` + `vendor` |
| TermsSelector dropdown clipped | Parent `<Card>` has `overflow-hidden` which clips absolute-positioned dropdown | Refactored to Radix Popover + Portal (rendered at document root) |

### Files Changed

```
NEW:
  app/Modules/Shop/Http/Controllers/TagsController.php
  app/Modules/Shop/Http/Controllers/BrandsController.php
  app/Modules/Shop/Models/ProductBrand.php
  app/Modules/Shop/Resources/js/Pages/Tags/Index.tsx
  app/Modules/Shop/Resources/js/Pages/Brands/Index.tsx
  app/Modules/Shop/database/migrations/2026_07_05_000000_create_product_brands_table.php
  app/Modules/Shop/database/migrations/2026_07_05_000001_add_vendor_to_products_table.php
  app/Modules/Shop/Requests/StoreProductRequest.php

MODIFIED:
  app/Modules/Shop/Models/Product.php                # +brands() BelongsToMany, vendor fillable, float casts
  app/Modules/Shop/Models/ProductVariation.php       # float casts
  app/Modules/Shop/ShopModule.php               # +tags/brands nav, pages, permissions
  app/Modules/Shop/Routes/web.php                    # +tags routes, +brands routes
  app/Modules/Shop/Http/Controllers/ProductsController.php  # +brands sync, eager load, pass to views
  app/Modules/Shop/Resources/js/Pages/Products/Form.tsx     # +brand TermsSelector, vendor field, Accept headers
  app/Modules/Shop/Resources/js/Pages/Products/Index.tsx    # +Categories/Tags/Brands columns
  resources/js/Components/shared/terms-selector.tsx              # Refactored to Radix Popover + Portal
  app/Modules/Posts/Controllers/PostTagController.php   # +update(), JSON when wantsJson()
  app/Modules/Posts/Routes/web.php                      # +PUT tags/{postTag}
  app/Modules/Posts/Resources/js/Pages/Form.tsx          # +addTag() POSTs to tags.store
  app/Modules/Posts/PostsModule.php                     # +tags nav item + frontend page
```

## Shop Competitive Analysis (2026-07-04)

### Status Legend
- **DONE** — Implemented and deployed
- **WIP** — In progress
- **—** — Not yet implemented
- ✓ — Feature exists (always DONE)

### Cross-System Feature Matrix vs WooCommerce + Shopify

| # | Feature Axis | Our System | WooCommerce | Shopify | Gap |
|---|-------------|-----------|-------------|---------|-----|
| **P1** | **Product Types** | simple, variable, **grouped**, **external**, **digital**, **service**, **virtual** | simple, variable, grouped, external | physical, digital, gift card, subscription, custom — no formal type enum | **DONE** |
| **P2** | **Catalog Visibility** | **visible**, **catalog**, **search**, **hidden** | visible, catalog, search, hidden (per product) | publish/unpublish toggle + sales channels | **DONE** |
| **P3** | **Short Description** | `short_description` field ✓ | `short_description` ✓ | `short_description` ✓ | NONE |
| **P4** | **Global ID (GTIN/UPC/EAN)** | `global_unique_id` **DONE** | `global_unique_id` (GTIN/UPC/EAN/ISBN) | barcode + HS code | **DONE** |
| **P5** | **Featured** | `is_featured` bool ✓ | `featured` bool ✓ | featured via collection | NONE |
| **P6** | **Menu Order** | `menu_order` **DONE** | `menu_order` (int) | sort by custom | **DONE** |
| **P7** | **Virtual / Downloadable** | `virtual` + `downloadable` **DONE** + `ProductDownload` model | `virtual` (bool), `downloadable` (bool) + `downloads`, `download_limit`, `download_expiry` | digital file attachment | **DONE** |
| **P8** | **External/Affiliate** | `external_url` + `button_text` **DONE** | `external_url` + `button_text` (type=external) | external products via apps | **DONE** |
| **P9** | **Sold Individually** | `sold_individually` **DONE** | `sold_individually` (bool) | `requires_shipping` + qty rules | **DONE** |
| **P10** | **Min/Max Quantities** | `min_quantity` + `max_quantity` **DONE** | via plugins + variation `min_qty`/`max_qty` | min/max per product | **DONE** |
| **P11** | **Stock Status** | calculated: `stock_status` column + 3-way (instock/outofstock/onbackorder) + `isInStock()` + scopes | explicit `stock_status` (instock/outofstock/onbackorder) | `inventoryTracked` + status | **DONE** |
| **P12** | **Backorders** | `backorders` (no/notify/yes) ✅ | `backorders` (no/notify/yes) + `backorders_allowed` + `backordered` read-only | `continue_selling_when_out_of_stock` | **DONE** |
| **P13** | **Shipping Class** | — | `shipping_class` slug on product + class-based rates | carrier-calculated, no class concept | MEDIUM — needed for rate differentiation |
| **P14** | **Cross-sells / Up-sells** | — | `cross_sell_ids`, `upsell_ids`, `related_ids` | custom (via sections + apps) | MEDIUM — storefront conversion |
| **P15** | **Low Stock Alerts** | `low_stock_threshold` per product ✓ | same + `low_stock_amount` per product ✓ | `inventoryQuantity` + low stock alerts | NONE |
| **O1** | **Order Statuses** | pending, processing, completed, cancelled, refunded | pending, processing, **on-hold**, completed, cancelled, refunded, **failed**, **trash** | not_confirmed, confirmed, fulfilled, paid, partially_fulfilled, ready_for_pickup, restocked, expired | MEDIUM — missing on-hold, failed, trash |
| **O2** | **Partial Refunds** | full refund only (refund_amount, refund_notes) | multiple partial refunds with line item + tax + shipping breakdown + `api_refund` | `refundCreate` mutation with partial amounts per line item | HIGH — needed for real Shop |
| **O3** | **Draft Orders** | — | no native support | `draftOrderCreate` with full line item + discount + B2B company support | HIGH — admin order entry |
| **O4** | **Order Editing** | — | order items can be modified post-creation | `draftOrderUpdate` + `orderEditBegin` | HIGH — admin correction |
| **O5** | **Invoice** | — | invoice PDF generated via template | invoice URL via draft orders | MEDIUM — printable invoice |
| **O6** | **Order Notes** | `notes` field on Order | public + private order notes system with auto-notes | order timeline with events + custom notes | MEDIUM — customer-visible notes |
| **S1** | **Shipping Classes** | — | `product_shipping_class` taxonomy + per-class flat rates | not applicable (carrier rates) | MEDIUM — needed for flat rate |
| **S2** | **Carrier Integration** | — | via plugins (UPS/FedEx/USPS/DHL) | native UPS/FedEx/DHL with real-time rates, labels, tracking | HIGH — major UX gap |
| **S3** | **Local Delivery / Pickup** | — | local pickup + local delivery shipping methods | local delivery + store pickup at checkout | MEDIUM — physical stores |
| **S4** | **Weight-Based Shipping** | weight on product, not used in rate calc | weight used in flat rate & carrier calcs | weight used in carrier rates | MEDIUM — needed for carrier |
| **T1** | **Tax Classes** | `tax_class` string on product, not wired | standard + reduced + zero rate classes with different rates | `taxLine` per line item, auto-calculated via Avalara | LOW — nice-to-have |
| **T2** | **Tax Status** | `is_taxable` bool | `tax_status` (taxable/shipping/none) | each line item has taxable boolean | LOW — shipping-only tax |
| **D1** | **BOGOF / Buy X Get Y** | — | via Smart Coupons plugin | buy-x-get-y, free items, fixed amount off | MEDIUM — promotional power |
| **D2** | **Product-Specific Discount** | coupons can target products via pivot | `fixed_product` type (per-product fixed discount) | via `entitledProductIds` | MEDIUM — per-product discount |
| **D3** | **Tiered Discounts** | — | via plugins or table rate shipping | via quantity breaks on `DiscountAutomaticNode` | MEDIUM — wholesale pricing |
| **D4** | **Coupon Stacking** | single coupon per order only | multiple coupons per order (3rd-party) | up to 5 discount codes per order | MEDIUM — multi-coupon |
| **I1** | **Multi-Location** | — | multi-stock via plugin or HPOS | multi-location native (locations, inventory per location) | MEDIUM — multi-warehouse |
| **I2** | **Incoming Stock Wired** | `incoming_stock` wired: admin form + storefront display ✅ | not standard | `inventoryItem.unitCost` + incoming via purchase orders | **DONE** |
| **I3** | **Purchase Orders / Suppliers** | — | via plugins (ATUM, WP Inventory) | via 3PL / purchase orders (Shopify Plus) | LOW — advanced ops |
| **C1** | **Multiple Addresses** | 1 billing + 1 shipping per user | unlimited addresses per customer | multiple addresses per customer | LOW — convenience |
| **C2** | **Customer Groups** | — | customer groups for role-based pricing | customer segments (marketing, not pricing) | MEDIUM — B2B requirement |
| **B1** | **B2B / Wholesale** | — | via Wholesale for WooCommerce plugin | native (Companies, Locations, Contacts with `purchasingEntity`, tiered pricing, net terms, quantity rules) | HIGH — major market |
| **B2** | **Net Terms** | — | via plugins | `paymentTermsTemplate` on company location | MEDIUM — B2B payments |
| **SU1** | **Subscriptions** | — | via WooCommerce Subscriptions ($199/yr) | via Shopify Subscriptions app (native) | HIGH — recurring revenue |
| **GC1** | **Gift Cards** | — | via WooCommerce Gift Cards plugin | native -> `giftCard` query, purchase, redeem, `GiftCardTransaction` | MEDIUM — storefront feature |
| **MC1** | **Multi-Currency** | single EUR | via GeoIP + currency switcher plugins | Shopify Markets (auto-conversion, per-market pricing, 133+ currencies) | HIGH — global reach |
| **ML1** | **Multi-Language** | — | via WPML / Polylang plugins | Shopify Markets + Translate & Adapt | LOW — nice-to-have |
| **POS1** | **Point of Sale** | — | via Square + POS plugins | Shopify POS (retail + inventory sync + hardware) | LOW — retail stores |
| **M1** | **Abandoned Cart** | 3-step email ✓ | built-in email (1 email) | email + SMS + push via Shopify Flow + apps | NONE |
| **M2** | **Email Campaigns** | transactional only | via plugins (MailPoet, etc.) | Shopify Email (10k emails/mo free) | MEDIUM — bulk campaigns |
| **AP1** | **Public REST API** | Inertia + internal JSON | REST API (full CRUD products, orders, customers) | GraphQL Storefront + Admin APIs | HIGH — developer ecosystem |

### Gap Priorities (Highest Impact First)

| Priority | Gap | Impact | Effort | Rationale |
|----------|-----|--------|--------|-----------|
| **P0** | **Partial refunds with line item breakdown** | HIGH | Medium | Full Shop requirement; legal/compliance |
| **P0** | **Multi-currency** | HIGH | Large | Global reach; top-requested feature in competitive matrix |
| **P0** | **B2B / Wholesale** | HIGH | Large | Opens enterprise market; Shopify's biggest growth segment |
| **P0** | **Subscriptions** | HIGH | Large | Recurring revenue; SaaS/hosting market |
| **P1** | **Draft orders (admin order entry)** | HIGH | Medium | Admin order flow incomplete |
| **P1** | **Order editing** | HIGH | Medium | Admin correction needed |
| **P1** | **Public REST API** | HIGH | Extra Large | Developer ecosystem; integrations |
| **P2** | **Carrier shipping integration** | HIGH | Extra Large | UX gap vs competitors |
| **P2** | **Shipping classes** | MEDIUM | Medium | Rate differentiation |
| **P2** | **Invoice PDF** | MEDIUM | Medium | Professional appearance |
| **P2** | **BOGOF + tiered discounts** | MEDIUM | Medium | Promotional power |
| **P3** | **Coupon stacking** | MEDIUM | Small | Config change + validation |
| **P3** | **Multi-location inventory** | MEDIUM | Large | Multi-warehouse |
| **P3** | **Gift cards** | MEDIUM | Medium | Storefront feature |
| **P3** | **Customer groups** | MEDIUM | Medium | B2B prerequisite |
| **P4** | **Email campaigns** | MEDIUM | Large | Marketing automation |
| **P4** | **Multi-language** | LOW | Extra Large | Nice-to-have |
| **P4** | **POS** | LOW | Extra Large | Retail store requirement |
| **P4** | **Purchase orders / suppliers** | LOW | Large | Advanced ops workflow |

### Best-of-Both Patterns

| Pattern | Source | Why Better |
|---------|--------|-----------|
| **Product attribute pivot storage** | WooCommerce | Referential integrity, SQL queryable — we already did this ✓ |
| **Stock status as explicit column** | WooCommerce | Separate from quantity, enables onbackorder state — already implemented ✓ |
| **Backorders 3-way enum (no/notify/yes)** | WooCommerce | `notify` communicates delay to customer — already implemented ✓ |
| **Partial refunds with line item breakdown** | WooCommerce | Legal compliance, partial order fulfillment |
| **Quantity rules (sold_individually + min/max)** | WooCommerce | Cart integrity, B2B requirement — already done ✓ |
| **Draft orders with B2B purchasing entity** | Shopify | Admin order entry, complete workflow |
| **Multi-currency with per-market pricing** | Shopify | Global reach without geoIP complexity |
| **B2B with company/location/contact hierarchy** | Shopify | Structured B2B vs WooCommerce flat customer groups |
| **Abandoned cart multi-step sequence** | Both | Already implemented ✓ |
| **Carrier shipping with real-time rates** | Shopify | Better UX than WooCommerce plugin hell |
| **Webhook HMAC signing** | Both | Already implemented ✓ |
| **Hold stock with auto-release** | Both | Already implemented ✓ — better than WooCommerce |

## Recent Changes — Code Audit & Phase 20 Completion (2026-07-05)

### Bugs Fixed

| Bug | File | Fix |
|-----|------|-----|
| Posts Form.tsx inline tag creation silently broken — missing `Accept: application/json` header | `Posts/Resources/js/Pages/Form.tsx:92` | Added `"Accept": "application/json"` |
| Storefront Show.tsx ignored backend `active_price` — expired sales shown as active | `Storefront/ProductDetail.tsx:90-96` | Use `active_price` from backend, added `active_price` to `Variation` interface |
| `ReleaseExpiredHolds` ignored per-product `hold_stock_minutes` | `ReleaseExpiredHolds.php:17` | Now evaluates per-order minimum hold minutes |
| `incoming_stock` was read-only badge (no edit input, storefront silent) | `Form.tsx:759`, `Show.tsx:44` | Added editable input in form + `"(X incoming)"` in storefront |
| `product.stock_changed` webhook dispatched but missing from `ShopModule::getNotificationTypes()` | `ShopModule.php:354` | Added registration with `default_enabled: false` |
| Stale `allow_backorders` boolean column on both `products` and `product_variations` | Migration `2026_07_05_134652` | Dropped both columns |
| Variation low stock threshold hardcoded to `5` instead of using parent's | `Storefront/ProductDetail.tsx:126` | Now reads `product.low_stock_threshold` |
| `isVirtual()` didn't account for `digital`/`service` types | `Product.php:346-349` | Updated to include both new types |

### Features Added

| Feature | Files |
|---------|-------|
| `digital` and `service` product type buttons in admin form | `Form.tsx`, `Product.php` |
| `Product::isDigital()`, `Product::isService()`, `Product::isOutOfStock()` | `Product.php` |
| `ProductVariation::isLowStock()`, `ProductVariation::isOutOfStock()` | `ProductVariation.php` |
| Stock scopes on `ProductVariation` (`scopeInStock`, `scopeOnBackorder`, `scopeOutOfStock`, `scopeLowStock`, `scopeStockStatus`) | `ProductVariation.php` |
| `Product::scopeOutOfStock()`, `Product::scopeStockStatus()` | `Product.php` |
| `ProductVariation` saving hook auto-calculates `stock_status` (mirrors Product behavior) | `ProductVariation.php:boot()` |
| `CartController` validates parent stock when variation uses `usesParentStock()` | `CartController.php:72-74` |
| Storefront grid shows `"Will ship when back in stock"` for `backorders='notify'` | `Storefront/ProductGrid.tsx:174` |
| `incoming_stock` editable admin input | `Form.tsx:759-765` |
| `product.stock_changed` notification type registration | `ShopModule.php:354-362` |

### Migrations

- `2026_07_05_134652_drop_allow_backorders_from_products.php` — Drops stale `allow_backorders` boolean from both `products` and `product_variations` tables

### Phase 20 Verification

- **176/176 tests passing**
- **Pint clean**, **typecheck clean**, **production build succeeds**

## Recent Changes — Attribute Swatches & Display Types (2026-07-08)

### Backend

- **Migrations**: `2026_07_08_000002_add_display_type_to_attributes_table.php` (+`display_type` varchar 20 default `'text'`), `2026_07_08_000003_add_swatch_value_to_attribute_terms_table.php` (+`swatch_value` varchar 500 nullable)
- **Attribute.php** — added `DISPLAY_TYPES` constant (`text`, `color`, `image`, `button`), `display_type` to `$fillable` + `$casts`
- **AttributeTerm.php** — added `swatch_value` to `$fillable`
- **StoreAttributeRequest.php** — validates `display_type` (`in:text,color,image,button`)
- **StoreAttributeTermRequest.php** — validates `swatch_value` (`nullable|string|max:500`)
- **StorefrontController::show()** — passes `display_type` and `swatch_value` in both `attributes` (variation selectors) and `visibleAttributes` (info table)
- Single `swatch_value` column stores hex codes (color) or image URLs (image), interpreted based on parent attribute's `display_type`

### Frontend — Admin (Attributes/Index.tsx)

- Create/Edit attribute dialogs: **Display Type** `<Select>` with icons (Type, Palette, ImageIcon, Square) + descriptions
- Create/Edit term dialogs: conditionally show color picker (hex input + native `<input type="color">`) for `color` type, URL input with preview for `image` type, nothing for `text`/`button`
- Term chips show swatch preview (colored circle or image thumbnail) next to term name

### Frontend — Storefront

- **AttributeSelector.tsx** — data-driven 4-mode rendering: `text` → Badge chips, `color` → 40×40 circular swatches with Check icon, `image` → circular swatches with `backgroundImage`, `button` → rectangle buttons with text
- **COLOR_NAMES** map retained as fallback when `display_type` is `color` but `swatch_value` is null (legacy support)
- **Storefront/ProductDetail.tsx** — Additional Information table shows 12px swatch preview dots (color or image) next to term names

### Frontend — Improved "Add existing" Attribute Dropdown

Both **Form.tsx** and **VariationsManager.tsx** now show a disabled "All added" button + inline text when all global attributes are assigned, instead of a misleading "Add existing" dropdown trigger:
- No attributes exist → disabled "Add existing" + "No attributes yet. Create one" link
- Some available → functional `<Select>` with available attributes
- All added → disabled "All added" badge + "All global attributes are assigned to this product." text

### Swatch Data Model

```
Attribute.display_type: text | color | image | button  (default: text)
AttributeTerm.swatch_value: null for text/button, hex code for color, URL for image
```

### Files Changed

```
NEW:
  app/Modules/Shop/database/migrations/2026_07_08_000002_add_display_type_to_attributes_table.php
  app/Modules/Shop/database/migrations/2026_07_08_000003_add_swatch_value_to_attribute_terms_table.php

MODIFIED:
  app/Modules/Shop/Models/Attribute.php         # DISPLAY_TYPES constant, display_type fillable/cast
  app/Modules/Shop/Models/AttributeTerm.php     # swatch_value fillable
  app/Modules/Shop/Http/Controllers/AttributesController.php   # passes display_type/swatch_value to views
  app/Modules/Shop/Http/Controllers/StorefrontController.php   # show() passes display_type + swatch_value
  app/Modules/Shop/Requests/StoreAttributeRequest.php          # display_type validation
  app/Modules/Shop/Requests/StoreAttributeTermRequest.php      # swatch_value validation
  app/Modules/Shop/Resources/js/Pages/Attributes/Index.tsx     # Display Type selector, swatch previews, color picker
  app/Modules/Shop/Resources/js/Pages/Products/Form.tsx        # 3-state "Add existing" dropdown
  app/Modules/Shop/Resources/js/components/VariationsManager.tsx  # 3-state "Add existing" dropdown
  app/Modules/Shop/Resources/js/components/AttributeSelector.tsx  # Data-driven 4-mode swatch rendering
  app/Modules/Shop/Resources/js/Pages/Storefront/ProductDetail.tsx      # Inline swatch dots in Additional Information
```

## Phased Implementation Plan (Phases 19–38)

### Phase 19 — Product Type Expansion ✅ COMPLETED (2026-07-04)
**Impact: HIGH | Effort: Medium | Dependencies: None**

All items completed:
- Add `grouped` product type (bundle multiple simple products as a virtual bundle)
- Add `external`/`affiliate` product type with `external_url` + `button_text` fields
- Add `digital` (downloadable software) and `service` (bookable services) product types
- Add `virtual` bool (services, no shipping) + `downloadable` bool (digital goods)
- Add file management for downloadable products: `ProductDownload` model, `download_limit`, `download_expiry`
- Add `catalog_visibility` enum (visible/catalog/search/hidden)
- Add `global_unique_id` for GTIN/UPC/EAN/ISBN standard
- Add `sold_individually` bool, `min_quantity`, `max_quantity` fields
- Add `menu_order` for custom sorting
- Permissions: no new permissions (uses existing `shop.products.*`)
- **Models**: Product (fields + fillable), ProductDownload (new), product_grouped_products pivot
- **Controller**: ProductsController — syncGroupedItems(), syncDownloads(), expanded import/export
- **Form**: grouped items picker, external URL, downloadable files manager, catalog visibility, quantity rules
- **Storefront**: affiliate badge + external link, grouped product display, download links, type badges, badges for digital/service types
- **Migrations**: 3 new (product columns, product_downloads, product_grouped_products)

*Audit additions (2026-07-05):* `Product::isDigital()`, `Product::isService()` helper methods, frontend type selector buttons for `digital`/`service`, `isVirtual()` updated to cover both new types

### Phase 20 — Stock & Inventory Depth ✅ COMPLETED (2026-07-05)
**Impact: HIGH | Effort: Small | Dependencies: Phase 19**

All items completed:

**Pre-existing:**
- `backorders` 3-way enum (`no`/`notify`/`yes`) — migration from `allow_backorders` bool ✅
- `stock_status` column with `instock`/`outofstock`/`onbackorder` states ✅
- `Product::scopeInStock()` — handles 3-way + non-tracked ✅
- `Product::scopeOnBackorder()` + `Product::isOnBackorder()` ✅
- `Product::scopeLowStock()` — filter products low on stock ✅
- `Product::isLowStock()` + `Product::isInStock()` ✅
- `ProductVariation::isInStock()` — delegates to parent via `usesParentStock()` ✅
- `ProductVariation::isOnBackorder()` — delegates to parent ✅
- Low stock email template (`low-stock-alert.blade.php`) ✅
- `InventoryService::dispatchStockChanged()` — fires `product.stock_changed` + `product.low_stock` webhooks ✅
- Storefront badges ("On backorder" with notify explanation, "Only X left", "Out of stock") ✅

**Added in audit (2026-07-05):**
- `Product::isOutOfStock()` + `Product::scopeOutOfStock()` — query/invert stock state ✅
- `Product::scopeStockStatus(string $status)` — filter by explicit status ✅
- `ProductVariation stock scopes` — `scopeInStock`, `scopeOnBackorder`, `scopeOutOfStock`, `scopeLowStock`, `scopeStockStatus` ✅
- `ProductVariation::isLowStock()` — reads parent `low_stock_threshold` ✅
- `ProductVariation::isOutOfStock()` ✅
- `ProductVariation stock_status auto-calculation` — saving hook recalculates on quantity change (mirrors Product behavior) ✅
- `ProductVariation::scopeLowStock()` — uses `manage_stock='true'` filter ✅
- `CartController` parent stock validation — variations using `usesParentStock()` now check parent product stock ✅
- `ReleaseExpiredHolds` per-product override — uses `hold_stock_minutes` from each order's products, falls back to global config ✅
- `incoming_stock` wired: editable input in admin form + `"(X incoming)"` in storefront ✅
- `product.stock_changed` registered in `ShopModule::getNotificationTypes()` ✅
- `allow_backorders` stale boolean column dropped from both tables (migration `2026_07_05_134652`) ✅
- Storefront grid backorder badge shows `"Will ship when back in stock"` for `notify` mode ✅
- Storefront `isLowStock` uses `product.low_stock_threshold` instead of hardcoded `5` ✅
- Storefront uses backend `active_price` instead of reimplementing sale logic (fixes expired sale display) ✅
- **Scope fix**: `scopeOnBackorder` redundant `orWhere('stock_quantity', 0)` removed ✅
- **Scope fix**: `scopeLowStock` now checks `whereNotNull('low_stock_threshold')` ✅
- **Models (new methods)**: `Product::isDigital()`, `Product::isService()`, `ProductVariation::isLowStock()`, `ProductVariation::isOutOfStock()` ✅

### Phase 21 — Order & Refund Expansion
**Impact: HIGH | Effort: Medium | Dependencies: Phase 19**

- Add `on-hold`, `failed`, `trash` order statuses
- Add status transition workflow (which transitions allowed)
- Implement partial refunds: new `OrderRefund` model with `items` JSON (line item id, quantity, amount, tax), `shipping_refund`, `created_by`
- Refactor refund controller: accept line item breakdown, auto-restock per item
- Implement order editing: add/remove/change items, prices. Log changes to OrderAction
- Implement draft orders: create order manually via admin UI, mark as draft, convert to pending on checkout
- Add invoice PDF generation (using barryvdh/laravel-dompdf or similar)
- Add order notes system: public (customer-visible) + private (admin-only) notes
- **Models**: OrderRefund, OrderNote
- **Controllers**: OrdersController (refund refactor, edit, notes), DraftOrdersController
- **Frontend**: refund dialog with line item checkboxes, edit order modal, notes timeline, invoice button
- **Permissions**: `shop.orders.create`, `shop.orders.refund`

### Phase 22 — Discount Engine Expansion
**Impact: MEDIUM | Effort: Medium | Dependencies: None**

- Add BOGOF coupon type: `buy_x_get_y` with `free_product_id`, `buy_quantity`, `free_quantity`
- Add product-specific discount type: `fixed_product` (fixed discount on specific products), `percentage_product`
- Add tiered/quantity-break discounts: `CartDiscount` model with threshold rules (`min_qty`, `discount_percent`)
- Add coupon stacking support: allow multiple active coupons per order, validate stacking rules
- **Models**: Coupon type expansion, CartDiscount
- **Service**: CouponService stacking validation, BOGOF calculation
- **Form**: new coupon type options, tiered discount rules UI
- **Storefront**: multiple coupon inputs, automatic tiered discount display

### Phase 23 — Storefront Enhancement
**Impact: MEDIUM | Effort: Medium | Dependencies: Phase 19, 20**

- Add attribute-based filtering (color swatches, size dropdowns) using existing AttributeTerm
- Add in_stock/on_backorder/out_of_stock badges per product card
- Add sale badge ("Sale" with % off calculated from compare_price)
- Add stock quantity display ("Only 3 left") for low stock products
- Add image gallery with lightbox (click thumbnail → fullscreen carousel)
- Add variations selector (size/color radio buttons on product detail, update price/image)
- Add quantity selector with min/max enforcement (`min_quantity`/`max_quantity`/`sold_individually`)
- Add related products carousel (same category, different product)
- Add upsells/cross-sells display on product detail + cart
- Add product tabs (Description / Additional Info / Reviews)
- **Components**: ProductGallery, VariationSelector, ProductTabs, RelatedProducts, ImageLightbox
- **Controller**: StorefrontController — pass relations, price ranges, availability per variation

### Phase 24 — Checkout & Cart Enhancement
**Impact: MEDIUM | Effort: Medium | Dependencies: Phase 20, 23**

- Add cart persistence across sessions for guests (use cart token saved to localStorage + DB)
- Add shipping calculator in cart (estimate shipping by country, shows methods + prices)
- Add estimated tax display in cart (based on billing address)
- Add checkout progress indicator (Cart → Shipping → Payment → Review)
- Add payment gateway selection UI (Stripe, cash on delivery, bank transfer)
- Add order summary review step (all items, totals, addresses, method)
- Add guest account creation during checkout (opt-in checkbox, creates User after order)
- Add cross-sell suggestions in cart ("You might also like...")
- **Components**: CheckoutProgress, PaymentMethodSelector, OrderReview, ShippingCalculator
- **Controller**: CheckoutController refactor (multi-step state management)

### Phase 25 — Shipping Depth
**Impact: MEDIUM | Effort: Medium | Dependencies: Phase 23**

- Add `ShippingClass` model: name, slug, description, sort_order
- Add `shipping_class_id` to Product model
- Add flat rate per class calculation in `ShippingMethod`: per-class base cost, per-class per-item cost
- Add local delivery shipping method (fixed cost, defined postal codes)
- Add local pickup shipping method (free, location address display)
- Weight-based shipping calculation (base rate + per-kg rate)
- **Models**: ShippingClass
- **Service**: ShippingService — class-based rate matching, weight calculation
- **Form**: ShippingClass CRUD, class assignment on product form
- **Storefront**: pickup location display, delivery date estimate

### Phase 26 — Tax Depth
**Impact: LOW | Effort: Small | Dependencies: None**

- Add tax classes: `TaxClass` model (name, slug, description), assigned to TaxRate
- Add `tax_status` enum to Product (taxable/shipping/none) replacing `is_taxable` bool
- Add `tax_exempt` at Order level (for B2B tax-exempt customers)
- **Models**: TaxClass
- **Service**: TaxService — multi-class rate lookup, order-level exemption
- **Form**: TaxClass CRUD, tax_status selector on product form

### Phase 27 — Customer & Account Depth
**Impact: MEDIUM | Effort: Medium | Dependencies: Phase 21**

- Add multiple addresses per user: `UserAddress` model (type: billing/shipping, is_default)
- Add customer groups/segments: `CustomerGroup` model (name, slug, description)
- Add customer notes (private admin notes on customer profile, via `OrderNote` or `CustomerNote`)
- Add rich order history page with search, filters (status, date range), export
- Add downloadable products section in account (purchased downloads with expiry)
- Add wishlist UI (WishlistButton + Wishlist/Index.tsx ✅, full page enhancements)
- Add saved payment methods (Stripe SetupIntents, stored payment methods)
- **Models**: UserAddress, CustomerGroup, CustomerNote (or reuse OrderNote), Wishlist
- **Controllers**: CustomerAddressController, WishlistController, UserPaymentMethodsController
- **Permissions**: none (uses existing)

### Phase 28 — B2B / Wholesale
**Impact: HIGH | Effort: Large | Dependencies: Phase 20, 22, 26, 27**

- Add `Company` model: name, tax_id, email, phone, address, is_active
- Add `CompanyLocation` model: name, address, is_primary, BelongsTo Company
- Add `CompanyContact` model: user_id, company_id, location_id, role (buyer/approver/admin)
- Add tiered pricing: `CustomerGroup` gets `price_list` with percentage markup/discount per product
- Add net terms: `PaymentTerm` model (due_days, discount_percent, discount_days) assigned to Company
- Add quantity rules per product for B2B: `min_b2b_quantity`, `max_b2b_quantity`, `b2b_price`
- Add B2B checkout flow: company context → tiered pricing → net terms → purchase order number
- **Models**: Company, CompanyLocation, CompanyContact, PriceList, PaymentTerm, PriceListEntry
- **Controllers**: CompanyController, CompanyLocationController, PriceListController
- **Permissions**: `shop.companies.view`, `shop.companies.manage`, `shop.price_lists.manage`
- **Frontend**: B2B pricing in storefront, company selector, net terms display

### Phase 29 — Subscriptions
**Impact: HIGH | Effort: Large | Dependencies: Phase 19, 24**

- Add `SubscriptionPlan` model: name, description, interval (day/week/month/year), interval_count, trial_days, price, signup_fee, is_active
- Add `Subscription` model: user_id, plan_id, status (active/paused/cancelled/expired), start_date, next_billing, end_date, trial_ends_at
- Add recurring billing: console command generates orders for due subscriptions
- Add subscription management in account: view plans, upgrade/downgrade, cancel, pause
- Add free trial support: `trial_days`, `trial_ends_at`, auto-convert to paid
- Add webhook events: `subscription.created`, `subscription.updated`, `subscription.cancelled`, `subscription.expired`
- **Models**: SubscriptionPlan, Subscription, SubscriptionOrder
- **Services**: SubscriptionService (create, cancel, pause, resume, generateOrders)
- **Controllers**: SubscriptionPlanController (admin), SubscriptionController (customer)
- **Permissions**: `shop.subscriptions.view`, `shop.subscriptions.manage`
- **Console**: `shop:process-subscriptions` daily

### Phase 30 — Gift Cards
**Impact: MEDIUM | Effort: Medium | Dependencies: Phase 19 (virtual/downloadable)**

- Add gift card product type (extends virtual, sets `is_gift_card`)
- Add `GiftCard` model: code (unique), initial_balance, remaining_balance, purchaser_id, recipient_email, message, expires_at, is_active
- Add gift card purchase flow: select amount → cart → checkout → email delivery to recipient
- Add gift card redemption flow: apply code at checkout, validate remaining balance
- Add gift card balance checking page (public: enter code, see balance)
- Add `GiftCardTransaction` model: gift_card_id, order_id, amount, type (purchase/redeem/refund)
- **Models**: GiftCard, GiftCardTransaction
- **Controllers**: GiftCardController (public), GiftCardsController (admin)
- **Permissions**: `shop.gift_cards.manage`
- **Storefront**: gift card product page, balance checker, apply in checkout

### Phase 31 — Multi-Currency
**Impact: HIGH | Effort: Medium | Dependencies: Phase 24**

*Pre-existing: Currency model + admin CRUD + CurrencyService (convert/format/getDefault) + CurrenciesController + currencies migration ✅*

Remaining work:
- Add currency detection (browser Accept-Language, IP geolocation or saved preference)
- Add currency switcher UI in store header
- Wire auto-conversion at checkout: convert product prices × exchange rate, display in selected currency
- Add per-product fixed prices in multiple currencies via `ProductPrice` model (product_id, currency_id, price)
- Add exchange rate sync command (optional: fetch from free API like exchangerate-api.com)
- **Models**: ProductPrice (Currency model exists)
- **Controllers**: CurrencySwitchController (storefront)
- **Permissions**: `shop.currencies.manage` (already registered)

### Phase 32 — POS (Point of Sale)
**Impact: LOW | Effort: Extra Large | Dependencies: Phase 21, 27**

- Add POS order creation page (optimized for touch/tablet)
- Add product lookup via search + barcode scanner
- Add cart builder with add/remove/qty (touch-friendly)
- Add customer lookup + attach to order
- Add payment capture: stripe terminal, cash, card
- Add receipt printing (browser print + thermal printer support via ESC/POS format)
- Add offline mode (queue orders locally, sync when online)
- **Note**: Phase 32 can be deferred until retail stores become a target market

### Phase 33 — Marketing & Analytics
**Impact: MEDIUM | Effort: Medium | Dependencies: Phase 21, 24**

- Add abandoned cart SMS recovery (via Twilio or similar)
- Add bulk email campaigns: `Campaign` model, send via MailService with queue, open/click tracking
- Add marketing analytics dashboard: subscribers, campaign stats, conversion funnel (cart→checkout→purchase)
- Add conversion funnel analytics: page views → add to cart → checkout → purchase
- Add product performance reports: sales by product/category/attribute, margin analysis
- Add customer lifetime value (LTV) calculation
- **Models**: Campaign, CampaignRecipient, AnalyticsEvent
- **Permissions**: `shop.marketing.manage`

### Phase 34 — API & Developer Experience
**Impact: HIGH | Effort: Extra Large | Dependencies: All CRUD phases**

- Add public REST API: Products CRUD, Orders CRUD, Customers CRUD, Categories, Reviews
- Add API authentication (Sanctum tokens, OAuth)
- Add API rate limiting (100 req/min per token)
- Add API documentation (OpenAPI/Swagger spec via `l5-swagger` or similar)
- Add GraphQL endpoints for storefront (read-only for products, categories, reviews)
- Fix module TypeScript typechecking: add `app/Modules/*/Resources/js` to `tsconfig.json` include
- Add migration tests: `RefreshDatabase` + schema assertions for each migration
- **Controllers**: Api\ProductsController, Api\OrdersController, Api\CustomersController, Api\CategoriesController
- **Permissions**: `shop.api.access`

### Phase 35 — Multi-Language
**Impact: LOW | Effort: Extra Large | Dependencies: All UI phases**

- Add i18n support for storefront: `locale` cookie/session, `Lang` facade with custom loader
- Add translation files for default strings (product, cart, checkout, account)
- Add `ProductTranslation` model for per-language product content (name, description, short_description)
- Add `CategoryTranslation` model for per-language category names
- Add language switcher in store header
- **Models**: ProductTranslation, CategoryTranslation, Translation

### Phase 36 — Shipping Carrier Integration
**Impact: HIGH | Effort: Extra Large | Dependencies: Phase 25**

- Add UPS API integration: rate quotes, label generation, tracking
- Add FedEx API integration: rate quotes, label generation, tracking
- Add DHL API integration: rate quotes, label generation, tracking
- Add real-time rate calculation at checkout (display carrier rates when address entered)
- Add tracking number management on Orders (input tracking number, auto-email customer)
- Add shipping label generation (download PDF label from carrier)
- **Services**: CarrierService (abstract), UpsCarrier, FedExCarrier, DhlCarrier
- **Config**: carrier API keys stored in settings
- **Permissions**: `shop.carriers.manage`

### Phase 37 — Purchase Orders & Supplier Management
**Impact: LOW | Effort: Large | Dependencies: Phase 20**

- Add `Supplier` model: name, contact, email, phone, address, lead_time_days
- Add `PurchaseOrder` model: supplier_id, status (draft/sent/confirmed/received/cancelled), expected_date, items JSON
- Add stock reorder recommendations: when stock < low_stock_threshold + lead_time consumption, suggest PO
- Add receiving UI: mark PO received, auto-increment stock quantities
- **Models**: Supplier, PurchaseOrder, PurchaseOrderItem
- **Permissions**: `shop.suppliers.view`, `shop.suppliers.manage`, `shop.purchase_orders.*`

### Phase 38 — Migration & Go-Live
**Impact: — | Effort: Medium | Dependencies: All phases**

- Migration scripts for all new columns/tables (cumulative)
- Database seeders for new permissions
- Performance optimization: index review, query profiling, eager loading audit
- Storefront caching strategy (full-page cache for product pages)
- Security audit: XSS, CSRF, SQL injection review across all new code
- Documentation update: API docs, admin manual, developer guide
- Final test suite: ensure all 176+ existing tests pass + new tests for each phase

## Recent Changes — Cart & Checkout Fixes (2026-07-06)

### Bugs Fixed

| Bug | Root Cause | Fix | Files |
|-----|-----------|-----|-------|
| **Subtotal/Total €NaN on cart page** | `subtotal`/`total` are Eloquent accessors not in `$appends` — Inertia serialization skipped them | Added `$appends = ['subtotal', 'total']` to Cart model | `app/Modules/Shop/Models/Cart.php` |
| **Quantity +/- buttons broken** | `router` import removed but `reloadCart()` uses `router.reload()` | Added `router` back to import | `ShoppingCart.tsx` |
| **Cart sidebar missing product images/names** | `HandleInertiaRequests` only shared `product_id`, not product data | Load `items.product` relation, include `name`/`slug`/`thumbnail_url` | `HandleInertiaRequests.php`, `SiteHeader.tsx`, `StoreLayout.tsx` |
| **Cart sidebar had no quantity controls** | Only had a remove (X) button | Added +/- buttons with fetch-based live updates | `SiteHeader.tsx` |
| **Checkout showed admin dashboard sidebar** | Rendered `AuthenticatedLayout` for logged-in users | Changed to always use `StoreLayout` (frontend header/footer) | `Checkout.tsx` |
| **Duplicate route names in guest-checkout.php** | Both `checkout.*` and `store.checkout.*` aliases for same routes | Removed duplicate `checkout.*` aliases | `guest-checkout.php` → `store-checkout.php` |
| **Route name mismatches in Checkout.tsx** | Used `dashboard.shop.checkout.*` / `checkout.*` conditionally | Unified to `store.checkout.*` | `Checkout.tsx` |
| **Confusing file name** | `guest-checkout.php` implied separate guest flow | Renamed to `store-checkout.php` | `ShopModule.php` |

### Cart Flow (Updated)

Quantity updates and item removal now use `fetch()` + `router.reload()` instead of `router.put()`/`router.delete()`. This is because cart endpoints (`CartController::updateItem()`, `removeItem()`) return JSON responses, not Inertia responses — using `router.put()` causes "All Inertia requests must receive a valid Inertia response" errors.

Pattern (applied in both `SiteHeader.tsx` sidebar and `ShoppingCart.tsx`):
```ts
const res = await fetch(route("store.cart.update-item", itemId), {
  method: "PUT",
  headers: { "Content-Type": "application/json", "X-CSRF-TOKEN": getCsrfToken() },
  body: JSON.stringify({ quantity: newQty }),
});
if (res.ok) router.reload({ only: ["cart"] });
```

### Checkout Routes (Simplified)

| Route | File | Middleware | Layout |
|-------|------|-----------|--------|
| `/store/checkout` | `store-checkout.php` | none | StoreLayout |
| `/dashboard/shop/checkout` | `web.php` | auth+verified+shop.view | AuthenticatedLayout |
| `/dashboard/my/checkout` | `customer-portal.php` | auth | AuthenticatedLayout |

### Cart Item Data Shape

Shared cart items (via `HandleInertiaRequests` and `CartController::page()`) now include product details:

```ts
interface CartItem {
  id: number;
  product_id: number;
  quantity: number;
  unit_price: number;
  variant_label: string | null;
  product: { id: number; name: string; slug: string; thumbnail_url: string | null } | null;
  variation: { id: number; image_url: string | null } | null;
}
```

### Files Changed

```
MODIFIED:
  app/Modules/Shop/Models/Cart.php                        # +$appends = ['subtotal', 'total']
  app/Modules/Shop/Models/CartItem.php                    # (no change needed)
  app/Modules/Shop/Routes/guest-checkout.php → store-checkout.php  # Renamed, deduplicated
  app/Modules/Shop/ShopModule.php                     # Updated route file reference
  app/Modules/Shop/Http/Controllers/CartController.php    # (no change needed)
  app/Modules/Shop/Http/Controllers/CheckoutController.php # (no change needed)
  app/Modules/Shop/Resources/js/Pages/Orders/Checkout.tsx # StoreLayout, unified route names, removed unused imports
  app/Modules/Shop/Resources/js/Pages/Storefront/ShoppingCart.tsx # fetch() + router.reload() for qty/remove
  resources/js/Components/frontend/SiteHeader.tsx        # Cart sidebar: images, qty +/- buttons, fetch-based updates
  resources/js/Layouts/StoreLayout.tsx                   # Updated CartItem/CartData interfaces
  app/Http/Middleware/HandleInertiaRequests.php           # Load items.product relation, include product data
```

## Shop Data Flow (Updated)

```
Products CRUD → Catalog (categories, tags, attributes, variations, types: simple/grouped/external/digital/service/variable)
       ↓
Stock Management (InventoryService: sale/refund/adjust, hold stock, low stock alerts, incoming stock, downloadable files)
       ↓
Cart (Inertia page + JSON API) ← Coupon (percentage/fixed/free_shipping/BOGOF/tiered, single/stacked)
       ↓                                                                   ↓
Checkout (multi-step: shipping → payment → review) ← Gift Card, Net Terms, Tax Exemption
       ↓
Order (full workflow: draft→pending→on-hold→processing→completed→refunded/cancelled/failed/trash)
       ↓
Post-Order: Invoice PDF, Ship with carrier (UPS/FedEx/DHL/tracking), Email campaign triggers
       ↓
Reviews + Returns + Partial Refunds
       ↓
Subscriptions → recurring order generation → notification → webhook dispatch

B2B Flow:
  Company→Location→Contact → tiered pricing → net terms → PO number → checkout

Multi-Currency Flow:
  Detect currency → convert prices → show in locale → checkout in selected currency

Multi-Language Flow:
  Detect locale → load translations → render storefront in language
```
