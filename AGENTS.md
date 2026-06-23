# Cost Breaker 360

A SaaS application built with Laravel + Inertia.js + React 19.

## Tech Stack

- **Backend**: Laravel 13 (PHP 8.4)
- **Frontend**: React 19.2 + Inertia.js 3.4
- **Build Tool**: Vite 8, TypeScript 6
- **Styling**: Tailwind CSS v4 + tailwindcss-animate
- **UI**: shadcn/ui (new-york, neutral base) + Radix UI + Lucide icons
- **Forms**: Inertia `useForm` (server-side validation)
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
app/Http/Controllers/     # Laravel controllers (pure, no WordPress)
app/Http/Middleware/       # HandleInertiaRequests shares auth + notifications
app/Models/               # Eloquent models (User, Page, Setting)
app/Services/             # Business logic
database/migrations/      # Standard Laravel migrations
resources/js/
  Components/             # React components
    ui/                   # shadcn/ui components
    app-sidebar.tsx       # Sidebar with real routes + useAuth
    nav-main.tsx          # Collapsible nav
    nav-user.tsx          # User dropdown with theme toggle
    command-search.tsx    # Ctrl+K command palette (cmdk)
    ThemeSwitcher.tsx     # Light/dark toggle
    NotificationBell.tsx  # 20s polling via router.reload
  hooks/
    use-auth.ts           # user, isAdmin, getUserInitials
  Layouts/
    AuthenticatedLayout.tsx  # SidebarProvider + header + CommandSearch
    AdminLayout.tsx          # Same + admin-only sections
    GuestLayout.tsx          # Centered card on bg-muted
    PublicLayout.tsx         # Vitrine pages
  Pages/                  # Inertia page components
    Dashboard/            # Index (stats), Notifications, Stats
    Account/Edit.tsx      # Name, email, password, delete
    Admin/                # Settings (tabs), SystemHealth
    Auth/                 # Login, Register (shadcn Card + Field + Input)
    Welcome.tsx           # Landing page
routes/web.php            # All route definitions
vite.config.js            # Vite 8 + Laravel plugin + code splitting
```

## Routes

| Prefix | Middleware | Pages |
|--------|-----------|-------|
| `/` | none (guest) | Welcome, About, Services, Contact |
| `/dashboard` | auth+verified | Dashboard, Stats, Notifications, Account |
| `/admin` | auth+verified+admin | Users, Settings, System Health |
| `/profile` | auth+verified | Edit profile (Breeze) |

## Key Patterns

- **Shared props**: `HandleInertiaRequests` shares `auth.user`, `notifications` (latest 5), `flash`
- **Partial reloads**: `router.reload({ only: ['notifications'] })` for polling
- **Admin check**: `useAuth().isAdmin` + `admin` middleware + `User::isAdmin()`
- **Component imports**: Use `@/Components/...` alias (NOT `@/components/...`) — Windows case-insensitive FS workaround
- **No ShadCN `asChild`**: Avoid `Slot` pattern when possible; use plain `<Link>` instead

## Development Commands

```bash
npm run dev        # Vite dev server with HMR
npm run build      # tsc + vite build (production)
php artisan migrate        # Run migrations
php artisan test           # PHPUnit
npm run typecheck          # tsc --noEmit
```

## Deployment

### CI/CD Pipeline (GitHub Actions → Release + Server Deploy)

Deploys automatically on every push to `main`:

**Workflow 1 — `deploy.yml`** (GitHub-hosted `windows-latest` runner):
1. Checkout with full depth + tags
2. Setup PHP 8.4 (extensions: fileinfo, gd, mbstring, pdo_mysql, pdo_sqlite, intl, bcmath, zip) + Node 22
3. git-cliff: parse commits → bump semver → generate CHANGELOG.md + release-notes.md
4. Generate build .env (SQLite)
5. `composer install --prefer-dist --no-scripts` (always runs; cache speeds up restore but dev deps must be present for tests)
6. `php artisan package:discover + migrate --force`
7. [cached] `npm install` → `npm run typecheck` → `npm run build` (generates manifest.json)
8. `php artisan test` + `vendor/bin/pint` (auto-fix)
9. `composer install --no-dev --optimize-autoloader` (strip dev deps before packaging)
10. Overwrite .env (production MySQL config)
11. Package `deploy.zip` (excludes `bootstrap/cache/`)
12. Create GitHub Release with semver tag `vX.Y.Z` + changelog body + deploy.zip attachment
13. Curl `/deploy/check?token=` directly on server to trigger deploy

**On the server — `DeployController::check()`**:
- Verifies token, checks `.deploy-lock` (10 min TTL)
- Fetches latest release from GitHub API via `GITHUB_TOKEN`
- Downloads `deploy.zip`, extracts over project root
- Restores `.env` from backup (never overwritten)
- Runs: `package:discover`, `migrate`, `config:cache`, `route:cache`, `view:cache`, `storage:link`

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
| `DEPLOY_TOKEN` | Must match `config('app.deploy_token')` in server `.env` — protects all `/deploy/` endpoints |
| `GH_TOKEN` | Fine-grained PAT with **Contents: Read and Write** — creates releases + downloads them |
| `DB_DATABASE` | Production MySQL database name |
| `DB_USERNAME` | Production MySQL username |
| `DB_PASSWORD` | Production MySQL password |

### Deploy Endpoints

- `GET /deploy/check?token=...` — Main deploy entry point (auto-called by deploy.yml; can also be triggered manually via the **Manual Deploy Check** workflow)
- `GET /deploy/cache-clear?token=...` — Emergency reset: migrate:fresh + cache clear

### After Schema/Seeder Changes

```bash
php artisan migrate
php artisan db:seed --class=RolesAndPermissionsSeeder
php artisan modules:sync-permissions
```

Then run typecheck + build + phpunit and deliver the results. Do not wait for user approval to deploy — automate the full pipeline.

For full details, see `DEPLOY.md`.

## Windows Notes

- Case-insensitive filesystem: `forceConsistentCasingInFileNames: false` in tsconfig.json
- `Components/` and `components/` are the same directory — use `@/Components/...` consistently

## Test Deploy

- `2026-06-21`: End-to-end deploy test passed — pipeline builds, zips, creates Release, and auto-deploys successfully. Fixed `package:discover` ordering to prevent BreezeServiceProvider errors.
