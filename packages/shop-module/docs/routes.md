# Routes

All Shop routes are **self-loaded** by `ShopServiceProvider::loadRoutes()` — there are no
`routes/shop*.php` files in the host. Eleven route files live under `src/Routes/` and are
grouped with distinct prefixes, middleware, and route-name prefixes.

## Route File Overview

| File | Prefix | Name prefix | Middleware | Purpose |
|------|--------|-------------|------------|---------|
| `web.php` | `/dashboard/shop` | `dashboard.shop.*` | `web`, `auth`, `verified`, `permission:shop.view` | Full admin CRUD (products, orders, coupons, shipping, taxes, webhooks…) |
| `admin.php` | `/admin/shop` | `admin.shop.*` | `web`, `auth`, `verified`, `admin`, `permission:shop.view` | Shop settings page |
| `customer-portal.php` | `/dashboard/my` | `dashboard.my.*` | `web`, `auth`, `verified` | Customer portal (orders, cart, checkout) |
| `storefront.php` | `/store` | `store.*` | `web` | Public product grid / detail / category |
| `store-cart.php` | `/store/cart` | `store.cart.*` | `web` | Guest cart (Inertia page + JSON endpoints) |
| `store-checkout.php` | `/store/checkout` | `store.checkout.*` | `web` | Guest checkout + Stripe webhook |
| `store-wishlist.php` | `/store/wishlist` | `store.wishlist.*` | `web` (+`auth` per-route) | Guest/authenticated wishlist |
| `store-notify.php` | `/store` | `store.*` | `web` | Back-in-stock notifications |
| `recovery.php` | `/store/cart/recover` | `store.cart.recover` | `web`, `signed` | Abandoned-cart recovery link |
| `api.php` | `/api` | `api.*` | `api` | Public JSON product listing |
| `api-auth.php` | `/api` | `api.*` | `api`, `auth:sanctum` | Sanctum-protected JSON orders |

The loading order inside `loadRoutes()`:

```php
// Customer portal — auth, no special permission
Route::middleware(['web', 'auth', 'verified'])
    ->prefix('/dashboard')->name('dashboard.')
    ->group(__DIR__.'/Routes/customer-portal.php');

// Admin management — auth + shop.view
Route::middleware(['web', 'auth', 'verified', 'permission:shop.view'])
    ->prefix('/dashboard/shop')->name('dashboard.shop.')
    ->group(__DIR__.'/Routes/web.php');

// Admin settings — admin + shop.view
Route::middleware(['web', 'auth', 'verified', 'admin', 'permission:shop.view'])
    ->prefix('/admin/shop')->name('admin.shop.')
    ->group(__DIR__.'/Routes/admin.php');

// Public storefront (no auth) — several small files
Route::middleware(['web'])->group(function () {
    require __DIR__.'/Routes/store-checkout.php';
    require __DIR__.'/Routes/recovery.php';
    require __DIR__.'/Routes/store-cart.php';
    require __DIR__.'/Routes/store-wishlist.php';
    require __DIR__.'/Routes/storefront.php';
    require __DIR__.'/Routes/store-notify.php';
});

// JSON API
Route::middleware(['api'])->prefix('/api')->group(function () {
    require __DIR__.'/Routes/api.php';
    require __DIR__.'/Routes/api-auth.php';
});
```

## Admin (`dashboard.shop.*` — `web.php`)

### Dashboard

| Method | URI | Name | Controller |
|--------|-----|------|------------|
| GET | `/dashboard/shop/dashboard` | `dashboard.shop.dashboard` | `ShopDashboardController@index` |

### Products

| Method | URI | Name |
|--------|-----|------|
| GET | `/products` | `products.index` |
| GET | `/products/create` | `products.create` |
| POST | `/products` | `products.store` |
| GET | `/products/export` | `products.export` |
| POST | `/products/import` | `products.import` |
| POST | `/products/upload-image` | `products.upload-image` |
| DELETE | `/products/delete-image` | `products.delete-image` |
| POST | `/products/bulk` | `products.bulk` |
| GET | `/products/{product}` | `products.show` |
| GET | `/products/{product}/edit` | `products.edit` |
| PUT | `/products/{product}` | `products.update` |
| DELETE | `/products/{product}` | `products.destroy` |
| PATCH | `/products/{product}/status` | `products.update-status` |
| POST | `/products/{product}/adjust-stock` | `products.adjust-stock` |
| GET | `/products/{product}/stock-history` | `products.stock-history` |
| POST | `/products/seo/generate` | `products.seo.generate` |

### Variations

| Method | URI | Name |
|--------|-----|------|
| GET | `/products/{product}/variations` | `products.variations.index` |
| POST | `/products/{product}/variations` | `products.variations.store` |
| PUT | `/products/{product}/variations/{variation}` | `products.variations.update` |
| DELETE | `/products/{product}/variations/{variation}` | `products.variations.destroy` |
| POST | `/products/{product}/variations/generate` | `products.variations.generate` |
| POST | `/products/{product}/variations/bulk` | `products.variations.bulk` |
| POST | `/products/{product}/variations/delete-bulk` | `products.variations.delete-bulk` |
| POST | `/products/{product}/variations/reorder` | `products.variations.reorder` |

### Orders

| Method | URI | Name |
|--------|-----|------|
| GET | `/orders` | `orders.index` |
| GET | `/orders/create` | `orders.create` |
| POST | `/orders` | `orders.store` |
| GET | `/orders/export` | `orders.export` |
| GET | `/orders/{order}` | `orders.show` |
| GET | `/orders/{order}/edit` | `orders.edit` |
| PUT | `/orders/{order}` | `orders.update` |
| PATCH | `/orders/{order}/status` | `orders.update-status` |
| GET | `/orders/{order}/invoice` | `orders.invoice` |
| POST | `/orders/{order}/mark-paid` | `orders.mark-paid` |
| POST | `/orders/{order}/refund` | `orders.refund` |
| POST | `/orders/{order}/convert-from-draft` | `orders.convert-from-draft` |
| POST | `/orders/{order}/notes` | `orders.notes.store` |
| DELETE | `/orders/{order}/notes/{note}` | `orders.notes.destroy` |
| GET | `/orders/search/products` | `orders.search-products` |
| GET | `/orders/search/customers` | `orders.search-customers` |

### Checkout (admin — rarely used; guest/storefront is the canonical path)

| Method | URI | Name |
|--------|-----|------|
| GET | `/checkout` | `checkout.index` |
| POST | `/checkout` | `checkout.store` |
| GET | `/checkout/confirmation/{order}` | `checkout.confirmation` |
| POST | `/checkout/payment-intent` | `checkout.payment-intent` |

### Reports & Customers

| Method | URI | Name |
|--------|-----|------|
| GET | `/reports` | `reports.index` |
| GET | `/customers` | `customers.index` |
| GET | `/customers/{user}` | `customers.show` |
| GET | `/customers/export` | `customers.export` |

### Taxonomies

| Resource | Routes |
|----------|--------|
| Categories | `categories.index/store/update/destroy` |
| Tags | `tags.index/store/update/destroy` |
| Brands | `brands.index/store/update/destroy` |

### Attributes & Terms

| Method | URI | Name |
|--------|-----|------|
| GET | `/attributes` | `attributes.index` |
| POST | `/attributes` | `attributes.store` |
| PUT | `/attributes/{attribute}` | `attributes.update` |
| DELETE | `/attributes/{attribute}` | `attributes.destroy` |
| POST | `/attributes/reorder` | `attributes.reorder` |
| POST | `/attributes/{attribute}/terms` | `attributes.terms.store` |
| GET | `/attributes/{attribute}/terms` | `attributes.terms` |
| PUT | `/attributes/{attribute}/terms/{term}` | `attributes.terms.update` |
| DELETE | `/attributes/{attribute}/terms/{term}` | `attributes.terms.destroy` |
| POST | `/attributes/{attribute}/terms/reorder` | `attributes.terms.reorder` |

### Product Fields

| Method | URI | Name |
|--------|-----|------|
| GET | `/product-fields` | `product-fields.index` |
| POST | `/product-fields` | `product-fields.store` |
| POST | `/product-fields/reorder` | `product-fields.reorder` |
| PUT | `/product-fields/{productField}` | `product-fields.update` |
| DELETE | `/product-fields/{productField}` | `product-fields.destroy` |

### Coupons, Reviews, Shipping, Tax, Currencies, Webhooks

| Resource | Routes |
|----------|--------|
| Coupons | `coupons.index/create/store/edit/update/destroy/toggle-active` |
| Reviews | `reviews.index/store/approve/reject/destroy` |
| Shipping Methods | `shipping-methods.index/store/update/destroy/toggle-active` |
| Shipping Zones | `shipping-zones.index/create/store/edit/update/destroy` |
| Tax Rates | `tax-rates.index/store/update/destroy/toggle-active` |
| Currencies | `currencies.index/store/update/destroy/toggle-active` |
| Webhooks | `webhooks.index/store/update/destroy/toggle-active/test/calls` |
| Shipments | `shipments.index/store/update` (scoped under `/orders/{order}/shipments`, update by `/{shipment}`) |

## Admin Settings (`admin.shop.*` — `admin.php`)

| Method | URI | Name |
|--------|-----|------|
| GET | `/admin/shop/settings` | `admin.shop.settings.index` |
| PUT | `/admin/shop/settings` | `admin.shop.settings.update` |

## Customer Portal (`dashboard.my.*` — `customer-portal.php`)

All routes run with `web`, `auth`, `verified`.

| Method | URI | Name |
|--------|-----|------|
| GET | `/dashboard/my/orders` | `dashboard.my.orders.index` |
| GET | `/dashboard/my/orders/{order}` | `dashboard.my.orders.show` |
| GET | `/dashboard/my/cart` | `dashboard.my.cart.index` |
| GET | `/dashboard/my/cart/json` | `dashboard.my.cart.json` |
| POST | `/dashboard/my/cart/items` | `dashboard.my.cart.add-item` |
| PUT | `/dashboard/my/cart/items/{cartItem}` | `dashboard.my.cart.update-item` |
| DELETE | `/dashboard/my/cart/items/{cartItem}` | `dashboard.my.cart.remove-item` |
| DELETE | `/dashboard/my/cart` | `dashboard.my.cart.clear` |
| POST | `/dashboard/my/cart/apply-coupon` | `dashboard.my.cart.apply-coupon` |
| POST | `/dashboard/my/cart/remove-coupon` | `dashboard.my.cart.remove-coupon` |
| GET | `/dashboard/my/checkout` | `dashboard.my.checkout.index` |
| POST | `/dashboard/my/checkout` | `dashboard.my.checkout.store` |
| GET | `/dashboard/my/checkout/confirmation/{order}` | `dashboard.my.checkout.confirmation` |
| POST | `/dashboard/my/checkout/payment-intent` | `dashboard.my.checkout.payment-intent` |

## Storefront (`store.*`)

### Products (`storefront.php`)

| Method | URI | Name |
|--------|-----|------|
| GET | `/store` | `store.index` |
| GET | `/store/products` | `store.products` |
| GET | `/store/category/{slug}` | `store.category` |
| GET | `/store/{product}` | `store.show` |

> **Ordering caveat:** `/store/{product}` is a wildcard and is registered **last** so it
> doesn't shadow `/store/products` or `/store/category/{slug}`.

### Cart (`store-cart.php`)

| Method | URI | Name |
|--------|-----|------|
| GET | `/store/cart` | `store.cart.index` |
| GET | `/store/cart/json` | `store.cart.json` |
| POST | `/store/cart/items` | `store.cart.add-item` |
| PUT | `/store/cart/items/{cartItem}` | `store.cart.update-item` |
| DELETE | `/store/cart/items/{cartItem}` | `store.cart.remove-item` |
| DELETE | `/store/cart` | `store.cart.clear` |
| POST | `/store/cart/apply-coupon` | `store.cart.apply-coupon` |
| POST | `/store/cart/remove-coupon` | `store.cart.remove-coupon` |
| POST | `/store/currency` | `store.currency.switch` |

> Cart mutation endpoints return **JSON** — the frontend calls them with `fetch()` and then
> `router.reload({ only: ['cart'] })`. See [Frontend](frontend.md#cart-fetch--reload-pattern).

### Checkout (`store-checkout.php`)

| Method | URI | Name |
|--------|-----|------|
| GET | `/store/checkout` | `store.checkout.index` |
| POST | `/store/checkout` | `store.checkout.store` |
| GET | `/store/checkout/confirmation/{order}` | `store.checkout.confirmation` |
| POST | `/store/checkout/payment-intent` | `store.checkout.payment-intent` |
| GET | `/store/order/{order}/register` | `store.checkout.register-from-order` |
| POST | `/store/order/{order}/register` | `store.checkout.store-register` |
| POST | `/store/webhook/stripe` | `store.webhook.stripe` |

> `store.webhook.stripe` is excluded from CSRF verification in the host
> (`PreventRequestForgery::except` in `DashboardCoreServiceProvider`).

### Wishlist (`store-wishlist.php`)

| Method | URI | Name | Middleware |
|--------|-----|------|------------|
| GET | `/store/wishlist` | `store.wishlist.index` | `web` |
| POST | `/store/wishlist/toggle/{product}` | `store.wishlist.toggle` | `web`, `auth` |
| GET | `/api/wishlist/ids` | `store.wishlist.ids` | `web`, `auth` |

### Back-in-stock (`store-notify.php`)

| Method | URI | Name |
|--------|-----|------|
| POST | `/store/notify-back-in-stock` | `store.notify-back-in-stock` |

### Abandoned-cart recovery (`recovery.php`)

| Method | URI | Name | Middleware |
|--------|-----|------|------------|
| GET | `/store/cart/recover/{cart}` | `store.cart.recover` | `web`, `signed` |

## JSON API (`api.*`)

| Method | URI | Name | Middleware |
|--------|-----|------|------------|
| GET | `/api/products` | `api.products.index` | `api` |
| GET | `/api/products/{product}` | `api.products.show` | `api` |
| GET | `/api/orders` | `api.orders.index` | `api`, `auth:sanctum` |
| GET | `/api/orders/{order}` | `api.orders.show` | `api`, `auth:sanctum` |

## CSRF & Token Exclusions

- `deploy/*`, `api/notifications/*`, `csp-report`, and the Stripe webhook path are excluded
  from CSRF by the core provider (`PreventRequestForgery::except`).
- Rate limiting on the public storefront is delegated to the host's web middleware stack.

## Related

- [Controllers](controllers.md) — the handlers behind every route above.
- [Permissions & Policies](permissions.md) — the `permission:shop.view` middleware plus per-action
  policy checks inside each controller.
