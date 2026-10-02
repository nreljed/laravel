# Role System

The application uses a role-based permission system built on a custom `Role` and `Permission` model with Gate-based authorization. Permissions are defined in two tiers: **core permissions** (seeded by `RolesAndPermissionsSeeder`) and **module permissions** (synced by `modules:sync-permissions`).

## Role Hierarchy

```
Admin (super admin)
  └── Shop Owner (full shop control)
       └── Shop Manager (edit existing content)
            └── Customer (account only)
```

## Roles Overview

| Role | Slug | Default? | Description |
|------|------|----------|-------------|
| Admin | `admin` | No | Full access to everything including user management, settings, roles, system health |
| Shop Owner | `shop_owner` | No | Full shop control: products, orders, categories, tags, brands, attributes, reviews, coupons, shipping, taxes, webhooks, reports + posts |
| Shop Manager | `shop_manager` | No | Manage existing shop content: edit products, view and update orders, moderate reviews + posts |
| Customer | `customer` | **Yes** | Account management: profile, notifications, account settings. No module or admin access |

> The default role for new registrations is `customer`, configurable in Settings > Registration.

## Core Permissions

These permissions are seeded by `RolesAndPermissionsSeeder` and are always present:

| Slug | Description | Admin | Shop Owner | Shop Manager | Customer |
|------|-------------|-------|------------|--------------|----------|
| `manage-users` | Create, edit, delete users | ✅ | ❌ | ❌ | ❌ |
| `manage-settings` | Change application settings | ✅ | ❌ | ❌ | ❌ |
| `manage-roles` | Assign and manage roles | ✅ | ❌ | ❌ | ❌ |
| `view-system-health` | View system diagnostics | ✅ | ❌ | ❌ | ❌ |
| `manage_options` | Module-level settings (admin only) | ✅ | ❌ | ❌ | ❌ |
| `view-dashboard` | Access the main dashboard | ✅ | ✅ | ✅ | ✅ |
| `view-profile` | View own profile | ✅ | ✅ | ✅ | ✅ |
| `edit-profile` | Edit own name, email, password | ✅ | ✅ | ✅ | ✅ |
| `delete-account` | Delete own account | ✅ | ✅ | ✅ | ✅ |
| `view-notifications` | View notifications | ✅ | ✅ | ✅ | ✅ |
| `manage-notifications` | Mark read, delete notifications | ✅ | ✅ | ✅ | ✅ |

## Module Permissions

Module permissions are registered by feature providers (`BookingServiceProvider`,
`PropertiesServiceProvider`) and synced to the database via:

```bash
php artisan modules:sync-permissions
```

### Shop Module

| Slug | Admin | Shop Owner | Shop Manager |
|------|-------|------------|--------------|
| `shop.view` | ✅ | ✅ | ✅ |
| `shop.products.create` | ✅ | ✅ | ❌ |
| `shop.products.edit` | ✅ | ✅ | ✅ |
| `shop.products.delete` | ✅ | ✅ | ❌ |
| `shop.orders.view` | ✅ | ✅ | ✅ |
| `shop.orders.edit` | ✅ | ✅ | ✅ |
| `shop.orders.refund` | ✅ | ✅ | ❌ |
| `shop.tags.manage` | ✅ | ✅ | ❌ |
| `shop.brands.manage` | ✅ | ✅ | ❌ |
| `shop.coupons.manage` | ✅ | ✅ | ❌ |
| `shop.reviews.moderate` | ✅ | ✅ | ✅ |
| `shop.shipping.manage` | ✅ | ✅ | ❌ |
| `shop.taxes.manage` | ✅ | ✅ | ❌ |
| `shop.webhooks.manage` | ✅ | ✅ | ❌ |
| `shop.currencies.manage` | ✅ | ✅ | ❌ |

## Permission Assignment Flow

```
1. RolesAndPermissionsSeeder::run()    — creates 4 roles + core permissions
2. modules:sync-permissions            — syncs module perms from providers
   → assigns all module perms to admin + shop_owner
3. New user registration               — gets customer role (configurable)
4. Guest checkout account creation     — gets customer role
```

## Creating Custom Roles

Custom roles can be created via the admin UI (Settings > Roles) or via the Artisan command:

```bash
php artisan roles:create "Editor" --permissions="view-dashboard,view-profile,properties.view,properties.edit" --description="Can edit properties"
```

## Technical Details

- **Models**: `App\Models\Role`, `App\Models\Permission`
- **Gates**: Registered dynamically in `App\Providers\PermissionGateServiceProvider`
- **Middleware**: `permission:{slug}` middleware for route protection
- **Admin Middleware**: `admin` middleware checks `$user->isAdmin()` (has `admin` role)

### Key Files

| File | Purpose |
|------|---------|
| `database/seeders/RolesAndPermissionsSeeder.php` | Seeds core roles and permissions |
| `app/Console/Commands/SyncModulePermissions.php` | Syncs module permissions to DB + assigns to roles |
| `app/Providers/PermissionGateServiceProvider.php` | Registers dynamic Gates for all permissions |
| `app/Http/Middleware/HandleInertiaRequests.php` | Shares `userCapabilities` with frontend |
| `app/Models/User.php` | `isAdmin()`, `hasRole()`, `hasPermission()` methods |
| `app/Http/Middleware/AdminMiddleware.php` | 403 if not admin |
| `app/Http/Middleware/CheckPermission.php` | Route-based permission check |
