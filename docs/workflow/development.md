# Development Workflow

## Daily Commands

```bash
npm run build            # Production build (tsc + vite)
npm run dev              # Vite dev server with HMR
npm run typecheck        # TypeScript check
npm run lint             # ESLint
vendor/bin/pint          # Laravel Pint (PSR-12)
php artisan test         # PHPUnit
```

## Adding Features

See [Features](../core/features.md) for the full guide.

## Code Conventions

- **4 spaces** indent (PHP), **2 spaces** (TSX)
- Use `cn()` from `@/lib/utils` for class merging
- All routes via Ziggy's `route()` helper — no hardcoded URLs
- Inertia `useForm` for all form state
- `useAuth()` hook from `@/hooks/use-auth` for auth state
- `asChild` / `Slot` pattern for component composition
- Component imports use `@/Components/...` alias (capital C — Windows FS workaround)

## After Schema/Seeder Changes

```bash
php artisan migrate
php artisan db:seed --class=RolesAndPermissionsSeeder
```

Then run typecheck + build + phpunit and deliver results. Do not wait for user approval to deploy.

## Git Branching

- `main` — production-ready
- `beta` — active development
