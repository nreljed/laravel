# Shop Module — Controllers

| Controller | File | Purpose |
|------------|------|---------|
| `ShopSettingsController` | `Controllers/ShopSettingsController.php` | Shop settings update (14 fields) |
| `ProductsController` | `Controllers/ProductsController.php` | Product CRUD, import/export, stock history |
| `ProductVariationsController` | `Controllers/ProductVariationsController.php` | Variation CRUD + generate + bulk save |
| `TagsController` | `Controllers/TagsController.php` | Product tag CRUD + JSON inline creation |
| `BrandsController` | `Controllers/BrandsController.php` | Product brand CRUD with image/logo + JSON inline creation |
| `AttributesController` | `Controllers/AttributesController.php` | Attribute + term CRUD |
| `CategoriesController` | `Controllers/CategoriesController.php` | Product category CRUD |
| `OrdersController` | `Controllers/OrdersController.php` | Order CRUD, status, refund, export |
| `CustomersController` | `Controllers/CustomersController.php` | Customer list, show, export |
| `CartController` | `Controllers/CartController.php` | Cart API + coupon apply/remove |
| `CheckoutController` | `Controllers/CheckoutController.php` | Checkout flow (billing, shipping, tax) |
| `CouponsController` | `Controllers/CouponsController.php` | Coupon CRUD + toggle active |
| `ReviewsController` | `Controllers/ReviewsController.php` | Review moderation (approve/reject) |
| `ShippingMethodsController` | `Controllers/ShippingMethodsController.php` | Shipping method CRUD |
| `ShippingZonesController` | `Controllers/ShippingZonesController.php` | Shipping zone CRUD with method assignment |
| `TaxRatesController` | `Controllers/TaxRatesController.php` | Tax rate CRUD |
| `WebhooksController` | `Controllers/WebhooksController.php` | Webhook CRUD + test |
| `StripeWebhookController` | `Controllers/StripeWebhookController.php` | Stripe event listener |
| `StorefrontController` | `Controllers/StorefrontController.php` | Guest storefront (grid, detail, category) |
| `ReportsController` | `Controllers/ReportsController.php` | Sales reports, top products |
| `ProductVariationsController` | `Controllers/ProductVariationsController.php` | Variation operations |

## ShopSettingsController

| Method | Route | Purpose | Access |
|--------|-------|---------|--------|
| `update()` | `PUT /admin/shop/settings` | Save Shop settings | Admin + `shop.view` |

Validates 14 fields across 4 groups: Product Defaults (status, backorder), Currency & Units (currency, position, weight, dimension), Inventory Management (inventory_enabled, low_stock_threshold, hold_stock_enabled, hold_stock_minutes), Feature Toggles (show_out_of_stock, enable_reviews, enable_wishlist, auto_complete_orders).

Stores settings with `group = 'shop'` in the `settings` table via `Setting::updateOrCreate()`. Booleans cast to `"1"`/`"0"` before storage.

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

## ProductVariationsController

All routes under `/dashboard/shop`:

| Method | Route | Purpose | Notes |
|--------|-------|---------|-------|
| `index()` | `GET /products/{product}/variations` | List variations as JSON | Uses `$this->authorize('view', $product)` |
| `store()` | `POST /products/{product}/variations` | Create single variation | Strips `attributes` array, calls `syncAttributes()` |
| `update()` | `PUT /products/{product}/variations/{variation}` | Update variation fields | Pass `attributes: null` to clear, array to set |
| `destroy()` | `DELETE /products/{product}/variations/{variation}` | Delete variation | Reverts product type to `simple` if last |
| `generate()` | `POST /products/{product}/variations/generate` | Cartesian generate from `attribute_ids[]` | Deduplicates via pivot table query |
| `storeBulk()` | `POST /products/{product}/variations/bulk` | Atomic bulk save | Create/update/delete in one call |
| `reorder()` | `POST /products/{product}/variations/reorder` | Bulk update sort_order from drag-drop | Accepts `{variations: [{id, sort_order}]}` |

### Data Flow (Variation Attribute Persistence)

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

**Why two layers:**
- **Pivot table** enables SQL queries: `ProductVariation::whereAttributeTerm(42)`, `Product::whereVariationAttributeTerm(7)`
- **JSON cache** keeps API response shape unchanged (no frontend changes needed)
- **`syncAttributes()`** is called atomically — both layers are always in sync

## AttributesController

| Method | Route | Purpose | Access |
|--------|-------|---------|--------|
| `index()` | `GET /attributes` | List attributes with terms | `shop.view` |
| `store()` | `POST /attributes` | Create attribute | `shop.products.create` |
| `update()` | `PUT /attributes/{attribute}` | Update attribute | `shop.products.edit` |
| `destroy()` | `DELETE /attributes/{attribute}` | Delete attribute + cascade terms | `shop.products.delete` |
| `listTerms()` | `GET /attributes/{attribute}/terms` | JSON terms list | `shop.view` |

## TagsController

| Method | Route | Purpose | Access |
|--------|-------|---------|--------|
| `index()` | `GET /tags` | List tags with products count | `shop.view` |
| `store()` | `POST /tags` | Create tag (returns JSON when `Accept: application/json`) | `shop.products.create` |
| `update()` | `PUT /tags/{tag}` | Update tag (returns JSON when `Accept: application/json`) | `shop.products.edit` |
| `destroy()` | `DELETE /tags/{tag}` | Delete tag (returns JSON when `Accept: application/json`) | `shop.products.delete` |

**Inline creation**: All mutation endpoints check `$request->wantsJson()`. When called with `Accept: application/json` header, they return `{ id, name }` instead of redirecting — enabling the TermsSelector to create tags on-the-fly from the product form.

## BrandsController

| Method | Route | Purpose | Access |
|--------|-------|---------|--------|
| `index()` | `GET /brands` | List brands with products count | `shop.view` |
| `store()` | `POST /brands` | Create brand with optional image/logo | `shop.products.create` |
| `update()` | `PUT /brands/{brand}` | Update brand | `shop.products.edit` |
| `destroy()` | `DELETE /brands/{brand}` | Delete brand | `shop.products.delete` |

Same inline creation pattern as TagsController — returns JSON when `$request->wantsJson()`.

## CategoriesController

| Method | Route | Purpose | Access |
|--------|-------|---------|--------|
| `index()` | `GET /categories` | List categories | `shop.view` |
| `store()` | `POST /categories` | Create category | `shop.products.create` |
| `update()` | `PUT /categories/{category}` | Update category | `shop.products.edit` |
| `destroy()` | `DELETE /categories/{category}` | Delete category | `shop.products.delete` |

## OrdersController

| Method | Route | Purpose | Access |
|--------|-------|---------|--------|
| `index()` | `GET /orders` | List orders | `shop.orders.view` |
| `show()` | `GET /orders/{order}` | View order detail | `shop.orders.view` |
| `updateStatus()` | `PATCH /orders/{order}/status` | Update status + notify + inventory | `shop.orders.edit` |
| `refund()` | `POST /orders/{order}/refund` | Process refund + restock | `shop.orders.edit` |
| `export()` | `GET /orders/export` | Stream CSV | `shop.orders.view` |

## CartController

Admin routes under `/dashboard/shop` (auth), guest routes under `/store` (no auth, JSON API only):

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

**Important:** All mutation endpoints (`updateItem`, `removeItem`, etc.) return JSON responses, not Inertia responses. Frontend must use `fetch()` + `router.reload({ only: ['cart'] })` instead of `router.put()`/`router.delete()`.

## CheckoutController

Shared across three route groups:

| Route Prefix | File | Middleware | Layout |
|--------------|------|-----------|--------|
| `/store/checkout` | `store-checkout.php` | None | StoreLayout |
| `/dashboard/shop/checkout` | `web.php` | auth+verified+shop.view | AuthenticatedLayout |
| `/dashboard/my/checkout` | `customer-portal.php` | Auth | AuthenticatedLayout |

| Method | Route | Purpose |
|--------|-------|---------|
| `index()` | `GET /checkout` | Checkout form (billing, shipping, payment) |
| `store()` | `POST /checkout` | Place order (validates coupon, calculates tax + shipping) |
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

## CustomersController

| Method | Route | Purpose | Access |
|--------|-------|---------|--------|
| `index()` | `GET /customers` | List customers | `shop.view` |
| `show()` | `GET /customers/{user}` | Customer detail + order history | `shop.view` |
| `export()` | `GET /customers/export` | Stream CSV | `shop.view` |

## ReportsController

| Method | Route | Purpose | Access |
|--------|-------|---------|--------|
| `index()` | `GET /reports` | Sales summary, sales-over-time, top products | `shop.view` |

## WebhooksController

| Method | Route | Purpose | Access |
|--------|-------|---------|--------|
| `index()` | `GET /webhooks` | List webhooks | `shop.webhooks.manage` |
| `store()` | `POST /webhooks` | Create webhook | `shop.webhooks.manage` |
| `show()` | `GET /webhooks/{webhook}` | View webhook | `shop.webhooks.manage` |
| `update()` | `PUT /webhooks/{webhook}` | Update webhook | `shop.webhooks.manage` |
| `destroy()` | `DELETE /webhooks/{webhook}` | Delete webhook | `shop.webhooks.manage` |
| `test()` | `POST /webhooks/{webhook}/test` | Fire test event | `shop.webhooks.manage` |
