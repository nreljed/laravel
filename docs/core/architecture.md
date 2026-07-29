# Architecture

## Boot Process

```
1. index.php → Laravel Application
2. AppServiceProvider::boot()
   ├── configureRateLimiting() — named rate limiters (login, register, auth)
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

## Rate Limiting

Named rate limiters defined in `AppServiceProvider::configureRateLimiting()`:

| Limiter    | Limit  | Key | Purpose                                                   |
| ---------- | ------ | --- | --------------------------------------------------------- |
| `login`    | 30/min | IP  | Safety net — `LoginRequest` handles strict per-user limit |
| `register` | 10/min | IP  | Registration abuse prevention                             |
| `auth`     | 30/min | IP  | General auth routes (password reset, logout, etc.)        |

**Login two-tier system:**

1. Route-level `throttle:login` — IP-based, 30/min (generous safety net)
2. `LoginRequest::ensureIsNotRateLimited()` — username+IP based, configurable via `max_login_attempts` setting (default 5/min), returns `ValidationException` with friendly "Please wait X seconds" message

The route-level limiter fires first. If exceeded, `Error.tsx` renders with a live countdown from the `Retry-After` header. If not exceeded, `LoginRequest` handles the stricter per-user throttle with a form validation error.

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
