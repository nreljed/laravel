# platform

Shared dashboard platform for Laravel applications (Cost Breaker, Shop, Booking). Owns the
dashboard, admin panel, auth, account, notifications, settings, email, blog, AI (BYOK), and
the unified deploy pipeline. Consuming apps keep only domain code on top (public pages, domain
features, custom dashboard pages).

- **Namespace:** `Nreljed\Platform`
- **Package:** `nreljed/platform`
- **Source:** [`nreljed/platform`](https://github.com/nreljed/platform)
- **Features:** Blog (`Nreljed\Platform\Blog`) ships in-core; the Shop feature is a
  separate package (`nreljed/shop-module`, see its own `docs/`)

## Modules

| Area                                | Where                                                           | Docs                                    |
| ----------------------------------- | --------------------------------------------------------------- | --------------------------------------- |
| Dashboard + account + admin         | `routes/web.php`, `Http/Controllers`, `Models/User`             | [Architecture](architecture.md)         |
| Auth (Breeze-style)                 | `routes/auth.php`, `Http/Controllers/Auth`                      | [Architecture](architecture.md)         |
| Settings (DB-backed)                | `Services/SettingsService.php`                                  | [Settings](settings.md)                 |
| Notifications (in-app, push, email) | `Services/NotificationService.php`, `Notifications/`            | [Notifications](notifications.md)       |
| Email templates                     | `Mail/`, `resources/views/email/`                               | [Notifications](notifications.md)       |
| Blog feature                        | ships as `nreljed/blog` (routes, models, editor, pages)       | [Blog](https://github.com/nreljed/blog) |
| AI agents (BYOK)                    | `src/Ai/`                                                       | [AI](ai.md)                             |
| Deploy pipeline (server-side)       | `src/Deploy/`, `Console/Commands/DeployAuto.php`                | [Deploy](deploy.md)                     |
| Artisan commands                    | `Console/Commands/`                                             | [Commands](commands.md)                 |
| Feature extension layer             | `Contracts/FeatureProvider.php`, `Services/FeatureRegistry.php` | [Feature Provider](feature-provider.md) |
| Frontend kit                        | `resources/js/` (React 19 + Inertia 3 + shadcn)                 | [Frontend](frontend.md)                 |

## Quick Install (host app)

1. `composer require nreljed/platform` (path repo in development; see the
   [README](https://github.com/nreljed/platform#readme) for both setups).
2. Provider is auto-discovered via `extra.laravel.providers`.
3. `php artisan vendor:publish --tag=core-config` to publish config.
4. Wire the frontend: `@dashboard` alias in `vite.config.js` + `tsconfig.json`, Tailwind
   `@source`, and `registerCoreTemplates()` in the app's `app.tsx` (see [Frontend](frontend.md)).
5. Register feature providers in `config/features.php` `providers`, then
   `php artisan permissions:sync`.

## Config

The package ships three publishable config files (`php artisan vendor:publish --tag=core-config`),
split by concern so consuming apps only override what they need:

**`config/core.php` — engine gates:**

| Key                       | Default                                | Purpose                                                     |
| ------------------------- | -------------------------------------- | ----------------------------------------------------------- |
| `core.load_migrations`    | `true`                                 | `false` when the app already ships equivalent migrations    |
| `core.github_repo`        | `nreljed/platform`                     | Repo used by system health + core version checks            |
| `core.inertia_middleware` | `HandleInertiaRequests::class`         | Override to add project-specific shared props               |
| `core.settings.groups`    | `[]`                                   | Extra settings groups (base groups always included)         |

**`config/features.php` — feature registry**

| Key                        | Default                              | Purpose                                            |
| -------------------------- | ------------------------------------ | -------------------------------------------------- |
| `features.providers`       | `[PublicSite, Ai]`                  | Feature providers discovered by the registry       |
| `features.enabled.<slug>`  | `true` per shipped feature           | Per-feature default state (below persisted settings) |

**`config/media.php` — media targets**

| Key                       | Default                | Purpose                                                     |
| ------------------------- | ---------------------- | ----------------------------------------------------------- |
| `media.orphan_media`      | `avatars`              | Folder → model pairs scanned by `core:cleanup-orphan-media` (modules add their own via `getOrphanMediaTargets()`) |

Deploy settings live in `config/deploy.php` → [Deploy](deploy.md). Roles/permissions defaults
live in `config/roles.php` and `config/admin.php`.

## Commands

`app:create-admin`, `app:prune-audit-logs`, `core:cleanup-orphan-media`, `core:version`,
`deploy:auto`, `permissions:list`, `roles:create`, `schedule:publish`,
`notifications:purge`, `feature:make`. See [Commands](commands.md).

## Tests

```bash
vendor/bin/phpunit
```

Runs `FeatureProviderContractTest` (contract conformance for all registered features) and
`NamespaceIntegrityTest` (PSR-4 + autoload integrity).
