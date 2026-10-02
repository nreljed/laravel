# Administration: Users, Roles & Permissions

## Architecture Overview

The administration system uses a custom RBAC (Role-Based Access Control) built directly on Laravel's Gate/Policy authorization layer — no third-party package required.

```
User ──belongsToMany──> Role ──belongsToMany──> Permission
  │                        │
  └──belongsToMany──> Permission (direct overrides)
```

Every permission slug is registered as a Laravel Gate, so all standard Laravel authorization methods work: `@can()`, `$user->can()`, `Gate::authorize()`, `middleware('can:...')`.

---

## Key Files

| File | Purpose |
|------|---------|
| `app/Models/User.php` | `hasRole()`, `hasPermission()`, `canAny()`, `hasAllPermissions()`, `isAdmin()`, `assignRole()` |
| `app/Models/Role.php` | Belongs-to-many users + permissions, `hasPermission()` |
| `app/Models/Permission.php` | Belongs-to-many roles + users |
| `app/Services/PermissionCacheService.php` | Caches permission→slug mappings in config cache |
| `app/Providers/PermissionGateServiceProvider.php` | Registers all permission slugs as Gates |
| `app/Http/Middleware/AdminMiddleware.php` | Route guard: `hasRole('admin')` |
| `app/Http/Middleware/CheckPermission.php` | Route guard: `hasPermission(slug)` |
| `app/Http/Middleware/CheckRole.php` | Route guard: `hasRole(slug)` |

---

## Database Schema

### `roles`
| Column | Type | Notes |
|--------|------|-------|
| id | bigint | PK |
| name | string | Display name (e.g. "Admin") |
| slug | string | Unique identifier (e.g. "admin") |
| description | text | Optional |
| guard_name | string | Always "web" |
| timestamps | | |

### `permissions`
| Column | Type | Notes |
|--------|------|-------|
| id | bigint | PK |
| name | string | Display name (e.g. "Manage Users") |
| slug | string | Unique identifier (e.g. "manage-users") |
| description | text | Optional |
| guard_name | string | Always "web" |
| timestamps | | |

### `role_user` (pivot)
`role_id` FK → roles, `user_id` FK → users, composite PK

### `permission_role` (pivot)
`permission_id` FK → permissions, `role_id` FK → roles, composite PK

### `permission_user` (pivot — direct user permissions)
`permission_id` FK → permissions, `user_id` FK → users, composite PK

---

## Built-in Roles

| Role | Slug | Description |
|------|------|-------------|
| Admin | `admin` | Full access — bypasses all Gate checks via `Gate::before` |
| Shop Owner | `shop_owner` | Full shop control: products, orders, categories, reviews, coupons, shipping, taxes, webhooks, posts |
| Shop Manager | `shop_manager` | Manage existing content: edit products, view/update orders, moderate reviews |
| Customer | `customer` | Account management: profile, notifications, account settings. Default registration role |

---

## Core Permissions

| Slug | Description |
|------|-------------|
| `manage-users` | Create, edit, delete users |
| `manage-settings` | Change application settings |
| `manage-roles` | Assign and manage roles |
| `view-system-health` | View system diagnostics |
| `manage_options` | Module-level system operations (admin only) |
| `view-dashboard` | Access the main dashboard |
| `view-profile` | View own profile |
| `edit-profile` | Edit own name, email, password |
| `delete-account` | Delete own account |
| `view-notifications` | View notifications |
| `manage-notifications` | Mark read, delete notifications |

Module permissions follow the pattern `{module}.{action}` (e.g. `properties.view`, `bookings.create`, `shop.products.edit`).

---

## Admin Panel

All admin routes are under `/dashboard/admin/*` and require:
- `auth` — authenticated
- `verified` — email verified (if required by settings)
- `admin` — has `admin` role
- `throttle:60,1` — rate limited

### Pages

| Route | Page | Features |
|-------|------|----------|
| `/dashboard/admin/users` | Users | Search, paginate, impersonate, verify email, send reset link, bulk delete, CSV export |
| `/dashboard/admin/users/create` | Create User | Username, name, email, password, role assignment |
| `/dashboard/admin/users/{id}/edit` | Edit User | Update fields, change role, view sessions |
| `/dashboard/admin/users/{id}/sessions` | Sessions | View active sessions, revoke remote sessions |
| `/dashboard/admin/roles` | Roles | List with user/permission counts, create, edit, delete |
| `/dashboard/admin/roles/create` | Create Role | Name (auto-slug), description, permission checkboxes |
| `/dashboard/admin/roles/{id}/edit` | Edit Role | Name, description, grouped permission checkboxes |
| `/dashboard/admin/permissions` | Permissions | Search, list, create, edit, delete |
| `/dashboard/admin/permissions/create` | Create Permission | Name (auto-slug), description |
| `/dashboard/admin/permissions/{id}/edit` | Edit Permission | Name, slug, description |
| `/dashboard/admin/audit-logs` | Audit Logs | Filter by action/user/date range, paginated |
| `/dashboard/admin/settings` | Settings | Tabbed app configuration |
| `/dashboard/admin/system-health` | System Health | PHP, Laravel, DB diagnostics + module debug |

### Impersonation

Admins can "log in as" any user for troubleshooting:

1. **Start**: Click "Login as User" in user dropdown → stored `impersonator_id` in session
2. **Indicators**: Yellow banner across all pages: _"You are acting as {name}"_
3. **Stop**: Click "Stop Impersonating" → returns to admin account
4. **Audit**: Both start and stop are logged in `audit_logs`

---

## Authorization Flow

```
Request → middleware('auth') → middleware('admin') → Controller
                                                      │
                                              Gate::authorize()  ←── Checks User → Role → Permission
                                                      │
                                              Policy::method()   ←── Ownership check
```

1. **Route middleware**: `admin` / `permission:slug` / `role:slug` — first line of defense
2. **Gates**: All permission slugs registered in `PermissionGateServiceProvider` — enables `@can()`, `$user->can()`, `middleware('can:...')`
3. **Policies**: `PropertyPolicy`, `BookingPolicy` — ownership + permission checks on specific models
4. **`Gate::before`**: Admins automatically pass all authorization checks

---

## Caching

`PermissionCacheService` caches:
- All permission slugs in a single key
- Per-role permission slug lists
- Per-user permission slug lists (merged from roles + direct permissions)

Cache is flushed automatically when:
- A role is created, updated, or deleted
- A permission is created, updated, or deleted
- A user's role assignment changes

---

## Console Commands

```bash
# Sync all module permissions to the database
php artisan modules:sync-permissions

# Create a new role
php artisan roles:create "Editor" --permissions="view-dashboard,edit-profile" --description="Can edit content"

# List permissions (with optional filters)
php artisan permissions:list
php artisan permissions:list --role=admin
php artisan permissions:list --group=properties
php artisan permissions:list --format=json
```

---

## Audit Logging

The following actions are automatically logged to `audit_logs`:

| Action | Trigger |
|--------|---------|
| `login` | User signs in |
| `logout` | User signs out |
| `password_change` | User changes own password |
| `password_reset` | Password reset via email |
| `email_change` | User changes email |
| `account_delete` | User deletes own account |
| `profile_update` | User updates profile |
| `impersonation_start` | Admin starts impersonating a user |
| `impersonation_stop` | Admin stops impersonating |
| `role_assigned` | Admin assigns a role to a user |
| `role_revoked` | Admin removes a role from a user |
| `permission_assigned` | Role permissions are updated |
| `permission_revoked` | Permissions are removed from a role |
| `booking_*` | Booking CRUD operations |

---

## Security

- **Rate limiting**: Admin routes are throttled to 60 requests per minute
- **Email verification**: Configurable via Settings — can require verified email for access
- **Session management**: Admins can view and revoke user sessions
- **CSRF**: All state-changing requests require CSRF token (except deploy endpoints)
- **Admin role protection**: The `admin` role cannot be deleted via the UI
- **Self-deletion prevention**: Users cannot delete themselves via admin panel

---

## Test Coverage

| Test Suite | Tests | What it covers |
|------------|-------|----------------|
| `AuthorizationTest` | 8 | Route access for admin/customer/guest |
| `AdminUserManagementTest` | 6 | CRUD user operations |
| `RoleAssignmentTest` | 3 | Registration role, permission checks |
| `PermissionSyncTest` | 4 | Permission sync consistency |
| `PolicyEnforcementTest` | 7 | Model ownership + permission checks |
| `PermissionManagementTest` | 7 | Permission CRUD + authorization |
| `RoleCreationTest` | 6 | Role create/delete/command + protection |
| `ImpersonationTest` | 4 | Start/stop/audit/auth |
| `BulkOperationsTest` | 3 | Bulk delete/self-protection/export |
| `PolicyEnforcementTest` | 7 | Model ownership + permission checks |

---

## Adding a New Permission

### 1. In a module (auto-synced)
```php
// app/Modules/YourModule/YourModule.php
public function permissions(): array
{
    return [
        [
            'name' => 'View Reports',
            'slug' => 'reports.view',
            'description' => 'View analytics reports',
        ],
    ];
}
```
Then run `php artisan modules:sync-permissions`.

### 2. As a core permission (manually)
Create via the admin panel at `/dashboard/admin/permissions/create`, or run:
```bash
php artisan tinker
Permission::create(['name' => 'Export Data', 'slug' => 'export-data', 'description' => 'Export data to CSV/Excel']);
```
Then assign it to the desired role via the role edit page.

---

## Extending

### Add a new Gate ability
If you need an authorization check that isn't a simple permission slug, add it to `AppServiceProvider`:
```php
Gate::define('publish-content', function (User $user, $content) {
    return $user->hasPermission('content.publish') && $user->id === $content->author_id;
});
```

### Create a new Policy
```bash
php artisan make:policy ReportPolicy --model=Report
```
Register in `AppServiceProvider` and use `Gate::policy()`.

### Custom middleware for complex checks
Create a middleware that uses Gate/Permission checks:
```php
public function handle(Request $request, Closure $next): Response
{
    if (! $request->user()?->hasAllPermissions(['reports.view', 'reports.export'])) {
        abort(403);
    }
    return $next($request);
}
```
