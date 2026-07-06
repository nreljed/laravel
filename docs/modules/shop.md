# Shop Module

Manage your online store: products, orders, inventory, customers, reviews, discounts, shipping, taxes, and webhooks.

- **Slug**: `shop`
- **Version**: `1.0.0`
- **Dependencies**: None
- **Has Settings**: Yes (via Settings tab in admin panel)
- **Nav Label**: Shop
- **Frontend Pages**: 20+ (products, orders, categories, tags, brands, attributes, coupons, reviews, customers, reports, shipping methods/zones, tax rates, webhooks, stock history, storefront)
- **Tests**: 189+ PHPUnit tests across `ShopCrudTest`, `ApiAndWebhookTest`, and `VariationMechanismTest`
- **Order Statuses**: `draft`, `pending`, `on-hold`, `confirmed`, `processing`, `completed`, `cancelled`, `refunded`, `failed`, `trash`
- **Refund Type**: Partial line-item refunds (multiple per order, tracks `total_refunded`)
- **Order Notes**: Public (customer-visible) and private (admin-only)

---

## Architecture

### Module Structure

```
app/Modules/Shop/
  ShopModule.php                      # Module class (slug, deps, routes, nav, permissions)
  Config/
    shop.php                          # Default config values
  Console/                                 # (in app/Console/Commands/)
    ReleaseExpiredHolds.php                # shop:release-expired-holds
    ProcessAbandonedCarts.php              # shop:abandoned-cart-process
  Controllers/
    ProductsController.php                 # Full CRUD + import/export + stock adjustment
    OrdersController.php                   # Full CRUD + status workflow + refund + export
    CustomersController.php                # Index + show + export
    CartController.php                     # Guest cart CRUD with coupon support
    CheckoutController.php                 # Multi-step checkout
    ShopSettingsController.php        # Settings CRUD
    CategoriesController.php               # CRUD
    TagsController.php                     # CRUD (JSON for inline creation)
    BrandsController.php                   # CRUD (JSON for inline creation)
    AttributesController.php               # CRUD + listTerms
    ProductVariationsController.php        # CRUD + generate + storeBulk
    CouponsController.php                  # CRUD + toggleActive
    ReviewsController.php                  # Index + store + approve/reject + destroy
    ShippingMethodsController.php          # CRUD + toggleActive
    ShippingZonesController.php            # CRUD with method assignment
    TaxRatesController.php                 # CRUD + toggleActive
    WebhooksController.php                 # CRUD + test
    StripeWebhookController.php            # Stripe signature verification
    ReportsController.php                  # Sales summary + trends + top products
    StorefrontController.php               # Product grid + detail + category
    AbandonedCartRecoveryController.php    # Signed-URL recovery
  Models/
    Product.php                            # Core product model
    ProductVariation.php                   # Variations (size/color/etc)
    ProductVariationAttributeTerm.php      # Pivot model
    ProductCategory.php                    # Category taxonomy
    ProductTag.php                         # Tag taxonomy
    ProductBrand.php                       # Brand taxonomy
    Attribute.php                          # Global attribute definitions (Color, Size)
    AttributeTerm.php                      # Attribute values (Red, XL)
    ProductDownload.php                    # Digital download files
    Coupon.php                             # Discount coupons
    CouponUsage.php                        # Coupon usage tracking
    ProductReview.php                      # Ratings & reviews
    ShippingMethod.php                     # Shipping rate definitions
    ShippingZone.php                       # Geographic shipping zones
    TaxRate.php                            # Tax rate definitions
    Order.php                              # Order model
    OrderItem.php                          # Order line items
    Cart.php                               # Guest cart
    CartItem.php                           # Cart line items
    Webhook.php                            # Outbound webhook config
    WebhookCall.php                        # Webhook delivery log
    StockAdjustment.php                    # Stock change audit trail
  Requests/
    StoreProductRequest.php                # Validation rules for product store/update
  Resources/js/
    Pages/
      Products/
        Index.tsx                          # Product list with search, filters, import, export
        Form.tsx                           # Product create/edit with tabs + sidebar
        Show.tsx                           # Product detail with reviews
        StockHistory.tsx                   # Per-product stock adjustment log
      Orders/
        Index.tsx                          # Order list with search, filters, export
        Form.tsx                           # Order create/edit
        Show.tsx                           # Order detail with timeline
      Categories/Index.tsx                 # Category CRUD
      Tags/Index.tsx                       # Tag CRUD
      Brands/Index.tsx                     # Brand CRUD
      Attributes/Index.tsx                 # Attribute + term CRUD
      Coupons/
        Index.tsx                          # Coupon list
        Form.tsx                           # Coupon create/edit
      Reviews/Index.tsx                    # Review moderation
      Customers/
        Index.tsx                          # Customer list
        Show.tsx                           # Customer detail with order history
      Reports/Index.tsx                    # Sales analytics dashboard
      ShippingMethods/Index.tsx            # Ship method CRUD
      ShippingZones/
        Index.tsx                          # Zone list
        Form.tsx                           # Zone create/edit with method assignment
      TaxRates/Index.tsx                   # Tax rate CRUD
      Webhooks/Index.tsx                   # Webhook CRUD
      Storefront/
        Index.tsx                          # Public product grid
        Show.tsx                           # Public product detail
        Cart.tsx                           # Public cart page
    components/
      VariationsManager.tsx                # Variation editor (938 lines)
  Routes/
    web.php                                # Admin CRUD routes
    storefront.php                         # Public store routes
    store-cart.php                         # Guest cart routes
    webhooks.php                           # Webhook + Stripe routes
  Services/
    ShopService.php                   # Module singleton
    InventoryService.php                   # Stock management
    CouponService.php                      # Coupon validation + discount calc
    ShippingService.php                    # Zone-matched shipping
    TaxService.php                         # Per-country tax calc
    WebhookService.php                     # Outbound dispatch + HMAC
  database/migrations/                     # 25+ migration files (module's own migrations)
```

---

## Schema

### Core Tables

#### `products` (62 columns)
Primary product table. Every product type stored here with polymorphic fields.

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint PK | |
| `name` | string | Product title |
| `slug` | string unique | URL slug |
| `description` | text | Full description |
| `short_description` | text | Excerpt/teaser |
| `price` | decimal(10,2) | Regular price (base for variable types) |
| `compare_price` | decimal(10,2) | Was/crossed-out price |
| `cost_price` | decimal(10,2) | Cost for margin calculation |
| `sale_price` | decimal(10,2) | Sale price |
| `sale_from` | timestamp nullable | Sale start |
| `sale_to` | timestamp nullable | Sale end |
| `sku` | string nullable | Stock keeping unit |
| `barcode` | string nullable | Barcode/GTIN |
| `global_unique_id` | string nullable | GTIN/UPC/EAN/ISBN |
| `stock_quantity` | int | Stock count |
| `low_stock_threshold` | int nullable | Per-product low stock alert |
| `track_stock` | bool | Enable inventory tracking |
| `stock_status` | string nullable | instock/outofstock/onbackorder |
| `backorders` | string | no/notify/yes |
| `hold_stock_minutes` | int nullable | Per-product hold timer override |
| `incoming_stock` | int | Expected incoming stock |
| `status` | string | draft/published/private/future/archived |
| `publish_date` | timestamp nullable | Scheduled publish date |
| `type` | string | simple/variable/grouped/external/digital/service/virtual |
| `catalog_visibility` | string | visible/catalog/search/hidden |
| `virtual` | bool | Service/no shipping |
| `downloadable` | bool | Digital goods with files |
| `is_featured` | bool | Featured product |
| `is_taxable` | bool | Taxable |
| `tax_class` | string nullable | Tax class |
| `sold_individually` | bool | Max 1 per order |
| `min_quantity` | int | Minimum purchase qty |
| `max_quantity` | int | Maximum purchase qty |
| `menu_order` | int | Custom sort |
| `external_url` | string nullable | Affiliate link |
| `button_text` | string nullable | CTA text for external |
| `weight` | decimal(10,2) | Weight |
| `length` | decimal(10,2) | Length |
| `width` | decimal(10,2) | Width |
| `height` | decimal(10,2) | Height |
| `images` | json | Array of gallery images |
| `thumbnail_url` | string nullable | Featured image |
| `attributes` | json | Cached attribute data |
| `seo` | json | meta_title, meta_description, og_image |
| `default_attributes` | json | Default variation selection {slug: slug} |
| `vendor` | string nullable | Brand/manufacturer name |
| `min_variation_price` | decimal(10,2) | Cached min variation price |
| `max_variation_price` | decimal(10,2) | Cached max variation price |
| `price_hash` | string nullable | Cache invalidation hash |
| `average_rating` | decimal(3,2) | Cached avg rating |
| `reviews_count` | int | Cached count |
| `created_by` | bigint nullable | FK users |

Pivot tables: `product_category_product`, `product_tag_product`, `product_product_brand`, `product_attribute_term`, `coupon_product`, `product_grouped_products`

#### `product_variations` (26 columns)

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint PK | |
| `product_id` | bigint FK | Parent product |
| `sku` | string nullable | Unique SKU |
| `barcode` | string nullable | |
| `price` | decimal(10,2) | Regular price |
| `compare_price` | decimal(10,2) nullable | Was price |
| `sale_price` | decimal(10,2) nullable | Sale price |
| `sale_from` | timestamp nullable | Sale start |
| `sale_to` | timestamp nullable | Sale end |
| `active_price` | decimal(10,2) nullable | Computed effective price |
| `stock_quantity` | int | Stock count |
| `stock_status` | string nullable | instock/outofstock/onbackorder |
| `manage_stock` | string | true/false/parent |
| `backorders` | string | no/notify/yes |
| `weight` | decimal(10,2) nullable | |
| `length` | decimal(10,2) nullable | |
| `width` | decimal(10,2) nullable | |
| `height` | decimal(10,2) nullable | |
| `description` | text nullable | Variation description |
| `is_active` | bool | Enabled/disabled |
| `visible` | bool | Show on storefront ⚠️ Must use `getAttribute('visible')` inside model (see note below) |
| `image_url` | string nullable | Featured image |
| `images` | json nullable | Gallery images |
| `attributes` | json | Cached attribute data [{attribute_id, attribute_term_id, name}] |
| `sort_order` | int | Menu order |
| `default_attributes` | json nullable | Inherited from product |

#### `attributes` / `attribute_terms` / `product_variation_attribute_term`

```
attributes ──┬── attribute_terms ──┬── product_variation_attribute_term ── product_variations
             │                      └── product_attribute_term ── products (flat assignment)
             │
             └── product_attribute ── products (with options JSON for custom attributes)
```

- `attributes`: Global attribute definitions (Color, Size, Material)
- `attribute_terms`: Values for global attributes (Red, XL, Cotton)
- `product_variation_attribute_term`: Pivot linking variations to attribute terms (with `attribute_id` for direct FK)
- `product_attribute_term`: Pivot linking products to attribute terms (flat catalog assignment)
- Custom attributes: Stored inline on `product_attributes.options` JSON column

### Order Tables

#### `orders` (29 columns)

| Column | Type | Description |
|--------|------|-------------|
| `order_number` | string unique | Auto-generated |
| `user_id` | bigint nullable | FK (null on guest) |
| `status` | string | pending/processing/completed/cancelled/refunded |
| `subtotal` | decimal(10,2) | |
| `tax_total` | decimal(10,2) | |
| `shipping_total` | decimal(10,2) | |
| `discount_total` | decimal(10,2) | |
| `total` | decimal(10,2) | |
| `currency` | string | |
| `payment_method` | string nullable | |
| `payment_status` | string nullable | |
| `payment_intent_id` | string nullable | |
| `notes` | text nullable | |
| `billing_address` | json | |
| `shipping_address` | json | |
| `customer_email` | string nullable | |
| `customer_phone` | string nullable | |
| `customer_name` | string nullable | |
| `processed_by` | bigint nullable | Admin user FK |
| `paid_at` | timestamp nullable | |
| `shipped_at` | timestamp nullable | |
| `refunded_at` | timestamp nullable | |
| `refund_amount` | decimal(10,2) nullable | |
| `refund_notes` | text nullable | |

#### `order_items` (14 columns)
Links to `product_id` and `product_variation_id` (nullable). Stores snapshot of product name, SKU, unit_price at time of order.

### Cart Tables

#### `carts` (14 columns)
| Column | Type | Description |
|--------|------|-------------|
| `user_id` | bigint nullable | |
| `session_id` | string nullable | Guest session |
| `coupon_code` | string nullable | Applied coupon |
| `discount` | decimal(10,2) | Computed discount |
| `shipping_address` | json nullable | |
| `billing_address` | json nullable | |
| `last_activity_at` | timestamp nullable | For abandoned cart detection |
| `abandoned_at` | timestamp nullable | Marked as abandoned |
| `recovered_at` | timestamp nullable | Recovered from abandonment |
| `recovery_email_sent_at` | timestamp nullable | |
| `recovery_email_count` | int | Email sequence step |
| `converted_to_order_id` | bigint nullable | FK to orders |

### Transactional Tables

- `coupons` (14 cols): code, type (percentage/fixed/free_shipping), value, min_order_amount, max_discount, usage_limit, per_user_limit, used_count, is_automatic, auto_discount_label, dates
- `coupon_usages` (5 cols): coupon_id, order_id, user_id, discount_amount
- `product_reviews` (12 cols): rating (1-5), title, content, is_approved, approved_at, approved_by
- `shipping_methods` (10 cols): name, description, price, free_above, estimated_days, is_active
- `shipping_zones` (5 cols): name, countries (json), regions (json), is_active
- `shipping_zone_method` (pivot): override_price per method per zone
- `tax_rates` (9 cols): name, rate, type (percentage/fixed), country, state, is_default
- `webhooks` (10 cols): name, url, secret, events (json), headers (json), timeout, retry_attempts, retry_delay, is_active
- `webhook_calls` (11 cols): webhook_id, event, payload (json), status, attempts, response_status, response_body, completed_at
- `stock_adjustments` (12 cols): product_id, variation_id (nullable), type, quantity (signed), stock_before, stock_after, reason, reference_type/reference_id, user_id

---

## Product Types

| Type | Description | Price Source | Stock Source | Storefront Behavior |
|------|-------------|-------------|-------------|-------------------|
| `simple` | Standard physical product | `price` field | Own stock | Add to cart + shipping |
| `variable` | Multiple variations (size/color) | Per variation (`active_price`) | Per variation or parent | Variation selector dropdown |
| `grouped` | Bundle of simple products (unpurchasable itself) | Sum of children | From children | Shows child products as list |
| `external` | Affiliate/off-site product | Displayed for reference | N/A | "Buy Now" → external URL |
| `digital` | Downloadable software/media | `price` | Optional | Download link after purchase |
| `service` | Bookable service (consultation) | `price` | Optional | No shipping, contact form |
| `virtual` | Service/digital (no shipping) | `price` | Optional | No shipping |

**Combined types**: `simple + virtual + downloadable` = digital product (PDF/software). `simple + service` = consultation.

### Catalog Visibility

| Value | Storefront | Search |
|-------|-----------|--------|
| `visible` | ✓ | ✓ |
| `catalog` | ✓ | — |
| `search` | — | ✓ |
| `hidden` | — | — |

---

## Variation Architecture

### WC-Parity Audit

Our variation system is modeled after WooCommerce but with significant improvements:

#### Strengths (better than WooCommerce)

| Feature | Us | WooCommerce | Why Better |
|---------|-----|-------------|------------|
| Attribute storage | **Pivot table** `product_variation_attribute_term` | Postmeta key-value | SQL queryable, FK constraints, indexed |
| Price cache | Persisted `active_price` column | Computed on read | Queryable without computation |
| Cartesian generation | Built-in `generate()` endpoint | Plugin-only feature | One-click variation creation |
| Stock status | Auto-calculated on save + persisted | Computed on read | Consistent across queries |
| `attributes` cache | JSON column + pivot (dual storage) | Postmeta only | API-ready without joins |
| Sale date semantics | Day-boundary normalization | Raw timestamps | No off-by-one bugs |

#### WC-Computed Accessors (all in `$appends`)

| Accessor | Logic | WC Equivalent |
|----------|-------|---------------|
| `is_visible` | `getAttribute('visible') === true && active_price !== null` | `visible` (separate bool) |
| `on_sale` | `sale_price` set and within active date range | `on_sale` (computed) |
| `purchasable` | `is_active && active_price !== null && isInStock()` | `purchasable` (computed) |
| `backorders_allowed` | `backorders !== 'no'` | `backorders_allowed` (computed) |
| `backordered` | `backorders_allowed && stock <= 0 && managesOwnStock()` | `backordered` (computed) |

#### Missing vs WooCommerce

| Feature | WC | Us | Priority |
|---------|-----|-----|----------|
| `virtual`/`downloadable` per variation | ✓ | ❌ | P2 |
| Shipping class per variation | ✓ | ❌ | P2 |
| Tax override per variation | ✓ | ❌ | P2 |
| `meta_data` (arbitrary key-value) | ✓ | ❌ | P3 |
| Drag-reorder variations | ✅ (drag handle + reorder API) | ❌ (sort_order only) | ✅ Done |
| `gallery_image_ids` separation | ✓ | Uses `featured` flag in array | Equivalent |

#### ⚠️ `visible` Column — Eloquent Property Conflict

The `visible` column name **conflicts with Eloquent's** `HidesAttributes::$visible` property (`protected $visible = []`). When accessing `$this->visible` inside a model method, PHP resolves it to the trait property (`[]`), **not** the database column.

**Rule**: Always use `$this->getAttribute('visible')` when reading the column inside model methods. The `isVisible()` method already uses this workaround. From outside the model (e.g., controllers, views, tests), `$variation->visible` works correctly (goes through `__get` → `getAttribute`).

The `$attributes` default `'visible' => true` is set on the model so newly created variations default to visible without explicit assignment. On fresh DB rows, `visible` is `true` from the migration default, but Eloquent doesn't refresh the model from DB after insert, so the in-memory default is needed.

#### Stock Management Inheritance

```
variation.manage_stock:
  "true"   → Own stock: stock_quantity, stock_status, backorders
  "false"  → No tracking: stock_status = "instock", stock_quantity ignored
  "parent" → Inherits from Product: product.isInStock(), product.backorders
```

#### Sale Price Date Logic

```php
// Day-boundary normalization (done in controller):
//   sale_from → Y-m-d 00:00:00
//   sale_to   → Y-m-d 23:59:59

// Active price calculation (done in model saving):
if (salePrice set) {
    if (saleFrom && saleTo)     → active_price = salePrice if now between
    elseif (saleFrom)           → active_price = salePrice if now >= saleFrom
    elseif (saleTo)             → active_price = salePrice if now <= saleTo
    else                        → active_price = salePrice (no date limits)
} else {
    active_price = price (or null if price not set)
}
```

#### Price Range Caching

On every variation save/delete, `Product::syncPriceRange()` recalculates:
- `min_variation_price` = min of all variation `active_price`
- `max_variation_price` = max of all variation `active_price`
- `price_hash` = md5 of serialized price data (for cache invalidation)

Also triggered on backfill via `php artisan shop:backfill-active-prices`.

---

## Stock Management

### InventoryService

Central stock mutation service. All stock changes MUST go through this service.

| Method | Purpose |
|--------|---------|
| `adjust($entity, $type, $quantity, ...)` | Core method — caps stock at ≥ 0, records StockAdjustment |
| `recordSale($entity, $qty)` | Negative adjustment ("sale") |
| `recordRefund($entity, $qty)` | Positive adjustment ("refund") |
| `recordSaleForOrder($order)` | Iterates order items, records sale on each product/variation |
| `recordRefundForOrder($order)` | Same for refunds |
| `manualAdjust($product, $qty, $reason)` | Manual adjustment with reason |
| `getHistory($product, $perPage)` | Paginated stock log |

### Integration Points

| Point | Action |
|-------|--------|
| `CheckoutService::checkout()` | Calls `recordSaleForOrder()` — prevents overselling |
| `OrdersController::updateStatus() → completed` | No action (already done at checkout) |
| `OrdersController::updateStatus() → cancelled` | Calls `recordRefundForOrder()` |
| `OrdersController::refund()` | Calls `recordRefundForOrder()` |
| `ReleaseExpiredHolds` command | `recordRefundForOrder()` on expired pending orders |
| `ProductsController::adjustStock()` | `manualAdjust()` for admin UI |

### Hold Stock Flow

```
Order placed (CheckoutService)
  → recordSaleForOrder() decrements stock
  → Order status = pending
      ↓
ReleaseExpiredHolds command (every 5 min):
  → Finds pending unpaid orders older than hold_stock_minutes
  → Respects per-product hold_stock_minutes (falls back to config)
  → recordRefundForOrder() restores stock
  → Order cancelled
```

### Low Stock Notification

- `ProductsController::update()` checks `wasChanged('stock_quantity')`
- Dispatches `product.low_stock` event to admins when `stock_quantity <= low_stock_threshold`
- Registered as notification type with `default_enabled: false`

### Stock Scopes

**Product:** `scopeInStock`, `scopeOutOfStock`, `scopeOnBackorder`, `scopeLowStock`, `scopeStockStatus`
**Variation:** `scopeInStock`, `scopeOutOfStock`, `scopeOnBackorder`, `scopeLowStock`, `scopeStockStatus`

---

## Controllers & Routes

### Route Files

| File | Prefix | Auth | Middleware |
|------|--------|------|------------|
| `Routes/web.php` | `/dashboard/shop` | Required | `shop.view` |
| `Routes/admin.php` | `/admin/shop` | Admin | `shop.view` |
| `Routes/storefront.php` | `/store` | Guest | — |
| `Routes/store-cart.php` | `/store/cart` | Guest | — |
| `Routes/webhooks.php` | `/dashboard/shop/webhooks` + `/stripe/webhook` | Auth + public (Stripe) | — |

### Key Controllers

| Controller | Actions | Permission |
|------------|---------|------------|
| `ProductsController` | index, create, store, show, edit, update, destroy, export, import, uploadImage, deleteImage, adjustStock, stockHistory | `shop.products.*` |
| `OrdersController` | index, create, store, show, edit, update, destroy, refund, markPaid, export, invoice | `shop.orders.*` |
| `CartController` | page, addItem, updateItem, removeItem, clear, applyCoupon, removeCoupon, index (JSON) | Guest |
| `CheckoutController` | index, store | Guest + Auth |
| `StorefrontController` | index, show, category | Guest |
| `ProductVariationsController` | index, store, update, destroy, generate, storeBulk | `shop.products.edit` |
| `CouponsController` | index, create, store, edit, update, destroy, toggleActive | `shop.coupons.manage` |
| `ReviewsController` | index, store, approve, reject, destroy | `shop.reviews.moderate` |
| `ShippingMethodsController` | index, store, update, destroy, toggleActive | `shop.shipping.manage` |
| `ShippingZonesController` | index, create, store, edit, update, destroy | `shop.shipping.manage` |
| `TaxRatesController` | index, store, update, destroy, toggleActive | `shop.taxes.manage` |
| `WebhooksController` | index, store, show, update, destroy, test | `shop.webhooks.manage` |
| `CategoriesController` | index, store, update, destroy | `shop.products.edit` |
| `TagsController` | index, store, update, destroy | `shop.tags.manage` |
| `BrandsController` | index, store, update, destroy | `shop.brands.manage` |
| `AttributesController` | index, store, update, destroy, listTerms | `shop.products.edit` |
| `ReportsController` | index | `shop.view` |
| `CustomersController` | index, show, export | `shop.view` |
| `ShopSettingsController` | update | Admin |

---

## Services

| Service | Purpose | Key Methods |
|---------|---------|-------------|
| `InventoryService` | Central stock management | `adjust()`, `recordSale()`, `recordRefund()`, `recordSaleForOrder()`, `recordRefundForOrder()`, `manualAdjust()`, `getHistory()` |
| `CouponService` | Coupon validation + discount calculation | `validateAndApply()`, `calculateDiscount()`, `recordUsage()`, `getAutoDiscounts()` |
| `ShippingService` | Zone-matched shipping rates | `getAvailableMethods($billingAddress)` |
| `TaxService` | Per-country tax calculation with fallback | `calculate($subtotal, $billingAddress, $isTaxable)` → `{tax_total, breakdown[]}` |
| `WebhookService` | Outbound webhook dispatch | `dispatch($event, $payload)` — fires queued `WebhookSendJob` with HMAC signing, auto-deactivates after 10 consecutive failures |
| `ShopService` | Module singleton | Boot only |

---

## Permissions

| Slug | Description |
|------|-------------|
| `shop.view` | View Shop dashboard |
| `shop.products.create` | Create products |
| `shop.products.edit` | Edit products |
| `shop.products.delete` | Delete products |
| `shop.orders.view` | View orders |
| `shop.orders.edit` | Edit orders |
| `shop.tags.manage` | Manage tags |
| `shop.brands.manage` | Manage brands |
| `shop.coupons.manage` | Manage coupons |
| `shop.reviews.moderate` | Moderate reviews |
| `shop.shipping.manage` | Manage shipping methods + zones |
| `shop.taxes.manage` | Manage tax rates |
| `shop.webhooks.manage` | Manage webhooks |

Policies: `ProductPolicy`, `OrderPolicy`, `CouponPolicy`, `ProductReviewPolicy`, `ShippingMethodPolicy`, `ShippingZonePolicy`, `TaxRatePolicy` — all registered in `AppServiceProvider`.

---

## Settings

Stored in `settings` table with `group = 'shop'`. Managed via `ShopSettingsController` at `PUT /admin/shop/settings`.

| Key | Type | Default | Description |
|-----|------|---------|-------------|
| `default_product_status` | select | `draft` | New product default status |
| `backorder_default` | select | `do_not_allow` | Default backorder behavior |
| `currency` | select | `EUR` | Store currency |
| `currency_position` | select | `before` | Currency symbol position |
| `weight_unit` | select | `kg` | Default weight unit |
| `dimension_unit` | select | `cm` | Default dimension unit |
| `inventory_enabled` | bool | `1` | Enable inventory tracking globally |
| `low_stock_threshold` | int | `5` | Default low stock warning threshold |
| `hold_stock_enabled` | bool | `1` | Enable hold stock on pending orders |
| `hold_stock_minutes` | int | `60` | Minutes before releasing held stock |
| `show_out_of_stock` | bool | `0` | Show out-of-stock products on storefront |
| `enable_reviews` | bool | `1` | Enable product reviews |
| `enable_wishlist` | bool | `1` | Enable wishlist feature |
| `auto_complete_orders` | bool | `0` | Auto-complete paid orders |

---

## Config (`config/shop.php`)

```php
'currency_symbol' => env('SHOP_CURRENCY_SYMBOL', '€'),
'currency_position' => env('SHOP_CURRENCY_POSITION', 'before'),

'abandoned_cart' => [
    'enabled' => env('ABANDONED_CART_ENABLED', true),
    'hours_until_abandoned' => (int) env('ABANDONED_CART_HOURS', 1),
    'sequence_hours' => [1, 24, 72],
],

'hold_stock' => [
    'enabled' => env('HOLD_STOCK_ENABLED', true),
    'default_minutes' => (int) env('HOLD_STOCK_MINUTES', 60),
],

'inventory' => [
    'default_low_stock_threshold' => (int) env('DEFAULT_LOW_STOCK_THRESHOLD', 5),
    'enabled' => true,
],
```

---

## Nav Items

```
Shop                    (SidebarGroupLabel)
  └─ Shop          (collapsible parent)
      ├─ Dashboard
      ├─ All Products
      ├─ Add Product
      ├─ Categories
      ├─ Tags
      ├─ Brands
      ├─ Attributes
      ├─ Stock History
      ├─ Orders
      ├─ Coupons
      ├─ Reviews
      ├─ Customers
      ├─ Reports
      ├─ Shipping Methods
      ├─ Shipping Zones
      ├─ Tax Rates
      └─ Webhooks
```

---

## Console Commands

| Command | Schedule | Description |
|---------|----------|-------------|
| `shop:release-expired-holds` | Every 5 min | Cancels unpaid pending orders older than `hold_stock.default_minutes`, refunds stock |
| `shop:abandoned-cart-process` | Hourly | Marks abandoned carts, sends recovery email sequence (1h/24h/72h) |
| `schedule:publish` | Every minute | Publishes scheduled products/posts/properties (shared command) |

---

## Data Flows

### Guest Checkout Flow

```
Cart (session)
  → Apply coupon (CouponService::validateAndApply)
  → Checkout: billing/shipping address → shipping method → tax calc
  → Order created (CheckoutService)
      ├── InventoryService::recordSaleForOrder() → decrements stock
      ├── Sets cart.converted_to_order_id
      ├── MailService::send() order confirmation email
      └── WebhookService::dispatch('order.placed')
```

### Admin Order Flow

```
Create Order (search products + customers)
  → Invoice → Update Status → Notify Customer → Refund
  → Product stock adjusted via InventoryService
  → Reviews enabled after order completion
```

### Abandoned Cart Recovery

```
Cart activity timeout (1h)
  → Mark abandoned (abandoned_at set)
  → Hourly: ProcessAbandonedCarts
      1. Identify new abandoned carts
      2. Send recovery email sequence at 1h/24h/72h
      3. Each email includes signed recovery URL
  → Customer clicks URL → handleRecovery() → redirect to cart
```

### Webhook Dispatch Flow

```
Any mutation that fires an event:
  ProductsController (create/update/delete)
  OrdersController (create/update/status/refund)
  CustomersController (create/update)

  → WebhookService::dispatch($event, $payload)
    → Queued WebhookSendJob (unique per webhook_id + event)
      → POST with HMAC-SHA256 signature header
      → Logs result to webhook_calls table
      → Increments failure_count on non-2xx
      → Auto-deactivates after 10 consecutive failures
```

---

## Email Templates

| Key | Description | Variables |
|-----|-------------|-----------|
| `order-confirmation` | Order Confirmation | `$customerName`, `$orderNumber`, `$orderTotal`, `$orderStatus` |
| `order-shipped` | Order Shipped | `$newStatus`, `$trackingNumber` (conditional) |
| `low-stock-alert` | Low Stock Alert | Product name, stock level |
| `cart-recovery-1h` | "You left items in your cart!" | Signed recovery URL |
| `cart-recovery-24h` | "Still thinking about it?" | Signed recovery URL |
| `cart-recovery-72h` | "Your cart is still waiting!" | Signed recovery URL |

All templates use null coalescing (`??`) for variable fallbacks. `$siteName`, `$userName`, `$notificationLink` merged globally by `AppNotification::toArray()`.

---

## Notification Types

| Type | Channels | Template | Default |
|------|----------|----------|---------|
| `order.placed` | in_app + email | `order-confirmation` | Enabled |
| `order.status_changed` | in_app + email | `order-shipped` | Enabled |
| `low_stock` | in_app only | — | Disabled |
| `product.stock_changed` | in_app only | — | Disabled |

---

## Export / Import

### Product Export (`GET /products/export`)
CSV with BOM (Excel-compatible). 20+ columns: name, slug, description, short_description, price, compare_price, cost_price, sku, barcode, global_unique_id, stock_quantity, low_stock_threshold, track_stock, status, type, catalog_visibility, virtual, downloadable, external_url, is_featured, sold_individually, menu_order, weight, categories, tags.

### Product Import (`POST /products/import`)
Accepts CSV, validates each row (name required, price numeric, sku unique, status/type enum). Returns success count + first 5 errors via flash.

### Order Export (`GET /orders/export`)
CSV with order items inline. Status-filterable. Columns: order_number, customer, status, total, items, date.

### Customer Export (`GET /customers/export`)
CSV with order count + total spent.

---

## Frontend Pages

| Page | Route | Component |
|------|-------|-----------|
| Product List | `dashboard.shop.products.index` | `Products/Index.tsx` |
| Product Form | `dashboard.shop.products.{create,edit}` | `Products/Form.tsx` |
| Product Detail | `dashboard.shop.products.show` | `Products/Show.tsx` |
| Product Stock History | `dashboard.shop.products.stock-history` | `Products/StockHistory.tsx` |
| Order List | `dashboard.shop.orders.index` | `Orders/Index.tsx` |
| Order Form | `dashboard.shop.orders.{create,edit}` | `Orders/Form.tsx` |
| Order Detail | `dashboard.shop.orders.show` | `Orders/Show.tsx` |
| Category List | `dashboard.shop.categories.index` | `Categories/Index.tsx` |
| Tag List | `dashboard.shop.tags.index` | `Tags/Index.tsx` |
| Brand List | `dashboard.shop.brands.index` | `Brands/Index.tsx` |
| Attribute List | `dashboard.shop.attributes.index` | `Attributes/Index.tsx` |
| Coupon List | `dashboard.shop.coupons.index` | `Coupons/Index.tsx` |
| Coupon Form | `dashboard.shop.coupons.{create,edit}` | `Coupons/Form.tsx` |
| Review Moderation | `dashboard.shop.reviews.index` | `Reviews/Index.tsx` |
| Customer List | `dashboard.shop.customers.index` | `Customers/Index.tsx` |
| Customer Detail | `dashboard.shop.customers.show` | `Customers/Show.tsx` |
| Reports | `dashboard.shop.reports.index` | `Reports/Index.tsx` |
| Shipping Methods | `dashboard.shop.shipping-methods.index` | `ShippingMethods/Index.tsx` |
| Shipping Zones | `dashboard.shop.shipping-zones.{index,create,edit}` | `ShippingZones/{Index,Form}.tsx` |
| Tax Rates | `dashboard.shop.tax-rates.index` | `TaxRates/Index.tsx` |
| Webhooks | `dashboard.shop.webhooks.index` | `Webhooks/Index.tsx` |
| Storefront Grid | `store.index` | `Storefront/ProductGrid.tsx` |
| Storefront Detail | `store.show` | `Storefront/ProductDetail.tsx` |
| Storefront Cart | `store.cart.index` | `Storefront/ShoppingCart.tsx` |

---

## Dashboard Stats

| Stat Key | Source | Description |
|----------|--------|-------------|
| `total_products` | Product count | All products |
| `published_products` | status=published | Published count |
| `total_orders` | Order count | All orders |
| `pending_orders` | status=pending | Pending count |
| `low_stock_count` | Stock query | Products low on stock |
| `abandoned_carts` | Cart query | Abandoned (unrecovered) carts |
| `recovered_carts` | Cart query | Recovered carts |

Analytics KPIs (cached 5-min): Revenue Today, Orders Today, AOV, Conversion Rate, Revenue This Month (each with period-over-period % change). Revenue over time (14 days), top products, order status distribution.

---

## Phase 21 — Order & Refund Expansion (2026-07-08)

**Goal**: Add partial refunds, order notes, status transitions, draft orders, and invoice PDF to WooCommerce parity.

### What was built

| Component | Details |
|-----------|---------|
| `OrderStatusService` | Status transition rules for 10 statuses + `validateTransition()` |
| `OrderRefund` model | Line-item partial refund with `items` JSON (order_item_id, quantity, amount), `shipping_refund`, `total`, `reason`, `created_by` |
| `OrderNote` model | Public (customer-visible) or private notes with user attribution |
| `total_refunded` column | Cumulative refund tracking on orders table |
| Refactored `refund()` | Accepts per-item breakdown, creates `OrderRefund` record, auto-marks order refunded only when total_refunded >= total |
| `storeNote()` / `destroyNote()` | Order notes CRUD on the controller |
| `convertFromDraft()` | Converts draft orders to pending with inventory deduction |
| `updateStatus()` | Uses `OrderStatusService` to validate transitions instead of hardcoded values |
| `is_draft` support | Form.tsx "Save as Draft" button, skips inventory deduction |
| Line-item refund dialog | Show.tsx — checkbox per item, qty/amount inputs, shipping refund, reason |
| Order notes UI | Show.tsx — add note with public/private toggle, delete note, badge indicators |
| Draft banner | Show.tsx — prominent card with "Convert to Pending" button |
| Status filter | Index.tsx — all 9 statuses in filter dropdown + proper badge variants |
| `shop.orders.refund` | New permission, synced to DB, assigned to admin + crm_user roles |

### Status transition map

```text
draft → pending, cancelled
pending → confirmed, on-hold, cancelled, failed
on-hold → pending, confirmed, cancelled
confirmed → processing, on-hold, cancelled
processing → completed, on-hold, cancelled
completed → refunded, cancelled
cancelled → pending, trash
failed → pending, cancelled
trash → pending
refunded → (none)
```

### Files changed

```
NEW:
  app/Modules/Shop/Models/OrderRefund.php
  app/Modules/Shop/Models/OrderNote.php
  app/Modules/Shop/Services/OrderStatusService.php
  app/Modules/Shop/Requests/StoreOrderNoteRequest.php
  app/Modules/Shop/database/migrations/2026_07_08_000000_create_order_refunds_table.php
  app/Modules/Shop/database/migrations/2026_07_08_000001_create_order_notes_table.php

MODIFIED:
  app/Modules/Shop/Models/Order.php              # +total_refunded, +refunds(), +notes()
  app/Modules/Shop/Controllers/OrdersController.php  # Full rewrite
  app/Modules/Shop/Routes/web.php                 # +notes routes, +draft convert
  app/Modules/Shop/ShopModule.php            # +shop.orders.refund permission
  app/Modules/Shop/Requests/RefundOrderRequest.php  # Items array validation
  app/Modules/Shop/Requests/StoreOrderRequest.php  # +is_draft field
  app/Modules/Shop/Requests/UpdateOrderStatusRequest.php  # +new statuses
  app/Modules/Shop/Services/OrderActionService.php  # Refund with items count
  app/Policies/OrderPolicy.php                        # +refund() method
  app/Modules/Shop/Resources/js/Pages/Orders/Show.tsx  # Refund dialog, notes, draft banner
  app/Modules/Shop/Resources/js/Pages/Orders/Form.tsx   # Save as Draft button
  app/Modules/Shop/Resources/js/Pages/Orders/Index.tsx  # New statuses + badge variants
```

---

## Known Gaps

### Structural
- No `webhook_logs` table — uses `webhook_calls` instead (different schema: has attempts-based retry model vs log-only)
- No dedicated `product_grouped_products` table in migrations (grouped product type referenced but pivot may not exist)

### vs WooCommerce (per competitive analysis)

| Priority | Feature | Status |
|----------|---------|--------|
| P0 | Partial refunds with line item breakdown | ✅ (OrderRefund model with items JSON) |
| P0 | Multi-currency | Partial (Currency model exists, not wired) |
| P0 | B2B / Wholesale | ❌ |
| P0 | Subscriptions | ❌ |
| P1 | Draft orders (admin order entry) | ✅ (draft status + convert to pending) |
| P1 | Order editing | ✅ (admin Form.tsx with product/customer search) |
| P1 | Public REST API | ❌ |
| P2 | Carrier shipping integration | ❌ |
| P2 | Shipping classes | ❌ |
| P2 | BOGOF + tiered discounts | ❌ |
| P3 | Coupon stacking | ❌ |
| P3 | Multi-location inventory | ❌ |
| P3 | Gift cards | ❌ |
| P4 | Email campaigns | ❌ |
| P4 | POS | ❌ |

---

## Implementation Status

| Phase | Title | Status | Date |
|-------|-------|--------|------|
| — | Core Products & Orders (initial) | ✅ Done | — |
| — | Webhooks (Phase 3) | ✅ Done | 2026-07-02 |
| — | Storefront (Phase 4) | ✅ Done | 2026-07-02 |
| — | Core Fixes + Attributes (Phase 5.0-5.2) | ✅ Done | 2026-07-01 |
| — | Tags, Variations, Categories, Customers, Reports (Phase 5.x) | ✅ Done | 2026-07-01 |
| — | CSV Import/Export (Phase 12) | ✅ Done | — |
| — | Inventory Log (Phase 14) | ✅ Done | — |
| — | Dashboard Analytics Overhaul (Phase 15) | ✅ Done | — |
| — | Abandoned Cart Recovery (Phase 16) | ✅ Done | — |
| — | Discount Engine Expansion (Phase 17) | ✅ Done | — |
| — | Product Form & Scheduling (Phase 18) | ✅ Done | 2026-07-02 |
| — | Hold Stock + Inventory Defaults (Stage 1) | ✅ Done | 2026-07-04 |
| — | Shop Settings UI (Stage 2) | ✅ Done | 2026-07-04 |
| — | Brands & Product Taxonomy Expansion | ✅ Done | 2026-07-05 |
| — | Variation Attribute Pivot Table | ✅ Done | 2026-07-04 |
| — | Code Audit & Phase 20 Completion | ✅ Done | 2026-07-05 |
| — | Variation WC Parity (P0 + P1) | ✅ Done | 2026-07-05 |
| — | Variation WC Parity Tests (13 tests, 117 assertions) | ✅ Done | 2026-07-05 |
| 19 | Product Type Expansion | ✅ Done | 2026-07-04 |
| 20 | Stock & Inventory Depth | ✅ Done | 2026-07-05 |
| 21 | Order & Refund Expansion | ✅ Done | 2026-07-08 |
| 22 | Discount Engine Expansion (BOGOF, stacking) | ⏳ Pending | — |
| 23 | Storefront Enhancement | ⏳ Pending | — |
| 24 | Checkout & Cart Enhancement | ⏳ Pending | — |
| 25 | Shipping Depth (classes, carrier) | ⏳ Pending | — |
| 26 | Tax Depth (classes, exemption) | ⏳ Pending | — |
| 27 | Customer & Account Depth | ⏳ Pending | — |
| 28 | B2B / Wholesale | ⏳ Pending | — |
| 29 | Subscriptions | ⏳ Pending | — |
| 30 | Gift Cards | ⏳ Pending | — |
| 31 | Multi-Currency | ⏳ Pending | — |
| 32 | POS | ⏳ Pending | — |
| 33 | Marketing & Analytics | ⏳ Pending | — |
| 34 | API & Developer Experience | ⏳ Pending | — |
| 35 | Multi-Language | ⏳ Pending | — |
| 36 | Shipping Carrier Integration | ⏳ Pending | — |
| 37 | Purchase Orders & Supplier Management | ⏳ Pending | — |
| 38 | Migration & Go-Live | ⏳ Pending | — |
