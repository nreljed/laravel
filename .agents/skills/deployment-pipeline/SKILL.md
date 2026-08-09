---
name: deployment-pipeline
description: "GitHub Actions CI/CD and deployment pipeline for Cost Breaker 360. Activate when working with workflows, deploy.yml, ci.yml, deploy-package.yml, pr.yml; when user mentions deployment, CI/CD, releases, or server setup; or when debugging build failures. Covers the full pipeline from push to production."
license: MIT
metadata:
  author: laravel
---

# Deployment Pipeline

## Architecture

```
push main → deploy.yml (orchestrator)
  ├─ ci.yml (reusable workflow_call)    — ubuntu-latest, full validation
  ├─ deploy-package.yml (reusable)      — windows-latest, builds deploy.zip
  ├─ release                            — creates GitHub Release
  └─ push-deploy                        — uploads deploy.zip to server

push beta → beta-ci.yml
  └─ ci.yml (reusable workflow_call)    — same validation, no deploy

pull_request → pr.yml (branches: main + beta)
  └─ ci.yml (reusable workflow_call)    — identical validation, no secrets needed

manual → database-maintenance.yml (workflow_dispatch)
  └─ command=migrate → curl /deploy/migrate  (confirm='yes')
  └─ command=reset   → curl /deploy/reset    (confirm='RESET')
```

Repo: `nreljed/laravel` · Production site: `https://laravel.tunispromo.tn`

## Workflow Files

### ci.yml (Reusable Validation)

- PHP 8.4 + Node 22
- Composer + npm caches
- SQLite .env + migrate
- Steps: lint → typecheck → format:check → vitest → build → `php artisan test --parallel --compact` → `vendor/bin/pint --test`
- Inputs: `skip-php-tests`, `skip-npm-tests` (boolean, default false)

### deploy-package.yml (Build Package)

- git-cliff version bump
- SQLite build .env
- Full build pipeline
- Strip dev deps → production .env
- Output: `version` string
- Artifact: `deploy.zip`

### deploy.yml (Orchestrator)

- Calls ci.yml → deploy-package.yml → creates GitHub Release → uploads to server
- All jobs run sequentially with `needs:` dependencies

### pr.yml (Pull Request)

- Runs on PRs to `main` and `beta`; `paths-ignore` for `docs/**`, `**.md`, `.gitignore`
- Calls ci.yml — identical validation, no secrets needed

### beta-ci.yml (Beta Validation)

- On push to `beta`: calls ci.yml with `secrets: inherit` — validates but does not deploy

### database-maintenance.yml (Manual Maintenance)

- `workflow_dispatch` with a `command` input (`migrate` | `reset`)
- `migrate` requires `confirm='yes'` and curls `/deploy/migrate` on production
- `reset` requires `confirm='RESET'` and curls `/deploy/reset` (with_demo, purge_media)

## Server Deploy Flow

1. Verify token + lock file (10 min TTL)
2. Fetch latest release from GitHub API
3. Download `deploy.zip`, extract, restore `.env`
4. Run: migrate, create-admin, cache, storage:link

### Deploy Endpoints (token-protected)

| Endpoint              | Used by                                        |
| --------------------- | ---------------------------------------------- |
| `/deploy/upload`      | Primary — direct `deploy.zip` upload           |
| `/deploy/check`       | Fallback — fetch latest GitHub release         |
| `/deploy/cache-clear` | Post-deploy cache clear                        |
| `/deploy/migrate`     | Manual migrations (`database-maintenance.yml`) |

`push-deploy` uploads to `/deploy/upload` first; if the response is not JSON (e.g. Cloudflare HTML), it falls back to `/deploy/check`. The deploy zip includes a copied `deploy.php` (from `scripts/deploy-cron.php`).

## GitHub Secrets

| Secret                                          | Purpose                                 |
| ----------------------------------------------- | --------------------------------------- |
| `DEPLOY_TOKEN`                                  | Must match `config('app.deploy_token')` |
| `GH_TOKEN`                                      | Fine-grained PAT (Contents: Read+Write) |
| `DB_DATABASE` / `DB_USERNAME` / `DB_PASSWORD`   | Production MySQL                        |
| `ADMIN_EMAIL` / `ADMIN_PASSWORD` / `ADMIN_NAME` | Initial admin bootstrap                 |

## After Schema/Seeder Changes

```bash
php artisan migrate
php artisan db:seed --class=RolesAndPermissionsSeeder
```

Then run typecheck + build + phpunit and deliver results. Do not wait for user approval to deploy.

## Key Rules

- Deployments happen on push to `main` — never force-push to main
- `deploy.zip` excludes dev dependencies (telescope, pail, etc.)
- Server uses `DeployController::check()` with token auth
- Lock file prevents concurrent deploys (10 min TTL)
- CSP middleware only applies in production
