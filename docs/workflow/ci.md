# CI Pipeline

## GitHub Actions — Validation Workflow

`.github/workflows/ci-validation.yml` runs on every push and PR:

```yaml
- name: TypeScript Check
  run: npm run typecheck

- name: Build
  run: npm run build

- name: PHP CS Fixer
  run: vendor/bin/pint --test

- name: Tests
  run: php artisan test
```

## GitHub Actions — Deploy Workflow

`.github/workflows/deploy.yml` runs on push to `main`:

1. Checkout with full depth + tags
2. Setup PHP 8.4 + Node 22
3. git-cliff: parse commits → bump semver → CHANGELOG.md + release-notes.md
4. Generate build .env (SQLite)
5. `composer install --prefer-dist --no-scripts`
6. `php artisan package:discover + migrate --force`
7. `npm install` → `npm run typecheck` → `npm run build`
8. `php artisan test` + `vendor/bin/pint` (auto-fix)
9. `composer install --no-dev --optimize-autoloader`
10. Overwrite .env with production MySQL config
11. Package `deploy.zip` (excludes `bootstrap/cache/`)
12. Create GitHub Release with semver tag `vX.Y.Z`
13. Curl `/deploy/check?token=` on server

## Cron Deploy

`.github/workflows/cron-deploy.yml` — periodic deploy via cron trigger.
