# Scripts, Deploy & Runners (`scripts/`, `.github/`)

Scoped context for the deploy pipeline, GitHub Actions, self-hosted runners, and the
pre-commit hook. Read before touching `scripts/**`, `.github/**`, hooks, or deploys. See also
the `deploy`, `deployment-pipeline`, and `self-hosted-runners` skills.

## Self-Hosted Runners

GitHub-hosted runners are billing-blocked account-wide, so CI/CD runs on eight self-hosted
runners on this laptop — one per repo, all under `C:\runners\<suffix>` (moved from flat
`C:\actions-runner-*` on 2026-08-31): `C:\runners\shop` (`win-runner-shop` → laravel.shop),
`C:\runners\laravel` (`win-runner-laravel` → laravel), `C:\runners\booking`
(`win-runner-booking` → laravel.booking), `C:\runners\platform` (`win-runner-platform` →
nreljed/platform), `C:\runners\blog` (`win-runner-blog` → nreljed/blog),
`C:\runners\shopmodule`, `C:\runners\bookingmodule`, `C:\runners\projectwp`. Plain `run.cmd`
processes (NO Windows services, no UAC), label `self-hosted`. Runners do NOT auto-start —
start them on-demand via `pwsh -NoProfile -File C:\tools\gha-runner.ps1 <name|all>`.

### Runner switch (self-hosted ⇄ GitHub-hosted)

All nreljed workflows read `runs-on` from **per-repo Actions variables** (set on all 8 repos),
so choosing the runner is a variable flip, not a code change:

| Variable                                                                | self-hosted (default) | GitHub-hosted    |
| ----------------------------------------------------------------------- | --------------------- | ---------------- |
| `RUNNER_CI` (validators, push-deploy, update workflows, core/module CI) | `self-hosted`         | `ubuntu-latest`  |
| `RUNNER_PACKAGE` (Windows-bound build/packaging: pwsh, 7-Zip)           | `self-hosted`         | `windows-latest` |

- Flip all repos at once: `pwsh -NoProfile -File C:\tools\switch-runners.ps1 self|cloud`.
  Single repo: `gh variable set RUNNER_CI -R nreljed/laravel --body ubuntu-latest`.
- `runs-on` is `${{ vars.RUNNER_CI || 'self-hosted' }}` — missing var degrades safely.
- `workflow_call` reusables (consumer-ci.yml) resolve `vars` against the **calling** repo —
  laravel/shop/booking must each have `RUNNER_CI` set.
- `consumer-ci.yml` cache steps are gated `RUNNER_CI != 'self-hosted'` — cache is re-enabled
  only on GitHub-hosted (ephemeral) runners; self-hosted stays cache-free (upload-stall trap).
- Two vars (not one) because GitHub-hosted bills Linux 1x vs Windows 2x; validators run on
  cheap ubuntu, only pwsh `Compress-Archive`/7-Zip packaging is Windows-bound.
- GitHub has NO native auto-failover between runner types — this variable is the mechanism.
  `nreljed` is a User account → no org-level vars; per-repo only.
- **Project-WP caveat:** its workflows hardcode `C:\php` (preinstalled toolchain) — a cloud
  flip breaks those jobs until setup-php is added. Staged for consistency only.

> Full setup/reproduction steps, the `.env` PATH override, and the failure-modes table live in
> the **`self-hosted-runners` skill** — read it before touching runner config. Never force-kill
> listeners repeatedly (ghost broker sessions = jobs stuck queued; fix is API-delete +
> re-register). See also `docs/workflow/runners.md`.

## Deployment

### CI/CD Pipeline (GitHub Actions → Release + Server Deploy)

Deploys automatically on push to `main`. Uses **reusable workflows** for cross-repo reuse:

```
push main → deploy.yml
  ├─ ci.yml (reusable workflow_call)    — RUNNER_CI, full validation
  ├─ deploy-package.yml (reusable)      — RUNNER_PACKAGE, builds deploy.zip
  ├─ release                            — creates GitHub Release
  └─ push-deploy                        — canonical check-first deploy (env-push → check → upload → FTP)

pull_request → pr.yml
  └─ ci.yml (reusable workflow_call)    — same validation as deploy
```

**ci.yml** (reusable `workflow_call`, `RUNNER_CI`):

- PHP 8.4 + Node 22, Composer + npm caches
- SQLite .env + migrate, then: lint, typecheck, format:check, vitest, build, phpunit, pint
- Inputs: `skip-php-tests`, `skip-npm-tests` (boolean, default false)

**deploy-package.yml** (reusable `workflow_call`, `RUNNER_PACKAGE`):

- git-cliff version bump → SQLite build .env → full build pipeline
- Strip dev deps → production .env → `deploy.zip` artifact
- Output: `version` string

**deploy.yml** (orchestrator, push main):

- Calls `ci.yml` → `deploy-package.yml` → creates GitHub Release → canonical `push-deploy`
- `push-deploy` job (shared shape with `laravel.shop`):
  1. **env-push** — `POST /deploy/env` with unified `GITHUB_TOKEN` (`if: always()`, `continue-on-error`)
  2. **Poll `/deploy/check`** (8 × 45s) — accept `"deployed"` or `"current"` matching the release tag
  3. **Fallback `/deploy/upload`** only if check didn't confirm, then re-confirm via check (response can be lost mid-extract)
  4. **FTP fallback** (`continue-on-error`) — DirectAdmin account (`laravel@tunispromo.tn`), home IS the project root, uploads `deploy.zip` to root. Skips itself when `FTP_*` secrets are unset.
- Resilience: if CI is blocked by the host's security layer (datacenter IPs):
  - **Release-layout host (this app since 2026-09):** `deploy:auto` is a **no-op** —
    `DeployService::check()` returns `release-layout` and refuses the in-place swap. The net
    is the CI-side scheduled `Deploy Runner (Auto-Repair)` workflow (or manual
    `workflow_dispatch`), which runs the runner's `release-install`.
  - **Host cron runs the app scheduler only** (not deploy logic): `* * * * * <php>
<home>/current/artisan schedule:run` — must target the `current` symlink, never a
    legacy root `artisan`.
  - **`.env` backups**: `/deploy/env` snapshots `.env` → `.env.backup.<ts>` and prunes to
    `deploy.env_backup_retention` (default 5). On release-layout hosts they land inside the
    active release dir (pruned by `release-cleanup`); root `.env.backup.*` are legacy residue
    (archived 2026-09-14 to `legacy-root-env-backups-2026-09-14/`).

**pr.yml** (pull_request to main):

- Calls `ci.yml` — identical validation, no secrets needed

**Server (`DeployController::check()`):**

1. Verify token + lock file (10 min TTL)
2. Fetch latest release from GitHub API (via `GITHUB_TOKEN` in server `.env`)
3. Download `deploy.zip`, extract, restore `.env`
4. Run: migrate, create-admin, cache, storage:link

**database-maintenance.yml** (`workflow_dispatch`, manual only):

- Inputs: `command` (`migrate` | `reset`), `confirm` (`yes` for migrate, `RESET` for reset),
  `with_demo`, `purge_media` (reset only)
- `migrate` curls `POST /deploy/migrate`; `reset` curls `POST /deploy/reset` with
  `secrets.DEPLOY_TOKEN`
- Requires `DB_MAINTENANCE_ENABLED=true` in production `.env` for reset (the endpoint itself
  is always available — it just calls `DatabaseMaintenanceService::resetDatabase()`)

### Account Data Backup & Restore (data-feature conventions)

The **Data** feature (`dashboard → Account → Data`) lets a user export or restore *their own*
account data. It is the canonical example of the **module contract**: a feature is not
data-owning unless it says so. Backend lives in `vendor/nreljed/platform`.

- **Contract**: a data-owning feature returns capability objects from
  `FeatureProvider::getDataBackupCapabilities()`. Each is an instance of
  `Nreljed\Platform\Support\DataBackupCapability` — a **class**, not an interface — built with
  a constructor and exposing `getKey()`, `getLabel()`, `getDescription()`, `supportsExport()`,
  `supportsRestore()`, `getActions()` and `toArray()`. `toArray()` is the serializer, so the
  export shape is whatever the capability returns; there is no versioned envelope to extend.
- **Registry**: `FeatureRegistry::getDataBackupModules()` returns
  `list<array{provider: FeatureProvider, capability: DataBackupCapability}>`;
  `FeatureRegistry::resolveDataBackupModule(string $key)` resolves one by key and returns
  `null` for an unknown key (the controller surfaces that as "Unknown data module").
- **Routes** (`AccountDataController`, prefix `/dashboard/account`):
  `GET /data` (page, `dashboard.account.page.data`),
  `GET /data/export/{module}` (download, `dashboard.account.data.export`) — note it is **GET**,
  not POST, because it streams a file response —
  and `POST /data/restore/{module}` (`throttle:10,1`). Restore is validated by
  `RestoreAccountDataRequest`: `backup` required file `mimes:json,text/plain` `max:20480`
  (KiB) plus `confirmation` `in:restore my data` — restore is **not** a password-confirmed
  flow, the typed phrase + throttle are the gate. It **replaces only the user's own rows**
  and is idempotent via the capability's restore key.
- **Audits**: `AuditAction::DataExported` (`data_exported`, severity `info`) and
  `AuditAction::DataRestored` (`data_restored`, severity `warning` — not `critical`).
- **Frontend**: `packages/platform/resources/js/Pages/dashboard/account/data.tsx` renders
  `DataBackupSection` (`packages/platform/resources/js/Components/account/sections/`).
  Uses the shared `SettingsAccordion` pattern; export is a plain `router.get` download,
  restore is a `useForm` with a file input.
- **Known gap (pinned versions)**: no feature supplies a capability yet, so the page lists
  zero modules and every export/restore returns "Unknown data module". `vendor/nreljed/blog` does
  not implement `getDataBackupCapabilities()` in the version this app pins. Add the
  capability in the **owning feature repo**, tag, then update this app's lock — do not patch
  the vendor tree or the mirror.
- Local dev cache: keep `CACHE_STORE=file` in the app `.env` — a `database` store on a
  shared SQLite cache table causes intermittent read flapping (settings/feature props);
  middleware reads are `Cache::remember` + fail-closed, controllers `Cache::forget` on toggle.

### Database Maintenance Feature

`vendor/nreljed/platform` ships an environment-gated reset/reseed feature:

| Surface         | Gate                                                  | Route                                       |
| --------------- | ----------------------------------------------------- | ------------------------------------------- |
| Admin UI page   | `admin` + `manage-options` + `DB_MAINTENANCE_ENABLED` | `dashboard.admin.database(.reset\|.reseed)` |
| Deploy endpoint | `DEPLOY_TOKEN` + `confirm=RESET`                      | `POST /deploy/reset`                        |

- Shared logic lives in `Nreljed\Platform\Services\DatabaseMaintenanceService` —
  `resetDatabase()` runs `migrate:fresh` + baseline seeders (roles/permissions + settings) +
  `app:create-admin`; demo-tier seeders (`demo_users`, `blog`) only run when `demo: true`
  (`with_demo` on the endpoint, UI always resets baseline-only). `reseedDatabase()` re-runs
  only the selected seeder groups via `db:seed --class`. Seeder groups are a config catalog
  (`config/database-maintenance.php` → `seeders`; baseline/destructive flags). Optional media
  purge of `storage/app/public`, `.maintenance-lock` file (15 min TTL), toggles maintenance
  mode, writes a `critical` audit log, and notifies all admins.
- `DB_MAINTENANCE_ENABLED` defaults to enabled everywhere **except** production. Keep it `false`
  in production unless you intend to use the GitHub Action reset. `.env.example` and
  `.env.production.example` both ship `DB_MAINTENANCE_ENABLED=false`.
- UI reset ends the session and redirects to `/login`; deploy reset returns JSON.
- Tests: `tests/Feature/Admin/DatabaseMaintenanceTest.php`, `tests/Feature/Admin/DatabaseMaintenanceServiceTest.php`, `tests/Feature/Deploy/DatabaseResetDeployEndpointTest.php`.

### GitHub Secrets

| Secret                                                      | Purpose                                                                                                                                             |
| ----------------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------- |
| `DEPLOY_TOKEN`                                              | Must match `config('app.deploy_token')` — **per-repo** (this app's server)                                                                          |
| `GH_TOKEN`                                                  | ONE unified fine-grained PAT covering all repos (`laravel`, `laravel.shop`, `laravel.booking`, `platform`, `blog`, `shop-module`, `booking-module`) |
| `DB_DATABASE` / `DB_USERNAME` / `DB_PASSWORD`               | Production MySQL                                                                                                                                    |
| `ADMIN_EMAIL` / `ADMIN_PASSWORD` / `ADMIN_NAME`             | Initial admin bootstrap                                                                                                                             |
| `FTP_SERVER` / `FTP_PORT` / `FTP_USERNAME` / `FTP_PASSWORD` | DirectAdmin FTP fallback for this app's own domain (`laravel@tunispromo.tn`)                                                                        |

> Full secrets table + `gh` cheat sheet: [`../docs/github-cli.md`](../docs/github-cli.md).
> Deploy contract (config keys, endpoints, FeatureProvider checklist, publish flow):
> [`../docs/core-dashboard-package.md`](../docs/core-dashboard-package.md) §7.1.

### After Schema/Seeder Changes

```bash
php artisan migrate
php artisan db:seed --class=RolesAndPermissionsSeeder
```

Then run typecheck + build + phpunit and deliver results. Do not wait for user approval to deploy.

## Agent harness & shell permission

The harness is validated by `node scripts/check-agent-context.mjs` (also the `check:context`
script). `permission.bash` in `.opencode/opencode.json` is intentionally **unset** so shell
access inherits the global default — this project's agents legitimately need `composer`, `npm`
and `git`, so we do not scope-allow them here. The validator reports this as a tracked `warn`
(advisory, not a defect); if you ever tighten it, set `permission.bash` explicitly as a map
keyed by glob rather than a bare string.

## Package mirrors & parity

`packages/**` are one-way mirrors of the standalone package repos, and both tools now reference
the **released tree** — the tree at the upstream's latest `v*` tag as `git archive` emits it
(`.gitattributes export-ignore` honored), i.e. exactly what Composer installs into
`vendor/nreljed/*`:

- `scripts/sync-package-mirror.mjs <repo> <mirror>` / `scripts/diff-package-dir.mjs <mirror> <repo>`
  drive/report the mirror from that released tree. They materialize `git archive <latest-tag>`
  (`--ref <git-ref>` overrides it), EOL-normalize before comparing, and ignore `composer.lock` /
  `.github` / tool caches — so their result **agrees with the gate**. A CRLF checkout, an
  export-ignored file, or an unreleased commit no longer reads as drift.
- `npm run check:platform-consistency` is the authoritative gate (mirror vs `vendor/nreljed/*`).

Rule: to land a change in a mirror you must commit **and tag/release** it upstream, then re-sync
and update the consumer lock. Never hand-edit a version string to silence a check, and never sync
a mirror from an unreleased upstream — the archive would simply be missing your commit.

## Pre-commit scope

The pre-commit hook (lint-staged) is **scoped to the files this app owns** — `resources/js/**`,
`app/**` for TS/TSX eslint+prettier, and `{app,bootstrap,config,database,routes,tests,resources}/**`
for Pint. `packages/**` and `apps/**` are upstream mirrors, so they are excluded from the root
lint-staged AND root ESLint (`eslint.config.js` `ignores`) — those trees are owned by their own
repos/apps and run their own toolchains (shop/booking `check:ci`, package repo CIs). Never widen
the catch-all back to `*.{ts,tsx}` / `*.php` or the root hook will reformat foreign package sources.
