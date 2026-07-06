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
