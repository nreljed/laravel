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
> blog, AI) is documented in [`platform/docs/`](https://github.com/nreljed/platform/tree/main/docs)
> — this package builds on it but does not re-document it.

## Table of Contents

| Document | Covers |
|----------|--------|
| [Architecture](architecture.md) | Package layout, provider registration, shared data, frontend wiring |
| [Data Model](data-model.md) | All 29 models, relationships, variation system |
| [Services](services.md) | The 14 domain services (inventory, checkout, coupons, shipping, tax, webhooks…) |
| [Controllers](controllers.md) | Every controller + method/route/access reference |
| [Routes](routes.md) | The 11 self-loaded route files, prefixes, middleware |
| [Frontend](frontend.md) | Inertia pages, key components, cart fetch+reload pattern |
| [Settings](settings.md) | DB-backed Shop settings page, `ShopSettingsService`, config keys |
| [Permissions & Policies](permissions.md) | The 16 permission slugs + 9 policies |
| [Workflows](workflows.md) | Order lifecycle, hold stock, abandoned cart, coupon engine, checkout flow |

## Quick Reference

| Attribute | Value |
|-----------|-------|
| **Permissions** | `shop.view`, `shop.products.create/edit/delete`, `shop.orders.view/edit/refund`, `shop.tags.manage`, `shop.brands.manage`, `shop.coupons.manage`, `shop.reviews.moderate`, `shop.shipping.manage`, `shop.taxes.manage`, `shop.webhooks.manage`, `shop.currencies.manage` |
| **Route files** | 11 self-loaded (see [Routes](routes.md)) |
| **Models** | 29 (see [Data Model](data-model.md)) |
| **Services** | 14 (see [Services](services.md)) |
| **Policies** | 9 (see [Permissions](permissions.md)) |
| **Commands** | 5 (`shop:release-expired-holds`, `shop:abandoned-cart-process`, `shop:process-pending-webhooks`, `shop:process-scheduled-sales`, `shop:product-seed`) |
| **Email templates** | 7 (order-confirmation, order-shipped, order-invoice, low-stock-alert, cart-recovery-1h/24h/72h) |
| **Notification types** | 4 (`order.placed`, `order.status_changed`, `low_stock`, `product.stock_changed`) |
| **Migrations** | 58, auto-loaded |
| **Shared props** | `currencies`, `selectedCurrency`, `currencyPosition`, `cart`, `wishlistIds`, `shopSettings`, `currencyFormat` |

## Related Docs

- **Host integration / page registration:** see `platform/docs/architecture.md`
  (feature providers, `config/features.php`, Vite aliases, page globs).
- **Deploy pipeline:** both apps ship the same canonical check-first deploy flow —
  see `platform/docs/deploy.md`.
