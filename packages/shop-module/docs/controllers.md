# Controllers

All controllers, keyed by route prefix. Every mutation endpoint validates via
`$request->validate()` and authorizes via `$this->authorize()` against the
registered policies.

## Controller inventory

| Controller | File | Purpose |
|------------|------|---------|
| `ShopSettingsController` | `Controllers/ShopSettingsController.php` | Shop settings page + update |
| `ProductsController` | `Controllers/ProductsController.php` | Product CRUD, import/export, stock history, images |
| `ProductVariationsController` | `Controllers/ProductVariationsController.php` | Variation CRUD + generate + bulk save + reorder |
| `TagsController` | `Controllers/TagsController.php` | Product tag CRUD + JSON inline creation |
| `BrandsController` | `Controllers/BrandsController.php` | Brand CRUD with image/logo + JSON inline creation |
| `AttributesController` | `Controllers/AttributesController.php` | Attribute + term CRUD, `listTerms` JSON |
| `CategoriesController` | `Controllers/CategoriesController.php` | Product category CRUD |
| `OrdersController` | `Controllers/OrdersController.php` | Order CRUD, status, refund, export, invoice, notes, draft conversion, search |
| `CustomersController` | `Controllers/CustomersController.php` | Customer list, show, export |
| `CartController` | `Controllers/CartController.php` | Cart API + coupon apply/remove |
| `CheckoutController` | `Controllers/CheckoutController.php` | Checkout flow (billing, shipping, tax) |
| `CouponsController` | `Controllers/CouponsController.php` | Coupon CRUD + toggle active |
| `ReviewsController` | `Controllers/ReviewsController.php` | Review moderation (approve/reject) |
| `ShippingMethodsController` | `Controllers/ShippingMethodsController.php` | Shipping method CRUD + toggle |
| `ShippingZonesController` | `Controllers/ShippingZonesController.php` | Shipping zone CRUD with method assignment |
| `TaxRatesController` | `Controllers/TaxRatesController.php` | Tax rate CRUD |
| `WebhooksController` | `Controllers/WebhooksController.php` | Webhook CRUD + test |
| `ShipmentsController` | `Controllers/ShipmentsController.php` | Per-order shipment create/list + ship/deliver/tracking |
| `StripeWebhookController` | `Controllers/StripeWebhookController.php` | Stripe event listener |
| `StorefrontController` | `Controllers/StorefrontController.php` | Guest storefront (grid, detail, category) |
| `ReportsController` | `Controllers/ReportsController.php` | Sales reports, top products |
| `AbandonedCartRecoveryController` | `Controllers/AbandonedCartRecoveryController.php` | Signed recovery link |
| `WishlistController` | `Controllers/WishlistController.php` | Wishlist add/remove |
| `NotifyBackInStockController` | `Controllers/NotifyBackInStockController.php` | Back-in-stock subscriptions |
| `MyOrderController` | `Controllers/MyOrderController.php` | Customer portal my-orders |
| `CurrencySwitchController` | `Controllers/CurrencySwitchController.php` | Storefront currency switch |
| `Api\ProductsController` | `Controllers/Api/ProductsController.php` | Public REST products index/show |
| `Api\OrdersController` | `Controllers/Api/OrdersController.php` | Auth REST orders index/show |

## ShopSettingsController

| Method | Route | Purpose | Access |
|--------|-------|---------|--------|
| `index()` | `GET /admin/shop/settings` | Render settings page (`shop/settings/index`) with `settings`/`countries`/`currencies` | Admin |
| `update()` | `PUT /admin/shop/settings` | Save Shop settings | Admin + `shop.view` |

Validates the full settings set (store profile, selling locations, currency &
pricing, tax options, inventory, checkout, units, features). Stores with
`group = 'shop'` in the `settings` table via `Setting::updateOrCreate()`. Booleans
cast to `"1"`/`"0"`; arrays JSON-encoded. Clears both `SettingsService` and
`ShopSettingsService` caches. Typed reads via `ShopSettingsService`. See
[Settings](settings.md).

## ProductsController

All routes under `/dashboard/shop`:

| Method | Route | Purpose | Access |
|--------|-------|---------|--------|
| `index()` | `GET /products` | List products (DataTable) | `shop.view` |
| `create()` | `GET /products/create` | Show create form | `shop.products.create` |
| `store()` | `POST /products` | Create product | `shop.products.create` |
| `edit()` | `GET /products/{product}/edit` | Show edit form | `shop.products.edit` |
| `update()` | `PUT /products/{product}` | Update product | `shop.products.edit` |
| `destroy()` | `DELETE /products/{product}` | Soft delete product | `shop.products.delete` |
| `export()` | `GET /products/export` | Stream CSV with BOM | `shop.view` |
| `import()` | `POST /products/import` | Upload CSV import | `shop.products.create` |
| `uploadImage()` | `POST /products/{product}/images` | Upload image | `shop.products.edit` |
| `deleteImage()` | `DELETE /products/{product}/images/{image}` | Delete image | `shop.products.edit` |
| `adjustStock()` | `POST /products/{product}/stock` | Manual stock adjustment | `shop.products.edit` |
| `stockHistory()` | `GET /products/{product}/stock-history` | Stock adjustment log | `shop.view` |

`applyScheduleStatus()` helper handles the publish/draft/schedule radio +
`publish_date` (future status → scheduled, past → published). `syncGroupedItems()`,
`syncDownloads()`, and brand/category/tag sync run in store/update.

## ProductVariationsController

All routes under `/dashboard/shop`:

| Method | Route | Purpose | Notes |
|--------|-------|---------|-------|
| `index()` | `GET /products/{product}/variations` | List variations as JSON | `$this->authorize('view', $product)` |
| `store()` | `POST /products/{product}/variations` | Create single variation | Strips `attributes`, calls `syncAttributes()` |
| `update()` | `PUT /products/{product}/variations/{variation}` | Update variation fields | Pass `attributes: null` to clear, array to set |
| `destroy()` | `DELETE /products/{product}/variations/{variation}` | Delete variation | Reverts product type to `simple` if last |
| `generate()` | `POST /products/{product}/variations/generate` | Cartesian generate from `attribute_ids[]` | Deduplicates via pivot query |
| `storeBulk()` | `POST /products/{product}/variations/bulk` | Atomic bulk save | Create/update/delete in one call |
| `reorder()` | `POST /products/{product}/variations/reorder` | Bulk sort_order from drag-drop | Accepts `{variations: [{id, sort_order}]}` |

### Variation attribute persistence flow

```
Frontend → { attributes: [{attribute_id, attribute_term_id, name}] }
    ↓
Controller strips attributes from validated data
    ↓
syncAttributes():
    1. Sync pivot table (product_variation_attribute_term)
    2. Update JSON cache column (product_variations.attributes)
    ↓
Frontend receives same shape via JSON column cache
```

Two layers: the **pivot table** enables SQL queries
(`ProductVariation::whereAttributeTerm(42)`,
`Product::whereVariationAttributeTerm(7)`); the **JSON cache** keeps the API
response shape unchanged. `syncAttributes()` keeps both layers in sync atomically.

## AttributesController

| Method | Route | Purpose | Access |
|--------|-------|---------|--------|
| `index()` | `GET /attributes` | List attributes with terms | `shop.view` |
| `store()` | `POST /attributes` | Create attribute | `shop.products.create` |
| `update()` | `PUT /attributes/{attribute}` | Update attribute | `shop.products.edit` |
| `destroy()` | `DELETE /attributes/{attribute}` | Delete attribute + cascade terms | `shop.products.delete` |
| `listTerms()` | `GET /attributes/{attribute}/terms` | JSON terms list | `shop.view` |

Supports `display_type` (text/color/image/button) and term `swatch_value`.

## TagsController / BrandsController

| Method | Route | Purpose | Access |
|--------|-------|---------|--------|
| `index()` | `GET /tags` · `GET /brands` | List with products count | `shop.view` |
| `store()` | `POST /tags` · `POST /brands` | Create | `shop.products.create` |
| `update()` | `PUT /tags/{tag}` · `PUT /brands/{brand}` | Update | `shop.products.edit` |
| `destroy()` | `DELETE /tags/{tag}` · `DELETE /brands/{brand}` | Delete | `shop.products.delete` |

**Inline creation:** all mutation endpoints check `$request->wantsJson()`. When
called with `Accept: application/json`, they return `{ id, name }` instead of
redirecting — enabling the TermsSelector to create tags/brands/categories
on-the-fly from the product form.

## OrdersController

| Method | Route | Purpose | Access |
|--------|-------|---------|--------|
| `index()` | `GET /orders` | List orders | `shop.orders.view` |
| `show()` | `GET /orders/{order}` | Order detail | `shop.orders.view` |
| `updateStatus()` | `PATCH /orders/{order}/status` | Update status + notify + inventory | `shop.orders.edit` |
| `refund()` | `POST /orders/{order}/refund` | Process refund + restock | `shop.orders.edit` |
| `export()` | `GET /orders/export` | Stream CSV | `shop.orders.view` |
| + | `mark-paid`, `search-products`, `search-customers`, `invoice`, `notes`, `convertFromDraft`, `updateAdmin` | Extended order workflow | per-action |

`updateStatus()` records sale on `completed`, refund on `cancelled` (via
`InventoryService`), auto-sets `shipped_at`, notifies the customer, writes the
timeline. `refund()` supports partial refunds with line-item breakdown
(`RefundOrderRequest`). Invoice PDF via dompdf at
`GET /dashboard/shop/orders/{order}/invoice`.

## CartController

Admin routes under `/dashboard/shop` (auth), guest routes under `/store` (no auth,
JSON API only):

| Method | Route | Purpose | Response |
|--------|-------|---------|----------|
| `index()` | `GET /cart` | View cart JSON | JSON |
| `page()` | `GET /cart/page` | Cart page (Inertia) | Inertia render |
| `addItem()` | `POST /cart/add` | Add item | JSON |
| `updateItem()` | `PUT /cart/items/{item}` | Update quantity | JSON |
| `removeItem()` | `DELETE /cart/items/{item}` | Remove item | JSON |
| `clear()` | `DELETE /cart` | Clear cart | JSON |
| `applyCoupon()` | `POST /cart/coupon` | Apply coupon code | JSON |
| `removeCoupon()` | `DELETE /cart/coupon` | Remove coupon | JSON |

**Important:** all mutation endpoints return JSON, not Inertia responses. The
frontend must use `fetch()` + `router.reload({ only: ['cart'] })` instead of
`router.put()`/`router.delete()` (see [Frontend](frontend.md)). All mutations call
`touchLastActivity()` (abandoned-cart lifecycle). Stock validation covers
variations using `usesParentStock()`.

## CheckoutController

Shared across three route groups (see [Routes](routes.md)). Guards driven by shop
settings: guest checkout disabled → redirect/login; `require_company_field` →
`billing_address.company` required; `require_terms` → `accepted_terms` required;
selling-locations policy enforced on billing + shipping country.

| Method | Route | Purpose |
|--------|-------|---------|
| `index()` | `GET /checkout` | Checkout form (billing, shipping, payment) |
| `store()` | `POST /checkout` | Place order (re-validates coupon, calculates tax + shipping) |
| `confirmation()` | `GET /checkout/confirmation/{order}` | Order confirmation page |
| `createPaymentIntent()` | `POST /checkout/payment-intent` | Stripe payment intent |

## CouponsController

| Method | Route | Purpose | Access |
|--------|-------|---------|--------|
| `index()` | `GET /coupons` | List coupons | `shop.view` |
| `create()` | `GET /coupons/create` | Create form | `shop.coupons.manage` |
| `store()` | `POST /coupons` | Create coupon | `shop.coupons.manage` |
| `edit()` | `GET /coupons/{coupon}/edit` | Edit form | `shop.coupons.manage` |
| `update()` | `PUT /coupons/{coupon}` | Update coupon | `shop.coupons.manage` |
| `destroy()` | `DELETE /coupons/{coupon}` | Delete coupon | `shop.coupons.manage` |
| `toggleActive()` | `PATCH /coupons/{coupon}/toggle` | Toggle active | `shop.coupons.manage` |

## ReviewsController

| Method | Route | Purpose | Access |
|--------|-------|---------|--------|
| `index()` | `GET /reviews` | List with status filter | `shop.reviews.moderate` |
| `approve()` | `PATCH /reviews/{review}/approve` | Approve review | `shop.reviews.moderate` |
| `reject()` | `PATCH /reviews/{review}/reject` | Reject review | `shop.reviews.moderate` |
| `destroy()` | `DELETE /reviews/{review}` | Delete review | `shop.reviews.moderate` |

## CustomersController / ReportsController

| Method | Route | Purpose | Access |
|--------|-------|---------|--------|
| `index()` | `GET /customers` | List customers | `shop.view` |
| `show()` | `GET /customers/{user}` | Customer detail + order history | `shop.view` |
| `export()` | `GET /customers/export` | Stream CSV | `shop.view` |
| `index()` | `GET /reports` | Sales summary, sales-over-time, top products | `shop.view` |

## Shipping / Tax controllers

- **ShippingMethodsController** — CRUD + `toggleActive()`
- **ShippingZonesController** — CRUD; `create()`/`edit()` pass `methods` for
  assignment; stores pivot with `override_price`
- **TaxRatesController** — CRUD + `toggleActive()`

All authorized via `ShippingMethodPolicy` / `ShippingZonePolicy` / `TaxRatePolicy`.

## WebhooksController

| Method | Route | Purpose | Access |
|--------|-------|---------|--------|
| `index()` | `GET /webhooks` | List webhooks | `shop.webhooks.manage` |
| `store()` | `POST /webhooks` | Create webhook | `shop.webhooks.manage` |
| `show()` | `GET /webhooks/{webhook}` | View webhook | `shop.webhooks.manage` |
| `update()` | `PUT /webhooks/{webhook}` | Update webhook | `shop.webhooks.manage` |
| `destroy()` | `DELETE /webhooks/{webhook}` | Delete webhook | `shop.webhooks.manage` |
| `test()` | `POST /webhooks/{webhook}/test` | Fire `webhook.test` synchronously | `shop.webhooks.manage` |

## ShipmentsController

| Method | Route | Purpose | Access |
|--------|-------|---------|--------|
| `index()` | `GET /orders/{order}/shipments` | List shipments for an order | `shop.shipments.manage` |
| `store()` | `POST /orders/{order}/shipments` | Create a shipment (with item quantities) | `shop.shipments.manage` |
| `update()` | `PUT /shipments/{shipment}` | Mark shipped/delivered or update tracking | `shop.shipments.manage` |

## StorefrontController

| Method | Route | Purpose |
|--------|-------|---------|
| `index()` | `GET /store` | Product grid: search, category filter, min/max price, sort, pagination |
| `show()` | `GET /store/{product}` | Product detail: reviews, user review, variation selector, `active_price` |
| `category()` | `GET /store/category/{slug}` | Products by category slug |

Passes `display_type` + `swatch_value` for both variation selectors and the
Additional Information table.
