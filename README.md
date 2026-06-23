# Cost Breaker 360

A SaaS application for cost management built with Laravel + Inertia.js + React 19.

## Tech Stack

- **Backend**: Laravel 13 (PHP 8.4)
- **Frontend**: React 19.2 + Inertia.js 3.4
- **Build Tool**: Vite 8, TypeScript 6
- **Styling**: Tailwind CSS v4 + tailwindcss-animate
- **UI**: shadcn/ui (new-york, neutral base) + Radix UI + Lucide icons
- **Forms**: Inertia `useForm` (server-side validation)
- **Auth**: Laravel Breeze (session-based), Sanctum
- **Database**: SQLite (local), MySQL (production)
- **Notifications**: Laravel Notifications (database-only, no broadcast)
- **Icons**: Lucide React

## Requirements

- PHP 8.3+ (8.4 recommended)
- Composer 2.x
- Node.js 22+
- MySQL 8.0+ (production) or SQLite (local)

## Setup

```bash
# Install PHP dependencies
composer install

# Install frontend dependencies
npm install

# Environment
cp .env.example .env
php artisan key:generate

# Database
php artisan migrate
php artisan db:seed --class=RolesAndPermissionsSeeder
php artisan modules:sync-permissions

# Storage
php artisan storage:link

# Start dev servers
npm run dev
php artisan serve
```

## Development Commands

```bash
npm run dev        # Vite dev server with HMR
npm run build      # tsc + vite build (production)
php artisan migrate          # Run migrations
php artisan test             # PHPUnit
npm run typecheck            # tsc --noEmit
```

## Architecture

```
app/Http/Controllers/     # Laravel controllers
app/Http/Middleware/       # HandleInertiaRequests shares auth + notifications
app/Models/               # Eloquent models (User, Role, Permission, Setting)
app/Services/             # Business logic (SettingsService, NotificationService)
app/Modules/              # Feature modules (Booking, Properties)
resources/js/
  Components/             # React components (shadcn/ui + custom)
  Pages/                  # Inertia page components
  Layouts/                # Authenticated, Admin, Guest, Public layouts
routes/web.php            # All route definitions
database/migrations/      # Laravel migrations
database/seeders/         # Seeders (roles, permissions, settings)
docs/                     # Feature documentation
```

## Key Features

- **Role-based access control**: Admin and CRM user roles with granular permissions
- **Notification system**: Zero-infrastructure polling (10s intervals), database-only delivery
- **Settings management**: DB-driven app configuration (name, logo, URL, registration, email)
- **Module system**: Pluggable feature modules (booking, properties, e-commerce)
- **Email**: SMTP configuration via admin panel with template simulation
- **Deployment**: CI/CD via GitHub Actions with auto-deploy endpoint

## Roles

- **admin** — Full access, created via backend scripts only (not assignable through registration)
- **crm_user** — Default role for registered users, configurable via Settings > Registration

## Routes

| Prefix | Middleware | Purpose |
|--------|-----------|---------|
| `/` | guest | Welcome, About, Services, Contact |
| `/dashboard` | auth+verified | Dashboard, Notifications, Account |
| `/admin` | auth+verified+admin | Users, Settings, System Health |
| `/deploy` | token | Deploy endpoints |

## License

Proprietary — All rights reserved.
