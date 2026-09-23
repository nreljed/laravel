# Shop Module — Documentation

The e-commerce domain package for the Laravel dashboard. Ships as a standalone
Composer package (`nreljed/shop-module`) so the host app stays generic — the same
pattern as [`nreljed/platform`](https://github.com/nreljed/platform).

**Namespace:** `Nreljed\ShopModule\` (PSR-4 → `src/`)
**Feature slug:** `shop`
**Dependencies:** `nreljed/platform` ^0.1, laravel/framework ^13.0, PHP ^8.4

> **Where the docs live:** this `docs/` folder is the authoritative reference for the
> Shop **domain** (products, orders, coupons, shipping, taxes, storefront, …). The
> shared **platform** (feature-provider contract, deploy endpoints, notifications,
> AI) is documented in [`nreljed/platform/docs/`](https://github.com/nreljed/platform/tree/main/docs)
> — this package builds on it but does not re-document it.

## Table of Contents

| Document | Covers |
|----------|--------|
| [Architecture](architecture.md) | Package layout, provider registration, shared data, frontend wiring |
| [Data Model](data-model.md) | All 32 models, relationships, variation system |
| [Services](services.md) | The 18 domain services (inventory, checkout, coupons, shipping, tax, webhooks, shipments…) |
| [Controllers](controllers.md) | Every controller + method/route/access reference |
| [Routes](routes.md) | The 11 self-loaded route files, prefixes, middleware |
| [Frontend](frontend.md) | Inertia pages, key components, cart fetch+reload pattern |
| [Settings](settings.md) | DB-backed Shop settings page, `ShopSettingsService`, config keys |
| [Permissions & Policies](permissions.md) | The 17 permission slugs + 10 policies |
| [Workflows](workflows.md) | Order lifecycle, hold stock, abandoned cart, coupon engine, checkout flow, webhooks |

## Quick Reference

| Attribute | Value |
|-----------|-------|
| **Permissions** | `shop.view`, `shop.products.create/edit/delete`, `shop.orders.view/edit/refund`, `shop.tags.manage`, `shop.brands.manage`, `shop.coupons.manage`, `shop.reviews.moderate`, `shop.shipping.manage`, `shop.shipments.manage`, `shop.taxes.manage`, `shop.webhooks.manage`, `shop.currencies.manage` |
| **Route files** | 11 self-loaded (see [Routes](routes.md)) |
| **Models** | 32 (see [Data Model](data-model.md)) |
| **Services** | 18 (see [Services](services.md)) |
| **Policies** | 10 (see [Permissions](permissions.md)) |
| **Commands** | 4 (`shop:release-expired-holds`, `shop:abandoned-cart-process`, `shop:process-pending-webhooks`, `shop:process-scheduled-sales`) |
| **Email templates** | 7 (order-confirmation, order-shipped, order-invoice, low-stock-alert, cart-recovery-1h/24h/72h) |
| **Notification types** | 4 (`order.placed`, `order.status_changed`, `low_stock`, `product.stock_changed`) |
| **Migrations** | 63, auto-loaded |
| **Shared props** | `currencies`, `selectedCurrency`, `currencyPosition`, `cart`, `wishlistIds`, `shopSettings`, `currencyFormat` |

## Related Docs

- **Host integration / page registration:** see `nreljed/platform/docs/architecture.md`
  (feature providers, `config/features.php`, Vite aliases, page maps).
- **Deploy pipeline:** all apps ship the same canonical check-first deploy flow —
  see `nreljed/platform/docs/deploy.md`.
