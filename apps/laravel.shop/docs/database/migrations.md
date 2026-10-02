# Migration Conventions

## Naming Convention

Migrations follow standard Laravel naming: `YYYY_MM_DD_HHMMSS_description.php`

Module-specific migrations (Shop only) follow the same convention inside `app/Modules/Shop/database/migrations/`.

## Application Migrations

All application-level migrations in `database/migrations/`:

| File | Description |
|------|-------------|
| `*_create_users_table.php` | Users table |
| `*_create_password_resets_table.php` | Password resets |
| `*_create_roles_tables.php` | Roles, permissions, pivots |
| `*_create_modules_table.php` | Module registry |
| `*_create_settings_table.php` | Settings |
| `*_create_notifications_table.php` | Notifications |
| `*_create_audit_logs_table.php` | Audit trail |
| `*_create_property_types_table.php` | Property types |
| `*_create_cities_table.php` | Cities |
| `*_create_features_table.php` | Features |
| `*_create_property_statuses_table.php` | Property statuses |
| `*_create_properties_table.php` | Properties |
| `*_create_bookings_table.php` | Bookings |
| `*_create_booking_tokens_table.php` | Booking tokens |
| `*_create_booking_actions_table.php` | Booking action audit log |
| `*_create_posts_table.php` | Posts |
| `*_add_order_to_modules_table.php` | Module order column |
| `*_add_published_at_to_properties_table.php` | Scheduled publish |
| `*_add_image_to_term_tables.php` | Term image URLs |

## Module Migrations

Only the Shop module has its own migrations:

```
app/Modules/Shop/database/migrations/
  *_create_products_table.php
  *_create_orders_table.php
  *_create_order_items_table.php
  *_create_cart_items_table.php
```

## Running Migrations

```bash
php artisan migrate                        # Run all pending migrations
php artisan migrate --force                # Production (no prompts)
php artisan migrate:fresh                  # Drop all tables and re-run
```

After migration changes:
```bash
php artisan db:seed --class=RolesAndPermissionsSeeder
php artisan modules:sync-permissions
```
