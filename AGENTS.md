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

### CI/CD Pipeline (GitHub Actions → FTP)

Deploys automatically on every push to `main` via `.github/workflows/deploy.yml`:

1. **Build**: `composer install --no-dev` + `npm ci` + `npm run build` (in CI)
2. **FTP Sync**: Uploads built assets + app files (NOT `vendor/` tests/docs/node_modules/storage)
3. **Post-deploy**: HTTP calls to `/deploy/update-env` and `/deploy/cache-clear` (token-protected)

### Server Requirements

| Requirement | Value |
|------------|-------|
| PHP | 8.3+ (8.4 recommended) |
| MySQL | 8.0+ |
| Composer | Not required (vendor/ uploaded with FTP) |
| SSH | Not required |

### Deploy Endpoints (on production server)

- `POST /deploy/update-env` — Writes `.env` from CI secrets (backs up old .env first)
- `GET /deploy/cache-clear` — Runs migrate, config:cache, route:cache, view:cache

Both require `?token={DEPLOY_TOKEN}` matching `config('app.deploy_token')`.

### After Schema/Seeder Changes

```bash
php artisan migrate
php artisan db:seed --class=RolesAndPermissionsSeeder
php artisan modules:sync-permissions
```

Then run typecheck + build + phpunit and deliver the results. Do not wait for user approval to deploy — automate the full pipeline.

### Initial Server Setup (first deploy)

1. **PHP version**: Ensure host runs PHP 8.3+. Laravel 13 requires it.
2. **Storage permissions**: `storage/` and `bootstrap/cache/` must be writable by the web server.
3. **Upload everything**: The first FTP sync is the heaviest — after that only changed files transfer.
4. **Verify deploy**: After the workflow completes, hit `https://laravel.tunispromo.tn/deploy/cache-clear?token=...` manually if the workflow step fails due to old route cache.

## Windows Notes

- Case-insensitive filesystem: `forceConsistentCasingInFileNames: false` in tsconfig.json
- `Components/` and `components/` are the same directory — use `@/Components/...` consistently
