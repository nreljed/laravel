# Setup Guide

## Prerequisites

- PHP 8.3+ (8.4 recommended)
- Composer 2.x
- Node.js 22+
- npm 10+
- SQLite (local) or MySQL 8.0+ (production)

## Local Setup

```bash
# 1. Clone and install dependencies
composer install
npm install

# 2. Environment
cp .env.example .env
php artisan key:generate

# 3. Database (SQLite)
touch database/database.sqlite
php artisan migrate

# 4. Seed roles, permissions, and demo data
php artisan db:seed --class=RolesAndPermissionsSeeder
php artisan modules:sync-permissions

# 5. Create admin user
php artisan app:create-admin

# 6. Start development servers
npm run dev
php artisan serve
```

## Running Tests

```bash
php artisan test                       # All tests
php artisan test --filter=Booking       # Module-specific
php artisan test --stop-on-failure      # Stop on first failure
```

## Type Checking & Linting

```bash
npm run typecheck                      # TypeScript check only
npm run build                          # Full build (typecheck + vite)
vendor/bin/pint                        # PHP CS fixer
```

## After Schema Changes

```bash
php artisan migrate
php artisan db:seed --class=RolesAndPermissionsSeeder
php artisan modules:sync-permissions
npm run typecheck
npm run build
php artisan test
```
