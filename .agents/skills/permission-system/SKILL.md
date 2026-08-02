---
name: permission-system
description: "Custom slug-based RBAC for Cost Breaker 360 (package-owned, not Spatie). Activate when working with roles, permissions, middleware, gates, authorization; when user mentions access control, RBAC, role assignment, or permission checks. Covers the Nreljed\\DashboardCore models, PermissionGateServiceProvider, config/roles.php catalog, seeder, and authorization patterns."
license: MIT
metadata:
  author: laravel
---

# Permission System

## Architecture

Custom Role/Permission Eloquent models (not Spatie — `spatie/laravel-permission` is NOT installed). Uses `slug` as the identifier, not `name`. All classes are **package-owned** in `packages/dashboard-core` (vendor mirrors via `composer reinstall nreljed/dashboard-core`).

- `Nreljed\DashboardCore\Models\Role` — fillable `name`, `slug`, `description`, `guard_name`, `sort_order`
- `Nreljed\DashboardCore\Models\Permission` — fillable `name`, `slug`, `description`, `guard_name`
- Pivots: `role_user` (users), `permission_role` (role→permission), `permission_user` (direct user grants)
- User relations: `$user->roles`, `$user->permissions` (direct grants), plus `hasRole()`, `hasPermission()`, `isAdmin()`, `assignRole()`
- Cache: `Nreljed\DashboardCore\Services\PermissionCacheService` (cached user/role permission slugs, 1h TTL, `flushAll()` after role changes)

## Gate Registration

`Nreljed\DashboardCore\Providers\PermissionGateServiceProvider` auto-registers one `Gate::define($slug, ...)` per permission slug by merging:

1. `FeatureRegistry::permissions()` — every feature provider's `permissions()` array
2. `Permission::pluck('slug')` — every slug already present in the `permissions` table

`Gate::before` (in `DashboardCoreServiceProvider`) lets the `admin` role pass every ability.

## Permission Slugs

Convention: **kebab-case for global/admin permissions** (`manage-*`, `view-*`, `edit-*`, `delete-*`), **dot-namespaced for feature permissions** (`{feature}.{action}`, e.g. `posts.view`). No snake_case slugs (legacy `manage_options` was renamed to `manage-options`).

| Slug                                                                              | Description                         |
| --------------------------------------------------------------------------------- | ----------------------------------- |
| `manage-users` / `manage-settings` / `manage-roles` / `manage-audit-logs`         | Admin panel operations              |
| `manage-options`                                                                  | Feature-level settings (admin only) |
| `view-system-health`                                                              | View system diagnostics             |
| `view-dashboard`                                                                  | Access the main dashboard           |
| `view-profile` / `edit-profile` / `delete-account`                                | Profile management                  |
| `view-notifications` / `manage-notifications`                                     | Notification management             |
| `manage-categories` / `manage-tags` / `manage-email-settings`                     | Content/admin settings              |
| `posts.view` / `posts.create` / `posts.edit` / `posts.delete`                     | Blog CRUD (feature)                 |
| `test-posts.view` / `test-posts.create` / `test-posts.edit` / `test-posts.delete` | Test posts (app feature)            |

## Default Roles (Config Catalog)

Roles are a **config catalog** in `packages/dashboard-core/config/roles.php` (per-app overridable via `php artisan vendor:publish --tag=core-config`, then edit `config/roles.php`). The catalog is authoritative — the seeder syncs roles to it and roles are never deleted.

| Role   | Slug     | Permissions                                                                                          |
| ------ | -------- | ---------------------------------------------------------------------------------------------------- |
| Admin  | `admin`  | `'*'` — every registered permission (auto-grants newly added permissions)                            |
| Editor | `editor` | user + posts CRUD, manage-categories, manage-tags, manage-email-settings, test-posts CRUD            |
| Author | `author` | user + posts.view, posts.create, posts.edit                                                          |
| User   | `user`   | view-dashboard, view-profile, edit-profile, delete-account, view-notifications, manage-notifications |

- `roles.default_role` config key is the fallback for the `default_role` setting (DB `settings` table wins when present).
- `roles.permissions` config key holds the **core** permission catalog; feature permissions come from each feature provider's `permissions()`.

## Seeder

`Nreljed\DashboardCore\Database\Seeders\RolesAndPermissionsSeeder` (package-owned) — syncs permissions (`firstOrCreate`) then roles (`updateOrCreate` + `sync`), and flushes the permission cache. Idempotent, safe to run repeatedly.

```php
// The APP wrapper keeps backward compatibility; both resolve the same logic:
$this->call(CoreRolesAndPermissionsSeeder::class); // via Database\Seeders\RolesAndPermissionsSeeder
```

Run: `php artisan db:seed --class=RolesAndPermissionsSeeder`

## Middleware

All middleware live in `Nreljed\DashboardCore\Http\Middleware\`, aliased in `bootstrap/app.php`:

```php
// Route-level permission check
Route::middleware(['permission:posts.view'])->group(function () { ... });

// Admin-only route
Route::middleware(['admin'])->group(function () { ... });

// Role check
Route::middleware(['role:editor'])->group(function () { ... });
```

### CheckPermission Middleware

```php
// packages/dashboard-core/src/Http/Middleware/CheckPermission.php
if (! $request->user() || ! $request->user()->hasPermission($slug)) {
    abort(403);
}
```

### AdminMiddleware

```php
// packages/dashboard-core/src/Http/Middleware/AdminMiddleware.php
if (! $request->user() || ! $request->user()->hasRole('admin')) {
    abort(403);
}
```

## Authorization Patterns

### In Controllers

```php
$this->authorize('posts.view'); // Via permission gate
```

### In FormRequests

```php
public function authorize(): bool
{
    return $this->user()->hasPermission('posts.create');
}
```

### In Blade/React

```php
@can('posts.view')
    <a href="{{ route('dashboard.posts.index') }}">Posts</a>
@endcan
```

```tsx
{
  userCapabilities.includes("posts.view") && (
    <Link href={route("dashboard.posts.index")}>Posts</Link>
  );
}
```

## Adding New Permissions

1. **Feature permission** → add to the feature's `permissions()` method in its `{Feature}ServiceProvider` (e.g. `['slug' => 'posts.view', 'name' => ..., 'description' => ...]`). No seeder edit needed.
2. **Global permission** → add to `roles.permissions` in `config/roles.php`.
3. **Grant to roles** → add the slug to the target role's `permissions` list in `config/roles.php` (`'*'` for admin grants it automatically).
4. Run: `php artisan db:seed --class=RolesAndPermissionsSeeder`
5. `permissions:list --group=posts` verifies feature groups; `--role=admin` verifies role grants.

## Key Rules

- Always check permission before showing UI elements
- Use `permission:slug` middleware for route protection
- Use `$this->authorize()` for controller-level checks
- Admin role gets ALL permissions automatically (`'*'`)
- Feature permissions follow `{feature}.action` (e.g., `posts.view`); global permissions are kebab-case (`manage-users`)
- Never mix snake_case slugs
- After changing `config/roles.php` or a feature's `permissions()`, re-run the seeder and flush the permission cache (the seeder does this)
