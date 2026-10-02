# Route Architecture

## Route Files

| File | Prefix | Middleware | Purpose |
|------|--------|------------|---------|
| `routes/web.php` | `/` | web | Dashboard, admin, public, deploy, switch-mode |
| `routes/auth.php` | `/` | web | Breeze auth + profile, loaded by `RouteServiceProvider` |

## Route Registration via Modules

Module routes are registered by `ModuleRegistry::registerRoutes()` which calls `routes()` on each booted module. Modules use the `HasRoutes` trait:

### `loadRoutes()` — Authenticated Dashboard Routes

```php
$this->loadRoutes(
    __DIR__ . '/Routes/web.php',   // route file
    '/dashboard/bookings',          // URI prefix
    'dashboard.bookings.',          // name prefix
    'bookings.view'                 // permission slug (optional)
);
```

Middleware: `web`, `auth`, `verified`, `permission:{slug}`

### `loadAdminRoutes()` — Admin Routes

```php
$this->loadAdminRoutes(
    __DIR__ . '/Routes/admin.php',
    '/admin/shop',
    'admin.shop.',
    'shop.view'
);
```

Middleware: `web`, `auth`, `verified`, `admin`, `permission:{slug}`

### `loadPublicRoutes()` — Public Routes

```php
$this->loadPublicRoutes(
    __DIR__ . '/Routes/guest.php',  // route file
    ''                               // prefix (empty for guest, 'api' for API)
);
```

Middleware: `web` only.

## Route Naming

Routes follow the convention: `{prefix}.{name}` where name is defined in the route file.

Examples:
- `dashboard.bookings.index`
- `dashboard.bookings.create`
- `dashboard.properties.edit`
- `booking.show`
- `api.booking.check-availability`

## Guest Routes

Guest portal routes under `/booking/...` use token-based auth (no session required). Loaded via `loadPublicRoutes()` with empty prefix.

## API Routes

Public API routes under `/api/booking/...` loaded via `loadPublicRoutes()` with `api` prefix.

## Route Listing

```bash
php artisan route:list --path=booking
php artisan route:list --path=properties
php artisan route:list --path=posts
php artisan route:list --path=Shop
```
