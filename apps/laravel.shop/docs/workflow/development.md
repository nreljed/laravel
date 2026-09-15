# Development Workflow

## Commands

```bash
npm run dev              # Vite HMR dev server
npm run build            # tsc + vite build (production)
npm run typecheck        # tsc --noEmit
php artisan test         # PHPUnit
vendor/bin/pint          # Laravel Pint (PSR-12)
php artisan migrate      # Run migrations
```

## Package Local Development (workspace)

This app is a **workspace member** of the `laravel` monorepo. All PHP packages
resolve from **path repos** (`../../packages/*`) for local dev; flip to VCS
before production pushes. Frontend resolves via **pnpm workspace symlinks**
(`@nreljed/*`) — not vendor.

| Package               | Composer              | Workspace checkout           |
| --------------------- | --------------------- | ---------------------------- |
| `nreljed/platform`    | `nreljed/platform`    | `../../packages/platform`    |
| `nreljed/blog`        | `nreljed/blog`        | `../../packages/blog`        |
| `nreljed/shop-module` | `nreljed/shop-module` | `../../packages/shop-module` |

**Where to edit what:**

- **Package feature code** (models, controllers, services, routes, migrations,
  policies, package frontend pages) → edit in the **package workspace checkout**
  (`packages/*`), then release via a semver tag in the package repo — this app's
  update workflow picks it up automatically.
- **Host glue** (`GenerateDemoData`, `config/features.php` provider list, public
  pages, the `/`→storefront route override) → edit in `app/`, deploy via the
  workspace deploy pipeline.

**Never edit `vendor/` or `apps/*/packages`** — the workspace checkout (`packages/*`)
is the source of truth for local dev, mirrored into the app via the path repo.
After editing a package, refresh the app's dependency:

```bash
composer update nreljed/platform nreljed/blog nreljed/shop-module
npm run build   # frontend reads the workspace symlink tree directly
```

Package changes release through their own repo (tag `vX.Y.Z`) — see the root
`../../AGENTS.md` dev loop.

## Module Commands

```bash
php artisan modules:sync-permissions  # Sync module permissions to DB
php artisan modules:debug {slug?}     # Debug module registration
php artisan permissions:list          # List all permissions
```

## Git Conventions

### Commit Messages

Follow Conventional Commits:

```
type(scope): description

feat:     New feature
fix:      Bug fix
refactor: Code change (no feature/fix)
docs:     Documentation only
style:    Formatting only
test:     Adding tests
chore:    Build/config changes
```

Scopes: `booking`, `properties`, `shop`, `posts`, `admin`, `auth`, `deploy`, `docs`

### Branch Strategy

- `main` — Production-ready, auto-deploys
- Feature branches from `main`
- PRs squash-merge to `main`

## Code Review Checklist

- [ ] `npm run typecheck` passes
- [ ] `vendor/bin/pint` passes
- [ ] `php artisan test` passes
- [ ] Routes use `route()` helper (no hardcoded URLs)
- [ ] Form data uses Inertia `useForm`
- [ ] Authorization checks (`$this->authorize()`, Policy, FormRequest)
- [ ] No console.log / dd() / debug code
- [ ] TypeScript strict — no `any`
