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

## Package Local Development (platform / shop-module)

This app is a thin consumer of two private Composer packages (plus blog via `Nreljed\Blog`):

| Package | Source-of-truth repo | Local checkout |
|---------|---------------------|----------------|
| `nreljed/platform` | `nreljed/platform` (repo) | `C:\Users\nrelj\Local Sites\platform` |
| `nreljed/blog` | `nreljed/blog` (repo) | `C:\Users\nrelj\Local Sites\blog` |
| `nreljed/shop-module` | `nreljed/shop-module` (repo) | `C:\Users\nrelj\Local Sites\shop-module` |

**Where to edit what:**
- **Package feature code** (models, controllers, services, routes, migrations, policies, package frontend pages) → edit in the **package repo**, NOT `app/`. Changes release via a semver tag in the package repo, then the app's `core-update.yml` / `shop-update.yml` picks them up automatically.
- **Cross-package orchestration / host glue** (`GenerateDemoData`, `config/features.php` provider list, public pages, the `/`→storefront route override) → edit in `app/`, deploy via the normal `deploy.yml` pipeline.

**Never edit `vendor/`.** `vendor/nreljed/*` is a Composer-managed copy/checkout; edits there are lost on the next install and bypass peer review. The current dev loop keeps `vendor/nreljed/platform` in sync with the local `C:\Users\nrelj\Local Sites\platform` checkout — the vendor copy must mirror the local repo.

### The "edit a package and see it in the app" loop

1. Open the package repo (e.g. `C:\Users\nrelj\Local Sites\platform`).
2. Make your change in a feature branch there.
3. Sync it into this app's `vendor/nreljed/<pkg>` (copy the changed files, or `composer update nreljed/<pkg>` if you've tagged a version).
4. Run the app + `php artisan test` against it.
5. When green, commit + push the package branch, tag `vX.Y.Z` → the app auto-updates via the update workflows.

### Optional: Composer path repos (faster iterate loop)

For instant edits without the copy step, point Composer at the local clones instead of VCS:

```json
"repositories": [
    { "type": "path", "url": "../platform" },   // local-only; NOT for CI
    { "type": "path", "url": "../shop-module" },
    { "type": "vcs", "url": "https://github.com/nreljed/platform.git" },
    { "type": "vcs", "url": "https://github.com/nreljed/shop-module.git" }
]
```

Then `composer update nreljed/platform nreljed/shop-module`. This symlinks `vendor/nreljed/*` → the local repos so every save is live. **Do not commit path repos into the app's `composer.json`** — CI/production must use VCS only. Keep path repos as a local, uncommitted modification, or manage them out-of-band. Both local packages declare an explicit `version` field so they satisfy the host's `^0.1` constraint.

## Module Commands

```bash
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
