# Booking App

A SaaS application built with Laravel + Inertia.js + React 19.

## Package Model & Source of Truth (2026-08-24)

Shared packages are **products**; this repo is a **consumer**. Golden rules:

| Package          | Source of truth (edit here)                 | Consumed as                    |
| ---------------- | ------------------------------------------- | ------------------------------ |
| `platform`       | `nreljed/platform` repo, developed directly | composer `^0.1` from `vendor/` |
| `booking-module` | `nreljed/booking-module` repo               | composer `^0.1` from `vendor/` |

- **Never edit `vendor/`, and never push feature code to a package repo from
  inside an app checkout.** Core changes happen via PRs in the package repo,
  released with a semver tag.
- Releases fan out automatically: tag → GitHub Release → `core-updated`
  repository_dispatch → this repo's `core-update.yml`.
- `core-update.yml` bumps `composer.lock`, runs the FULL ci suite on the
  updated tree, opens a lock-only PR and squash-merges it immediately
  (no branch protection exists; PR-check approval would stall). Merging fires
  `deploy.yml`. Weekly Monday cron is the fallback trigger.
- `composer.lock` is the pin of record — commit it whenever packages update.

> **Important:** If this project was copied from another app (e.g., `shop-laravel` → `booking-laravel`), read `docs/project-setup-after-copy.md` first — the copy leaves stale APP_KEY, APP_NAME, URLs, and can confuse opencode Desktop's project registry.

## Tech Stack

- **Backend**: Laravel 13 (PHP 8.4)
- **Frontend**: React 19.2 + Inertia.js 3.4
- **Build Tool**: Vite 8, TypeScript 6
- **Styling**: Tailwind CSS v4 + tailwindcss-animate
- **UI**: shadcn/ui (new-york, neutral base) + Radix UI + Lucide icons
- **Forms**: Inertia `useForm` (server-side validation) + optional Zod client-side
- **Auth**: Laravel Breeze (session-based), Sanctum
- **Database**: SQLite (local), MySQL (production)
- **Notifications**: Laravel Notifications (database channel)
- **Icons**: Lucide React

## Code Style

- Functional components with hooks, strict TypeScript
- 4 spaces for indentation (PHP), 2 spaces (TSX)
- Use `cn()` from `@/lib/utils` for class merging
- All routes via Ziggy's `route()` helper — no hardcoded URLs
- Laravel validation in controllers via `$request->validate()`
- Inertia `useForm` for all form state (not react-hook-form)
- `useAuth` hook from `@/hooks/use-auth` for auth state
- `next-themes` `<ThemeProvider>` for dark mode
- `toast()` from `sonner` for flash messages (wrapped in pages from `usePage().props.flash`)

## Architecture

````
app/                          # Core Laravel application (thin host shell)
  Http/
    Middleware/
      HandleInertiaRequests.php  # Shares auth, notifications, flash, userMode, canSwitchMode, sharedData, featureNavItems, userCapabilities
  Models/
    User.php              # getDisplayNameOptions(), isAdmin(), role/permission checks, hasPermission()
    Permission.php, Role.php, Setting.php, NotificationPreference.php
  Services/
    SettingsService.php   # DB-driven app config
  Traits/
    EditorContentSanitizer.php, PasswordValidationTrait.php
  Providers/
    AppServiceProvider.php    # Gate policies, container bindings
    TelescopeServiceProvider.php

> Everything else (controllers, middleware, policies, services, migrations, seeders,
> dashboard/booking resources) lives in the `nreljed/platform` and
> `nreljed/booking-module` packages (see Feature Architecture). The host
> `app/Http/Controllers` and `app/Shop` trees are gone —
> edit the packages, not the host.

database/
  migrations/             # Core app migrations (settings, roles, permissions, users, etc.)
  seeders/                # DatabaseSeeder, RolesAndPermissionsSeeder, SettingsSeeder
resources/js/
  Components/
    ui/                   # shadcn/ui components (host-local)
  Layouts/
    AuthenticatedLayout.tsx, AdminLayout.tsx, GuestLayout.tsx, PublicLayout.tsx
  Pages/                  # Host-local marketing templates; feature pages come from packages
  page-config.ts          # pageMap = registerCoreTemplates() + registerBookingPages() + registerPropertiesPages() + localPages
routes/
  web.php                 # Host-only routes (dashboard/admin/public lives in the packages)
  api.php, channels.php, console.php

> Dashboard/admin/auth/public + feature routes are self-loaded by their package
> providers (`nreljed/platform`, `nreljed/booking-module`); there
> are no `routes/shop*.php` or `routes/blog.php` files in the host.

## Routes

| Prefix | Middleware | Pages |
|--------|-----------|-------|
| `/` | none (guest) | Welcome, About, Services, Contact |
| `/dashboard` | auth+verified | Dashboard, Stats, Notifications, Account, Profile |
| `/dashboard/admin` | auth+verified+admin+throttle:60,1 | Users, Roles, Permissions, Settings, System Health, Audit Logs |
| `/dashboard/bookings` | auth+verified+permission:bookings.view | Bookings pipeline, calendar |
| `/dashboard/properties` | auth+verified+permission:properties.view | Property listings, terms |
| `/deploy/...` | token | Deploy endpoints |
| `/api/notifications/...` | auth | Notification JSON endpoints |

## Key Patterns

- **Shared props**: `HandleInertiaRequests` shares `auth.user`, `latestNotifications`, `notificationUnreadCount`, `flash`, `userMode`, `canSwitchMode`, `sharedData` (from FeatureRegistry), `featureNavItems` (with `groupLabel`), `userCapabilities`, `repeaterConfigs`, `profileFieldConfigs`, `emailSettings`, `brand`, `impersonating`
- **Admin check**: `useAuth().isAdmin` + `admin` middleware + `User::isAdmin()`
- **Component imports**: Use `@/Components/...` alias (NOT `@/components/...`) — Windows case-insensitive FS workaround; use `@nreljed/booking-module/booking/...` and `@nreljed/booking-module/properties/...` for Booking module components (all mapped in vite.config.js + tsconfig.json)
- **No ShadCN `asChild`**: Avoid `Slot` pattern when possible; use plain `<Link>` instead
- **Authorization**: Route groups use `permission:{slug}` middleware. Individual create/edit/delete actions are authorized via Policy + `$this->authorize()` in controllers or FormRequest `authorize()`
- **Policies**: Registered in `AppServiceProvider` via `Gate::policy()`
- **Nav group labels**: Defined via providers' `getNavGroupLabel()` — used by sidebar to display group headings like "Shop", "Content"
- **Rate limiting**:
  - Login: application-level `RateLimiter` — `{username}|{ip}` key, 5 attempts, 60s decay
  - Other auth: `throttle:5,60` — IP-based, returns 429
  - Admin routes: `throttle:60,1` — 60 requests/min
- **Validation**: Optional Zod + `$request->validate()` on all mutation endpoints

## Feature Architecture

The old `app/Modules/*/` and `App\Shop` domains have been consolidated into two feature providers shipped by packages:

| Feature | Namespace | Directory | Service Provider | Slug |
|---------|-----------|-----------|------------------|------|
| Bookings | `Nreljed\BookingModule\Booking` | `vendor/nreljed/booking-module/src/Booking/` | `BookingServiceProvider` | `bookings` |
| Properties | `Nreljed\BookingModule\Properties` | `vendor/nreljed/booking-module/src/Properties/` | `PropertiesServiceProvider` | `properties` |

Each service provider implements `Nreljed\Platform\Contracts\FeatureProvider`: `name()`, `slug()`, `permissions()`, `getNavItems()`, `getSharedData()`, `getEmailTemplates()`, `getNotificationTypes()`, `getDashboardData()`, `onUserDelete()`, etc., aggregated by the platform `FeatureRegistry`.

### FeatureRegistry
`Nreljed\Platform\Services\FeatureRegistry` (in the `nreljed/platform` package) replaces the old `ModuleRegistry`. It:
- Collects the feature providers listed in `config/features.php` `providers` (host adds `BookingServiceProvider`/`PropertiesServiceProvider` alongside the `PublicSiteServiceProvider`/`AiServiceProvider` from the `nreljed/platform` package)
- Exposes `allProviders()`, `getModuleList()`, `getAllNavItems()`, `getAllSharedData()`, `permissions()`, `missingDependencies()`, etc. **There is NO public `get(string $slug)` method** — build slug→provider maps from `allProviders()` instead
- Used by `HandleInertiaRequests`, `PermissionGateServiceProvider`, `DashboardService`, `FormattingService`, `NotificationTypeRegistry`, `EmailSettingsController`, and other services

### Route Loading
Each feature provider self-loads its own routes from its own `Routes/` directory in `boot()`:
- Bookings: `vendor/nreljed/booking-module/src/Booking/Routes/*.php`, loaded by `BookingServiceProvider::boot()`, group `web` middleware + `permission:bookings.view` + prefixes `dashboard.bookings.`
- Properties: `vendor/nreljed/booking-module/src/Properties/Routes/*.php`, loaded by `PropertiesServiceProvider::boot()`, prefixes `dashboard.properties.` + public (prefix `properties`)

### Frontend Page Loading
Inertia pages are loaded from package `pages.ts` kits, spread explicitly in `resources/js/page-config.ts`:
- Core control-panel pages: `registerCoreTemplates()` from `@nreljed/platform/dashboard`
- Booking pages: `registerBookingPages()` from `@nreljed/booking-module/booking/pages` (under `bookings/*`)
- Properties pages: `registerPropertiesPages()` from `@nreljed/booking-module/properties/pages` (under `properties/*` + `properties/public/*`)
- Host-local forked templates: `localPages` gathered via `import.meta.glob("./Pages/**/*.tsx")`

The entry (`app.tsx`) imports `pageConfig` and calls `createFoundationApp(pageConfig)` — the config block is never duplicated.

Alias mappings (`vite.config.js` + `tsconfig.json`): `@nreljed/platform` → `vendor/nreljed/platform/resources/js`; `@nreljed/booking-module/booking` → `vendor/nreljed/booking-module/src/Booking/Resources/js`; `@nreljed/booking-module/properties` → `vendor/nreljed/booking-module/src/Properties/Resources/js`.

## Dev Environment

- **Not using `npm run dev`** — the app runs via LocalWP (Local by Flywheel), which serves the production build
- `public/hot` should NOT exist — if present, delete it; Laravel's Vite helper reads it and tries to connect to the Vite dev server, causing `ERR_CONNECTION_REFUSED` on `[::1]:5173`
- Always run `npm run build` after code changes to update `public/build/`
- CSP middleware (`ContentSecurityPolicy.php`) only applies in production — never tweak Vite config or hot file for CSP reasons; skip the middleware in non-production instead

## Development Commands

```bash
npm run dev              # Vite dev server with HMR
npm run build            # tsc + vite build (production)
php artisan migrate      # Run migrations
php artisan test         # PHPUnit
npm run typecheck        # tsc --noEmit
vendor/bin/pint          # Laravel Pint (PSR-12)
php artisan permissions:list         # List all permissions
php artisan demo:generate            # Create realistic demo data (--fresh to clear first)
php artisan demo:generate --fresh    # Clear all demo data and regenerate
````

## Deployment

### CI/CD Pipeline (GitHub Actions → Release + Server Deploy)

Deploys automatically on every push to `main`:

**Workflow 1 — `deploy.yml`** (runs-on via `RUNNER_CI`/`RUNNER_PACKAGE` Actions variables — `self-hosted` by default, flip to `ubuntu-latest`/`windows-latest` for cloud):

1. Checkout with full depth + tags
2. Setup PHP 8.4 (extensions: fileinfo, gd, mbstring, pdo_mysql, pdo_sqlite, intl, bcmath, zip) + Node 22 (npm cache on)
3. git-cliff: parse commits → bump semver → generate CHANGELOG.md + release-notes.md
4. Generate build .env (SQLite)
5. `composer install --prefer-dist --no-scripts` (composer cache on)
6. `php artisan package:discover + migrate --force`
7. [cached] `npm install` → `npm run typecheck` → `npm run build` (generates manifest.json)
8. `php artisan test` + `vendor/bin/pint` (auto-fix)
9. `composer install --no-dev --optimize-autoloader`
10. Overwrite .env (production MySQL config — APP_KEY from secrets, not hardcoded)
11. Package `deploy.zip` (excludes `bootstrap/cache/`)
12. Create GitHub Release with semver tag `vX.Y.Z` + changelog body + deploy.zip attachment
13. Upload via Laravel `/deploy/upload` endpoint (or legacy `/deploy/check`)
14. **FTP fallback** via `FtpWebRequest` to DirectAdmin — uploads `deploy.zip` for manual extraction if steps 13 fails

**On the server — `DeployController::check()`**:

- Verifies token, checks `.deploy-lock` (10 min TTL)
- Fetches latest release from GitHub API via `GITHUB_TOKEN`
- Downloads `deploy.zip`, extracts over project root
- Restores `.env` from backup (never overwritten)
- Runs: `package:discover`, `migrate`, `app:create-admin`, `config:cache`, `route:cache`, `view:cache`, `storage:link`
- `app:create-admin` reads `X-Admin-Email/PASSWORD/NAME` headers from the deploy webhook (GitHub secrets) to bootstrap the first admin on fresh DB

### Prerequisites

| Requirement | Value                                                                 |
| ----------- | --------------------------------------------------------------------- |
| PHP         | 8.3+ (8.4 recommended)                                                |
| MySQL       | 8.0+                                                                  |
| SSH         | Not required                                                          |
| GitHub PAT  | Fine-grained with **Contents: Read and Write** (stored as `GH_TOKEN`) |

### GitHub Secrets

All secrets are managed via the `gh` CLI (NOT the GitHub web UI — see `gh CLI` section below).

| Secret           | Purpose                                                  |
| ---------------- | -------------------------------------------------------- |
| `DEPLOY_TOKEN`   | Must match `config('app.deploy_token')` in server `.env` |
| `GH_TOKEN`       | Fine-grained PAT with **Contents: Read and Write**       |
| `DB_DATABASE`    | Production MySQL database name                           |
| `DB_USERNAME`    | Production MySQL username                                |
| `DB_PASSWORD`    | Production MySQL password                                |
| `ADMIN_EMAIL`    | Email for initial admin user (created on first deploy)   |
| `ADMIN_PASSWORD` | Password for initial admin user                          |
| `ADMIN_NAME`     | Display name / username for initial admin user           |
| `APP_KEY`        | Unique Laravel `base64:...` key (never hardcoded)        |
| `FTP_SERVER`     | DirectAdmin FTP hostname (for shared hosting fallback)   |
| `FTP_PORT`       | DirectAdmin FTP port (typically 21)                      |
| `FTP_USERNAME`   | DirectAdmin FTP username                                 |
| `FTP_PASSWORD`   | DirectAdmin FTP password                                 |

### GitHub CLI (`gh`)

All GitHub administration for this project is done via the official GitHub CLI (`gh`), not the web UI. This ensures reproducibility and can be automated.

**Setup:**

```bash
# Install (Windows)
winget install --id GitHub.cli

# Authenticate
gh auth login
# → Login via web browser, select repo scope
```

**Manage secrets:**

```bash
# Set a secret (creates/updates)
gh secret set SECRET_NAME -b "value" -R nreljed/laravel.booking

# List all secrets
gh secret list -R nreljed/laravel.booking

# Generate a secure Laravel APP_KEY
php -r "echo 'base64:' . base64_encode(random_bytes(32));"
gh secret set APP_KEY -b "$(php -r 'echo "base64:" . base64_encode(random_bytes(32));')" -R nreljed/laravel.booking
```

**Repo operations:**

```bash
gh repo view nreljed/laravel.booking      # View repo details
gh run list                             # View recent workflow runs
gh run watch <run-id>                   # Watch a workflow live
gh release list                         # List releases
gh release create v1.2.3 --generate-notes  # Create release with changelog
```

**Checking auth status:**

```bash
gh auth status
```

### Playwright MCP (Browser Testing)

Playwright MCP (`@playwright/mcp` v0.0.77 by Microsoft) is installed globally and configured via `playwright-mcp.json` in the project root. The MCP server is registered in `~/.config/opencode/opencode.jsonc`.

### How It Works

- Run headless Chromium via `npx @playwright/mcp --config playwright-mcp.json`
- Accessible as MCP tools to interact with `http://booking-laravel.local/`
- Config: headless, isolated profiles (no disk caching), `core+network+storage+testing` caps
- Output dir: `storage/debug/playwright-output/` (auto-cleaned on size > 50MB via `outputMaxSize`)

### Bloat Prevention (Why Playwright Eats Disk)

Playwright's persistent Chrome profiles download massive ML models if not disabled. The config in `playwright-mcp.json` prevents this with:

- **`isolated: true`** — profiles stay in memory, never written to disk
- **`--disable-features=OptimizationGuideModelDownloading,...`** — prevents the 4GB Chrome ML model download
- **`--disable-component-update`** — prevents Chrome component updates
- **`outputMaxSize: 52428800`** — auto-evicts old output files at 50MB

### Cleanup Commands

```bash
npm run playwright:clean-output     # Clean test output files only
npm run playwright:clean-profiles   # Delete persisted browser profiles (~5GB!)
npm run playwright:clean-npx        # Clean npx download cache
npm run playwright:clean-all        # All of the above
npm run playwright:doctor           # Show Playwright disk usage breakdown
```

### Disk Usage Breakdown

| Location                                                  | Typical Size | Notes                                                          |
| --------------------------------------------------------- | ------------ | -------------------------------------------------------------- |
| `%USERPROFILE%\AppData\Local\ms-playwright\chromium-1228` | ~415 MB      | Browser binary (needed, don't delete)                          |
| `%USERPROFILE%\AppData\Local\ms-playwright\mcp-*`         | ~5 GB        | **Persisted profiles** (clean via `playwright:clean-profiles`) |
| `storage/debug/playwright-output/`                        | ~0-50 MB     | Screenshots/traces (auto-evicted)                              |

### First-Time Setup

```bash
npm install -g @playwright/mcp                # Already done
npx playwright install chromium                # Download browser binary (already done)
```

### Key Files

- `playwright-mcp.json` — MCP server config (launch args, caps, output dir)
- `~/.config/opencode/opencode.jsonc` — MCP server registration
- `.opencode/opencode.json` — custom commands for cleanup

## Deploy Endpoints

- `GET /deploy/check?token=...` — Main deploy entry point
- `GET /deploy/cache-clear?token=...` — Emergency reset: migrate:fresh + cache clear

### Admin Bootstrap

On first deploy, `app:create-admin` runs after `migrate`. It reads `X-Admin-Email`, `X-Admin-Password`, and `X-Admin-Name` headers from the deploy webhook (set from GitHub secrets). On subsequent deploys, the existing admin is skipped gracefully.

See also: `app/Console/Commands/CreateAdmin.php`, `DEPLOY.md`

### After Schema/Seeder Changes

```bash
php artisan migrate
php artisan db:seed --class=RolesAndPermissionsSeeder
```

Then run typecheck + build + phpunit and deliver the results. Do not wait for user approval to deploy — automate the full pipeline.

For full details, see `DEPLOY.md`.

## Test Deploy

- `2026-06-21`: End-to-end deploy test passed — pipeline builds, zips, creates Release, and auto-deploys successfully.
- `2026-07-01`: Full module audit completed — all 14 issues fixed (order column migration, FormRequest authorize(), controller authorize(), missing migration dirs, booking create/edit routes, updateStatus route, city table validation, CommandSearch modules, groupLabel in sidebar, policies for all modules, Shop route standardization)
- `2026-07-02`: Phase 3 (Webhooks) + Phase 4 (Storefront) completed — Webhook system with stripe/order/customer events, storefront API with product grid/detail/category pages, PSR-4 autoloader fix for all modules. 157/157 tests passing.

## Change History

The phase-by-phase implementation history that used to live in this file (Webhooks,
Storefront, Shop parity, Inventory, Analytics, Abandoned Cart, Discount Engine, Brands,
Swatches, Cart fixes, etc.) has been removed — it referenced the legacy `app/Modules/*`
trees that no longer exist in this repo. Use `git log -p -- AGENTS.md` for the archive.
