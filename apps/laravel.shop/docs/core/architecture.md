# Core Architecture

## Boot Process

```
1. index.php → Laravel Application
2. AppServiceProvider::boot()
   ├── Gate::before() — permission fallback
   ├── Policy registration (Property, Booking, Post, Product, Order)
   └── Blade component namespace registration

3. ModuleServiceProvider::boot()
   ├── ModuleLoader::load()                    — discovers Module classes
   ├── ModuleRegistry::bootEnabled()           — topological sort, calls boot()
   ├── ModuleRegistry::registerRoutes()        — calls routes() on each module
   ├── Migration discovery from migrationPaths()
   ├── Gate::define() for each permission slug
   └── RouteCollection::refreshNameLookups()

4. HandleInertiaRequests::share()
   ├── auth.user, impersonating
   ├── latestNotifications, notificationUnreadCount
   ├── flash, userMode, canSwitchMode
   ├── moduleNavItems (with groupLabel)
   ├── userCapabilities (flat permission map)
   ├── modules, moduleDebugData
   ├── repeaterConfigs, profileFieldConfigs
   ├── emailSettings, brand
   └── booking_global_settings, ...
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

**Web Group:**
- All global middleware
- `VerifyCsrfToken`

**Module Routes (via `HasRoutes` trait):**
- `web`, `auth`, `verified`, `permission:{slug}` (dashboard routes)
- `web`, `auth`, `verified`, `admin`, `permission:{slug}` (admin routes)
- `web` only (public guest routes)

## Service Providers

| Provider | Registration |
|----------|-------------|
| `AppServiceProvider` | Policies, Blade components, Gate::before |
| `ModuleServiceProvider` | ModuleRegistry, ModuleLoader (singletons), module boot |
| `PermissionGateServiceProvider` | Permission-based Gate definitions |
| `AuthServiceProvider` | Auth guards, Sanctum config |

## Key Singletons

| Class | Registered In | Purpose |
|-------|--------------|---------|
| `ModuleRegistry` | `ModuleServiceProvider` | Central registry of all modules |
| `ModuleLoader` | `ModuleServiceProvider` | File-system scanning for modules |
| `SettingsService` | `AppServiceProvider` | DB-driven configuration |
| `NotificationService` | `AppServiceProvider` | Multi-channel notification dispatch |
| `DashboardService` | `AppServiceProvider` | Cached dashboard stats |
| `FormattingService` | `AppServiceProvider` | Price/surface formatting |

## Configuration

| File | Purpose |
|------|---------|
| `.env` | Environment variables (DB, mail, app URL, deploy token) |
| `config/app.php` | App name, URL, timezone, locale |
| `config/sanctum.php` | SPA auth configuration |
| `config/session.php` | Session driver (file, database) |
| `config/mail.php` | Mail driver (SMTP, log, array) |
| `config/booking-meta.php` | Booking field definitions (65+ fields) |
