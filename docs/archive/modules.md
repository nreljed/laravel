# Feature Architecture

This dashboard uses a **feature-based architecture** where each domain (Blog, etc.) is self-contained with routes, controllers, models, migrations, frontend pages, permissions, and nav items.

## How It Works

1. **`FeatureRegistry`** (`app/Services/FeatureRegistry.php`) — singleton, delegates to individual feature service providers.
2. **Feature Service Providers** (e.g., `BlogServiceProvider`) — register routes, permissions, and nav items.
3. **`HandleInertiaRequests`** calls `FeatureRegistry::getAllNavItems()` and `getAllSharedData()` to share props with the frontend.

## Adding a New Feature

1. Create `app/Features/{Name}/` with Controllers/, Models/, Routes/, Resources/js/Pages/
2. Create `{Name}ServiceProvider.php` — implement `boot()` to register routes, permissions, and nav items
3. Register in `bootstrap/providers.php`
4. Add permission slugs to `database/seeders/RolesAndPermissionsSeeder.php`
5. Register the service provider in `FeatureRegistry`

## Current Features

| Feature | Slug    | Permissions                                                | Frontend Pages                           |
| ------- | ------- | ---------------------------------------------------------- | ---------------------------------------- |
| Blog    | `posts` | `posts.view`, `posts.create`, `posts.edit`, `posts.delete` | Index, Form, Dashboard, Categories, Tags |
