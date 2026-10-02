# Contributing

## Commit Convention

This project uses **Conventional Commits** to automate versioning and changelog generation.

### Format

```
type(scope): description

BREAKING CHANGE: description
```

### Types

| Type | Description | Version Bump |
|------|-------------|-------------|
| `feat` | A new feature | minor |
| `fix` | A bug fix | patch |
| `docs` | Documentation only | patch |
| `refactor` | Code change that neither fixes a bug nor adds a feature | patch |
| `perf` | A code change that improves performance | patch |
| `style` | Changes that do not affect the meaning of the code | patch |
| `test` | Adding missing or correcting existing tests | patch |
| `chore` | Changes to the build process or auxiliary tools | patch |

### Scope (optional but recommended)

- `api` — backend endpoints
- `ui` — frontend components
- `auth` — authentication
- `deploy` — deployment pipeline
- `deps` / `deps-dev` — dependency updates
- `ci` — CI configuration
- `db` — database migrations / seeders

### Examples

```
feat(ui): add billing page with stripe integration
fix(api): handle null response from payment gateway
chore(deps): upgrade laravel/framework to 13.10
docs: update deployment guide
refactor(auth): extract password validation to service class
feat(db): add subscription plans table

BREAKING CHANGE: removed legacy v1 API endpoints
```

### Breaking Changes

Add `BREAKING CHANGE:` footer OR append `!` after type/scope:

```
feat(api)!: migrate to v2 webhook format
```

This triggers a **major** version bump.

## Development Setup

```bash
composer setup
npm run dev
```

## Before Pushing

```bash
php artisan test
npm run typecheck
php artisan pint --test
```
