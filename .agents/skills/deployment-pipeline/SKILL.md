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

pull_request → pr.yml
  └─ ci.yml (reusable workflow_call)    — identical validation, no secrets needed
```

## Workflow Files

### ci.yml (Reusable Validation)

- PHP 8.4 + Node 22
- Composer + npm caches
- SQLite .env + migrate
- Steps: lint → typecheck → format:check → vitest → build → phpunit → pint
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

- Calls ci.yml — identical validation, no secrets needed

## Server Deploy Flow

1. Verify token + lock file (10 min TTL)
2. Fetch latest release from GitHub API
3. Download `deploy.zip`, extract, restore `.env`
4. Run: migrate, create-admin, cache, storage:link

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
