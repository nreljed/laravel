# Setup

## Prerequisites

- PHP 8.3+
- Composer
- Node.js 22+
- SQLite (local) or MySQL (prod)

## Install

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan app:create-admin
npm run build
```

## Dev Server

```bash
php artisan serve        # Laravel
npm run dev              # Vite HMR
```

## LocalWP

This project runs via LocalWP (Local by Flywheel).

- **URL**: `https://laravel.local/`
- **Stack**: nginx, PHP 8.3, MySQL
- **Build**: Production build served directly (`npm run build`)

### Important

- `public/hot` should **NOT** exist — it causes `ERR_CONNECTION_REFUSED` on Vite dev server
- Always run `npm run build` after code changes

## Environment Variables

| Variable                   | Purpose                           |
| -------------------------- | --------------------------------- |
| `APP_URL`                  | `https://laravel.local/`          |
| `DB_CONNECTION`            | `sqlite` (local) / `mysql` (prod) |
| `MAIL_MAILER`              | `log` (local)                     |
| `SANCTUM_STATEFUL_DOMAINS` | `laravel.local`                   |
