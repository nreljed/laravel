# Permissions & Policies

## Permissions

All permissions are declared in `ShopServiceProvider::permissions()` and granted to
roles by `php artisan db:seed --class=RolesAndPermissionsSeeder` (idempotent,
config-catalog backed). They are consumed by:

- the `permission:{slug}` route middleware (admin groups),
- `$this->authorize()` calls inside controllers,
- `$user->can(...)` checks in `getNavItems()` / `getNavGroupLabel()`.

| Slug | Name | Used for |
|-------|------|----------|
| `shop.view` | View Shop | Gate for the entire `/dashboard/shop/*` admin group |
| `shop.products.create` | Create Products | Product store (controller `authorize('create')`) |
| `shop.products.edit` | Edit Products | Product update/edit |
| `shop.products.delete` | Delete Products | Product destroy |
| `shop.orders.view` | View Orders | Orders index/show |
| `shop.orders.edit` | Edit Orders | Order create/update/status change |
| `shop.orders.refund` | Refund Orders | Order refund |
| `shop.tags.manage` | Manage Tags | Tags CRUD |
| `shop.brands.manage` | Manage Brands | Brands CRUD |
| `shop.coupons.manage` | Manage Coupons | Coupons CRUD |
| `shop.reviews.moderate` | Moderate Reviews | Review approve/reject/destroy |
| `shop.shipping.manage` | Manage Shipping | Shipping methods + zones CRUD |
| `shop.shipments.manage` | Manage Shipments | Per-order shipment create/update (ship/deliver/tracking) |
| `shop.taxes.manage` | Manage Taxes | Tax rates CRUD |
| `shop.webhooks.manage` | Manage Webhooks | Webhooks CRUD + test |
| `shop.currencies.manage` | Manage Currencies | Currencies CRUD + toggle-active |

> The customer portal (`/dashboard/my/*`) requires no special permission — only `auth` +
> `verified`. Admins with `shop.products.create` get the "Manage Shop" nav group; customers
> with only `shop.view` get the "My Account" nav group (see `getNavGroupLabel()`).

## Policies

Policies are registered in `ShopServiceProvider::registerPolicies()` via `Gate::policy()`.
Controllers call `$this->authorize(...)` in every mutation/read action. Note the core
`Gate::before` grants admins blanket access.

| Policy | Model | Abilities |
|--------|-------|-----------|
| `ProductPolicy` | `Product` | `viewAny`, `view`, `create`, `update`, `delete` — permission-based |
| `OrderPolicy` | `Order` | `viewAny`, `view`, `create`, `update` — permission-based |
| `CouponPolicy` | `Coupon` | `viewAny` (`shop.view`), manage (`shop.coupons.manage`) |
| `CurrencyPolicy` | `Currency` | `viewAny` / manage (`shop.currencies.manage`) |
| `ProductReviewPolicy` | `ProductReview` | `viewAny` (`shop.reviews.moderate`), `create`, `approve`, `reject`, `delete` |
| `ShippingMethodPolicy` | `ShippingMethod` | `viewAny` (`shop.view`), manage (`shop.shipping.manage`) |
| `ShippingZonePolicy` | `ShippingZone` | `viewAny` (`shop.view`), manage (`shop.shipping.manage`) |
| `TaxRatePolicy` | `TaxRate` | `viewAny` (`shop.view`), manage (`shop.taxes.manage`) |
| `WebhookPolicy` | `Webhook` | `viewAny` (`shop.view`), manage (`shop.webhooks.manage`) |
| `ShipmentPolicy` | `Shipment` | `viewAny`/`view`/`create`/`update`/`delete` — `shop.shipments.manage` |

## How Access is Enforced (example chain)

For a `PUT /dashboard/shop/products/{product}` request:

1. **Route middleware** `web`, `auth`, `verified`, `permission:shop.view` — user must be
   authenticated and able to *see* the Shop section.
2. **Policy** `$this->authorize('update', $product)` in `ProductsController::update()` — user
   must have `shop.products.edit` (admin bypasses via core `Gate::before`).

## Adding a New Permission

1. Add the slug to `ShopServiceProvider::permissions()`.
2. Run `php artisan db:seed --class=RolesAndPermissionsSeeder`.
3. Reference it in a policy and call `$this->authorize(...)` in the controller.

## Related

- [Controllers](controllers.md) — which ability each controller method authorizes.
- [Routes](routes.md) — where the `permission:shop.view` middleware is applied.
