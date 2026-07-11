# Cost Breaker 360

A SaaS dashboard built with Laravel 13 + Inertia.js 3 + React 19.

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
- **Component imports**: Use `@/Components/...` alias (NOT `@/components/...`) — Windows case-insensitive FS workaround
- **No ShadCN `asChild`**: Avoid `Slot` pattern when possible; use plain `<Link>` instead

## Architecture

```
app/
  Http/
    Controllers/
      Admin/                  # User, Role, Permission, AuditLog, Session
      Api/                    # NotificationPollController (JSON endpoints)
      Auth/                   # Login, Register, Password, Email verification
      AccountController.php   # Email, password, notification prefs, delete
      AdminController.php     # Settings, System Health
      AvatarController.php
      DashboardController.php # Index (greeting), Stats, Notifications
      DeployController.php    # check, cacheClear, updateEnv
      EmailSettingsController.php
      NotificationTestController.php
      ProfileController.php   # Edit, update, destroy (delegates to AccountService)
      PublicController.php    # About, Services, Contact
      SettingsController.php  # Edit, update, upload/delete logo
    Middleware/
      HandleInertiaRequests.php  # Shares auth, notifications, flash, featureNavItems, userCapabilities
      AdminMiddleware.php        # 403 if not admin
      CheckPermission.php       # user.permission:slug middleware
      CheckRole.php             # user.role:slug middleware
      ContentSecurityPolicy.php
      ForceInertiaCacheHeaders.php
    Requests/
      Admin/                  # StoreUserRequest, UpdateUserRequest, StoreRoleRequest, etc.
  Models/
    User.php              # getDisplayNameOptions(), isAdmin(), role/permission checks, hasPermission()
  Policies/
    PostPolicy.php        # viewAny/view/create/update/delete (owner or admin)
  Services/
    AccountService.php    # deleteAccount()
    SettingsService.php   # DB-driven app config
    FeatureRegistry.php   # Central feature registry (delegates to BlogServiceProvider)
    NotificationService.php
    DashboardService.php  # Stats + Analytics cache (5-min TTL)
    FormattingService.php # Price/surface formatting
  Blog/                   # Blog feature (was Posts module)
    BlogServiceProvider.php
    Controllers/          # PostController, PostCategoryController, PostTagController
    Models/               # Post, PostCategory, PostTag, ContentBlock
    Routes/web.php
    Resources/js/Pages/   # Index, Form, Dashboard, Categories, Tags
    database/migrations/
  Console/Commands/
    CreateAdmin.php
    CreateRole.php
    ListPermissions.php
    PublishScheduledContent.php
    PurgeNotifications.php
database/
  migrations/
  seeders/                # RolesAndPermissionsSeeder, SettingsSeeder
resources/js/
  Components/
    ui/                   # shadcn/ui components + PublishSection, DateTimePicker, Field
    shared/               # FeaturedImageSelector, TermsSelector, GalleryUploader
    dashboard/            # KpiCard, Sparkline, RevenueChart, RecentOrdersTable, TopProductsTable
    app-sidebar.tsx       # Dynamic sidebar from featureNavItems + groupLabel
    nav-main.tsx          # Collapsible nav
    nav-user.tsx          # User dropdown: theme toggle, logout
    command-search.tsx    # Ctrl+K palette (includes feature items dynamically)
    ThemeSwitcher.tsx     # Light/dark toggle
    NotificationBell.tsx  # 20s polling
    notifications/
      NotificationItem.tsx
      NotificationDetailDialog.tsx
  hooks/
    use-auth.ts           # user, isAdmin, getUserInitials
    use-notifications.ts  # Axios-based polling (not Inertia router)
  Layouts/
    AuthenticatedLayout.tsx
    AdminLayout.tsx
    GuestLayout.tsx
    PublicLayout.tsx
    StoreLayout.tsx
  Pages/
    Dashboard/            # Index (greeting), Notifications, Stats
    Account/Edit.tsx      # Name, email, password, delete, address
    Admin/                # Settings (tabs), System Health (feature debug card)
    Auth/                 # Login, Register
    Welcome.tsx           # Landing page
  lib/
    icons.ts              # featureIcons map (string → Lucide component)
    utils.ts              # cn() helper
routes/
  web.php                 # Dashboard, admin, public, deploy routes
  auth.php                # Breeze auth routes + profile + switch-mode
```

## Feature Architecture

The dashboard uses a **feature-based architecture** where each domain is self-contained.

### How It Works

1. **`FeatureRegistry`** (`app/Services/FeatureRegistry.php`) — singleton bound in `AppServiceProvider`. Delegates to individual feature service providers.
2. **Feature Service Providers** (e.g., `BlogServiceProvider`) — register routes, permissions, and nav items.
3. **`HandleInertiaRequests`** calls `FeatureRegistry::getAllNavItems()` and `getAllSharedData()` to share props with the frontend.

### Current Features

| Feature | Slug    | Permissions                                                | Frontend Pages                           |
| ------- | ------- | ---------------------------------------------------------- | ---------------------------------------- |
| Blog    | `posts` | `posts.view`, `posts.create`, `posts.edit`, `posts.delete` | Index, Form, Dashboard, Categories, Tags |

### Feature File Structure

```
app/Blog/
  BlogServiceProvider.php     # Registers routes, permissions, nav items
  Controllers/
    PostController.php        # CRUD + search/filter/pagination
    PostCategoryController.php
    PostTagController.php
  Models/
    Post.php                  # Eloquent model with relations
    PostCategory.php
    PostTag.php
    ContentBlock.php
  Routes/web.php
  Resources/js/
    Pages/                    # Inertia page components
      Index.tsx               # Posts list with search, filters, pagination
      Form.tsx                # Create/edit with tabs (Content / Blocks / SEO)
      Dashboard.tsx           # Blog-specific dashboard
      Categories/Index.tsx
      Tags/Index.tsx
    components/
      ContentBlockEditor.tsx  # Reusable block editor
  database/migrations/
```

### Adding a New Feature

1. Create `app/Features/{Name}/` (or `app/{Name}/`) with Controllers/, Models/, Routes/, Resources/js/Pages/
2. Create `{Name}ServiceProvider.php` — implement `boot()` to register routes, permissions, nav items
3. Register in `bootstrap/providers.php`
4. Add permission slugs to `database/seeders/RolesAndPermissionsSeeder.php`
5. Register the service provider in `FeatureRegistry`

## Routes

| Prefix                   | Middleware                          | Pages                                                          |
| ------------------------ | ----------------------------------- | -------------------------------------------------------------- |
| `/`                      | none (guest)                        | Welcome, About, Services, Contact                              |
| `/dashboard`             | auth+verified                       | Dashboard, Stats, Notifications, Account, Profile              |
| `/dashboard/admin`       | auth+verified+admin+throttle:60,1   | Users, Roles, Permissions, Settings, System Health, Audit Logs |
| `/dashboard/posts`       | auth+verified+permission:posts.view | Posts CRUD, Dashboard, Categories, Tags                        |
| `/deploy/...`            | token                               | Deploy endpoints                                               |
| `/api/notifications/...` | auth                                | Notification JSON endpoints                                    |

## Key Patterns

- **Shared props**: `HandleInertiaRequests` shares `auth.user`, `latestNotifications`, `notificationUnreadCount`, `flash`, `featureNavItems` (with `groupLabel`), `userCapabilities`, `emailSettings`, `brand`, `impersonating`
- **Partial reloads**: `router.reload({ only: ['notifications'] })` for polling (replaced by axios)
- **Admin check**: `useAuth().isAdmin` + `admin` middleware + `User::isAdmin()`
- **Feature authorization**: Route group uses `permission:feature.view` middleware. Individual create/edit/delete actions are authorized via Policy + `$this->authorize()` in controllers or FormRequest `authorize()`
- **Feature policies**: Each feature model has a Policy in `app/Policies/` registered in `AppServiceProvider`
- **Rate limiting**:
  - Login: application-level `RateLimiter` — `{username}|{ip}` key, 5 attempts, 60s decay
  - Other auth: `throttle:5,60` — IP-based, returns 429
  - Admin routes: `throttle:60,1` — 60 requests/min
- **Validation**: Optional Zod + `$request->validate()` on all mutation endpoints
- **Dashboard homepage**: Greeting only ("Welcome back, {name}!"), no analytics/KPIs/stats
- **Blog dashboard**: Posts-specific dashboard at `/dashboard/posts/dashboard`

## Dev Environment

- **Not using `npm run dev`** — the app runs via LocalWP (Local by Flywheel), which serves the production build
- `public/hot` should NOT exist — if present, delete it; Laravel's Vite helper reads it and tries to connect to the Vite dev server, causing `ERR_CONNECTION_REFUSED` on `[::1]:5173`
- Always run `npm run build` after code changes to update `public/build/`
- CSP middleware (`ContentSecurityPolicy.php`) only applies in production — never tweak Vite config or hot file for CSP reasons; skip the middleware in non-production instead
- Site runs at `https://laravel.local/`
- Admin credentials: username `nstudio`, email `nstudio.webdesign@gmail.com`, password `azerty123/*`

## Development Commands

```bash
npm run dev              # Vite dev server with HMR
npm run build            # tsc + vite build (production)
php artisan migrate      # Run migrations
php artisan test         # PHPUnit
npm run typecheck        # tsc --noEmit
vendor/bin/pint          # Laravel Pint (PSR-12)
php artisan permissions:list          # List all permissions
php artisan app:create-admin                           # Bootstrap or update the admin user
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

| Requirement | Value                                                                 |
| ----------- | --------------------------------------------------------------------- |
| PHP         | 8.3+ (8.4 recommended)                                                |
| MySQL       | 8.0+                                                                  |
| SSH         | Not required                                                          |
| GitHub PAT  | Fine-grained with **Contents: Read and Write** (stored as `GH_TOKEN`) |

### GitHub Secrets

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
```

Then run typecheck + build + phpunit and deliver the results. Do not wait for user approval to deploy — automate the full pipeline.

For full details, see `DEPLOY.md`.

## Git Branching

- `main` — production-ready, clean/unmodified
- `beta` — active development branch with refactor work

## Recent Changes

### 2026-07-11 — Module System Removal & Feature Architecture

**What was done:**

- Deleted `packages/dashboard-core/` entirely
- Removed all module infrastructure: ModuleRegistry, ModuleLoader, ModuleInterface, HasRoutes, BookableRegistry, ModuleServiceProvider, Module model, ModuleController
- Created `FeatureRegistry` singleton — delegates to individual feature service providers
- Renamed Posts → Blog (`app/Blog/`) with new `BlogServiceProvider`
- Renamed all `module*` → `feature*` across PHP and TSX (shared props, sidebar, icons, nav, seeders)
- Removed dead `modules` table via new migration
- Cleaned up all stale `modules:sync-permissions` references from DeployController, deploy-cron, docs
- Archived historical docs (phases-6-14-plan.md, modules/, PLAN.md) to `docs/archive/`
- Rewrote AGENTS.md as core-only source of truth
- Rewrote all core docs (architecture, routes, services, sidebar, etc.)

**Files deleted:**

```
packages/dashboard-core/           # Entire package
app/Modules/                       # Entire directory
app/Providers/ModuleServiceProvider.php
app/Console/Commands/DebugModules.php
app/Console/Commands/SyncModulePermissions.php
app/Http/Controllers/ModuleController.php
app/Http/Requests/Module/ToggleModuleRequest.php
config/modules.php
database/migrations/*_modules_table  # Replaced by drop migration
resources/js/Components/ModuleDebugPanel.tsx
```

**Files created:**

```
app/Services/FeatureRegistry.php
app/Blog/BlogServiceProvider.php
database/migrations/*_drop_modules_table.php
```

**Tests:** 85/85 passing, typecheck clean, production build succeeds, pint clean.

### 2026-07-05 — Dashboard Analytics Overhaul

- `DashboardService::getAnalytics()` — cached (5-min TTL) comprehensive analytics
- 5 KPI cards, Revenue Chart, Recent Orders, Top Products, Order Status Distribution
- Dashboard page simplified to greeting only in subsequent refactor

### 2026-07-04 — Blog Post Tags

- PostTagController with CRUD
- Tags admin page (Posts/Tags/Index.tsx)
- Posts Form.tsx integrated with tag creation

## Admin Credentials

| Field    | Value                         |
| -------- | ----------------------------- |
| Username | `nstudio`                     |
| Email    | `nstudio.webdesign@gmail.com` |
| Password | `azerty123/*`                 |
