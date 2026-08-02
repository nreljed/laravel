---
name: feature-architecture
description: "Feature-based architecture pattern for Cost Breaker 360. Activate when adding new features, creating FeatureServiceProviders, registering features in FeatureRegistry, or when user mentions feature architecture, feature registration, or self-contained features. Covers the complete pattern from directory structure to registration."
license: MIT
metadata:
  author: laravel
---

# Feature Architecture

## Concept

Each domain (Blog, future features) is self-contained with its own routes, controllers, models, migrations, and frontend pages. Features register themselves via `FeatureRegistry`.

## Directory Structure

```
app/{Feature}/
  {Feature}ServiceProvider.php    # Routes, permissions, nav items
  Controllers/                    # Feature controllers
  Models/                         # Feature models
  Routes/
    web.php                       # Admin routes (auth+verified)
    public.php                    # Public routes (optional)
  Resources/js/Pages/             # React pages (optional)
  database/migrations/            # Feature migrations
```

## ServiceProvider Pattern

```php
<?php

namespace App\Blog;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class BlogServiceProvider extends ServiceProvider
{
    public function name(): string { return 'Blog'; }
    public function slug(): string { return 'posts'; }

    public function boot(): void
    {
        $this->loadRoutes();
        $this->loadPublicRoutes();
        $this->loadMigrations();
    }

    protected function loadRoutes(): void
    {
        Route::middleware(['web', 'auth', 'verified', 'throttle:60,1'])
            ->prefix('dashboard/posts')
            ->name('dashboard.posts.')
            ->group(__DIR__.'/Routes/web.php');
    }

    protected function loadPublicRoutes(): void
    {
        Route::middleware(['web'])
            ->prefix('blog')
            ->group(__DIR__.'/Routes/public.php');
    }

    protected function loadMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
    }

    public function permissions(): array { return [...]; }
    public function getNavItems(): array { return [...]; }
    public function getDashboardData(): array { return [...]; }
    public function getSharedData(): array { return []; }
    public function getNotificationTypes(): array { return [...]; }
    public function getEmailTemplates(): array { return []; }
    public function getGalleryConfigs(): array { return [...]; }
    public function onUserDelete(int $userId): void { ... }
}
```

## Registration

### 1. bootstrap/providers.php

```php
return [
    // ...
    App\Blog\BlogServiceProvider::class,
];
```

### 2. FeatureRegistry

```php
// app/Services/FeatureRegistry.php
public function __construct()
{
    $this->blog = app()->getProvider(BlogServiceProvider::class);
}

public function getAllNavItems(): array
{
    return array_merge(
        $this->blog->getNavItems(),
    );
}
```

### 3. Permissions Seeder

Feature permissions are declared in `{Name}ServiceProvider::permissions()` and are picked up automatically by `FeatureRegistry::permissions()`, the `PermissionGateServiceProvider`, and the shared seeder — **no seeder edits needed**.

```php
// packages/dashboard-core/src/Database/Seeders/RolesAndPermissionsSeeder.php
// Syncs permissions from config('roles.permissions') + FeatureRegistry::permissions(),
// then syncs roles to config('roles.roles'). Idempotent.
```

## Required Methods

| Method                      | Purpose                         |
| --------------------------- | ------------------------------- |
| `name()`                    | Human-readable feature name     |
| `slug()`                    | URL-safe identifier             |
| `permissions()`             | Array of permission definitions |
| `getNavItems()`             | Sidebar navigation items        |
| `getDashboardData()`        | Dashboard stats/cards           |
| `getSharedData()`           | Shared Inertia props            |
| `getNotificationTypes()`    | Notification type definitions   |
| `getEmailTemplates()`       | Email template configs          |
| `getGalleryConfigs()`       | Gallery/image upload configs    |
| `onUserDelete(int $userId)` | Cleanup when user is deleted    |

## Adding a New Feature

1. Create `packages/dashboard-core/src/{Name}/` directory structure
2. Create `{Name}ServiceProvider.php` with all required methods
3. Register the provider in `config/core.php` (`features`)
4. Declare feature permissions in `permissions()`; grant them to roles via `config/roles.php`
5. Register in `FeatureRegistry` (auto-discovered from `config('core.features')`)
6. Run: `php artisan db:seed --class=RolesAndPermissionsSeeder`

## Key Rules

- Feature must be self-contained — no cross-feature dependencies
- All routes use `dashboard.{feature}.` prefix and name
- Permissions follow `{feature}.action` pattern
- `onUserDelete()` must clean up all feature data
- Migrations must be reversible
