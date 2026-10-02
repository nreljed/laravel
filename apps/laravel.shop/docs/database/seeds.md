# Seed System

The application has a **two-tier** data initialization system: **essential seeders** (for fresh installs) and **demo data generator** (for development/testing).

## Architecture Overview

```
php artisan db:seed
  └── DatabaseSeeder.php
        ├── RolesAndPermissionsSeeder.php  — roles, permissions, role-permission assignments
        ├── User factory (x4)              — nstudio, shop_owner, shop_manager, customer
        ├── SettingsSeeder.php             — app config (general, email, security, etc.)
        └── CurrencySeeder.php             — EUR, USD, GBP, TND

php artisan demo:generate [--fresh]
  └── GenerateDemoData.php
        ├── createCustomers()              — 12 demo customer accounts
        ├── createShippingMethods()        — 4 shipping methods (skip if exist)
        ├── createTaxRates()               — 5 tax rates (skip if exist)
        ├── createCoupons()                — 6 coupons (WELCOME10, FLAT5, FREESHIP, etc.)
        ├── createOrders()                 — 18 orders across all statuses
        ├── createProductReviews()         — 11 reviews (some pending moderation)
        ├── createStockHistory()           — stock adjustments for 5 products
        └── createBlogPosts() (commented)  — 5 blog posts with categories/tags

php artisan shop:product-seed
  └── ShopProductSeeder.php
        ├── createBrands()                 — Xiaomi, Redmi
        ├── createCategories()             — 6 categories (parent-child hierarchy)
        ├── createTags()                   — 6 tags
        ├── createAttributes()             — Color (swatch) + Storage (button)
        ├── createProducts()               — 8 products (simple, variable, grouped, external, digital, service)
        ├── createReviews()                — 5 reviews for Xiaomi 14 Pro + Redmi Note 13 Pro
        └── ProductDownloads               — 2 downloadable files attached to Themes Pack

php artisan app:create-admin
  └── CreateAdmin.php                      — Production-only: creates admin from GitHub secrets
```

## Seeder Details

### DatabaseSeeder (Essential — `php artisan db:seed`)

| Component | What it does | Idempotent? |
|-----------|-------------|-------------|
| `RolesAndPermissionsSeeder` | Creates 7 roles (admin, editor, author, user, shop_owner, shop_manager, customer) + core permissions. Migrates old `crm_user` → `customer`. Shop/blog module perms (shop.*, posts.*) are also synced here (via `FeatureRegistry` merge), and roles are assigned per `config/roles.php` | Yes (`firstOrCreate`) |
| User factory (x4) | Creates `nstudio` (admin), `shop_owner`, `shop_manager`, `customer` — all password `azerty123/*` | No (creates every run) |
| `SettingsSeeder` | 25 settings across 7 groups (general, registration, notifications, security, email, maintenance, localization, formatting) | Yes (`firstOrCreate`) |
| `CurrencySeeder` | 4 currencies (EUR default, USD, GBP, TND) | Yes (`updateOrInsert`) |

**Warning:** `DatabaseSeeder` is for **DEV only**. Production uses `app:create-admin` from GitHub secrets (see Deployment section).

### ShopProductSeeder (Product Catalog — `php artisan shop:product-seed`)

Creates a realistic Xiaomi/Redmi product catalog:

| # | Product | Type | Key Features |
|---|---------|------|-------------|
| 1 | Xiaomi 14 Pro | `simple` | Flagship phone, 50 stock, on sale |
| 2 | Redmi Note 13 Pro | `variable` | 6 variations (3 colors × 2 storage), all on sale |
| 3 | 67W Turbo Charger | `simple` | Accessory, backorder notify, 200 stock |
| 4 | 20000mAh Power Bank 3 | `simple` | Accessory, backorders yes, 150 stock |
| 5 | Smart Band 8 Pro | `external` | Affiliate link to mi.com, no stock tracking |
| 6 | Smart Life Bundle | `grouped` | Bundles charger + power bank |
| 7 | Pro Themes Pack | `digital` | Downloadable, 2 files, catalog visibility only |
| 8 | Priority Care (1 Year) | `service` | Virtual, sold individually, not taxable |

**Variations:** Redmi Note 13 Pro uses the relational pivot table (`product_variation_attribute_term`) for Color (midnight-black, pearl-white, forest-green) and Storage (128GB, 256GB, 512GB) — 6 variations total, all with sale pricing active.

**SEO:** Every product has `meta_title`, `meta_description`, and `og_image`.

### GenerateDemoData (Demo — `php artisan demo:generate`)

Creates realistic e-commerce activity for development/testing.

**`--fresh` flag:** Deletes existing demo data before regenerating (customers by email, orders by user, coupons by code, stock adjustments by admin, shipping methods/tax rates by name). Safe to re-run.

**Customers:** 12 Tunisian-named accounts (e.g., Amine Ben Ali, Sarra Mansour) with realistic billing/shipping addresses (7 Tunisian cities).

**Orders:** 18 orders across all 8 statuses:

| Status | Count | Details |
|--------|-------|---------|
| completed | 10 | Includes notes, coupons, full status flow |
| pending | 2 | Fresh orders, one with coupon |
| processing | 1 | Color change request note |
| confirmed | 1 | Cash on delivery |
| cancelled | 1 | Refunded via Stripe |
| refunded | 1 | Damaged item, full refund + shipping |
| on-hold | 1 | Bank transfer verification |
| failed | 1 | Card declined |

Each order includes:
- Realistic subtotal/discount/shipping/tax breakdown
- `OrderAction` entries for full status transition history
- `OrderNote` (public and private where applicable)
- `CouponUsage` records when coupons applied
- Refund details for refunded orders

**Coupons:** 6 coupons ranging from percentage (WELCOME10, VIP25, SUMMER20, EXPIRED10) to fixed (FLAT5) to free_shipping (FREESHIP). SUMMER20 is auto-apply (`is_automatic: true`).

**Reviews:** 11 reviews across 5 products, some pending approval.

**Stock History:** 5 tracking-enabled products get initial stock, a sale event, and a restock event with realistic timestamps.

**Address pool:** 7 Tunisian addresses (Tunis, Sfax, Sousse, Bizerte, Nabeul, Gabès).

### CreateAdmin (Production — `php artisan app:create-admin`)

Used during CI/CD deployment. Reads `ADMIN_EMAIL`, `ADMIN_PASSWORD`, `ADMIN_NAME` from environment variables (or options). Creates the admin user if not exists, attaches the `admin` role. **Skips gracefully** if already exists and no password is provided.

Called automatically by `DeployController::check()` during deploy via the webhook.

## Permission Management

| Command | Purpose |
|---------|---------|
| `php artisan db:seed --class=RolesAndPermissionsSeeder` | Syncs feature permissions from platform + module `permissions()` catalogs to DB (idempotent) |
| `php artisan permissions:list` | Lists all registered permissions with their features |

Module permissions (e.g., `shop.products.*`, `posts.*`) are defined in each package's `ServiceProvider::permissions()`. They are seeded to the database and attached to roles by `RolesAndPermissionsSeeder`, and gates auto-register from the `permissions` table plus `FeatureRegistry::permissions()`.

## Idempotency Summary

| Seeder | Idempotent? | Key |
|--------|-------------|-----|
| `RolesAndPermissionsSeeder` | Yes | `firstOrCreate` by slug |
| `SettingsSeeder` | Yes | `firstOrCreate` by key |
| `CurrencySeeder` | Yes | `updateOrInsert` by code |
| `ShopProductSeeder` | Yes | `firstOrCreate` by slug (brands, categories, tags, products, variations) |
| `GenerateDemoData` | No | Use `--fresh` to clear before re-run |
| `DatabaseSeeder` (users) | No | Creates duplicates each run; use `migrate:fresh` first |

## Quick Start

```bash
# Full reset: wipe + seed essentials + products + demo
php artisan migrate:fresh
php artisan db:seed
php artisan shop:product-seed
php artisan db:seed --class=RolesAndPermissionsSeeder
php artisan demo:generate --fresh

# Quick refresh (seed essentials only)
php artisan migrate:fresh --seed
php artisan db:seed --class=RolesAndPermissionsSeeder
```
