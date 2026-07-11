# Core Architecture

## Boot Process

```
1. index.php → Laravel Application
2. AppServiceProvider::boot()
   ├── Gate::before() — permission fallback
   ├── Policy registration
   ├── FeatureRegistry singleton binding
   └── Blade component namespace registration

3. HandleInertiaRequests::share()
   ├── auth.user, impersonating
   ├── latestNotifications, notificationUnreadCount
   ├── flash, userMode, canSwitchMode
   ├── featureNavItems (with groupLabel)
   ├── userCapabilities (flat permission map)
   ├── emailSettings, brand
   └── formattingSettings, ...
```

## Middleware Stack

Applied in `kernel.php`:

**Global:**

- `CheckForMaintenanceMode`
- `EncryptCookies`
- `AddQueuedCookiesToResponse`
- `StartSession`
- `ShareErrorsFromSession`
- `SubstituteBindings`
- `ContentSecurityPolicy`
- `ForceInertiaCacheHeaders`

**Web Group:**

- All global middleware
- `VerifyCsrfToken`

## Service Providers

| Provider                        | Registration                                                        |
| ------------------------------- | ------------------------------------------------------------------- |
| `AppServiceProvider`            | Policies, FeatureRegistry singleton, Blade components, Gate::before |
| `PermissionGateServiceProvider` | Permission-based Gate definitions                                   |
| `BlogServiceProvider`           | Blog feature routes, permissions, nav items                         |
| `AuthServiceProvider`           | Auth guards, Sanctum config                                         |

## Key Singletons

| Class                 | Registered In        | Purpose                                                   |
| --------------------- | -------------------- | --------------------------------------------------------- |
| `FeatureRegistry`     | `AppServiceProvider` | Central registry — delegates to feature service providers |
| `SettingsService`     | `AppServiceProvider` | DB-driven configuration                                   |
| `NotificationService` | `AppServiceProvider` | Multi-channel notification dispatch                       |
| `DashboardService`    | `AppServiceProvider` | Cached dashboard stats                                    |
| `FormattingService`   | `AppServiceProvider` | Price/surface formatting                                  |

## Configuration

| File                 | Purpose                                                 |
| -------------------- | ------------------------------------------------------- |
| `.env`               | Environment variables (DB, mail, app URL, deploy token) |
| `config/app.php`     | App name, URL, timezone, locale                         |
| `config/sanctum.php` | SPA auth configuration                                  |
| `config/session.php` | Session driver (file, database)                         |
| `config/mail.php`    | Mail driver (SMTP, log, array)                          |
