# Cost Breaker 360

A SaaS application built with Laravel + Inertia.js + React 19.

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

```
app/
  Http/
    Controllers/              # Core controllers
      Admin/                  # User, Role, Permission, Impersonation, BulkUser, AuditLog, Session
      Api/                    # NotificationPollController (JSON endpoints)
      Auth/                   # Login, Register, Password, Email verification
      AccountController.php   # Email, password, notification prefs, delete
      AdminController.php     # Settings, System Health
      AvatarController.php
      DashboardController.php # Index, Stats, Notifications, switchMode
      DeployController.php    # check, cacheClear, updateEnv
      EmailSettingsController.php
      ModuleController.php    # toggle, reorder
      NotificationTestController.php
      ProfileController.php   # Edit, update, destroy (delegates to AccountService)
      PublicController.php    # About, Services, Contact
      SettingsController.php  # Edit, update, upload/delete logo
    Middleware/
      HandleInertiaRequests.php  # Shares auth, notifications, flash, userMode, canSwitchMode, moduleDebugData, moduleNavItems, userCapabilities
      AdminMiddleware.php        # 403 if not admin
      CheckPermission.php       # user.permission:slug middleware
      CheckRole.php             # user.role:slug middleware
      ContentSecurityPolicy.php
      ForceInertiaCacheHeaders.php
    Requests/
      Module/ToggleModuleRequest.php
  Models/
    User.php              # getDisplayNameOptions(), isAdmin(), role/permission checks, hasPermission()
    Module.php            # Module DB model (name, slug, enabled, version, dependencies, order)
  Policies/               # Gate-based authorization
    PropertyPolicy.php    # viewAny/view/create/update/delete (owner or admin)
    BookingPolicy.php     # viewAny/view/create/update/delete/transition (owner or admin)
    PostPolicy.php        # viewAny/view/create/update/delete (owner or admin)
    ProductPolicy.php     # viewAny/view/create/update/delete (permission-based)
    OrderPolicy.php       # viewAny/view/update (permission-based)
  Services/
    AccountService.php    # deleteAccount()
    SettingsService.php   # DB-driven app config
    NotificationService.php
    DashboardService.php  # Stats cache
    FormattingService.php # Price/surface formatting
  Modules/
    Properties/           # Property listings (slug: properties, deps: [])
    Booking/              # Guest bookings (slug: bookings, deps: ['properties'])
    ECommerce/            # Online store (slug: ecommerce, deps: [])
    Posts/                # Blog posts (slug: posts, deps: [])
    Contracts/
      ModuleInterface.php   # 53 methods including getNavGroupLabel()
    ModuleRegistry.php      # Central registry: boot, dependencies, routes, nav items
    ModuleLoader.php        # Scans app/Modules/*/ for {Name}Module.php
    Traits/
      HasRoutes.php         # loadRoutes(), loadAdminRoutes(), loadPublicRoutes()
database/
  migrations/             # Standard + module migrations
  seeders/                # RolesAndPermissionsSeeder
resources/js/
  Components/
    ui/                   # shadcn/ui components
    app-sidebar.tsx       # Dynamic sidebar from moduleNavItems + groupLabel
    nav-main.tsx          # Collapsible nav
    nav-user.tsx          # User dropdown: theme toggle, host/guest switch, logout
    command-search.tsx    # Ctrl+K palette (includes module items dynamically)
    ThemeSwitcher.tsx     # Light/dark toggle
    NotificationBell.tsx  # 20s polling
  hooks/
    use-auth.ts           # user, isAdmin, getUserInitials
  Layouts/
    AuthenticatedLayout.tsx
    AdminLayout.tsx
    GuestLayout.tsx
    PublicLayout.tsx
  Pages/
    Dashboard/            # Index (stats), Notifications, Stats
    Account/Edit.tsx      # Name, email, password, delete
    Admin/                # Settings (tabs), SystemHealth (module debug card)
    Auth/                 # Login, Register
    Welcome.tsx           # Landing page
routes/
  web.php                 # Dashboard, admin, public, deploy routes
  auth.php                # Breeze auth routes + profile + switch-mode
```

## Routes

| Prefix | Middleware | Pages |
|--------|-----------|-------|
| `/` | none (guest) | Welcome, About, Services, Contact |
| `/dashboard` | auth+verified | Dashboard, Stats, Notifications, Account, Profile |
| `/dashboard/admin` | auth+verified+admin+throttle:60,1 | Users, Roles, Permissions, Settings, System Health, Audit Logs |
| `/dashboard/properties` | auth+verified+permission:properties.view | Properties CRUD + terms |
| `/dashboard/bookings` | auth+verified+permission:bookings.view | Bookings CRUD, Calendar, Demo |
| `/dashboard/posts` | auth+verified+permission:posts.view | Posts CRUD |
| `/dashboard/ecommerce` | auth+verified+permission:ecommerce.view | Products, Orders, Cart, Customers |
| `/booking/...` | none (guest) | Guest portal (magic-link auth) |
| `/deploy/...` | token | Deploy endpoints |
| `/api/notifications/...` | auth | Notification JSON endpoints |

## Key Patterns

- **Shared props**: `HandleInertiaRequests` shares `auth.user`, `latestNotifications`, `notificationUnreadCount`, `flash`, `userMode`, `canSwitchMode`, `moduleDebugData`, `moduleNavItems` (with `groupLabel`), `userCapabilities`, `modules`, `repeaterConfigs`, `profileFieldConfigs`, `emailSettings`, `brand`, `impersonating`
- **Partial reloads**: `router.reload({ only: ['notifications'] })` for polling
- **Admin check**: `useAuth().isAdmin` + `admin` middleware + `User::isAdmin()`
- **Component imports**: Use `@/Components/...` alias (NOT `@/components/...`) — Windows case-insensitive FS workaround
- **No ShadCN `asChild`**: Avoid `Slot` pattern when possible; use plain `<Link>` instead
- **Module authorization**: Route group uses `permission:module.view` middleware. Individual create/edit/delete actions are authorized via Policy + `$this->authorize()` in controllers or FormRequest `authorize()`
- **Module policies**: Each module model has a Policy in `app/Policies/` registered in `AppServiceProvider`
- **Module group labels**: Defined via `ModuleInterface::getNavGroupLabel()` — used by sidebar to display group headings like "Manage Property", "Shop", "Manage Booking", "Content"
- **Rate limiting**:
  - Login: application-level `RateLimiter` — `{username}|{ip}` key, 5 attempts, 60s decay
  - Other auth: `throttle:5,60` — IP-based, returns 429
  - Admin routes: `throttle:60,1` — 60 requests/min
- **Validation**: Optional Zod + `$request->validate()` on all mutation endpoints
- **Host/Guest mode**: `userMode` stored in session (default `guest`). Toggle via `dashboard.switch-mode` POST. `canSwitchMode` checks `bookings.view` permission. Displayed in nav-user dropdown.

## Module Architecture

Each module lives in `app/Modules/{ModuleName}/` and implements `ModuleInterface` (53 methods).

| Module | Slug | Dependencies | Group Label | Permissions | Frontend Pages |
|--------|------|-------------|-------------|-------------|----------------|
| Properties | `properties` | `[]` | Manage Property | `properties.view/create/edit/delete` | Index, Form, Types, Cities, Features, Statuses |
| Booking | `bookings` | `['properties']` | Manage Booking | `bookings.view/create/edit/delete` | Index, Form, Show, Calendar, Demo, DemoResult, GuestView, MyBookings, Settings |
| E-Commerce | `ecommerce` | `[]` | Shop | `ecommerce.view`, `ecommerce.products.create/edit/delete`, `ecommerce.orders.view/edit` | Products/Index, Products/Form, Orders/Index, Orders/Show |
| Posts | `posts` | `[]` | Content | `posts.view/create/edit/delete` | Index, Form |

Module files follow this structure:
```
app/Modules/{Name}/
  {Name}Module.php          # Module class (slug, deps, routes, nav, permissions, etc.)
  Routes/
    web.php                 # Dashboard routes
    admin.php               # (optional) Admin-only routes
    guest.php               # (optional) Public guest routes
  Controllers/
  Models/
  Services/
  Requests/                 # Form requests with authorize() checks
  Resources/js/
    Pages/                  # Inertia page components (auto-loaded via glob)
    components/             # Module-specific React components
    lib/                    # Module-specific utilities
  database/migrations/      # (optional) Module-specific migrations (ECommerce only)
```

### Module Discovery & Boot
1. `ModuleServiceProvider` registers `ModuleRegistry` and `ModuleLoader`
2. `ModuleLoader::load()` scans `app/Modules/*/` for `{Name}Module.php`
3. `ModuleRegistry::bootEnabled()` boots enabled modules in dependency order
4. Each booted module's `routes()` is called, registering web/auth/admin routes with proper middleware
5. Frontend pages are auto-discovered via Vite glob imports in `app.tsx`

## Development Commands

```bash
npm run dev              # Vite dev server with HMR
npm run build            # tsc + vite build (production)
php artisan migrate      # Run migrations
php artisan test         # PHPUnit
npm run typecheck        # tsc --noEmit
vendor/bin/pint          # Laravel Pint (PSR-12)
php artisan modules:sync-permissions  # Sync module permissions to DB
php artisan modules:debug {slug?}     # Debug module registration
php artisan permissions:list          # List all permissions
```

## Deployment

### CI/CD Pipeline (GitHub Actions → Release + Server Deploy)

Deploys automatically on every push to `main`:

**Workflow 1 — `deploy.yml`** (GitHub-hosted `windows-latest` runner):
1. Checkout with full depth + tags
2. Setup PHP 8.4 (extensions: fileinfo, gd, mbstring, pdo_mysql, pdo_sqlite, intl, bcmath, zip) + Node 22
3. git-cliff: parse commits → bump semver → generate CHANGELOG.md + release-notes.md
4. Generate build .env (SQLite)
5. `composer install --prefer-dist --no-scripts`
6. `php artisan package:discover + migrate --force`
7. [cached] `npm install` → `npm run typecheck` → `npm run build` (generates manifest.json)
8. `php artisan test` + `vendor/bin/pint` (auto-fix)
9. `composer install --no-dev --optimize-autoloader`
10. Overwrite .env (production MySQL config)
11. Package `deploy.zip` (excludes `bootstrap/cache/`)
12. Create GitHub Release with semver tag `vX.Y.Z` + changelog body + deploy.zip attachment
13. Curl `/deploy/check?token=` directly on server to trigger deploy

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
| GitHub PAT | Fine-grained with **Contents: Read and Write** (stored as `GH_TOKEN`) |

### GitHub Secrets

| Secret | Purpose |
|--------|---------|
| `DEPLOY_TOKEN` | Must match `config('app.deploy_token')` in server `.env` |
| `GH_TOKEN` | Fine-grained PAT with **Contents: Read and Write** |
| `DB_DATABASE` | Production MySQL database name |
| `DB_USERNAME` | Production MySQL username |
| `DB_PASSWORD` | Production MySQL password |
| `ADMIN_EMAIL` | Email for initial admin user (created on first deploy) |
| `ADMIN_PASSWORD` | Password for initial admin user |
| `ADMIN_NAME` | Display name / username for initial admin user |

### Deploy Endpoints

- `GET /deploy/check?token=...` — Main deploy entry point
- `GET /deploy/cache-clear?token=...` — Emergency reset: migrate:fresh + cache clear

### Admin Bootstrap

On first deploy, `app:create-admin` runs after `migrate`. It reads `X-Admin-Email`, `X-Admin-Password`, and `X-Admin-Name` headers from the deploy webhook (set from GitHub secrets). On subsequent deploys, the existing admin is skipped gracefully.

See also: `app/Console/Commands/CreateAdmin.php`, `DEPLOY.md`

### After Schema/Seeder Changes

```bash
php artisan migrate
php artisan db:seed --class=RolesAndPermissionsSeeder
php artisan modules:sync-permissions
```

Then run typecheck + build + phpunit and deliver the results. Do not wait for user approval to deploy — automate the full pipeline.

For full details, see `DEPLOY.md`.

## Test Deploy

- `2026-06-21`: End-to-end deploy test passed — pipeline builds, zips, creates Release, and auto-deploys successfully.
- `2026-07-01`: Full module audit completed — all 14 issues fixed (order column migration, FormRequest authorize(), controller authorize(), missing migration dirs, booking create/edit routes, updateStatus route, city table validation, CommandSearch modules, groupLabel in sidebar, policies for all modules, ECommerce route standardization)

## Windows Notes

- Case-insensitive filesystem: `forceConsistentCasingInFileNames: false` in tsconfig.json
- `Components/` and `components/` are the same directory — use `@/Components/...` consistently
- Module page glob patterns in `app.tsx` use relative paths (Windows-compatible)