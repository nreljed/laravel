# Scripts, Tooling & Deployment (`scripts/`, `.github/`, hooks)

> **How this file loads**: not auto-loaded (traversal stops at the repo-root
> `AGENTS.md`). Referenced from the root `AGENTS.md` — **read it when the task
> touches `scripts/**`, `.github/**`, `.husky/**`, or deploy/runners/CI.**

House rules for this tree: `npm run check:ci` is a local **superset** of CI, not
the pipeline itself; `check:platform-consistency` is skipped (never silently
green) when upstream env vars are unset; boundary asymmetry between
lint-staged/ESLint (`packages/**` + `apps/**`) and Pint (`packages/**` only) is
deliberate — see the root `AGENTS.md` for why.

## Self-Hosted Runners

GitHub-hosted runners are billing-blocked account-wide, so CI/CD runs on seven self-hosted
runners on this laptop — one per repo, all under `C:\runners\<suffix>` (moved from flat
`C:\actions-runner-*` on 2026-08-31): `C:\runners\shop` (`win-runner-shop` → laravel.shop),
`C:\runners\laravel` (`win-runner-laravel` → laravel), `C:\runners\booking` (`win-runner-booking` →
laravel.booking), `C:\runners\platform` (`win-runner-core` → nreljed/platform), `C:\runners\shopmodule`,
`C:\runners\bookingmodule`, `C:\runners\projectwp`. Plain `run.cmd` processes (NO Windows services,
no UAC), label `self-hosted`. Runners do NOT auto-start — start them on-demand via
`pwsh -NoProfile -File C:\tools\gha-runner.ps1 <name|all>`.

### Runner switch (self-hosted ⇄ GitHub-hosted)

All nreljed workflows read `runs-on` from **per-repo Actions variables** (set on all 7 repos),
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

> Full setup/reproduction steps, the `.env` PATH override, and the failure-modes table live in the
> **`self-hosted-runners` skill** (`~/.config/opencode/skills/self-hosted-runners/SKILL.md`) — read it
> before touching runner config. Never force-kill listeners repeatedly (ghost broker sessions = jobs
> stuck queued; fix is API-delete + re-register).

## Commands

```bash
pnpm install             # workspace install (single root pnpm-lock.yaml — the npm pin of record)
npm run build            # tsc + vite build (production)
npm run dev              # Vite dev server with HMR
npm run typecheck        # tsc --noEmit
npm run lint             # ESLint
npm run format:check     # Prettier check
npm run test             # Vitest frontend tests (app shell + package workspaces)
npm run check:ci         # Local superset (see note below)
vendor/bin/pint          # Laravel Pint (PSR-12)
php artisan test         # PHPUnit
php artisan test --compact  # PHPUnit (compact output)
php artisan test --parallel --compact  # PHPUnit (parallel, via paratest)
php artisan migrate      # Run migrations
php artisan permissions:list          # List all permissions
php artisan app:create-admin           # Bootstrap/update admin user
```

> **`check:ci` is a local superset, not the CI pipeline.** It runs, in order:
> `check:platform-consistency` → `lint` → `typecheck` → `typecheck:packages` →
> `format:check` → `test` → `pint` → `phpstan` → `rector --dry-run` →
> `artisan test --parallel`. Actual CI (root `ci.yml` → platform's reusable
> `consumer-ci.yml`) runs `check:core-deps`, `check:platform-consistency`, `npm audit`,
> `lint`, `typecheck`, `format:check`, `test`, `build`, `artisan test --parallel`, and
> `pint --test` — plus a Playwright auth smoke job.
>
> **`typecheck:packages`, `phpstan` and `rector` are NOT run by CI.** They are local-only
> gates, so they can rot without turning the pipeline red — `typecheck:packages` is
> currently **failing** on a pre-existing `shop-module` error
> (`Pages/Products/Form.tsx:605` — `Object.entries(effects.data)` yields `string` keys that
> `form.setData` rejects). That source belongs to the shop-module repo, so the fix is made
> and released upstream, not here. Run `npm run check:ci` knowing this one step is red.

The pre-commit hook (lint-staged) is **scoped to this app's own files only** — `resources/js/**`,
`app/**` for TS/TSX eslint+prettier, and `{app,bootstrap,config,database,routes,tests,resources}/**`
for Pint. `packages/**` and `apps/**` are excluded from the root lint-staged AND root ESLint
(`eslint.config.js` `ignores`) — those trees are owned by their own repos/apps and run their own
toolchains (shop/booking `check:ci`, package repo CIs). Never widen the catch-all back to `*.{ts,tsx}`
/ `*.php` or the root hook will reformat foreign package sources.

> **`--config package.json` in the pre-commit hook is load-bearing.** lint-staged v16 discovers and
> *merges* the `lint-staged` block of every `package.json` in the repo, then runs each block with that
> package as `cwd`. Without the flag the hook also loads `apps/*/package.json` (whose globs are the
> broad `*.{ts,tsx}` / `*.{json,css,md}` / `*.php`), so root Pint/Prettier/ESLint rewrite upstream-owned
> mirrored files and `check-platform-consistency` fails with ~46 + ~43 forked files. Always pass
> `--config package.json` so only root-owned paths are touched. If the root hook ever "fixes" a file
> under `apps/**` or `packages/**`, that is this bug, not a formatting nit — re-sync from upstream.


**Root Pint is boundary-scoped too** — the root `pint.json` sets `{"exclude": ["packages"]}`, which is
what keeps `vendor/bin/pint` (root `pre-push` hook, `check:ci`, and CI's `Laravel Pint` step from
`nreljed/platform/.github/workflows/consumer-ci.yml`) from touching `packages/*` mirrors. Those mirrors
must stay **byte-identical to the released package tree** (the package repos own their formatting and
their CIs don't run Pint), so a repo-wide Pint run would both break CI's `pint --test` and silently
reformat ~100 mirror files on every push.

Note the two boundaries are **deliberately different**, and the reason is *not* ownership —
`apps/laravel.shop` and `apps/laravel.booking` are **upstream mirrors** of the standalone
`nreljed/laravel.shop` / `nreljed/laravel.booking` repos (they each deploy independently, and
CI's `parity` job clones both and byte-compares them against `apps/**`). lint-staged and ESLint
exclude `packages/**` **and** `apps/**`; Pint excludes `packages/**` **only**. The asymmetry
is toolchain coverage, not content ownership: the apps share the root pnpm workspace, so a root
PHP formatting or dependency change can break them, and `apps/**` stays Pint-clean because their
standalone repos run Pint. **A fix authored under `apps/**` still does not ship** — it lands in
the standalone repo. So do not add `apps` to `pint.json`, and when adding a tool to the root
hooks, copy the boundary from Pint for PHP and from lint-staged/ESLint for TS/TSX rather than
assuming one shared rule.

`.opencode/plugins/mirror-boundary.ts` enforces the `packages/**` write boundary at the
agent level, and `.opencode/opencode.json` registers the four upstream package repos as
`references` so their real source is reachable from this workspace.

Also useful: `pnpm -w update <pkg>` (roll a `^` range forward in the workspace lock),
`pnpm workspace <name> add <pkg>` (per-package manifest change), `pnpm approve-builds`
(add postinstall scripts to `allowBuilds` in `pnpm-workspace.yaml` — pnpm 11 blocks them
by default and ignores the `package.json` `pnpm` field).

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

> **Note:** there is currently **no `database-maintenance.yml`** in `.github/workflows`
> (actual workflows: `ci.yml`, `pr.yml`, `deploy.yml`, `deploy-package.yml`,
> `core-update.yml`, `restore.yml`). Database resets are driven manually with
> `gh workflow run ...` against `deploy.yml`'s deploy job, or by calling the
> `/deploy/*` endpoints directly — the inputs below describe the manual curl
> contract, not a committed workflow file.

- Inputs: `command` (`migrate` | `reset`), `confirm` (`yes` for migrate, `RESET` for reset),
  `with_demo`, `purge_media` (reset only)
- `migrate` curls `POST /deploy/migrate`; `reset` curls `POST /deploy/reset` with
  `secrets.DEPLOY_TOKEN`
- Requires `DB_MAINTENANCE_ENABLED=true` in production `.env` for reset (the endpoint itself is always
  available — it just calls `DatabaseMaintenanceService::resetDatabase()`)

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

> Full secrets table + `gh` cheat sheet: [`docs/github-cli.md`](docs/github-cli.md).
> Deploy contract (config keys, endpoints, FeatureProvider checklist, publish flow):
> [`docs/core-dashboard-package.md`](docs/core-dashboard-package.md) §7.1.

### After Schema/Seeder Changes

```bash
php artisan migrate
php artisan db:seed --class=RolesAndPermissionsSeeder
```

Then run typecheck + build + phpunit and deliver results. Do not wait for user approval to deploy.
