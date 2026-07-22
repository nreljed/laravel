# Architecture

## Boot Process

```
1. index.php → Laravel Application
2. AppServiceProvider::boot()
   ├── Gate::before() — admin bypasses all permissions
   ├── Policy registration (PostPolicy)
   ├── FeatureRegistry singleton binding
   ├── Notification creating hook (populates title/message/link/severity)
   └── Vite::prefetch(concurrency: 3)

3. HandleInertiaRequests::share()
   ├── auth.user (full profile with roles, display options)
   ├── latestNotifications, notificationUnreadCount
   ├── flash (status, error, mail_debug)
   ├── featureNavItems (dynamic sidebar from FeatureRegistry)
   ├── userCapabilities (admin flag + granular permissions)
   ├── formattingSettings, emailSettings, brand, seo
   └── impersonating, impersonator
```

## Middleware Stack

Configured in `bootstrap/app.php`:

**Web group:**

1. `ContentSecurityPolicy` — CSP headers (production only)
2. `HandleInertiaRequests` — Inertia shared props
3. `AddLinkHeadersForPreloadedAssets`

**Route aliases:**

- `admin` → `AdminMiddleware` (403 if not admin)
- `role` → `CheckRole` (accepts variadic role slugs)
- `permission` → `CheckPermission` (checks `$user->hasPermission($slug)`)

## Service Providers

| Provider                        | Purpose                                     |
| ------------------------------- | ------------------------------------------- |
| `AppServiceProvider`            | Singletons, policies, gates, mail config    |
| `PermissionGateServiceProvider` | Gate::define() for every permission slug    |
| `BlogServiceProvider`           | Blog feature routes, permissions, nav items |
| `TelescopeServiceProvider`      | Debug toolbar (conditional)                 |

## Directory Structure

```
app/
  Blog/                    # Self-contained feature
  Console/Commands/        # Artisan commands
  Enums/                   # AuditAction enum
  Http/
    Controllers/           # By domain (Admin/, Api/, Auth/)
    Middleware/             # Inertia, Admin, Permission, Role, CSP
    Requests/              # FormRequest classes by domain
  Jobs/                    # SendEmail
  Mail/                    # DynamicMailable
  Models/                  # User, Notification, etc.
  Notifications/           # Database notification classes
  Policies/                # PostPolicy
  Providers/               # Service providers
  Services/                # Business logic
  Traits/                  # Shared traits
```
