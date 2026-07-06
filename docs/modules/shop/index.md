# Shop Module

Manage your online store: products, orders, inventory, and customers.

- **Slug**: `shop`
- **Version**: `1.0.0`
- **Dependencies**: None
- **Has Settings**: Yes
- **Nav Group Label**: Shop

## Sub-Documents

| Document | Description |
|----------|-------------|
| [Controllers](controllers.md) | Controller methods reference |
| [Frontend](frontend.md) | Frontend pages and components |

## Quick Reference

| Attribute | Value |
|-----------|-------|
| **Permissions** | `shop.view`, `products.create/edit/delete`, `orders.view/edit`, `tags.manage`, `brands.manage`, `coupons.manage`, `reviews.moderate`, `shipping.manage`, `taxes.manage`, `webhooks.manage`, `currencies.manage` |
| **Route Files** | `web.php` (auth), `admin.php` (admin), `webhooks.php`, `storefront.php`, `store-cart.php`, `store-checkout.php`, `customer-portal.php` |
| **Dashboard Stats** | `total_products`, `published_products`, `total_orders`, `pending_orders`, `low_stock_count` |
| **Gallery Config** | `product_gallery` (max 10, hidden from library) |
| **Email Templates** | `order-confirmation`, `order-shipped`, `low-stock-alert`, `cart-recovery-1h/24h/72h` |
| **Notification Types** | `order.placed`, `order.status_changed`, `low_stock`, `product.stock_changed` |
| **Console Commands** | `shop:release-expired-holds` (5min), `shop:abandoned-cart-process` (hourly), `schedule:publish` (1min) |
| **Migration Paths** | `[__DIR__.'/database/migrations']` — own migrations for products, orders, cart, attributes, variations, coupons, reviews, shipping, tax, webhooks, stock adjustments, brands, vendor |
| **Boot** | Registers `ShopService` as singleton |
| **Config** | `config/shop.php` — hold stock, abandoned cart, inventory defaults |

## Data Model

### Core Models

| Model | Table | Description |
|-------|-------|-------------|
| `Product` | `products` | Simple and variable products with pricing, inventory, SEO |
| `ProductCategory` | `product_categories` | Hierarchical product categories |
| `ProductTag` | `product_tags` | Product tags |
| `ProductBrand` | `product_brands` | Product brands (BelongsToMany via `product_product_brand`) |
| `ProductReview` | `product_reviews` | Product reviews with approval workflow |
| `Attribute` | `attributes` | Global attributes (Color, Size, Material) |
| `AttributeTerm` | `attribute_terms` | Attribute values (Red, XL, Cotton) |
| `ProductAttribute` | `product_attributes` | Product-attribute join with visibility/variation flags |

### Variation System

**Two-layer storage:**

1. **Pivot `product_variation_attribute_term`** — authoritative source:
   ```
   product_variation_id | attribute_id | attribute_term_id
   ```
   Enables SQL-level filtering (`WHERE EXISTS`), FK constraints, and efficient queries.

2. **JSON `attributes` column** on `product_variations` — read-only cache for API responses (same shape as before, no frontend changes needed).

**Relationships:**

```
Attribute ──1:N──> AttributeTerm
    │                    │
    │                    │  (via product_variation_attribute_term)
    │                    ├──> ProductVariation ──N:1──> Product
    │                    │
    └── (via product_attributes)
         └──> Product (with visible/variation flags)
```

**ProductAttribute** (`product_attributes`):
- Links a global or custom attribute to a product
- Flags: `visible` (filterable on shop), `variation` (used for variation generation)
- Custom attributes store pipe-separated options in `options` JSON column

**ProductVariation** (`product_variations`):
- Each row = one specific combination of attribute terms
- Fields: price, compare_price, sale_price, SKU, barcode, stock, weight, dimensions, images, description, manage_stock, allow_backorders, is_active
- `attributes` JSON column = denormalized cache `[{attribute_id, attribute_term_id, name}]`
- Pivot `product_variation_attribute_term` = queryable source of truth

**Queryable scopes:**
```php
// Find all Red variations
ProductVariation::whereAttributeTerm($redTermId)->get();

// Find all products with Red + Size M variation
Product::whereVariationAttributeTerm($redTermId)
    ->whereVariationAttributeTerm($sizeMTermId)
    ->get();
```

### Shop Models

| Model | Table | Description |
|-------|-------|-------------|
| `Order` | `orders` | Order with billing/shipping, status workflow |
| `OrderItem` | `order_items` | Order line items |
| `CartItem` | `cart_items` | Cart line items |
| `Cart` | `carts` | Guest/auth cart with coupon support, `$appends = ['subtotal', 'total']` |
| `Coupon` | `coupons` | Discount coupons (percentage/fixed/free_shipping) |
| `CouponUsage` | `coupon_usages` | Coupon usage tracking |
| `ShippingMethod` | `shipping_methods` | Flat-rate shipping methods |
| `ShippingZone` | `shipping_zones` | Country-based shipping zones with override pricing |
| `TaxRate` | `tax_rates` | Per-country tax rates |
| `Webhook` | `webhooks` | Outbound webhooks with HMAC signing |
| `WebhookLog` | `webhook_logs` | Webhook delivery logs |
| `StockAdjustment` | `stock_adjustments` | Inventory change audit trail |

## Routes

| File | Prefix | Middleware | Purpose |
|------|--------|------------|---------|
| `Routes/web.php` | `/dashboard/shop` | `auth`, `verified`, `shop.view` | Products, Orders, Cart, Customers, Attributes, Coupons, Reviews, Shipping, Tax |
| `Routes/admin.php` | `/admin/shop` | `admin`, `shop.view` | Settings |
| `Routes/webhooks.php` | `/dashboard/shop/webhooks` + `/stripe/webhook` | Auth + public | Webhook management + Stripe listener |
| `Routes/storefront.php` | `/store` | Guest | Storefront product grid, detail, category |
| `Routes/store-cart.php` | `/store/cart` | Guest | Guest cart CRUD (JSON API) |
| `Routes/store-checkout.php` | `/store/checkout` | Guest | Checkout flow (billing, shipping, payment) — used by all users |
| `Routes/customer-portal.php` | `/dashboard/my` | Auth | Customer portal (my orders, my checkout) |

### Key Variation Routes

| Method | Route | Controller | Purpose |
|--------|-------|------------|---------|
| GET | `/products/{product}/variations` | `index()` | List all variations |
| POST | `/products/{product}/variations` | `store()` | Add single variation |
| POST | `/products/{product}/variations/generate` | `generate()` | Cartesian generation from attribute term IDs |
| POST | `/products/{product}/variations/bulk` | `storeBulk()` | Atomic bulk save (create/update/delete) |
| PUT | `/products/{product}/variations/{variation}` | `update()` | Update variation fields + sync attribute terms |
| POST | `/products/{product}/variations/reorder` | `reorder()` | Bulk update sort_order for drag-drop reorder |
| DELETE | `/products/{product}/variations/{variation}` | `destroy()` | Delete variation, revert to simple if last |

### Storefront Routes (Guest)

| Method | Route | Purpose |
|--------|-------|---------|
| GET | `/store` | Product grid with search, filter, sort, pagination |
| GET | `/store/{product}` | Product detail with reviews, rating, variation selector |
| GET | `/store/category/{slug}` | Products by category |
| GET/POST | `/store/cart/...` | Full guest cart CRUD + coupon + checkout |

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
      ├─ Shipping Methods
      ├─ Shipping Zones
      ├─ Tax Rates
      └─ Webhooks
```

## Policies

| Policy | Model | Scope |
|--------|-------|-------|
| `ProductPolicy` | `Product` | Permission-based (`shop.products.*`) |
| `OrderPolicy` | `Order` | Permission-based (`shop.orders.view/edit`) |
| `CouponPolicy` | `Coupon` | Permission-based (`shop.coupons.manage`) |
| `ProductReviewPolicy` | `ProductReview` | Permission-based (`shop.reviews.moderate`) |
| `ShippingMethodPolicy` | `ShippingMethod` | Permission-based (`shop.shipping.manage`) |
| `ShippingZonePolicy` | `ShippingZone` | Permission-based (`shop.shipping.manage`) |
| `TaxRatePolicy` | `TaxRate` | Permission-based (`shop.taxes.manage`) |

## Services

| Service | File | Purpose |
|---------|------|---------|
| `ShopService` | `Services/ShopService.php` | Module boot singleton |
| `InventoryService` | `Services/InventoryService.php` | Stock adjustments, sale/refund recording, variation support |
| `CouponService` | `Services/CouponService.php` | Coupon validation, discount calculation, auto-discounts |
| `ShippingService` | `Services/ShippingService.php` | Zone-matched shipping methods |
| `TaxService` | `Services/TaxService.php` | Per-country tax calculation |
| `WebhookService` | `Services/WebhookService.php` | Webhook dispatch with HMAC signing |

### InventoryService

Central stock management. All stock mutations go through this service.

**Core methods:**
- `adjust($entity, string $type, int $quantity, ...)`: Caps stock at 0, updates entity, records `StockAdjustment`
- `recordSale($entity, int $quantity)`: Negative adjustment named "sale"
- `recordRefund($entity, int $quantity)`: Positive adjustment named "refund"
- `recordSaleForOrder(Order $order)`: Iterates items, calls `recordSale()` per line (product or variation)
- `recordRefundForOrder(Order $order)`: Same pattern for refunds
- `manualAdjust(Product $product, int $quantity, string $reason)`: Manual adjustment
- `getHistory(Product $product, int $perPage = 20)`: Paginated adjustment log

**Variation support:** `adjust()` accepts `Product|ProductVariation`. `StockAdjustment` has optional `variation_id` FK.

**Integration:** Called from `CheckoutService::checkout()`, `OrdersController::updateStatus()`, `OrdersController::refund()`, `ReleaseExpiredHolds` command.

### CouponService

- `validateAndApply(string $code, float $subtotal, ?int $userId)`: Validates dates/limits/per-user, returns `Coupon` or throws `ValidationException`
- `calculateDiscount(Coupon $coupon, float $subtotal, ?float $shippingCost)`: Delegates to model (supports percentage/fixed/free_shipping)
- `recordUsage(Coupon $coupon, int $orderId, ?int $userId, float $discountAmount)`: Creates `CouponUsage` + increments `used_count`
- `getAutoDiscounts(float $subtotal)`: Returns active automatic coupons valid for subtotal

### ShippingService

- `getAvailableMethods(?array $billingAddress)`: Matches country to zone via `whereJsonContains('countries', $country)`, returns zone methods with override prices. Falls back to all active methods if no zone match.

### TaxService

- `calculate(float $subtotal, ?array $billingAddress, bool $isTaxable)`: Finds rates matching billing address country, falls back to `is_default`. Returns `{ tax_total, breakdown }`.

## Settings

Stored in `settings` table with `group = 'shop'`. Managed via `ShopSettingsController` at `PUT /admin/shop/settings`.

Grouped into 4 accordion sections on the **Admin → Settings → Shop** tab:

### Product Defaults
| Field | Type | Default |
|-------|------|---------|
| `default_product_status` | select: draft/published | `draft` |
| `backorder_default` | select: do_not_allow/allow_notify/allow | `do_not_allow` |

### Currency & Units
| Field | Type | Default |
|-------|------|---------|
| `currency` | select: USD/EUR/GBP/TND | `EUR` |
| `currency_position` | select: before/after | `before` |
| `weight_unit` | select: kg/g/lb/oz | `kg` |
| `dimension_unit` | select: cm/m/in/ft | `cm` |

### Inventory Management
| Field | Type | Default |
|-------|------|---------|
| `inventory_enabled` | bool toggle | `1` |
| `low_stock_threshold` | number 0-999 | `5` |
| `hold_stock_enabled` | bool toggle | `1` |
| `hold_stock_minutes` | number 1-10080 | `60` |

### Feature Toggles
| Field | Type | Default |
|-------|------|---------|
| `show_out_of_stock` | bool toggle | `0` |
| `enable_reviews` | bool toggle | `1` |
| `enable_wishlist` | bool toggle | `1` |
| `auto_complete_orders` | bool toggle | `0` |

## Console Commands

| Command | Schedule | Description |
|---------|----------|-------------|
| `shop:release-expired-holds` | Every 5 min | Cancels unpaid pending orders older than `hold_stock.default_minutes`, refunds stock |
| `shop:abandoned-cart-process` | Hourly | Marks abandoned carts, sends 3-step recovery email sequence (1h/24h/72h) |
| `schedule:publish` | Every minute | Publishes scheduled products/posts/properties (shared with Posts/Properties modules) |

## Config (`config/shop.php`)

```php
'currency_symbol' => env('SHOP_CURRENCY_SYMBOL', '€'),

'abandoned_cart' => [
    'enabled' => env('ABANDONED_CART_ENABLED', true),
    'hours_until_abandoned' => env('ABANDONED_CART_HOURS', 1),
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

## Hold Stock Data Flow

```
Order placed → CheckoutService → recordSaleForOrder() → stock decremented
  ↓
Order is "pending" (unpaid). Stock is "held".
  ↓
[shop:release-expired-holds runs every 5 min]
  → Finds pending unpaid orders older than hold_stock_minutes
  → recordRefundForOrder() restores stock
  → Order cancelled
```

## Meta Formatting

- `price_fields`: `['price', 'compare_price', 'cost_price', 'unit_price', 'total', 'subtotal']`

## User Cleanup

On user deletion, order `user_id` is set to null (orders kept for history).
