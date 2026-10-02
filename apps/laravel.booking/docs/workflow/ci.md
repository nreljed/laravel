# CI Pipeline

## Local Testing with `act`

[act](https://github.com/nektos/act) runs GitHub Actions locally using Docker. Installed globally via `winget`, version 0.2.89.

### Prerequisites

- Docker Desktop running (check: `docker info` shows Server Version)
- `.secrets` file in project root (contains `PACKAGE_TOKEN`)
- `.actrc` in `C:\Users\nrelj\.actrc` (sets ubuntu:22.04 image, `--reuse`, `--bind`)

### Running from Any Terminal

Works from VS Code terminal, PowerShell, Git Bash, or PhpStorm — just navigate to the project root first:

```powershell
cd "C:\Users\nrelj\Local Sites\booking-laravel\app"
```

### Commands

```powershell
# List all detected workflows
act -l

# Run PR validation (quickest test — ~2-3 min)
act pull_request -W ".github/workflows/pr.yml" --secret-file .secrets

# Run develop CI
act push -W ".github/workflows/develop-ci.yml" --secret-file .secrets

# Run full production deploy pipeline (all 4 stages)
act push -W ".github/workflows/deploy.yml" --secret-file .secrets

# Dry-run (validate without executing, ~30 sec)
act pull_request -W ".github/workflows/pr.yml" --secret-file .secrets -n

# Verbose output (debug step failures)
act push -W ".github/workflows/develop-ci.yml" --secret-file .secrets -v
```

### Detected Workflows

| Workflow | File | Trigger | Notes |
|----------|------|---------|-------|
| CI Validation | `ci.yml` | `workflow_call` | Called by other workflows |
| Develop CI | `develop-ci.yml` | `push` | Validates on push to develop |
| Pull Request Checks | `pr.yml` | `pull_request` | PR validation |
| Manual Deploy Check | `cron-deploy.yml` | `workflow_dispatch` | Manual trigger |
| Build Deploy Package | `deploy-package.yml` | `workflow_call` | Called by deploy.yml |
| Deploy to Production | `deploy.yml` | `push` | 4-stage pipeline (validate → package → release → push-deploy) |

> **Note:** `workflow_call` workflows (`ci.yml`, `deploy-package.yml`) can't be triggered directly with `act`. Use `pr.yml`, `develop-ci.yml`, or `deploy.yml` instead.

### Performance

| Scenario | Time | Notes |
|----------|------|-------|
| First run | 5-10 min | Downloads ubuntu:22.04, installs PHP/Node |
| Normal run | 2-3 min | Uses cached containers via `--reuse` |
| Dry-run | ~30 sec | Validation only, no execution |

---

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
