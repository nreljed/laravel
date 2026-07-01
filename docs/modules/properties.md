# Properties Module

Manage property listings with pricing, surface area, and rich metadata.

- **Slug**: `properties`
- **Version**: `1.0.0`
- **Dependencies**: None
- **Has Settings**: Yes
- **Nav Label**: Manage Property

## Permissions

| Slug | Name |
|------|------|
| `properties.view` | View Properties |
| `properties.create` | Create Properties |
| `properties.edit` | Edit Properties |
| `properties.delete` | Delete Properties |

## Routes

| File | Prefix | Auth | Middleware |
|------|--------|------|------------|
| `Routes/web.php` | `/dashboard/properties` | Required | `properties.view` |

### Web Routes (`Routes/web.php`)

Full CRUD (index, create, store, edit, update, destroy) + image upload/delete/reorder/set-featured + status change + term management:

- Property Types CRUD
- Cities CRUD
- Features CRUD
- Statuses CRUD
- `PATCH /{property}/status` — `updateStatus` (name: `dashboard.properties.update-status`)

### Authorization

- `StorePropertyRequest::authorize()` returns `$this->user()?->can('create', Property::class)`
- `UpdatePropertyRequest::authorize()` returns `$this->user()?->can('update', $property)`
- `PropertyController::store()` calls `$this->authorize('create', Property::class)`
- `PropertyController::update()` calls `$this->authorize('update', $property)`
- `PropertyController::destroy()` calls `$this->authorize('delete', $property)`
- City validation in form requests references `cities` table (not `property_categories`)

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

## Repeater Configs

- `additional_details` — max 20, title+value pairs
- `floor_plans` — max 10, 6 fields per plan (name, image, bedrooms, bathrooms, size, description)

## Gallery Config

- `gallery` — max 20 images, upload path `properties`, hidden from media library

## Profile Fields

Extends the user profile with:

- `phone_numbers` — repeater, max 10, preset types: Mobile, Work, Home, WhatsApp
- `social_media` — repeater, max 20, preset platforms: Facebook, Instagram, LinkedIn, X, TikTok, YouTube

## Notification Types

| Type | Channels |
|------|----------|
| `property.created` | in_app only |
| `property.updated` | in_app only |
| `property.inquiry` | in_app + email (template `property-inquiry`) |

## Email Templates

None currently.

## Meta Formatting

- `price_fields`: `['price']`
- `surface_fields`: `['surface']`

## User Cleanup

No-op on user deletion.
