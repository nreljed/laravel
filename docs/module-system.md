# Module System

## Overview

A modular CMS-style architecture. Each module is a self-contained unit with its own routes, controllers, models, migrations, frontend pages, permissions, and nav items. Modules are discovered automatically, dependency-resolved, and booted in order.

**4 modules:** Properties, Bookings, Posts, E-Commerce.

---

## Module Discovery

`ModuleLoader` scans `app/Modules/{Name}/{Name}Module.php` (e.g., `Booking/BookingModule.php`). Each class must implement `ModuleInterface`.

```php
// ModuleLoader convention:
// Directory: app/Modules/Booking/
// File:      BookingModule.php
// Class:     App\Modules\Booking\BookingModule
```

Registered as singletons in `ModuleServiceProvider`:

```php
$this->app->singleton(ModuleRegistry::class, fn () => new ModuleRegistry());
$this->app->singleton(ModuleLoader::class, fn ($app) => new ModuleLoader($app[ModuleRegistry::class]));
```

---

## Module Interface (`app/Modules/Contracts/ModuleInterface.php`)

Key methods:

```php
public function name(): string;              // "Bookings"
public function slug(): string;              // "bookings"
public function description(): ?string;
public function version(): string;
public function dependencies(): array;       // ["properties"]

public function register(): void;            // service container bindings
public function boot(): void;                // boot logic
public function routes(): void;              // route registration (uses HasRoutes trait)

public function getNavItems(): array;        // sidebar nav tree
public function getDashboardData(): array;   // stats widgets
public function getSharedData(): array;      // Inertia-shared props
public function getSettingsRoute(): ?string; // settings page URL

public function permissions(): array;        // [['slug','name','description'],...]
public function migrationPaths(): array;     // migration directories
public function onUserDelete(int $userId);   // cleanup on account deletion
```

Plus content-config methods: `getRepeaterConfig()`, `getGalleryConfig()`, `getProfileFieldsConfig()`, `getMetaFormattingConfig()`, `getNotificationTypes()`, `getEmailTemplates()`, `getFrontendPages()`.

---

## Module Lifecycle

```
ModuleServiceProvider::boot()
  1. ModuleLoader::load()          — discovers all Module classes
  2. ModuleRegistry::bootEnabled() — topological-sort by dependencies, calls boot()
  3. ModuleRegistry::registerRoutes() — calls routes() on each booted module
  4. Loads migrations from each module's migrationPaths()
  5. Defines Gates for each permission slug
```

### Dependency Resolution

Modules declare dependencies via `dependencies()` (e.g., Booking depends on Properties). `ModuleRegistry::resolveDependencyOrder()` runs topological sort — booted in correct order regardless of DB registration order.

### DB Enablement

The `modules` table controls which modules are enabled. Admin toggles via UI route `dashboard/admin/modules/{module}/toggle`. `Module::enabled()` scope filters `enabled = true`.

---

## Route Registration (`app/Modules/Traits/HasRoutes.php`)

Three methods in the `HasRoutes` trait:

### `loadRoutes()` — Authenticated Dashboard Routes

```php
$this->loadRoutes(
    __DIR__ . '/Routes/web.php',
    '/dashboard/bookings',
    'dashboard.bookings.',
    'bookings.view'
);
```

Middleware: `web`, `auth`, `verified`, `permission:{slug}` (optional).
Route names prefixed with 3rd arg, file defines suffix (e.g., `index`, `create`).

### `loadAdminRoutes()` — Admin Routes

```php
$this->loadAdminRoutes(
    __DIR__ . '/Http/Routes/admin.php',
    '/admin/ecommerce',
    'admin.ecommerce.',
    'ecommerce.view'
);
```

Middleware: `web`, `auth`, `verified`, `admin`, `permission:{slug}` (optional).

### `loadPublicRoutes()` — Public Routes (No Auth)

```php
$this->loadPublicRoutes(__DIR__ . '/Routes/guest.php', '');
```

Middleware: `web` only.
Route name computed from prefix if not provided.

---

## Permissions

Each module declares permissions in `permissions()`:

```php
// BookingModule
public function permissions(): array
{
    return [
        ['slug' => 'bookings.view',   'name' => 'View Bookings',   'description' => '...'],
        ['slug' => 'bookings.create', 'name' => 'Create Bookings', 'description' => '...'],
        ['slug' => 'bookings.edit',   'name' => 'Edit Bookings',   'description' => '...'],
        ['slug' => 'bookings.delete', 'name' => 'Delete Bookings', 'description' => '...'],
    ];
}
```

### Permission Flow

**Backend:** `ModuleServiceProvider::boot()` auto-defines a Gate for each permission slug:

```php
Gate::define($permission['slug'], fn ($user) => $user->hasPermission($permission['slug']));
```

Plus `AppServiceProvider` adds a `Gate::before` fallback:

```php
Gate::before(function (User $user, string $ability) {
    if ($user->hasPermissionTo($ability)) return true;
});
```

This means any `$user->can('properties.view')` check resolves against the custom permission system.

**Route Guard:** `CheckPermission` middleware (`'permission:{slug}'`) calls `$user->hasPermission($slug)`, returns `abort(403)` on failure.

**Frontend:** `HandleInertiaRequests::getUserCapabilities()` builds a flat boolean map shared as `userCapabilities`:

```php
$caps = [
    'isAdmin' => $user->isAdmin(),
    'can' => ['manageUsers' => ..., 'manageSettings' => ...],
    'bookings.view' => $user->hasPermission('bookings.view'),
    'hasModule_bookings' => $this->isModuleEnabled('bookings'),
    // ... all module permissions auto-derived
];
```

---

## Nav Items (`getNavItems()`)

Each module returns a nav tree for the sidebar:

```php
public function getNavItems(): array
{
    return [
        'title' => 'Bookings',
        'url' => route('dashboard.bookings.index', [], false), // RELATIVE URL
        'icon' => 'Calendar',       // string key → resolved via iconMap in sidebar
        'permission' => 'bookings.view',
        'module_slug' => 'bookings',
        'showModeBadge' => true,    // shows "Host" badge on parent item
        'items' => [
            ['title' => 'All Bookings', 'url' => route('dashboard.bookings.index', [], false)],
            ['title' => 'Add Booking',  'url' => route('dashboard.bookings.create', [], false)],
            ['title' => 'Calendar',     'url' => route('dashboard.bookings.calendar', [], false)],
        ],
    ];
}
```

- `url` uses `route(name, [], false)` — relative URL prevents cross-origin Inertia XHR failures when APP_URL differs from frontend URL
- `icon` is a **string key** (not a component) — resolved via `moduleIcons` map in `app-sidebar.tsx`
- `permission` controls visibility; `hasPermission()` on frontend filters the nav item
- `items[]` contains child links rendered in the collapsible dropdown

---

## Module Directory Structure

```
app/Modules/{Name}/
  {Name}Module.php              # Module class
  Controllers/                  # or Http/Controllers/
  Models/
  Requests/                     # Form requests
  Routes/                       # or Http/Routes/
    web.php
    guest.php                   # public routes (optional)
    my-bookings.php             # additional route files (optional)
  Services/
  database/
    migrations/
  Resources/
    js/
      index.ts                  # module JS entry point (import.meta.glob)
      Pages/                    # Inertia page components
      components/               # module-specific React components
      lib/                      # module-specific utilities
```

Frontend pages are lazy-loaded via `import.meta.glob` in `resources/js/app.tsx`, which automatically resolves page files from both `resources/js/Pages/` and `app/Modules/*/Resources/js/Pages/`.

---

## Test Bootstrapping (`tests/BootsModules.php`)

Test cases use the `BootsModules` trait:

```php
use Tests\BootsModules;

class BookingControllerTest extends TestCase
{
    use BootsModules;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootModules();
    }
}
```

`bootModules()`:
1. Loads module classes via `ModuleLoader`
2. Seeds module records (`ModuleSeeder`) and roles/permissions (`RolesAndPermissionsSeeder`)
3. Boots enabled modules and registers routes
4. Calls `RouteCollection::refreshNameLookups()` — required for `route()` helper in tests
5. Creates any module permissions not already in DB
6. Creates `manage_options` permission
7. Syncs all permissions to admin role

---

## Key Conventions

| Aspect | Convention |
|--------|-----------|
| **Route URLs** | Always `route(name, [], false)` for nav items (relative) |
| **Route middleware** | Module routes include `web`, `auth`, `verified`, `permission:{slug}` |
| **Permissions** | Module defines CRUD perms; Gates auto-defined by ModuleServiceProvider |
| **Nav icons** | Pass as **string key** matching iconMap in `app-sidebar.tsx` |
| **Dependencies** | Declared in `dependencies()`, resolved topologically |
| **Test modules** | Call `$this->bootModules()` in `setUp()` after `parent::setUp()` |
| **Settings link** | `getSettingsRoute()` returns relative URL for settings tab |
| **User cleanup** | `onUserDelete()` handles module-specific deletion when user is removed |
