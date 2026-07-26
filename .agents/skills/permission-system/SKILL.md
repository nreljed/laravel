---
name: permission-system
description: "Spatie Permission-based role/permission system for Cost Breaker 360. Activate when working with roles, permissions, middleware, guards, authorization; when user mentions access control, RBAC, role assignment, or permission checks. Covers the custom Role/Permission models, middleware, seeder, and authorization patterns."
license: MIT
metadata:
  author: laravel
---

# Permission System

## Architecture

Custom Role/Permission models (not Spatie's default). Uses `slug` as identifier, not `name`.

- `App\Models\Role` — has `slug`, `name`, `description`
- `App\Models\Permission` — has `slug`, `name`, `description`
- Pivot: `role_permission` table
- User relationship: `$user->roles`, `$user->permissions`

## Permission Slugs

| Slug                                                                              | Description                         |
| --------------------------------------------------------------------------------- | ----------------------------------- |
| `manage-users`                                                                    | Create, edit, delete users          |
| `manage-settings`                                                                 | Change application settings         |
| `manage-roles`                                                                    | Assign and manage roles             |
| `view-system-health`                                                              | View system diagnostics             |
| `manage_options`                                                                  | Feature-level settings (admin only) |
| `view-dashboard`                                                                  | Access the main dashboard           |
| `view-profile` / `edit-profile` / `delete-account`                                | Profile management                  |
| `view-notifications` / `manage-notifications`                                     | Notification management             |
| `posts.view` / `posts.create` / `posts.edit` / `posts.delete`                     | Blog CRUD                           |
| `test-posts.view` / `test-posts.create` / `test-posts.edit` / `test-posts.delete` | Test posts                          |

## Default Roles

| Role     | Slug       | Permissions                                                                                          |
| -------- | ---------- | ---------------------------------------------------------------------------------------------------- |
| Admin    | `admin`    | All permissions                                                                                      |
| CRM User | `crm_user` | view-dashboard, view-profile, edit-profile, delete-account, view-notifications, manage-notifications |

## Middleware

```php
// Route-level permission check
Route::middleware(['permission:posts.view'])->group(function () { ... });

// Admin-only route
Route::middleware(['admin'])->group(function () { ... });
```

### CheckPermission Middleware

```php
// app/Http/Middleware/CheckPermission.php
if (! $request->user() || ! $request->user()->hasPermission($slug)) {
    abort(403);
}
```

### AdminMiddleware

```php
// app/Http/Middleware/AdminMiddleware.php
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

## Seeder

```php
// database/seeders/RolesAndPermissionsSeeder.php
$this->seed(RolesAndPermissionsSeeder::class);
```

Uses `firstOrCreate` — safe to run multiple times.

## Adding New Permissions

1. Add to `RolesAndPermissionsSeeder::run()` array
2. Add to feature's `permissions()` method in ServiceProvider
3. Add middleware to routes
4. Run: `php artisan db:seed --class=RolesAndPermissionsSeeder`

## Key Rules

- Always check permission before showing UI elements
- Use `permission:slug` middleware for route protection
- Use `$this->authorize()` for controller-level checks
- Admin role gets ALL permissions automatically
- Feature permissions follow `feature.action` pattern (e.g., `posts.view`)
