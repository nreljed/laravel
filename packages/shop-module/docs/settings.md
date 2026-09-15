# Settings

Shop settings are **DB-backed** (`settings` table, `group = 'shop'`), managed through a
dedicated admin page at `/admin/shop/settings` (page key `shop/settings/index`). The
`config/shop.php` file only supplies **environment defaults** — the DB wins once a value
exists.

## Architecture

```
Admin Settings page (Settings/Index.tsx)
        │  PUT admin.shop.settings.update
        ▼
ShopSettingsController::update()  →  Setting::updateOrCreate(group='shop', key=…)
        ▼
settings table (group = 'shop')
        ▼
ShopSettingsService::all()/get()/…  (typed getters + casts + defaults)
        ▼
Shared as shopSettings + currencyFormat props  (lazy closures in getSharedData())
```

## Storage

- **Controller:** `ShopSettingsController::index()` renders the page with
  `settings` / `countries` / `currencies`; `update()` validates every group and persists via
  `Setting::updateOrCreate(['group' => 'shop', 'key' => …])`. Booleans are stored as `"1"`/`"0"`.
- **Service:** `ShopSettingsService` (singleton) reads `SettingsService::getGroup('shop')`,
  merges against a typed default map, and casts values to `bool`/`int`/`array`/`string` based
  on the default's type. `clearCache()` delegates to the core `SettingsService`.

## Setting Keys

### Store Profile
`store_name`, `store_email`, `store_address_line1`, `store_address_line2`, `store_city`,
`store_postcode`

### Selling Locations
| Key | Default | Notes |
|-----|---------|-------|
| `base_country` | `TN` | ISO-2, must be `size:2` |
| `selling_locations_mode` | `all` | `all` \| `specific` \| `except` |
| `selling_locations` | `[]` | Array of ISO-2 countries |

Enforced at checkout via `ShopSettingsService::isCountryAllowed()` (billing + shipping
country) and used as the `ShippingService` fallback base country.

### Product & Inventory
| Key | Default |
|-----|---------|
| `default_product_status` | `draft` |
| `low_stock_threshold` | `5` |
| `hold_stock_enabled` | `true` |
| `hold_stock_minutes` | `60` |
| `backorder_default` | `do_not_allow` |
| `inventory_enabled` | `true` |
| `show_out_of_stock` | `true` |

### Currency & Pricing
| Key | Default |
|-----|---------|
| `currency` | `EUR` |
| `currency_symbol` | `€` |
| `currency_position` | `before` (`before` \| `after` \| `before_space` \| `after_space`) |
| `thousands_separator` | `,` |
| `decimal_separator` | `.` |
| `price_decimals` | `2` |

`currencyFormat()` normalizes these into the shape consumed by `lib/currency.ts`:
`{ position, thousands_separator, decimal_separator, decimals }`.

### Tax
| Key | Default | Notes |
|-----|---------|-------|
| `prices_include_tax` | `false` | Inc-tax back-calculation at checkout |
| `tax_based_on` | `billing` | `billing` \| `shipping` |
| `tax_shipping` | `false` | Tax applied to shipping total |
| `tax_display` | `exc` | `inc` \| `exc` \| `both` |

### Checkout
| Key | Default | Notes |
|-----|---------|-------|
| `enable_guest_checkout` | `true` | When off, guests are redirected to login |
| `require_company_field` | `false` | `billing_address.company` becomes required |
| `require_terms` | `false` | Adds an `accepted_terms` checkbox + validation |
| `checkout_default_country` | `''` | Prefills the billing country |

### Units of Measure
`weight_unit` (`kg`), `dimension_unit` (`cm`)

### Features & Automation
| Key | Default |
|-----|---------|
| `enable_reviews` | `true` |
| `enable_wishlist` | `true` |
| `auto_complete_orders` | `false` |
| `abandoned_cart_enabled` | `true` |
| `abandoned_cart_hours_until_abandoned` | `1` |
| `abandoned_cart_sequence_hours` | `1,24,72` (CSV) |
| `sale_scheduling_enabled` | `true` |
| `sale_scheduling_normalize_to_day_boundaries` | `true` |

## `config/shop.php` (environment defaults)

The config file mirrors the DB keys as `env()` defaults (e.g. `CHECKOUT_GUEST_ENABLED`,
`SHOP_PRICES_INCLUDE_TAX`, `SHOP_SELLING_MODE`, `HOLD_STOCK_MINUTES`). It also holds:

| Key | Notes |
|-----|-------|
| `currency_symbol` | Legacy default symbol |
| `abandoned_cart` | `enabled`, `hours_until_abandoned`, `sequence_hours` |
| `hold_stock` | `enabled`, `default_minutes` |
| `inventory` | `default_low_stock_threshold`, `enabled` |
| `sale_scheduling` | `enabled`, `normalize_to_day_boundaries` |
| `storefront.hero` | `title`, `subtitle`, `cta_label` |
| `checkout` / `tax` / `selling_locations` / `formatting` / `store` | Env defaults for the above |

> The DB-backed settings (`ShopSettingsService`) are the **source of truth** at runtime. The
> config file is only consulted as a fallback for keys that were never persisted.

## Consumers

| Consumer | Uses |
|----------|------|
| `CheckoutController` | Guest/company/terms guards, selling-locations policy |
| `TaxService` | `tax_based_on`, `prices_include_tax`, `tax_shipping` |
| `ShippingService` | `base_country` fallback |
| `CurrencyService::format()` | `currencyFormat()` (position/separators/decimals) |
| `Checkout.tsx` | Terms checkbox, company label, default country, totals |
| `lib/currency.ts` | `currencyFormat` shared prop |
| `HandleInertiaRequests` (core) | `shopSettings` + `currencyFormat` shared props via `getSharedData()` |

## Related

- [Frontend](frontend.md) — `Settings/Index.tsx` accordion layout.
- [Architecture](architecture.md) — how `getSharedData()` lazy closures feed Inertia props.
