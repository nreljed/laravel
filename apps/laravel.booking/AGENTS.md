# Booking App

A **thin consumer** of the Nreljed platform. This app is a workspace member of
the `laravel` monorepo — all controllers, middleware, policies, services,
migrations, seeders, and frontend page kits live in the installed packages.

> **Full architecture reference:** read `../../AGENTS.md` at the workspace root
> for the complete package model, feature architecture, and conventions. This
> file only records what is specific to the Booking app.

## Package Model

This app is the live demo/deploy target for the **booking-module** package:

| Package        | Composer                 | npm                       | Provides                                         |
| -------------- | ------------------------ | ------------------------- | ------------------------------------------------ |
| platform       | `nreljed/platform`       | `@nreljed/platform`       | Engine: routes, RBAC, deploy, AI, media          |
| blog           | `nreljed/blog`           | `@nreljed/blog`           | Posts CMS                                        |
| booking-module | `nreljed/booking-module` | `@nreljed/booking-module` | Booking pipeline + Property listings (2 domains) |

- **booking-module ships two sub-domains** with two providers and two page kits:
  - `Nreljed\BookingModule\Booking\BookingServiceProvider` → `@nreljed/booking-module/booking/*`
  - `Nreljed\BookingModule\Properties\PropertiesServiceProvider` → `@nreljed/booking-module/properties/*`
- All PHP packages resolve from **vcs repos** (`github.com/nreljed/*`); pinned by `composer.lock` as the standalone apps are.
- Frontend resolves via **pnpm workspace symlinks** (`@nreljed/*`) — not vendor.
- **Never edit `vendor/` or `packages/*` from this app** — package changes go
  through their own repos/PRs.

## What Lives in THIS App (thin shell only)

```
app/
  Http/Middleware/
    HandleInertiaRequests.php               # extends platform's (empty subclass)
  Models/
    User.php                                # extends Nreljed\Platform\Models\User
    Role.php, Permission.php, Setting.php, NotificationPreference.php  # thin aliases/extends
  Providers/
    AppServiceProvider.php                  # container setup
    TelescopeServiceProvider.php
  Services/
    SettingsService.php                     # thin subclass of platform's
  Traits/
    EditorContentSanitizer.php              # local helper
    PasswordValidationTrait.php             # local helper
routes/
  web.php                                   # project-only routes (mostly empty)
```

Registration:

- `bootstrap/providers.php` — platform + blog + public-site + AI + booking + properties
- `config/features.php` — `PublicSite`, `Blog`, `Ai`, `Bookings`, `Properties`
- `resources/js/page-config.ts` — `registerCoreTemplates()` + `registerBlogPages()`
  - `registerBookingPages()` + `registerPropertiesPages()` + host `localPages`

## Workspace-Only

This app is **workspace-only**: it has no standalone git repo, no `.github/`
workflows, and no remote. It deploys as part of the workspace mono. Do not
initialize a nested `.git` here — the mono tracks this tree.

## Dev Environment

- Runs via LocalWP: the `booking-laravel` site's `{site}\app` is a directory
  junction → this workspace `apps/laravel.booking`. Edits hit
  `http://booking-laravel.local/` directly. DB is this app's sqlite.
- Rollback = delete junction + rename preserved `app.standalone` → `app`.
- `public/hot` must NOT exist (causes `ERR_CONNECTION_REFUSED`).
- Always run `npm run build` after code changes.
- CSP middleware applies in production only.

## Commands

```bash
npm run build                # tsc + vite build
npm run typecheck            # tsc --noEmit
php artisan migrate          # Run migrations
php artisan test             # PHPUnit
php artisan permissions:list # List all permissions
```

See root `../../AGENTS.md` for the full workflow, deploy pipeline, and package
dev loop (edit core clone → tag → `composer update nreljed/platform`).
