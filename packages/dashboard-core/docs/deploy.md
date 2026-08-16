# Deploy (Unified Pipeline)

The deploy system lives **in dashboard-core** so every consuming project shares the same
server-side behavior. Hosts ship no deploy logic — they only carry a `DEPLOY_TOKEN` and the
CI workflow that pushes the release.

## Components

| Component  | Path                                          | Role                                                                              |
| ---------- | --------------------------------------------- | --------------------------------------------------------------------------------- |
| Config     | `config/deploy.php`                           | `token`, `github_repo`, `auto_enabled`, `lock_ttl`, `lock_path`, `version_path`   |
| Controller | `src/Deploy/Controllers/DeployController.php` | HTTP endpoints (token-gated)                                                      |
| Service    | `src/Deploy/Services/DeployService.php`       | Check latest release, download+extract, run post-deploy steps                     |
| Command    | `src/Console/Commands/DeployAuto.php`         | `deploy:auto` — self-deploy every 5 minutes (registered by the provider schedule) |

## Config (`config/deploy.php`)

| Key            | Default                                 | Notes                                                             |
| -------------- | --------------------------------------- | ----------------------------------------------------------------- |
| `token`        | `env('DEPLOY_TOKEN')`                   | Must match the `DEPLOY_TOKEN` GitHub secret                       |
| `github_repo`  | `env('GITHUB_REPO', 'nreljed/laravel')` | Repo queried for the latest release (Shop overrides via `.env`)   |
| `auto_enabled` | `true`                                  | When `true`, the provider schedules `deploy:auto` every 5 minutes |
| `lock_ttl`     | `600`                                   | Seconds a `.deploy-lock` stays valid before it can be re-acquired |
| `lock_path`    | `base_path('.deploy-lock')`             | Deploy lock file (overridable in tests)                           |
| `version_path` | `base_path('.deployed-version')`        | Last-deployed version marker                                      |

> The `deploy:auto` schedule is registered by `DashboardCoreServiceProvider::configureSchedules()`
> (gated on `deploy.auto_enabled`). Hosts must **not** schedule `deploy:auto` themselves —
> that was the old, duplicated approach. To disable, set `auto_enabled` to `false` in the
> published `config/deploy.php`.

## HTTP Endpoints

All endpoints are CSRF-exempt (see `configureMiddleware()` → `PreventRequestForgery::except`)
and token-gated via `DeployController::verifyToken()`. Grouped with `maintenance` middleware +
`throttle:5,1`.

| Method       | Path                   | Purpose                                                                                                                                                                                                                                                                                                                                                                             |
| ------------ | ---------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `GET`/`POST` | `/deploy/check`        | **Main entry point.** Verify token, check `.deploy-lock` (10 min TTL), fetch latest release from GitHub API via `GITHUB_TOKEN`, download `deploy.zip`, extract over project root, restore `.env` from backup, run `package:discover`, `migrate`, `app:create-admin`, `config:cache`, `route:cache`, `view:cache`, `storage:link`. Returns `"deployed"` / `"current"` / lock status. |
| `POST`       | `/deploy/upload`       | Upload a `deploy.zip` directly (fallback when check can't reach GitHub).                                                                                                                                                                                                                                                                                                            |
| `POST`       | `/deploy/env`          | Update `.env` values from a JSON payload (used to keep the server's `GITHUB_TOKEN` fresh before upload).                                                                                                                                                                                                                                                                            |
| `POST`       | `/deploy/migrate`      | Run migrations only.                                                                                                                                                                                                                                                                                                                                                                |
| `POST`       | `/deploy/create-admin` | Bootstrap/refresh the admin user (reads `X-Admin-Email/Name/Password` headers).                                                                                                                                                                                                                                                                                                     |
| `GET`/`POST` | `/deploy/cache-clear`  | Emergency: `migrate:fresh` + cache clear + re-seed.                                                                                                                                                                                                                                                                                                                                 |
| `POST`       | `/deploy/reset`        | Full database reset (re-seed).                                                                                                                                                                                                                                                                                                                                                      |

## CI/CD Flow (GitHub Actions)

1. Push to `master` → `deploy.yml` builds a release:
   - PHP 8.4 + Node 22, `composer install`, `migrate --force`, `npm run typecheck` +
     `npm run build`, `php artisan test`, Pint, `composer install --no-dev`.
   - Creates GitHub Release with semver tag + `deploy.zip` attachment.
   - Pushes the fresh `GITHUB_TOKEN` to the server `.env` via `POST /deploy/env` (before
     upload, retried up to 3×).
   - Calls `/deploy/check` (canonical — server pulls the release itself), falling back to
     `/deploy/upload`, then FTP upload of `deploy.zip` for manual extraction.
2. Server-side `deploy:auto` (every 5 minutes) re-runs the same check so any missed trigger
   self-heals.

## Publishing the Core Package

Because the deploy code lives in core, a core change reaches the Shop app through the publish
chain — **publish before you deploy the Shop app** (a new core is required for the unified
schedule to exist there):

1. Push core changes to the monorepo `main` → `core-publish.yml` publishes
   `nreljed/dashboard-core` + bumps the version tag + dispatches a `core-updated` event to the
   Shop repo.
2. Shop's `core-update.yml` (weekly schedule + `repository_dispatch`) runs
   `composer update nreljed/dashboard-core --with-dependencies --no-scripts --no-interaction`
   and opens a `composer.lock`-only PR.
3. Merge the PR, then the Shop `deploy.yml` ships it.

## Related

- [Commands](commands.md) — `deploy:auto`.
- Full pipeline details live in the monorepo/shop `AGENTS.md` and the `deploy` skill.
