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

### CI/CD Pipeline (GitHub Actions → Release + Web Endpoint)

Deploys automatically on every push to `main`:

**Workflow 1 — `deploy.yml`** (self-hosted runner, runs on push):
1. `composer install --no-dev` + `npm ci` + `npm run typecheck` + `npm run build`
2. Generates `.env` from secrets, copies `scripts/deploy-cron.php` → `deploy.php`
3. Packages `deploy.zip` (excludes `bootstrap/cache/`)
4. Creates GitHub Release with `deploy.zip` attached

**Workflow 2 — `cron-deploy.yml`** (GitHub-hosted runner, every 5 min):
5. Calls `GET https://site.com/deploy/check?token=${{ secrets.DEPLOY_TOKEN }}`

**On the server — `DeployController::check()`**:
6. Verifies token, checks `.deploy-lock`
7. Fetches latest release from GitHub API via `GITHUB_TOKEN` in `.env`
8. Downloads `deploy.zip`, extracts over project root
9. Restores `.env` from backup
10. Runs `migrate`, `config:cache`, `route:cache`, `view:cache`, `storage:link`

### Prerequisites

| Requirement | Value |
|------------|-------|
| PHP | 8.3+ (8.4 recommended) |
| MySQL | 8.0+ |
| SSH | Not required |
| Composer | Not required (vendor/ in ZIP) |
| Cron | Not required — GitHub-hosted runner handles scheduling |
| GitHub PAT | Fine-grained with **Contents: Read** |

### GitHub Secrets

| Secret | Purpose |
|--------|---------|
| `DEPLOY_TOKEN` | Must match `config('app.deploy_token')` in server `.env` — protects all `/deploy/` endpoints |
| `GH_TOKEN` | Fine-grained PAT with **Contents: Read** — used by server to download releases |

### Deploy Endpoints (manual fallback)

- `GET /deploy/check?token=...` — Main deploy entry point (called by cron-deploy.yml)
- `GET /deploy/cache-clear?token=...` — Emergency reset: migrate:fresh + cache clear

### After Schema/Seeder Changes

```bash
php artisan migrate
php artisan db:seed --class=RolesAndPermissionsSeeder
php artisan modules:sync-permissions
```

Then run typecheck + build + phpunit and deliver the results. Do not wait for user approval to deploy — automate the full pipeline.

### Initial Server Setup (first deploy)

1. **Secrets**: Add `DEPLOY_TOKEN` and `GH_TOKEN` to GitHub repo secrets
2. **Push**: `git push origin main` → workflow creates a Release with `deploy.zip`
3. **Upload**: Download `deploy.zip` from the Release → upload to cPanel → extract at `public_html/`
4. **Verify**: Future pushes auto-deploy within ~10 min (build time + 5 min cron interval)

For full details, see `DEPLOY.md`.

## Windows Notes

- Case-insensitive filesystem: `forceConsistentCasingInFileNames: false` in tsconfig.json
- `Components/` and `components/` are the same directory — use `@/Components/...` consistently
