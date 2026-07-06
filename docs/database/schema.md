# Database Schema

## Application Tables

### `users`

| Column | Type | Notes |
|--------|------|-------|
| `id` | bigint AI | |
| `name` | string | Display name |
| `email` | string | Login identifier |
| `password` | string | Bcrypt hashed |
| `display_name` | string nullable | Separate display name |
| `avatar` | string nullable | Avatar path |
| `email_verified_at` | timestamp nullable | |
| `remember_token` | string nullable | |
| `created_at` | timestamp | |
| `updated_at` | timestamp | |
| `deleted_at` | timestamp | Soft delete |

Relations: `roles` (many-to-many via `role_user`), `permissions` (many-to-many via `permission_user`), `bookings`, `posts`, `properties`, `notifications`

### `roles`

| Column | Type | Notes |
|--------|------|-------|
| `id` | bigint AI | |
| `name` | string | Display name |
| `slug` | string unique | Identifier |
| `description` | text nullable | |
| `created_at` | timestamp | |
| `updated_at` | timestamp | |

### `permissions`

| Column | Type | Notes |
|--------|------|-------|
| `id` | bigint AI | |
| `name` | string | Display name |
| `slug` | string unique | Identifier |
| `description` | text nullable | |
| `guard_name` | string | Default: `web` |
| `created_at` | timestamp | |
| `updated_at` | timestamp | |

### `role_user` / `permission_user` / `permission_role`

Pivot tables for many-to-many relationships.

### `modules`

| Column | Type | Notes |
|--------|------|-------|
| `id` | bigint AI | |
| `name` | string | Module name |
| `slug` | string unique | Module identifier |
| `enabled` | boolean | Toggle on/off |
| `version` | string | Version string |
| `dependencies` | json | Dependency slugs |
| `order` | integer | Display order |
| `created_at` | timestamp | |
| `updated_at` | timestamp | |

### `settings`

| Column | Type | Notes |
|--------|------|-------|
| `id` | bigint AI | |
| `key` | string unique | Setting key |
| `value` | text | Serialized value |
| `group` | string nullable | Setting group |
| `created_at` | timestamp | |
| `updated_at` | timestamp | |

### `notifications`

Standard Laravel notifications table.

### `personal_access_tokens`

Sanctum tokens table.

### `sessions`

Session storage table.

### `audit_logs`

| Column | Type | Notes |
|--------|------|-------|
| `id` | bigint AI | |
| `user_id` | bigint FK nullable | Who performed action |
| `action` | string | Action type |
| `metadata` | json | Action context |
| `ip_address` | string nullable | |
| `created_at` | timestamp | |

### `property_types` / `cities` / `features` / `property_statuses`

Taxonomy tables for properties:

| Column | Type | Notes |
|--------|------|-------|
| `id` | bigint AI | |
| `name` | string | Display name |
| `slug` | string unique | URL-friendly |
| `description` | text nullable | |
| `image` | string nullable | URL (types, cities only) |
| `order` | integer nullable | Sort order |
| `created_at` | timestamp | |
| `updated_at` | timestamp | |

### `properties`

| Column | Type | Notes |
|--------|------|-------|
| `id` | bigint AI | |
| `user_id` | bigint FK | Owner |
| `name` | string | Property name |
| `description` | text | |
| `status` | string | Published, Draft, etc. |
| `type_id` | bigint FK→property_types | |
| `city_id` | bigint FK→cities | |
| `price` | decimal | Nightly rate |
| `surface` | decimal nullable | Surface area |
| `surface_unit` | string nullable | sqft, sqm |
| `address` | text nullable | |
| `latitude` | decimal nullable | |
| `longitude` | decimal nullable | |
| `metadata` | json | Flexible metadata (booking config, features, etc.) |
| `published_at` | datetime nullable | Scheduled publish date |
| `created_at` | timestamp | |
| `updated_at` | timestamp | |
| `deleted_at` | timestamp | Soft delete |

## Module Tables

### `bookings`

See [Booking Architecture](../modules/booking/architecture.md).

### `booking_actions`

| Column | Type | Notes |
|--------|------|-------|
| `id` | bigint AI | |
| `booking_id` | bigint FK→bookings | |
| `action` | string | check_in, check_out, etc. |
| `performed_by` | string nullable | |
| `metadata` | json nullable | |
| `created_at` | timestamp | |

### `booking_tokens`

| Column | Type | Notes |
|--------|------|-------|
| `id` | bigint AI | |
| `booking_id` | bigint FK→bookings | |
| `token` | string | Hashed token |
| `expires_at` | timestamp | |
| `used_at` | timestamp nullable | |
| `created_at` | timestamp | |

### `posts`

| Column | Type | Notes |
|--------|------|-------|
| `id` | bigint AI | |
| `user_id` | bigint FK | Author |
| `title` | string | |
| `slug` | string unique | |
| `content` | text | |
| `status` | string | draft, published |
| `featured_image` | string nullable | |
| `published_at` | datetime nullable | |
| `created_at` | timestamp | |
| `updated_at` | timestamp | |
| `deleted_at` | timestamp | Soft delete |

### Shop Tables (module-specific migrations)

Products, orders, order_items, cart_items — managed by the Shop module's own `database/migrations/` directory.

## Key Relationships

```
User ──hasMany── Property
User ──hasMany── Post
User ──hasMany── Booking
User ──belongsToMany── Role
User ──belongsToMany── Permission
Role ──belongsToMany── Permission

Property ──belongsTo── User
Property ──belongsTo── PropertyType
Property ──belongsTo── City
Property ──hasMany── Booking

Booking ──belongsTo── Property
Booking ──belongsTo── User (optional)
Booking ──hasMany── BookingAction
```
