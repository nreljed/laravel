# Properties Module

Manage property listings with pricing, surface area, and rich metadata.

- **Slug**: `properties`
- **Version**: `1.0.0`
- **Dependencies**: None
- **Has Settings**: Yes
- **Nav Group Label**: Manage Property

## Sub-Documents

| Document | Description |
|----------|-------------|
| [Controllers](controllers.md) | Controller methods reference |
| [Services](services.md) | Services reference |
| [Frontend](frontend.md) | Frontend pages and components |

## Quick Reference

| Attribute | Value |
|-----------|-------|
| **Permissions** | `properties.view`, `create`, `edit`, `delete` |
| **Route Files** | `web.php` (auth) |
| **Dashboard Stats** | `total`, `published`, `draft`, `totalValue` |
| **Repeater Configs** | `additional_details`, `floor_plans` |
| **Gallery Config** | `gallery` (max 20, hidden from library) |
| **Profile Fields** | `phone_numbers` (max 10), `social_media` (max 20) |
| **Notification Types** | `property.created`, `updated`, `inquiry` |
| **Migration Paths** | `[]` — no module-specific migrations |

## Routes

| File | Prefix | Middleware | Purpose |
|------|--------|------------|---------|
| `Routes/web.php` | `/dashboard/properties` | `auth`, `verified`, `properties.view` | CRUD + terms management |

Full CRUD (index, create, store, edit, update, destroy) + image upload/delete/reorder/set-featured + status change + term management (Types, Cities, Features, Statuses CRUD).

### Authorization

- `StorePropertyRequest::authorize()` checks `create` Policy
- `UpdatePropertyRequest::authorize()` checks `update` Policy
- `PropertyController` calls `$this->authorize()` on store/update/destroy
- City validation references `cities` table

## Nav Items

```
Manage Property          (SidebarGroupLabel)
  └─ Properties          (collapsible parent)
      ├─ All Properties
      ├─ Add Property
      ├─ Types
      ├─ Categories
      ├─ Features
      └─ Statuses
```

## Dashboard Stats

- `total` — All properties count
- `published` — Published status count
- `draft` — Draft status count
- `totalValue` — Sum of published property prices

## Scheduled Publishing

Properties have a `published_at` column. The `PublishScheduledProperties` artisan command (runs every minute via `routes/console.php`) transitions properties from `future` status to `published` when their `published_at` date is reached.

- Migration: `2026_07_01_000001_add_published_at_to_properties_table.php`
- Command: `app/Console/Commands/PublishScheduledProperties.php`

## Term Image Support

`property_types` and `cities` tables have an `image` column for URL-based term images. Displayed as thumbnails in Types.tsx and Cities.tsx frontend pages.

- Migration: `2026_07_01_000002_add_image_to_term_tables.php`

## Taxonomy Seed Data

| Taxonomy | Count | Details |
|----------|-------|---------|
| Property Types | 14 | Apartment, Villa, House, Studio, Condo, Townhouse, Duplex, Penthouse, Farmhouse, Cabin, Cottage, Bungalow, Loft |
| Statuses | 8 | Published, Draft, Pending, Sold, Rented, Under Offer, Off Market, Under Construction, Promissory |
| Feature Groups | 4 | Exterior (12), Interior (15), Security (9), Special (12) — 48 total features |
| Cities | 200+ | Tunisian cities |

## Meta Formatting

- `price_fields`: `['price']`
- `surface_fields`: `['surface']`

## User Cleanup

No-op on user deletion (properties are not deleted when user is removed).
