# Architecture

```
packages/dashboard-core/
├── config/                 # core, roles, admin, deploy, database-maintenance
├── database/migrations/    # users, roles, permissions, settings, notifications, audit logs, ...
├── resources/
│   ├── css/core.css        # shared stylesheet
│   ├── js/                 # React 19 + Inertia 3 + shadcn frontend kit
│   ├── views/              # Blade: app shell, emails, errors, vendor overrides
├── routes/
│   ├── web.php             # dashboard, admin, deploy, maintenance, sitemap (requires auth.php)
│   └── auth.php            # Breeze-style auth + account routes
├── src/
│   ├── DashboardCoreServiceProvider.php  # boot: routes, schedules, middleware, rate limits, policies
│   ├── Ai/                 # AI agents (BYOK) + provider models
│   ├── Blog/               # Blog feature (self-contained feature provider)
│   ├── Concerns/           # InteractsWithFeatures, UserDataTrait
│   ├── Console/Commands/   # artisan commands
│   ├── Contracts/FeatureProvider.php
│   ├── Database/           # extra migrations/seeds helpers
│   ├── Deploy/             # DeployController + DeployService (server-side pipeline)
│   ├── Enums/              # AuditAction
│   ├── Http/               # Controllers, Middleware, Requests
│   ├── Jobs/               # queued jobs (media, notifications)
│   ├── Mail/               # mailables (verification, welcome, ...)
│   ├── Models/             # User, Role, Permission, Setting, Notification, ...
│   ├── Notifications/      # AppNotification + auth notifications
│   ├── Observers/          # UserObserver (avatar, audit)
│   ├── Policies/           # Post, Role, AiProvider policies
│   ├── Providers/PermissionGateServiceProvider.php
│   ├── PublicSite/         # PublicController (about/services/contact)
│   ├── Services/           # 24 services (settings, mail, notifications, feature registry, ...)
│   ├── Support/            # helpers (html sanitizer, version, ...)
│   └── Traits/             # RelativeTime
└── tests/                  # FeatureProviderContractTest, NamespaceIntegrityTest
```

## Boot Sequence (`DashboardCoreServiceProvider`)

`register()`: merges the 5 config files, registers singletons (`FormattingService`,
`FeatureRegistry`).

`boot()`:

1. Load views (`core::` namespace).
2. Load migrations unless `core.load_migrations` is `false`.
3. `loadRoutes()` — `routes/web.php` (web middleware), `loadConsoleRoutes()` — the 10
   artisan commands, `configureSchedules()` — registers `deploy:auto` every 5 minutes when
   `deploy.auto_enabled` is `true` (the unified, host-independent self-deploy schedule).
4. `configureMiddleware()` — aliases (`admin`, `role`, `permission`, `maintenance`,
   `session-lifetime`, `ip-access`), appends core middleware to the `web` group
   (`ConfigureSessionLifetime`, `CheckIpAccess`, `ContentSecurityPolicy`,
   `HandleInertiaRequests`, `AddLinkHeadersForPreloadedAssets`), and CSRF-exempts
   `deploy/*`, avatar upload, notification-poll, and `csp-report`.
5. `configureRateLimiting()` — `login` (30/min/IP), `register` (10/min/IP), `auth` (30/min/IP).
6. Policies + `Gate::before` (admins bypass all abilities).
7. `User::observe(UserObserver::class)`.
8. `configureMailer()` — DB-driven mail config when the `settings` table exists.
9. `configureNotifications()` — swaps Laravel's `VerifyEmail`/`ResetPassword` mailables for
   the core templates.
10. `Notification::creating` hook — fills `title`/`message`/`link`/`severity`/`module` from
    `data` and invalidates the unread cache.

## Route Structure

| Group   | Middleware                                                | Prefix               | Names                                                                 | Pages                                                                                                          |
| ------- | --------------------------------------------------------- | -------------------- | --------------------------------------------------------------------- | -------------------------------------------------------------------------------------------------------------- |
| Public  | `web`                                                     | `/`                  | `sitemap`, `robots.txt`, `/deploy/*`, `/maintenance/*`, `/csp-report` | —                                                                                                              |
| Auth    | `guest`/`auth` + `maintenance`                            | —                    | `login`, `register`, `password.*`, `verification.*`                   | Auth/*                                                                                                         |
| Account | `auth` + `maintenance`                                    | `/dashboard/account` | `dashboard.account.*`                                                 | Account pages                                                                                                  |
| Admin   | `auth`, `maintenance`, `throttle:60,1`                    | `/dashboard/admin`   | `dashboard.admin.*`                                                   | Users, roles, permissions, settings, audit logs, system health, database maintenance, notifications, developer |
| Blog    | `auth`, `verified`, `throttle:60,1`, `permission:posts.*` | `/dashboard/posts`   | `dashboard.posts.*`                                                   | Blog feature (see [Blog](blog.md))                                                                             |

Deploy routes live outside auth so CI can trigger them with a token:
`POST /deploy/upload`, `GET|POST /deploy/check`, `POST /deploy/migrate`,
`POST /deploy/create-admin`, `POST /deploy/env`, `POST /deploy/reset`,
`GET|POST /deploy/cache-clear` — all `throttle:5,1`.

## Services

24 services, all constructor-injected (Laravel container), business logic in services not
controllers. Highlights: `SettingsService` (DB-backed config), `MailService` +
`MailerConfigurator` (transport), `NotificationService` (channel dispatch + per-user prefs),
`FeatureRegistry` (feature aggregation), `DashboardService` (stats/analytics),
`SystemHealthService`, `AuditService`, `MediaService`/`GalleryService`, `EmailTemplateService`,
`RepeaterService`, `FormattingService`, `PermissionCacheService`, `PermissionGroups`,
`CacheService`, `DatabaseMaintenanceService`, `PushNotificationService`, `CoreVersionService`,
`AccountService`, `EmailSettingsService`, `SettingsSanitizer`, `HtmlSanitizer`.

## Cross-App Concerns

- **Shared User model** — `Nreljed\DashboardCore\Models\User` is the single user model across
  apps. Features query it directly (e.g. Shop's `Order::distinct('user_id')`); a host that
  needs custom user logic subclasses or extends via the model's interfaces.
- **Policies registered in core** — `PostPolicy`, `RolePolicy`, `AiProviderPolicy` +
  `Gate::before` admin bypass. Feature policies (Shop, etc.) are registered by their own
  providers.
- **Inertia shared props** — `HandleInertiaRequests` shares auth, notifications, flash,
  `userMode`, `sharedData` (aggregated from `FeatureRegistry::getAllSharedData()`), nav items,
  and capabilities. Hosts subclass it (via `core.inertia_middleware`) to add project props.

## Related

- [Feature Provider](feature-provider.md) — the extension layer.
- [Deploy](deploy.md) — how the server-side pipeline is wired from core.
- [Settings](settings.md), [Notifications](notifications.md).
