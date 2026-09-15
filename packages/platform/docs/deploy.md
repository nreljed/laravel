# Deploy (Unified Pipeline)

The deploy system lives **in platform** so every consuming project shares the same
server-side behavior. Hosts ship no deploy logic — they only carry a `DEPLOY_TOKEN` and the
CI workflow that pushes the release.

## Components

| Component  | Path                                          | Role                                                                              |
| ---------- | --------------------------------------------- | --------------------------------------------------------------------------------- |
| Config     | `config/deploy.php`                           | `token`, `github_repo`, `auto_enabled`, `lock_ttl`, `lock_path`, `version_path`, safe-install settings |
| Controller | `src/Deploy/Controllers/DeployController.php` | HTTP endpoints (token-gated)                                                      |
| Service    | `src/Deploy/Services/DeployService.php`       | Check latest release, download+install (atomic), run post-deploy steps            |
| Command    | `src/Console/Commands/DeployAuto.php`         | `deploy:auto` — self-deploy every 5 minutes (registered by the provider schedule) |

## Config (`config/deploy.php`)

| Key                  | Default                                 | Notes                                                                                                  |
| -------------------- | --------------------------------------- | ------------------------------------------------------------------------------------------------------ |
| `token`              | `env('DEPLOY_TOKEN')`                   | Must match the `DEPLOY_TOKEN` GitHub secret                                                            |
| `github_repo`        | `env('GITHUB_REPO', 'nreljed/laravel')` | Repo queried for the latest release (Shop overrides via `.env`)                                        |
| `auto_enabled`       | `true`                                  | When `true`, the provider schedules `deploy:auto` every 5 minutes                                      |
| `lock_ttl`           | `600`                                   | Seconds a `.deploy-lock` stays valid before it can be re-acquired                                      |
| `lock_path`          | `base_path('.deploy-lock')`             | Deploy lock file (overridable in tests)                                                                |
| `version_path`       | `base_path('.deployed-version')`        | Last-deployed version marker                                                                           |
| `env_backup_retention` | `env('DEPLOY_ENV_BACKUP_RETENTION', 5)` | Max `.env.backup.<ts>` snapshots kept beside `.env` (see below)                                        |
| `boot_check`         | `env('DEPLOY_BOOT_CHECK', true)`        | Boot-check the staged release (`php artisan --version`) before swapping it live                         |
| `boot_check_timeout` | `90`                                    | Seconds allowed for the staged boot check before it is treated as failed                               |
| `install_root`       | `base_path()`                           | Live install root the release swaps into (overridable so safety tests run in a sandbox)                |

> The `deploy:auto` schedule is registered by `PlatformServiceProvider::configureSchedules()`
> (gated on `deploy.auto_enabled`). Hosts must **not** schedule `deploy:auto` themselves —
> that was the old, duplicated approach. To disable, set `auto_enabled` to `false` in the
> published `config/deploy.php`.

## Safe Install (Atomic Swap)

Every release is installed through `DeployService::installZipFile()` — the same code path
used by `/deploy/check`, `/deploy/upload` and the `deploy:auto` cron. The old flow
(purge `vendor/`, then extract over the live tree) could leave an app wedged if the extract
died mid-way: no `vendor/`, `bootstrap/cache/`, or bundle was the reason the Shop app
hard-500d and could not self-heal. **The safe installer makes that impossible:**

1. **Stage** — the zip is extracted into `storage/app/deploy-staging-*` (unique per run),
   never over the live tree.
2. **Backup** — the live `vendor/` is *moved aside* (not deleted) to
   `deploy-staging-*-vendor-backup`.
3. **Boot check** — the staged release is booted in place (`php artisan --version` with the
   live `.env` copied in) so a broken new vendor + app file combination is caught **before**
   anything live is touched. Gated by `deploy.boot_check`; set `DEPLOY_BOOT_CHECK=false` only
   for a first-time bootstrap where the app cannot boot yet.
4. **Swap** — non-vendor files are mirrored first (the app keeps serving the old vendor),
   `bootstrap/cache` is cleared, then the staged `vendor/` is moved in *last* so it is never
   missing mid-swap. The backup is discarded only after the swap is complete.
5. **Rollback** — any failure at open/stage/boot-check/swap restores the previous `vendor/`
   from backup and clears caches. `installZipFile()` returns `false`, `/deploy/check`
   responds with a 500, `deploy:auto` reports `Deploy failed: Install failed; previous
   version kept` and re-tries on its next tick — **the app keeps serving the last good
   release and self-heals.**

A bad release can therefore fail loudly but never take the site down. The safety tests live
in the consumer app (`tests/Feature/Deploy/DeploySafetyTest.php`); they are skipped when the
pinned platform package predates `installZipFile` (`method_exists` guard), so consumer CI
stays green on older locks and activates as soon as the vendor copy is updated.

## Release-Layout Hosts (atomic `releases/<ts>-v<tag>` deployments)

Consumer hosts migrated to capistrano-style atomic releases are **served from
`releases/<active>`** (`public` and `current` at the home root are symlinks into the active
release; `.env` and `storage/` are symlinked to golden copies at the home root). On these
hosts:

- `DeployService::check()` detects the layout (`usesReleaseLayout()`) and **refuses the
  legacy in-place swap** — it never mutates an immutable release directory.
- Deploys go through the **deploy-runner** (`public/runner.php`, staged by the CI `restore`
  step): `action=release-install&version=<tag>` extracts the deploy.zip into a fresh
  `releases/<ts>-v<tag>` dir, restores `.env`, boot-checks, atomically points `current` and
  `public` at it, then prunes to the 3 newest releases (`release-cleanup` keeps rollback +
  `.public.legacy-*` fallback when no prior release exists).
- `deploy:auto` is a no-op here by design: it reports `release-layout` and exits, so the
  server never self-deploys into an immutable release. The 5-minute resilience net is CI's
  `Deploy Runner (Auto-Repair)` workflow + the runner's `auto-repair` action.

## Host Cron (scheduler only)

The host cron runs the **app scheduler**, not deploy logic (deploy is CI/runner-driven on
release-layout hosts; `deploy:auto` registers itself via the provider schedule but is a
no-op there). The single line every host needs, targeting the **active release** — never a
legacy root `artisan`:

```
* * * * * /usr/local/bin/php <home>/current/artisan schedule:run >> /dev/null 2>&1
```

`<home>/current` is the symlink to the active release, so the line stays valid across
releases. If it pointed at a legacy root `<home>/artisan` that no longer exists (after a
root cleanup), `schedule:run` fails and silenced daily jobs (`notifications:purge`,
`app:prune-audit-logs`) stop — fix the cron to `current/artisan`.

## .env Backup Retention

Every env-modifying endpoint (`/deploy/env` via `updateEnv`/`updateEnvValues`) snapshots the
live `.env` to `.env.backup.<timestamp>` **before** writing, then prunes to the newest
`deploy.env_backup_retention` (default 5) snapshots via `DeployService::pruneEnvBackups()`.
`updateEnvValues` is the CI env-push path (GITHUB_TOKEN refresh on every deploy).

- On release-layout hosts the snapshots land **inside the active release directory** and are
  further pruned by `release-cleanup` (keep=3 releases) — they are never written to the home
  root. `.env.backup.*` files at the home root are **legacy residue** from before the
  release-layout migration and can be deleted (archived to `legacy-root-env-backups-<date>/`
  on all three hosts on 2026-09-14).
- `DEPLOY_ENV_BACKUP_RETENTION` overrides the cap per host.

## HTTP Endpoints

All endpoints are CSRF-exempt (see `configureMiddleware()` → `PreventRequestForgery::except`)
and token-gated via `DeployController::verifyToken()`. Grouped with `maintenance` middleware +
`throttle:5,1`.

| Method       | Path                   | Purpose                                                                                                                                                                                                                                                                                                                                                                             |
| ------------ | ---------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `GET`/`POST` | `/deploy/check`        | **Main entry point.** Verify token, check `.deploy-lock` (10 min TTL), fetch latest release from GitHub API via `GITHUB_TOKEN`, download `deploy.zip`, install it atomically (safe-install: stage → boot-check → swap), restore `.env` from backup, run `package:discover`, `migrate`, `app:create-admin`, `config:cache`, `route:cache`, `view:cache`, `storage:link`. Returns `"deployed"` / `"current"` / lock status. |
| `POST`       | `/deploy/upload`       | Upload a `deploy.zip` directly (fallback when check can't reach GitHub). Installed with the same atomic safe-install.                                                                                                                                                                                                                                                                |
| `POST`       | `/deploy/env`          | Update `.env` values from a JSON payload (used to keep the server's `GITHUB_TOKEN` fresh before upload).                                                                                                                                                                                                                                                                            |
| `POST`       | `/deploy/migrate`      | Run migrations only.                                                                                                                                                                                                                                                                                                                                                                |
| `POST`       | `/deploy/create-admin` | Bootstrap/refresh the admin user (reads `X-Admin-Email/Name/Password` headers).                                                                                                                                                                                                                                                                                                     |
| `GET`/`POST` | `/deploy/cache-clear`  | Emergency: `migrate:fresh` + cache clear + re-seed.                                                                                                                                                                                                                                                                                                                                 |
| `POST`       | `/deploy/reset`        | Full database reset (re-seed).                                                                                                                                                                                                                                                                                                                                                      |

## CI/CD Flow (GitHub Actions)

1. Push to `main` → `deploy.yml` builds a release:
   - PHP 8.4 + Node 22, `composer install`, `migrate --force`, `npm run typecheck` +
     `npm run build`, `php artisan test`, Pint, `composer install --no-dev`.
   - Creates GitHub Release with semver tag + `deploy.zip` attachment.
   - Pushes the fresh `GITHUB_TOKEN` to the server `.env` via `POST /deploy/env` (before
     upload, retried up to 3×).
   - **Release-layout host:** stages `deploy-runner.php`, `deploy.zip` and the new
     `GITHUB_TOKEN` via FTP, then polls `runner.php?action=release-install&version=<tag>` —
     the canonical path (check → upload → FTP fallback order in the workflow; the runner
     installs atomically into a new `releases/` dir).
   - **Legacy host:** calls `/deploy/check` (self-deploy pulls the release itself), falling
     back to `/deploy/upload`, then FTP upload of `deploy.zip` for manual extraction.
2. Resilience: if CI is blocked by the host's security layer (datacenter IPs), the **CI-side
   `Deploy Runner (Auto-Repair)`** workflow or a manual `workflow_dispatch` picks the release
   up via the runner's `release-install`. `deploy:auto` self-healing only applies to legacy
   (non-release-layout) hosts.

## Publishing the Core Package

Because the deploy code lives in core, a core change reaches the Shop app through the publish
chain — **publish before you deploy the Shop app** (a new core is required for the unified
schedule to exist there):

1. Push core changes to `main` → core CI (tag-triggered) releases
   `nreljed/platform` + bumps the version tag + dispatches a `core-updated` event to the
   Shop repo.
2. Shop's `core-update.yml` (weekly schedule + `repository_dispatch`) runs
   `composer update nreljed/platform --with-dependencies --no-scripts --no-interaction`
   and opens a `composer.lock`-only PR.
3. Merge the PR, then the Shop `deploy.yml` ships it.

## Related

- [Commands](commands.md) — `deploy:auto`.
- Full pipeline details live in the monorepo/shop `AGENTS.md` and the `deploy` skill.
