---
name: deploy
description: Deploying the nreljed Laravel apps (laravel.shop host, laravel monorepo, laravel.booking) to production. Use when working on the CI/CD pipeline, GitHub Actions deploy workflows, FTP fallback, /deploy/* endpoints, GitHub secrets/tokens, releases, or diagnosing why a deploy failed or is stale. Covers the shared GH_TOKEN design, env-push step, canonical check-first flow, FTP home-root quirk, core-package publish, the deploy-race guard trio, and how to verify a deploy.
---

# Deploy Operations Guide (nreljed apps)

Three apps auto-deploy on push to their default branch via GitHub Actions. All share the
same infra pattern (DeployController/DeployService ship from `nreljed/platform`),
but have distinct repos, branches, servers, DBs, and deploy tokens.

| | Shop host | Monorepo (Cost Breaker 360) | Booking |
|---|---|---|---|
| Repo | `nreljed/laravel.shop` | `nreljed/laravel` | `nreljed/laravel.booking` |
| Branch | `main` | `main` | `master` |
| Local path (workspace era) | `…\laravel\app\apps\laravel.shop` | `C:\Users\nrelj\Local Sites\laravel\app` | `…\laravel\app\apps\laravel.booking` |
| LocalWP docroot | junction `…\shop-laravel\app` → `apps\laravel.shop` (2026-09-15) | same as Local path | junction `…\booking-laravel\app` → `apps\laravel.booking` (2026-09-15) |
| Server URL (`vars.APP_URL`) | `https://shop.laravel.tunispromo.tn` | `https://laravel.tunispromo.tn` | `https://booking.laravel.tunispromo.tn` |
| Release tags | `v0.x.y` on laravel.shop | `v0.x.y` on laravel | `v1.0.x` on laravel.booking |
| Latest verified | v0.6.25 (2026-08-25) | v0.17.6 (2026-08-25) | v1.0.21 (2026-08-25) |
| Workflow file | `.github/workflows/deploy.yml` | `.github/workflows/deploy.yml` (+ reusable `ci.yml`, `deploy-package.yml`) | `.github/workflows/deploy.yml` |
| FTP account | `shoplaravel@tunispromo.tn` | `laravel@tunispromo.tn` | booking app's own FTP account |

Both host a Laravel app with a `DeployController` exposing:
- `POST /deploy/check?token=...` — server pulls the latest GitHub release, downloads `deploy.zip`, extracts over project root, restores `.env`, runs `package:discover`/`migrate`/`app:create-admin`/`config:cache`/`route:cache`/`view:cache`/`storage:link`.
- `POST /deploy/env?token=...` — body `{"values":{"KEY":"value"}}` — writes env values into the server `.env` (backed up first). Rejects `APP_KEY`/`DEPLOY_TOKEN`. Throttled 5/min.
- `GET /deploy/cache-clear?token=...` — emergency reset.
- All `/deploy/*` routes throttle `5,1`. `DeployService::check()` reads `GITHUB_TOKEN` from the server `.env`; lock file `.deploy-lock` TTL 600s.

## Local dev in the workspace (path repos + LocalWP serving)

Since 2026-09-15 this repo is a pnpm+composer workspace: `packages/*` (platform, blog,
shop-module, booking-module) + `apps/laravel.shop`, `apps/laravel.booking` + the mono app at
root. Composer side of the 3 apps is unchanged for RELEASE (`nreljed/*` from GitHub via
`^0.1`), but for LOCAL dev the apps flip to `path` repos so `vendor/nreljed/*` become
junctions into `packages/*`:

```bash
node scripts/switch-composer-repos.js path shop booking   # dev mode
composer update nreljed/platform nreljed/blog nreljed/shop-module   # in each app (booking: ...booking-module)
node scripts/switch-composer-repos.js vcs shop booking    # back to release-pinned mode
```

- Mono root stays VCS at rest; `scripts/switch-composer-repos.js [status|path|vcs] [app...]`
  rewrites `repositories[].type` and prints next steps. `composer.lock` is the pin of record
  in both modes — commit it whenever it changes.
- Never edit `vendor/` (it's a junction/vcs copy); package changes go in `packages/*` and
  board in via `composer update`.
- LocalWP serves the monorepo apps through directory junctions (APPLIED 2026-09-15): `{site}\app` for
  `shop-laravel`/`booking-laravel` is a junction → `…\laravel\app\apps\laravel.{shop,booking}` (the old
  standalone checkouts were renamed to `app.standalone`, still present). Apache document root is the app
  ROOT with a root `.htaccess` rewriting to `public/`. PHP resolves the junction's realpath so
  `base_path()` stays in `apps/*`. App `.env` is sqlite + LocalWP-domain `APP_URL`; old site MySQL demo
  data lives in `app.standalone`. Rollback: remove the junction, rename `app.standalone` → `app`.
- Fresh app dirs (robocopy import) are missing `.env`/`.env.example`, `storage/`, and
  `database/database.sqlite` — tests fail `No application encryption key` → `valid cache
  path`. Scaffold `.env` + `php artisan key:generate`, storage skeleton
  (`storage/app/public`, `storage/framework/{cache/data,sessions,views,testing}`,
  `storage/logs`, `bootstrap/cache`), touch the sqlite file, and set a real per-app
  `APP_NAME` (brand prop falls back to `config('app.name')` — storefront `assertSee('Shop App')`
  fails on the default "Laravel").

## CI/CD pipeline (per repo, canonical check-first)

1. **ci / validate** — PHP/Node setup, composer + npm install, `composer audit`, `npm audit --audit-level=high`, ESLint, TS check, Prettier, vitest, build, PHPUnit, Pint.
2. **package** — builds `deploy.zip` (production build, no-dev composer), then **creates the GitHub Release `vX.Y.Z` directly** (softprops `action-gh-release`, git-cliff notes, `deploy.zip` attached as a release asset).
3. **push-deploy** (identical shape in both repos):
   a. **Download `deploy.zip` from the release** (`gh release download`) — NOT an Actions artifact
   b. **env-push** — `POST /deploy/env` with unified `GITHUB_TOKEN` (`if: always()` + `continue-on-error`)
   c. **Poll `/deploy/check`** (8 × 45s) — accept `"status":"deployed"` or `"status":"current"` matching the release tag
   d. **Fallback `/deploy/upload`** only if check didn't confirm, then **re-confirm via check** (response can be lost mid-extract)
   e. **FTP fallback** (`continue-on-error`, `if: always()`, skips when `FTP_*` unset)
- **Why no Actions artifact:** the free plan has only **0.5 GB Actions artifact storage/month**; an ~18MB `deploy-package` artifact per deploy exhausted it (usage recalc takes 6-12h, so cleanup alone doesn't unblock). `deploy.zip` ships as a **release asset**, which is NOT billed against that quota. Artifact upload was removed in both repos (2026-08-16).
- Resilience: on **release-layout hosts** (all three apps since 2026-09: `current`→`releases/<ts>-v<tag>`,
  `public` symlink, `.env`/`storage` golden at home root) `deploy:auto` **self-disables**
  (`DeployService::check()` returns `release-layout`, server never mutates an immutable
  release). The resilience net is CI: the scheduled **`Deploy Runner (Auto-Repair)`**
  workflow + manual `workflow_dispatch` run the runner's `release-install`. `deploy:auto`
  self-heal only applies to legacy (non-release-layout) hosts.
- **Host cron = app scheduler only** (deploys are CI/runner-driven): one line per host pointing
  at the ACTIVE release symlink, e.g. `* * * * * <php> <home>/current/artisan schedule:run`.
  A cron at a legacy root `<home>/artisan` breaks silently after root cleanup — fix to `current`.
- **`.env` backups**: `/deploy/env` (env-push) snapshots `.env` → `.env.backup.<ts>` before
  writing and prunes to `deploy.env_backup_retention` (default 5) via `pruneEnvBackups()`.
  On release-layout hosts snapshots land INSIDE the active release dir (pruned again by
  `release-cleanup` keep=3); `.env.backup.*` at the home root are LEGACY residue and safe to
  delete (all three hosts archived to `legacy-root-env-backups-2026-09-14/` on 2026-09-14).

## Secrets & token design (CRITICAL)

**ONE fine-grained PAT** `GH_TOKEN` ("nreljed-deploy") covers all six repos
(`nreljed/laravel`, `laravel.shop`, `laravel.booking`, `platform`, `shop-module`,
`booking-module`). It is set in all THREE app repos (`laravel`, `laravel.shop`,
`laravel.booking`) and stored in all THREE server `.env` files. This is because each
app's composer install VCS-clones its private packages (Shop: `platform` +
`shop-module`; Booking: `platform` + `booking-module`; monorepo: `platform`),
and per-repo tokens can't express that. The unified token is pushed to servers by the
env-push step, so it never goes stale.

Repo spaces:
- App repos (Contents R+W + **Pull requests R+W REQUIRED** — `gh pr merge` does a GraphQL `repository.pullRequest` lookup and fails with "Resource not accessible by personal access token" without it): `nreljed/laravel`, `nreljed/laravel.shop`, `nreljed/laravel.booking`
- Package repos (Contents only): `nreljed/platform` (R+W), `nreljed/blog` (R), `nreljed/shop-module` (R), `nreljed/booking-module` (R)

Rules:
- Never create/rotate per-repo tokens. Always use the one unified PAT.
- The value lives in: `gh secret set GH_TOKEN` in the three app repos + all three servers' `.env`.
- Editing the PAT's permissions/repo list does NOT rotate the token value — no secret/env re-push needed.
- If the server's `.env` token is stale, `/deploy/check` fails at "Fetching latest release" — fix by re-running the env-push POST (see below).
- Keep `DEPLOY_TOKEN` per-repo (it matches `config('app.deploy_token')` in that server's `.env`). Values differ between servers.

### Secrets inventory — `nreljed/laravel.shop` (13)

| Secret | Purpose |
|--------|---------|
| `GH_TOKEN` | ONE unified fine-grained PAT (all six repos; Contents R+W + Pull requests R+W) |
| `DEPLOY_TOKEN` | Matches shop server `app.deploy_token` |
| `APP_KEY` | Shop server Laravel key |
| `DB_DATABASE`/`DB_USERNAME`/`DB_PASSWORD` | Shop server MySQL |
| `ADMIN_EMAIL`/`ADMIN_NAME`/`ADMIN_PASSWORD` | Bootstrap admin (first deploy only) |
| `FTP_SERVER`=`ftp.tunispromo.tn` | DirectAdmin FTP |
| `FTP_PORT`=`21` | DirectAdmin FTP port |
| `FTP_USERNAME`=`shoplaravel@tunispromo.tn` | FTP login |
| `FTP_PASSWORD` | FTP password |

Vars (same repo): `APP_URL=https://shop.laravel.tunispromo.tn`, plus others.

`nreljed/laravel` monorepo has the same set — **FTP_* differ per app** (its own domain's
account, `laravel@tunispromo.tn`). The monorepo workflow HAS an FTP step (added 2026-08-15,
verified `FTP upload OK: 226` in v0.15.1 deploy).

### gh CLI cheat sheet

```bash
gh secret list -R nreljed/laravel.shop
gh secret set NAME -b "value" -R nreljed/laravel.shop
gh secret delete NAME -R nreljed/laravel.shop
gh secret list -R nreljed/laravel
gh run list -R nreljed/laravel.shop
gh run watch <id> -R <repo>
gh run view <id> -R <repo> --log-failed
gh run view <id> -R <repo> --log | Select-String "pattern"
gh release list -R <repo>
gh release view vX.Y.Z -R <repo>
```

## env-push step (in both deploy.yml files)

Runs before the upload, `if: always()` + `continue-on-error: true`, 3 retries:

```bash
ENV_URL="${{ vars.APP_URL }}/deploy/env?token=${{ secrets.DEPLOY_TOKEN }}"
curl -s -X POST -H "Content-Type: application/json" \
  -d "{\"values\":{\"GITHUB_TOKEN\":\"${{ secrets.GH_TOKEN }}\"}}" "$ENV_URL"
```

Expected: `{"success":true,"updated":["GITHUB_TOKEN"]}`.

To push manually from PowerShell (mind the 5/min throttle):
```powershell
$json = '{"values":{"GITHUB_TOKEN":"<unified-token>"}}'
Set-Content "$env:TEMP\env-push.json" $json -Encoding ascii
curl.exe -sS -X POST -H "Content-Type: application/json" --data-binary "@$env:TEMP\env-push.json" "https://<app>.tunispromo.tn/deploy/env?token=<DEPLOY_TOKEN>"
```

## FTP fallback (per-app account)

- Each app has its OWN DirectAdmin FTP account; the **account home directory IS that app's
  Laravel project root** (`/home/advertis/domains/<app>.tunispromo.tn/public_html/`).
  Upload to the ROOT path, NOT `domains/.../public_html/...`. The deploy.yml URL is
  `ftp://${{ secrets.FTP_SERVER }}:$ftpPort/deploy.zip` (home root).
- Shop: `shoplaravel@tunispromo.tn`; Monorepo: `laravel@tunispromo.tn`.
- Uses `System.Net.FtpWebRequest` with `UsePassive = $true` (passive is mandatory).
- `if: always()` + `continue-on-error: true` — it never blocks the main deploy. The step
  skips itself when `FTP_*` secrets are unset (script guard).
- It uploads `deploy.zip` but does NOT auto-extract; prints manual extract instructions
  (DirectAdmin File Manager → extract → visit `/deploy/cache-clear`).
- Raw curl from Windows PowerShell can't STOR (no passive flag) — use `curl.exe -T` with
  `--ftp-port -` or the FtpWebRequest code path for testing.

## Core package publish (tag pipeline, not the monorepo)

Core now publishes from the standalone **`nreljed/platform`** repo (and `nreljed/blog`). The old
monorepo `core-publish.yml` / `scripts/core-publish.php` subtree-split flow is **deleted**. To ship a
core change:

1. Edit the core clone (`C:\Users\nrelj\Local Sites\platform` or `...\blog`), commit + push (branch → PR).
2. Tag `vX.Y.Z` on `main`. Core CI hard-fails unless **both** `composer.json` and `package.json`
   `version` match the tag, then creates the GitHub Release automatically and fires the
   `core-updated` `repository_dispatch` to every consumer in the `CORE_CONSUMERS` repo variable.
3. Consumers pull via `composer update nreljed/platform` (+ `npm install` / `pnpm install` →
   `npm run build` → `php artisan migrate`). Their `core-update.yml` opens a validated, lock-only PR.

Never hand-create the release (the tag pipeline owns it) and never hand-edit `vendor/nreljed/*`
or the `packages/*` mirrors. See `docs/core-dashboard-package.md` §7.1 for the publish/consumption contract.

## Deploy Runner (auto-repair / release model)

`scripts/deploy-runner.php` (staged as `public/runner.php` via FTP by `restore.yml`)
is the zero-framework control plane used when `/deploy/*` can't answer (app wedged).
`restore.yml` actions (`status`/`recover`/`install`/`release-install`/`release-rollback`/
`release-cleanup`/`logs`/`health`/`prune`) run over HTTP with `DEPLOY_TOKEN`.

**Hybrid layout / 120s host connection timeout (CRITICAL):**
- The host kills HTTP requests at ~120s regardless of `set_time_limit` (verified:
  prune with a 240s budget returned no JSON; releases on this host cap out ~110s).
- All runner budgets are capped at **85s/action**; long actions return `"partial":true`
  with a `.progress` marker and resume on re-invoke. CI loops while partial.
- `release-install` = atomic Capistrano-style flip: `releases/<ts>-<ver>/` +
  `current` symlink + webroot `public` symlink; legacy webroot moved to
  `public.legacy-*` (restored by rollback when no previous release exists). Shared
  `.env`/`storage` are symlinked from the release; the runner self-copies into each
  release's `public/` so `/runner.php` survives the flip. `release-cleanup` keeps N.
- `rd_layout_of()` defaults missing `layout` to `legacy`; status reports `releases`
  with an `active` flag.
- On Windows/dev, PHP dir symlinks need admin — the 7 release-model tests in
  `DeployRunnerTest` skip there and run on Linux (prod).

## Verify a deploy

- `gh run view <id> --json jobs --jq ".jobs[] | {name, conclusion, steps:[.steps[]|{name,conclusion}]}"`
- Confirm `push-deploy` shows `Push GITHUB_TOKEN to server .env` + `Upload deploy archive to server` success.
- Expected check-first result: `Deployed via /deploy/check (GitHub API)` + `{"status":"deployed","tag":"vX.Y.Z",...}`.
- `gh release list` shows the new tag; optionally `gh run view <id> --log | Select-String "deployed|current"`.
- The `/deploy/upload` fallback can report HTTP 500 "Failed to open ZIP" (Cloudflare cutting
  the large upload); the code then confirms via `/deploy/check` — this is SUCCESS.

## Known failure modes

| Symptom | Cause / Fix |
|---------|-------------|
| **Deploy race on dependabot bursts**: multiple merges within seconds → losers compute the same version from a stale tag baseline; `/deploy/check` polls never match, `/deploy/upload` 500s (hit 2026-08-25 on laravel.shop #166/#167) | FIXED in all three app repos with the standard guard trio: ① `concurrency: { group: deploy-production, cancel-in-progress: true }`, ② `git fetch origin --tags --force` before computing the version, ③ `git ls-remote --exit-code origin "refs/tags/v$VER"` → if it exists, emit `skip=true` and gate the Create-Release step + push-deploy job on `skip != 'true'`. Replicate this pattern in any NEW app repo. |
| Reusable workflow (`on: workflow_call`) fails at startup ("workflow file issue", 0s) after adding an output | `workflow_call.outputs.<id>` REQUIRES both `description` AND `value` keys. Dropping `description` invalidates the whole file (and every caller). |
| `*-update.yml` merge produced a PR but **no deploy ever fired** | Merge step used `GH_TOKEN: github.token`. GITHUB_TOKEN-authored pushes emit NO `push` event, so `deploy.yml` never runs. FIXED in shop (2026-08) and booking (#29): merge step MUST use `GH_TOKEN: ${{ secrets.GH_TOKEN }}` (the PAT). PR creation can keep `github.token`. |
| `gh pr merge` in `core-update.yml` fails: "Resource not accessible by personal access token (repository.pullRequests)" | The fine-grained PAT lacks **Pull requests: Read+Write** on that repo. Add it in the PAT editor (token value does NOT rotate). |
| pwsh step fails "exit code 1" right after a command used inside an `if` condition (e.g. `git ls-remote --exit-code` when tag is absent) | GitHub's pwsh wrapper appends implicit `exit $LASTEXITCODE`; condition commands legitimately return non-zero. Fix: capture `$LASTEXITCODE -eq 0` into a variable, then `$global:LASTEXITCODE = 0`. Prefer `shell: bash` for tag checks (shop/booking use bash — immune). |
| PHP tests OOM silently (exit 255 right after migrations, no test output) | `memory_limit=128M` default. Fix: run tests as `php -d memory_limit=512M artisan test ...` in ALL repos (done 2026-08-25 in shop, monorepo, booking ci.yml/core-update.yml/shop-update.yml). |
| PHPUnit passes locally but view-rendering tests 500 in CI | Vite manifest missing — assets must be built BEFORE PHP tests. Ordering is canonical in all app workflows now; keep it. |
| `npm ci` ERESOLVE (eslint v10 vs eslint-plugin-react peers) in any repo | Append `--legacy-peer-deps` to npm ci (applied everywhere 2026-08-25). |
| ParaTest workers under `--parallel` still at 128M despite `-d memory_limit=512M` | `-d` does NOT propagate to spawned workers; `--passthru-php="'-d' ..."` quoting breaks cross-shell (verified empirically). PERMANENT FIX 2026-08-25: runner PHP `C:\php\php.ini` bumped to `memory_limit = 512M` machine-wide; worker inheritance PROVEN via probe test asserting `ini_get('memory_limit')==='512M'` under `--parallel`. |
| Tag pushed to `nreljed/platform`/`blog` but no release appears | `composer.json` and `package.json` `version` must BOTH equal the tag (without the `v`); core CI hard-fails on any mismatch. Bump both, re-tag |
| Publish succeeded but consumers never got a PR | Dispatch step is `continue-on-error` — check the publish run log for "WARN: dispatch ... failed" (GH_TOKEN missing Contents R+W on the consumer repo), or consumer ran before the release. Fallback: `gh workflow run core-update.yml -R <consumer>`. |
| `npm audit --audit-level=high` fails on nanoid | Need `"overrides": {"nanoid": "^3.3.18"}` in `package.json` + lockfile update |
| `/deploy/check` returns 403 "Invalid or missing deploy token" | Wrong `DEPLOY_TOKEN` used (e.g. passed the secret NAME instead of value). Use the literal value from the server `.env`. |
| `/deploy/env` returns 403 | Same as above; or stale token. |
| `{"error":"No values provided"}` | PowerShell mangled the JSON body. Write body to a temp file and use `--data-binary "@file"`. |
| FTP 530 login failed | Credentials wrong — get fresh ones from DirectAdmin, update `FTP_*` secrets for the right app. |
| FTP 550 / curl 9 "Server denied" | Tried to `CWD domains/.../public_html` — home IS the project root; upload to root. |
| `/deploy/check` fetch fails with stale token | Re-run env-push with the unified `GH_TOKEN` value. |
| CI `check:ci` fails at format/lint | Run `npm run lint:fix`, `npm run format`, `vendor/bin/pint` then commit. |
| Wrong project self-deploys on a server | `config/deploy.php` `github_repo` default `nreljed/laravel` — consumers MUST override it to their own repo. |

## Production Recovery (when the site is broken)

### Recovery flow decision tree

```
Site broken?
  │
  ├─ Can you push to main?
  │   └─ YES → Push a fix → CI auto-deploys → done
  │
  ├─ App still responds HTTP (even with errors)?
  │   └─ POST /deploy/cache-clear?token=X
  │       (migrate:fresh + seed + rebuild caches — nuclear option)
  │       This is the fastest recovery. Needs only the deploy token.
  │
  ├─ App dead (500 / white screen / PHP fatal)?
  │   └─ Upload deploy.zip via FTP (CI does this automatically)
  │      Then: POST /deploy/cache-clear?token=X
  │      OR: visit /deploy/cache-clear?token=X in browser
  │
  └─ Only DB corrupt but app runs?
      └─ POST /deploy/reset?token=X&confirm=RESET
         (resets DB without touching code)
```

### Available recovery endpoints

All token-protected endpoints require `DEPLOY_TOKEN` (the per-repo secret, NOT `GH_TOKEN`).
None require auth, sessions, or a working database — they only need the deploy token.

| Endpoint | Method | What it does | Use case |
|----------|--------|-------------|----------|
| `/deploy/check?token=X` | GET/POST | Pull latest release from GitHub, extract, migrate, cache | Standard deploy (CI does this) |
| `/deploy/cache-clear?token=X` | GET/POST | `migrate:fresh --seed` + rebuild all caches | **Nuclear reset** — fixes most broken states |
| `/deploy/reset?token=X` | POST | `migrate:fresh --seed` via `DatabaseMaintenanceService` | DB-only reset (requires `confirm=RESET`) |
| `/deploy/upload?token=X` | POST | Accept zip upload, extract, migrate, cache | When GitHub API is unreachable |
| `/deploy/migrate?token=X` | POST | Run `migrate` only (non-destructive) | Schema drift without data loss |
| `/deploy/create-admin?token=X` | POST | Create admin user | Lost admin access |
| `/deploy/env?token=X` | POST | Update .env values | Stale config, wrong DB creds |

### Quick manual recovery (from any machine)

```powershell
# Nuclear reset — wipe DB + rebuild caches (fastest fix for most issues)
curl.exe -sS "https://shop.laravel.tunispromo.tn/deploy/cache-clear?token=YOUR_DEPLOY_TOKEN"

# Or just re-deploy latest release
curl.exe -sS "https://shop.laravel.tunispromo.tn/deploy/check?token=YOUR_DEPLOY_TOKEN"
```

### What NOT to rely on for production recovery

| Path | Why it fails |
|------|-------------|
| Admin UI (`/dashboard/admin/database`) | **Disabled by default** (`database_maintenance_enabled` setting off). Needs auth + sessions + working DB. |
| `php artisan migrate:fresh` via SSH | Requires SSH access (not available on shared hosting). |
| Manual .env edit | Requires SSH/file access. |

The admin UI is a convenience for staging/dev — **the token-protected endpoints are the actual production recovery mechanism.**

### After a recovery

1. Verify the site loads: `curl -sS https://<app>.tunispromo.tn/ | head -20`
2. Log in as admin and check dashboard
3. If data was wiped (`cache-clear` or `reset`), re-seed demo content if needed
4. Check deploy logs: `gh run list -R <repo> --limit 5`
