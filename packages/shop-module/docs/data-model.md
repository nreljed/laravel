# Data Model

32 Eloquent models across the e-commerce domain.

## Products & Taxonomy

| Model | Table | Notes |
|-------|-------|-------|
| `Product` | `products` | Simple/variable/grouped/external/digital/service; pricing, inventory, SEO, sale scheduling |
| `ProductCategory` | `product_categories` | Hierarchical categories (`parent_id`) |
| `ProductTag` | `product_tags` | Tags (pivot `product_tag_product`) |
| `ProductBrand` | `product_brands` | Brands (pivot `product_product_brand`) |
| `ProductReview` | `product_reviews` | Ratings with approve/reject workflow, rating cache |
| `ProductDownload` | `product_downloads` | Downloadable files for digital products |
| `Attribute` | `attributes` | Global attributes with `display_type` (text/color/image/button) |
| `AttributeTerm` | `attribute_terms` | Attribute values with optional `swatch_value` |
| `ProductAttribute` | `product_attributes` | Product↔attribute join with `visible`/`variation` flags |
| `ProductVariation` | `product_variations` | One row per attribute-term combination |
| `ProductVariationAttributeTerm` | `product_variation_attribute_term` | Queryable pivot (source of truth) |

### Product types

`simple`, `variable`, `grouped`, `external`, `digital`, `service`. Helpers:
`isVirtual()`, `isDigital()`, `isService()`, `isOutOfStock()`. `grouped` uses the
`product_grouped_products` pivot; `external` stores `external_url` + `button_text`.

### Catalog visibility

`catalog_visibility` enum: `visible` / `catalog` / `search` / `hidden`.

### Stock & pricing

- `stock_status` (3-way `instock`/`outofstock`/`onbackorder`), auto-calculated on
  quantity change for both products and variations.
- `backorders` 3-way enum (`no`/`notify`/`yes`).
- `manage_stock` string flag; `low_stock_threshold`; `incoming_stock`; `hold_stock_minutes`.
- `sale_price`/`sale_from`/`sale_to`; the backend computes `active_price` on
  variation + product (storefront uses `active_price`, not a re-implementation).
- `sold_individually`, `min_quantity`, `max_quantity`, `menu_order`, `global_unique_id`.

### Variations (two-layer storage)

1. **Pivot `product_variation_attribute_term`** — authoritative, indexed,
   SQL-queryable:
   ```
   product_variation_id | attribute_id | attribute_term_id
   ```
2. **JSON `attributes` column** on `product_variations` — read-only cache for
   API/frontend responses (same shape as before, no frontend changes needed).

```php
ProductVariation::whereAttributeTerm($redTermId)->get();
Product::whereVariationAttributeTerm($redTermId)
    ->whereVariationAttributeTerm($sizeMTermId)->get();
```

`ProductVariation::syncAttributes()` writes the pivot + cache atomically.
`getDefaultVariation()` uses the pivot via `whereHas('attributeTerms', count)` —
no PHP-side looping.

## Cart, Orders & Customers

| Model | Table | Notes |
|-------|-------|-------|
| `Cart` | `carts` | Guest/auth cart, coupon + stacking; `$appends = ['subtotal','total']` |
| `CartItem` | `cart_items` | Line items (product + variation) |
| `Order` | `orders` | Billing/shipping, 10-status workflow, refunds, applied coupons |
| `OrderItem` | `order_items` | Line items (product + variation) |
| `OrderRefund` | `order_refunds` | Partial refunds with line-item breakdown |
| `OrderNote` | `order_notes` | Public + private order notes |
| `OrderAction` | `order_actions` | Order timeline / audit trail |

`Cart::$appends = ['subtotal','total']` ensures the Inertia serializer includes the
accessor values (a bug fix — without it, subtotal/total were missing → €NaN on the
cart page).

## Discounts, Shipping, Tax

| Model | Table | Notes |
|-------|-------|-------|
| `Coupon` | `coupons` | percentage/fixed/free_shipping/buy_x_get_y/fixed_product/percentage_product/tiered; automatic discounts, stacking, limits |
| `CouponUsage` | `coupon_usages` | Coupon usage tracking |
| `ShippingMethod` | `shipping_methods` | Flat-rate methods |
| `ShippingZone` | `shipping_zones` | Country-based zones, per-method override pricing (pivot `shipping_zone_method`) |
| `TaxRate` | `tax_rates` | Per-country rates with default fallback |

## Platform

| Model | Table | Notes |
|-------|-------|-------|
| `Currency` | `currencies` | Multi-currency with exchange rates |
| `Webhook` | `webhooks` | Outbound webhooks, HMAC-SHA256 signing; `consecutive_failures` auto-deactivates at 10 |
| `WebhookCall` | `webhook_calls` | Webhook delivery log |
| `Shipment` | `shipments` | Order fulfillment: carrier, tracking, `pending` → `shipped` → `delivered` (`shipped_at`/`delivered_at`) |
| `ShipmentItem` | `shipment_items` | Per-shipment snapshot of shipped order items |
| `StockAdjustment` | `stock_adjustments` | Inventory audit trail (who/what/why) |
| `Wishlist` | `wishlists` | Per-user saved products |
| `BackInStockSubscription` | `back_in_stock_subscriptions` | "Notify me" requests |
| `ProductBrand` | see Products | Taxonomy / digital assets |
| `ProductDownload` | see Products | Digital assets |

## Relationship map (variation system)

```
Attribute ──1:N──> AttributeTerm
    │                    │
    │                    │  (via product_variation_attribute_term)
    │                    ├──> ProductVariation ──N:1──> Product
    │                    │
    └── (via product_attributes)
         └──> Product (with visible/variation flags)
```

`ProductAttribute` links a global or custom attribute to a product; flags
`visible` (filterable on shop) and `variation` (used for variation generation).
Custom attributes store pipe-separated options in the `options` JSON column.
