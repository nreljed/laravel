# Shop App

A SaaS application built with Laravel + Inertia.js + React 19.

## Package Model & Source of Truth (2026-08-24)

Shared packages are **products**; this repo is a **consumer**. Golden rules:

| Package | Source of truth (edit here) | Consumed as |
|---------|----------------------------|-------------|
| `platform` | `nreljed/platform` repo, developed directly | composer `^0.1` from `vendor/` |
| `blog` | `nreljed/blog` repo | composer `^0.1` from `vendor/` |
| `shop-module` | `nreljed/shop-module` repo | composer `^0.1` from `vendor/` |

- **Never edit `vendor/`, and never push feature code to a package repo from
  inside an app checkout.** Platform/blog/shop-module changes happen via PRs in the
  package repos, released with a semver tag.
- Releases fan out automatically: tag → GitHub Release → `core-updated`
  repository_dispatch → this repo's `core-update.yml`.
- `core-update.yml` / `shop-update.yml` bump `composer.lock`, run the FULL ci
  suite on the updated tree, open a lock-only PR and squash-merge it
  immediately (no branch protection exists; PR-check approval would stall).
  Merging fires `deploy.yml`. Weekly Monday cron is the fallback trigger.
  **The merge step MUST use the PAT (`secrets.GH_TOKEN`), never
  `github.token`** — pushes made with `GITHUB_TOKEN` emit no `push` event
  (GitHub suppresses recursive workflow runs), so a `github.token` merge
  silently skips `deploy.yml`/`ci.yml`. `deploy.yml` also supports
  `workflow_dispatch` for manual redeploys.
- `composer.lock` is the pin of record — commit it whenever packages update.

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
- `toast.add()` from `@/Components/ui/toast` for flash messages (wrapped in pages from `usePage().props.flash`)

## Architecture

```
app/                          # Core Laravel application (thin host shell)
  Console/Commands/
    GenerateDemoData.php      # php artisan demo:generate
  Models/
    User.php              # getDisplayNameOptions(), isAdmin(), role/permission checks, hasPermission()
  Providers/
    AppServiceProvider.php    # Gate policies, container bindings
    FrontendRouteServiceProvider.php  # Registers `/` → StorefrontController override in booted()
    TelescopeServiceProvider.php

> Everything else (controllers, middleware, policies, services, migrations, seeders,
> dashboard/shop/blog resources) lives in the `nreljed/platform` and
> `nreljed/shop-module` packages (see Feature Architecture). The host `app/Http`,
> `app/Policies`, `app/Services`, `app/Shop`, `app/Blog` trees are gone — edit the
> packages, not the host.

database/
  migrations/             # Core app migrations (settings, roles, permissions, users, etc.)
  seeders/                # DatabaseSeeder, RolesAndPermissionsSeeder, SettingsSeeder
resources/js/
  Components/
    ui/                   # shadcn/ui components + PublishSection, DateTimePicker, Field (host-local)
    shared/               # FeaturedImageSelector, TermsSelector, GalleryUploader (host-local)
    dashboard/            # KpiCard, Sparkline, RevenueChart, RecentOrdersTable, TopProductsTable (host-local)
    app-sidebar.tsx       # Sidebar from featureNavItems + groupLabel
    nav-main.tsx          # Collapsible nav
    nav-user.tsx          # User dropdown: theme toggle, logout
    command-search.tsx    # Ctrl+K palette (includes nav items dynamically)
    ThemeSwitcher.tsx     # Light/dark toggle
    NotificationBell.tsx  # 20s polling
  hooks/
    use-auth.ts           # user, isAdmin, getUserInitials
    use-notifications.ts  # Axios-based polling (not Inertia router)
  Layouts/
    AuthenticatedLayout.tsx
    AdminLayout.tsx
    GuestLayout.tsx
    PublicLayout.tsx
    StoreLayout.tsx
  Pages/                  # Host-local dashboard/account/auth pages; feature pages come from packages
    Dashboard/            # Index (stats/analytics), Notifications, Stats
    Account/Edit.tsx      # Name, email, password, delete, address
    Admin/                # Settings (tabs), System Health
    Auth/                 # Login, Register
    Welcome.tsx           # Landing page
routes/
  web.php                 # Dashboard, admin, public, deploy routes
  api.php, channels.php, console.php

> Shop + Blog routes are self-loaded by their package providers (`vendor/nreljed/shop-module` + `vendor/nreljed/blog`); there are no `routes/shop*.php` or `routes/blog.php` files in the host.

## Routes

| Prefix | Middleware | Pages |
|--------|-----------|-------|
| `/` | none (guest) | Welcome, About, Services, Contact |
| `/dashboard` | auth+verified | Dashboard, Stats, Notifications, Account, Profile |
| `/dashboard/admin` | auth+verified+admin+throttle:60,1 | Users, Roles, Permissions, Settings, System Health, Audit Logs |
| `/dashboard/posts` | auth+verified+permission:posts.view | Posts CRUD, Categories, Tags |
| `/dashboard/shop` | auth+verified+permission:shop.view | Products, Orders, Cart, Customers, Coupons, Reviews, Shipping, Tax, Reports, Webhooks, Attributes, Categories, Tags, Brands |
| `/store` | none (guest) | Storefront (product grid, detail, category, cart, checkout) |
| `/deploy/...` | token | Deploy endpoints |
| `/api/notifications/...` | auth | Notification JSON endpoints |
| `/api/...` | auth | JSON API (products) |

## Key Patterns

- **Shared props**: `HandleInertiaRequests` shares `auth.user`, `latestNotifications`, `notificationUnreadCount`, `flash`, `userMode`, `canSwitchMode`, `sharedData` (from FeatureRegistry), `featureNavItems` (with `groupLabel`), `userCapabilities`, `repeaterConfigs`, `profileFieldConfigs`, `emailSettings`, `brand`, `impersonating`
- **Admin check**: `useAuth().isAdmin` + `admin` middleware + `User::isAdmin()`
- **Component imports**: Use `@/Components/...` alias (NOT `@/components/...`) — Windows case-insensitive FS workaround; use `@nreljed/shop-module/...` for Shop modules and `@nreljed/blog/...` for Blog pages (both mapped in vite.config.js + tsconfig.json)
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

The old `app/Modules/*/` module system has been consolidated into two feature providers:

| Feature | Namespace | Directory | Service Provider | Slug |
|---------|-----------|-----------|------------------|------|
| Shop | `Nreljed\ShopModule` | `vendor/nreljed/shop-module/src/` (private GitHub repo `nreljed/shop-module`, composer pkg `nreljed/shop-module`, installed via VCS) | `ShopServiceProvider` | `shop` |
| Blog | `Nreljed\Blog` | `vendor/nreljed/blog/src/` (private GitHub repo `nreljed/blog`, composer pkg `nreljed/blog`, installed via VCS) | `BlogServiceProvider` | `posts` |

Each service provider implements `Nreljed\Platform\Contracts\FeatureProvider` (via `InteractsWithFeatures`): `name()`, `slug()`, `permissions()`, `getNavItems()`, `getSharedData()`, `getEmailTemplates()`, `getNotificationTypes()`, `getDashboardData()`, `onUserDelete()`, etc., aggregated by the core `FeatureRegistry`.

### FeatureRegistry
`Nreljed\Platform\Services\FeatureRegistry` (in the `nreljed/platform` package) replaces the old `ModuleRegistry`. It:
- Collects the feature providers listed in `config/features.php` `providers` (host adds `Nreljed\ShopModule\ShopServiceProvider` alongside the `PublicSiteServiceProvider`/`PostsServiceProvider`/`AiServiceProvider` from the `nreljed/platform` and `nreljed/blog` packages)
- Exposes `allProviders()`, `getModuleList()`, `getAllNavItems()`, `getAllSharedData()`, `permissions()`, `missingDependencies()`, etc. **There is NO public `get(string $slug)` method** — build slug→provider maps from `allProviders()` instead
- Used by `HandleInertiaRequests`, `PermissionGateServiceProvider`, `DashboardService`, `FormattingService`, `NotificationTypeRegistry`, `EmailSettingsController`, and other services

### Module Enable/Disable (Project-WP parity)
Features are readable as enabled/disabled and can be toggled at runtime by an admin from `GET/PUT /dashboard/admin/settings/modules` (route names `dashboard.admin.settings.modules.{index,update}`, admin-role gated, CSRF protected).
- **State**: `FeatureSettingsService` (platform package) persists per-feature state in the `settings` table, group `core`, key `feature_enabled.<slug>`, layered above the `features.enabled.<slug>` config default. `isEnabled()` caches via `Cache::remember('feature_enabled.<slug>', 300, ...)`; `setEnabled()` forgets on write. Correct slugs: `feature_enabled.public-site`, `feature_enabled.posts`, `feature_enabled.shop`.
- **Route gating**: each provider self-gates in `boot()` by early-returning before `loadRoutes()`/`loadPublicRoutes()` when `enabled()` is false. Shop wraps only `loadRoutes()` in `if ($this->enabled())` (migrations/views/policies/commands still load so re-enable is non-destructive). Disabled features → their routes 404 and nav items vanish.
- **API contract**: the update payload is a NESTED `modules` map (`{ modules: { 'shop': true, ... } }`). The controller only toggles the slugs explicitly present in the payload (unknown slugs ignored) — a partial payload preserves untoggled features. `setEnabled()` returns the previous state; `onDisable()` (FeatureProvider contract, no-op default) runs on disable.
- **Tests**: `tests/Feature/ModuleManagementTest.php` asserts nested-payload persistence, partial-payload preservation, unknown-slug tolerance, admin/403 gating, and disable→re-enable roundtrips.

### Route Loading
Each feature provider self-loads its own routes from its own `Routes/` directory in `boot()`:
- Blog: `Nreljed\Blog\BlogServiceProvider::boot()` → `loadRoutesFrom(__DIR__.'/Routes/web.php')` (prefix `dashboard/posts`, name `dashboard.posts.`) + public routes (prefix `blog`)
- Shop: `vendor/nreljed/shop-module/src/Routes/*.php` (11 files: web, admin, storefront, store-cart, store-checkout, store-wishlist, store-notify, recovery, customer-portal, api, api-auth), loaded by `Nreljed\ShopModule\ShopServiceProvider::boot()` → `loadRoutesFrom(__DIR__.'/Routes/…')`, grouped with `web` middleware + `permission:shop.view` + prefixes `dashboard.shop.` / `admin.shop.` / `store.*`
- Shop JSON API: the package registers `api.*` routes (products/orders/categories, `auth:sanctum`) — the host's `routes/api.php` does NOT require shop files

### Frontend Page Loading
Inertia pages are loaded from package `pages.ts` kits, spread explicitly in `resources/js/page-config.ts`:
- Core control-panel pages: `registerCoreTemplates()` from `@nreljed/platform/dashboard`
- Blog pages: `registerBlogPages()` from `@nreljed/blog/pages` (registered under `posts/*`)
- Shop pages: `registerShopPages()` from `@nreljed/shop-module/pages` (registered under `shop/*`)
- Host-local forked templates: `localPages` gathered via `import.meta.glob("./Pages/**/*.tsx")`, with `pageOverrides` swapping `public/about`|`services`|`contact`

The entry (`app.tsx`) imports `pageConfig` and calls `createFoundationApp(pageConfig)` — the config block is never duplicated.

Alias mappings (`vite.config.js` + `tsconfig.json`): `@nreljed/platform` → `vendor/nreljed/platform/resources/js`; `@nreljed/blog` → `vendor/nreljed/blog/src/Resources/js`; `@nreljed/shop-module` → `vendor/nreljed/shop-module/src/Resources/js`.

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
```

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
13. Push unified `GITHUB_TOKEN` to server `.env` via `POST /deploy/env` (before upload, `if: always()` + `continue-on-error`, 3 retries — keeps the server's token fresh for the GitHub-API deploy path)
14. Upload via Laravel `/deploy/check` (preferred — server pulls the release itself) or `/deploy/upload`
15. **FTP fallback** via `FtpWebRequest` (passive) to DirectAdmin — uploads `deploy.zip` for manual extraction if step 14 fails. **The FTP account home IS the project root** (`/home/advertis/domains/shop.laravel.tunispromo.tn/public_html/`), so it uploads to `ftp://server:port/deploy.zip` (root), NOT a `domains/.../public_html/...` path.

**On the server — `DeployController::check()`**:
- Verifies token, checks `.deploy-lock` (10 min TTL)
- Fetches latest release from GitHub API via `GITHUB_TOKEN`
- Downloads `deploy.zip`, extracts over project root
- Restores `.env` from backup (never overwritten)
- Runs: `package:discover`, `migrate`, `app:create-admin`, `config:cache`, `route:cache`, `view:cache`, `storage:link`
- `app:create-admin` reads `X-Admin-Email/PASSWORD/NAME` headers from the deploy webhook (GitHub secrets) to bootstrap the first admin on fresh DB

### Prerequisites

| Requirement | Value |
|------------|-------|
| PHP | 8.3+ (8.4 recommended) |
| MySQL | 8.0+ |
| SSH | Not required |
| GitHub PAT | One fine-grained PAT over all seven nreljed repos: Contents + Pull requests R/W (see "GitHub Token Structure") |

### GitHub Secrets

All secrets are managed via the `gh` CLI (NOT the GitHub web UI — see `gh CLI` section below).

| Secret | Purpose |
|--------|---------|
| `DEPLOY_TOKEN` | Must match `config('app.deploy_token')` in server `.env` |
| `GH_TOKEN` | ONE fine-grained PAT covering all seven repos (see "GitHub Token Structure" below) |
| `DB_DATABASE` | Production MySQL database name |
| `DB_USERNAME` | Production MySQL username |
| `DB_PASSWORD` | Production MySQL password |
| `ADMIN_EMAIL` | Email for initial admin user (created on first deploy) |
| `ADMIN_PASSWORD` | Password for initial admin user |
| `ADMIN_NAME` | Display name / username for initial admin user |
| `APP_KEY` | Unique Laravel `base64:...` key (never hardcoded) |
| `FTP_SERVER` | DirectAdmin FTP hostname (for shared hosting fallback) |
| `FTP_PORT` | DirectAdmin FTP port (typically 21) |
| `FTP_USERNAME` | DirectAdmin FTP username |
| `FTP_PASSWORD` | DirectAdmin FTP password |

### GitHub Token Structure

The CI/CD spans seven repos owned by the same user. Composer's `COMPOSER_AUTH`
accepts only ONE token per host (`github.com`), yet each app's CI must clone its
private VCS packages in the same install (Shop: `platform` + `blog` + `shop-module`).
Per-repo tokens can't express that — so the design is **ONE fine-grained PAT covering all
seven repos**, stored as `GH_TOKEN` in all three app repos (and pushed to each
server's `.env` on deploy so the server can call the GitHub API).

```
ONE fine-grained PAT "nreljed-deploy"
├── Repository access → Only select repositories:
│     ✅ nreljed/laravel          → Contents: Read+Write · Pull requests: Read+Write
│     ✅ nreljed/laravel.shop     → Contents: Read+Write · Pull requests: Read+Write
│     ✅ nreljed/laravel.booking  → Contents: Read+Write · Pull requests: Read+Write
│     ✅ nreljed/platform         → Contents: Read+Write
│     ✅ nreljed/blog             → Contents: Read
│     ✅ nreljed/shop-module      → Contents: Read
│     ✅ nreljed/booking-module   → Contents: Read
└── Repository permissions (all repos):
      Contents: Read+Write    (composer VCS clones, server release downloads, release creation)
      Pull requests: Read+Write (required: `gh pr merge` uses the GraphQL `repository.pullRequest` lookup;
                                without it merges fail with "Resource not accessible by personal access token")
      Metadata: Read           (implicit baseline for fine-grained PATs)
```

Why each repo is needed (workflow → usage → requirement):

| Repo | Used by | Requirement |
|------|---------|-------------|
| `laravel.shop` | Shop `deploy.yml` `softprops/action-gh-release` (creates Release on itself) + server downloads the release zipball; `core-update.yml`/`shop-update.yml` merge step | Read+Write + Pull requests: Read+Write |
| `laravel.booking` | Booking `deploy.yml` release creation + server release download; `core-update.yml` merge step | Read+Write + Pull requests: Read+Write |
| `platform` | Shop/Booking/monorepo `ci.yml` + `*-update.yml` composer VCS clone; release tagging | Read+Write |
| `blog` | Shop/Booking/monorepo `ci.yml` composer VCS clone | Read |
| `shop-module` | Shop `ci.yml`/`shop-update.yml`/`deploy-package.yml` composer VCS clone | Read |
| `booking-module` | Booking `ci.yml`/`core-update.yml`/`deploy-*.yml` composer VCS clone | Read |
| `laravel` | Monorepo `deploy.yml` release creation; `core-updated` dispatch to consumers | Read+Write |

Set the SAME token value as `GH_TOKEN` in `nreljed/laravel`, `nreljed/laravel.shop`
and `nreljed/laravel.booking` (edited PAT permissions do NOT rotate the token value,
so no secret re-push is needed after permission changes).

**Never use `github.token` for merges.** GITHUB_TOKEN-authored pushes emit no `push`
event (GitHub suppresses recursive workflow runs), so a `github.token` merge silently
skips `deploy.yml`. Every `*-update.yml` merge step MUST use `secrets.GH_TOKEN`
(= this PAT). This bit laravel.shop (fixed) and laravel.booking (fixed via PR #29).

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
gh secret set SECRET_NAME -b "value" -R nreljed/laravel.shop

# List all secrets
gh secret list -R nreljed/laravel.shop

# Generate a secure Laravel APP_KEY
php -r "echo 'base64:' . base64_encode(random_bytes(32));"
gh secret set APP_KEY -b "$(php -r 'echo "base64:" . base64_encode(random_bytes(32));')" -R nreljed/laravel.shop
```

**Repo operations:**
```bash
gh repo view nreljed/laravel.shop      # View repo details
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

Playwright MCP (`@playwright/mcp` v0.0.78 by Microsoft) is installed globally and configured via `playwright-mcp.json` in the project root. The MCP server is registered in `~/.config/opencode/opencode.jsonc`.

### How It Works
- Run headless Chromium via `npx @playwright/mcp --config playwright-mcp.json`
- Accessible as MCP tools to interact with `http://shop-laravel.local/`
- Config: headless, isolated profiles (no disk caching), `core+network+storage+testing` caps
- Output dir: `storage/debug/playwright-output/` (auto-cleaned on size > 50MB via `outputMaxSize`)
- **`imageResponses: "omit"`** — screenshots/image data are never sent to the client, since the model cannot view images. Do NOT call `browser_take_screenshot`; use `browser_snapshot` (accessibility tree) instead.

### Bloat Prevention (Why Playwright Eats Disk)
Playwright's persistent Chrome profiles download massive ML models if not disabled. The config in `playwright-mcp.json` prevents this with:
- **`isolated: true`** — profiles stay in memory, never written to disk
- **`--disable-features=OptimizationGuideModelDownloading,...`** — prevents the 4GB Chrome ML model download
- **`--disable-component-update`** — prevents Chrome component updates
- **`outputMaxSize: 52428800`** — auto-evicts old output files at 50MB

### Cleanup Commands
```bash
npm run playwright:clean-output     # Clean test output files only
npm run playwright:clean-pngs       # Remove PNG screenshots from output dirs
npm run playwright:clean-profiles   # Delete persisted browser profiles (~5GB!)
npm run playwright:clean-npx        # Clean npx download cache
npm run playwright:clean-all        # All of the above
npm run playwright:doctor           # Show Playwright disk usage breakdown
```

### Disk Usage Breakdown
| Location | Typical Size | Notes |
|----------|-------------|-------|
| `%USERPROFILE%\AppData\Local\ms-playwright\chromium-1228` | ~415 MB | Browser binary (needed, don't delete) |
| `%USERPROFILE%\AppData\Local\ms-playwright\mcp-*` | ~5 GB | **Persisted profiles** (clean via `playwright:clean-profiles`) |
| `storage/debug/playwright-output/` | ~0-50 MB | Screenshots/traces (auto-evicted) |

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

Phase-by-phase implementation history (Webhooks, Storefront, Shop parity, Inventory,
Analytics, Abandoned Cart, Discount Engine, Brands, Swatches, Cart fixes, etc.) has
moved to docs/CHANGELOG-PHASES.md. That file records what shipped when — file listings
there reference the legacy `app/Modules/*` trees that no longer exist.
