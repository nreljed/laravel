# Deployment

> Canonical flow (shared with `laravel.shop`) — see also [`../github-cli.md`](../github-cli.md),
> [`runners.md`](runners.md) (runner placement: self-hosted default, cloud switch
> via `RUNNER_CI`/`RUNNER_PACKAGE`), and the `deploy` skill
> (`~/.config/opencode/skills/deploy/SKILL.md`). Server-side
> `DeployController`/`DeployService`/`deploy:auto` all ship from `nreljed/platform`
> (see `docs/core-dashboard-package.md` §7.1 — the platform package section).

## CI/CD Pipeline

Deploys automatically on push to `main`. The pipeline has four sequential jobs:

1. **ci** — lint, typecheck, test, build (`RUNNER_CI`, cloud: ubuntu-latest)
2. **package** — build `deploy.zip` on Windows (`RUNNER_PACKAGE`, cloud: windows-latest)
3. **release** — create GitHub Release with `deploy.zip` artifact
4. **push-deploy** — env-push + check-first deploy (upload/FTP fallback)

### push-deploy (canonical check-first flow)

The `push-deploy` job prefers `/deploy/check` over upload — the server pulls `deploy.zip`
from the GitHub API itself, avoiding a large upload through the edge (Cloudflare cuts
connections ~100s, which produced HTTP 500 "Failed to open ZIP"):

1. **env-push** — `POST /deploy/env` with the unified `GITHUB_TOKEN` (`if: always()`,
   `continue-on-error`) so the server can authenticate its self-deploy.
2. **Poll `/deploy/check`** (8 × 45s) — accept `"status":"deployed"` or `"status":"current"`
   matching the release tag. During extraction the server returns HTML/500 transiently —
   that means "still busy", keep polling.
3. **Fallback `/deploy/upload`** — only if check didn't confirm, then **confirm via check**
   again (the upload response can be lost mid-extract).
4. **FTP fallback** (`if: always()`, `continue-on-error`) — DirectAdmin account whose home
   IS the app project root (`laravel@tunispromo.tn`); uploads `deploy.zip` to
   `ftp://server:21/deploy.zip`. Skips itself when `FTP_*` secrets are unset.

**Resilience:** if the host's security layer blocks CI (datacenter) IPs, the server still
self-deploys via the scheduled `deploy:auto` command (`routes/console.php`, every 5 min,
`withoutOverlapping`) within ~5 minutes. The job fails so CI is honest, but the release
already exists and will be picked up.

### Server Deploy Flow (`DeployController::check()`)

1. Verify token + lock file (10 min TTL)
2. Fetch latest release from GitHub API
3. Download `deploy.zip`, extract, restore `.env`
4. `cleanStaleMigrations()` — removes any migration files absent from the release
5. `php artisan migrate`
6. `php artisan app:create-admin`
7. `php artisan config:cache`, `route:cache`, `view:cache`
8. `php artisan storage:link`

### Release Model (`deploy-runner.php` auto-repair fallback)

The standalone runner (`scripts/deploy-runner.php`, staged as `public/runner.php` via
FTP) is the zero-framework control plane used when `/deploy/*` cannot answer. Its
`release-install` action implements a Capistrano-style **atomic release layout**:

- Extracts `deploy.zip` into `releases/<ts>-<version>/` (budget-aware, resumable
  via a `.progress` marker — each HTTP pass runs ~85s before the host kills the
  connection, so big zips resume on re-invoke).
- Links the shared `.env` + `storage` into the release, then atomically flips the
  webroot `public/` symlink to the new release (`public` → the docroot). A legacy
  in-place webroot is moved aside to `public.legacy-*` on first migration and
  restored by rollback when no previous release exists.
- `current/` records the active release; `home/.deployed-version` mirrors it.
- `release-rollback` re-points `public`/`current` to the previous release
  (or restores the legacy webroot); `release-cleanup` keeps the newest N releases.
- The runner self-copies into each release's `public/`, so `/runner.php` keeps
  answering after the flip even if the new app is broken.

`restore.yml` drives it: FTP-stages `runner.php` (+ `deploy.zip` for release-install),
invokes the action over HTTP with `DEPLOY_TOKEN`, loops while extraction is `partial`,
then polls health. `deploy.yml` dispatches `recover` then `release-install` when the
check/upload path cannot confirm a deploy.

## Endpoints

All deploy endpoints are token-protected, CSRF-exempt, and rate-limited
(5 requests per 1 minute per IP). Defined in `vendor/nreljed/platform/routes/web.php`.

| Endpoint               | Method   | Purpose                                                                    |
| ---------------------- | -------- | -------------------------------------------------------------------------- |
| `/deploy/upload`       | POST     | Upload `deploy.zip` directly (fallback path)                               |
| `/deploy/check`        | GET/POST | Server pulls latest release from GitHub API, self-deploys (preferred path) |
| `/deploy/env`          | POST     | Update server `.env` values (rejects `APP_KEY`/`DEPLOY_TOKEN`)             |
| `/deploy/cache-clear`  | GET/POST | Emergency config/route/view cache clear                                    |
| `/deploy/migrate`      | POST     | Run pending migrations only                                                |
| `/deploy/create-admin` | POST     | Admin bootstrap/reset (see below)                                          |
| `/deploy/reset`        | POST     | Database reset (env-gated, see AGENTS.md)                                  |

### /deploy/create-admin

Sets or resets the admin user password. Useful when `ADMIN_PASSWORD` env var is not set
or you need to regain access.

```bash
# Token in query string, password in query string
curl -X POST "https://laravel.tunispromo.tn/deploy/create-admin?token=TOKEN&password=newpassword"

# Or token in query string, custom email/name in POST body
curl -X POST "https://laravel.tunispromo.tn/deploy/create-admin?token=TOKEN&password=newpassword&email=admin@example.com&name=Admin"
```

**Note:** The current password becomes `password` immediately — no current password check
is required, so use with caution.

## Versions & Update Chain

### App version vs package version

- **App versions** (`v<x.y.z>` tags on this repo, laravel.shop, laravel.booking) identify a
  **release artifact**: the whole app tree + `vendor/` snapshot + `deploy.zip`. They are what
  `.deployed-version` and `/deploy/check` report as the deploy tag.
- **Package versions** (`v0.1.x` on `nreljed/platform`, plus `nreljed/blog`, `nreljed/shop-module`,
  `nreljed/booking-module`) identify a **bundle** of engine/feature code that consumers copy in
  via Composer. Packages never ship standalone — only through consumer app releases.
- There is intentionally no coupling between the two series. An app release does not bump
  package versions; a package release does not deploy anything by itself (it only notifies
  consumers).

### composer.lock = pin of record

`composer.lock` (per app/monorepo root) is the **pin of record** for which package versions a
release will run. Never rely on `composer.json` ranges — they are floors, not pins. Commit
`composer.lock` every time a package bumps. The CI `release` job also records the pinned
package versions so a release's SBOM is reproducible from git history.

### SBOM-ish report from `/deploy/check`

Every `/deploy/check` (and `deploy:auto`) response includes a `core` envelope reporting the
**installed platform package**, resolved at runtime from the authoritative install state
`vendor/composer/installed.json` (never build-time snapshots):

```json
"core": { "name": "platform", "version": "0.1.57", "sha": "<commit>", "repo": "nreljed/platform" }
```

As of platform `v0.1.58` the envelope also includes `bundled` — every installed `nreljed/*`
package (`name`, `version`, `sha`, `repo`) read from the same installed.json, i.e. the
runtime SBOM of the deploy. Use it to confirm exactly which engine feature code a release
actually carries. Consumers at older pins see only `core` (platform identity).

### Full update chain

Package change → consumer deploy, end to end:

1. **Package tag** — bump `version` in the package's `composer.json` + `package.json`
   (must match — core CI fails the release job on mismatch), tag `v0.x.y`, push. The
   package's CI validates then auto-creates the GitHub Release (`--generate-notes`,
   git-cliff from commit history).
2. **Dispatch** — the core `release` job POSTs `repository_dispatch` `core-updated`
   (`blog-updated` for blog) to `nreljed/laravel`, `nreljed/laravel.shop`,
   `nreljed/laravel.booking` with the new version.
3. **Update pull** — each consumer's `core-update.yml` runs on the dispatch **and** on a
   weekly cron fallback (Mon 09:00 UTC, plus manual
   `gh workflow run core-update.yml -R <consumer>`). It runs
   `composer update nreljed/platform nreljed/blog` (private vcs repos authenticated via the
   unified `GH_TOKEN`), **validates the exact tree** (full CI suite on self-hosted runner),
   opens a **lock-only PR** and squash-merges it with the PAT (a `github.token` merge would
   emit no `push` event — deploys would never fire).
4. **CI → deploy** — the merge pushes `main`, the normal deploy pipeline runs, and the
   release carries the new `composer.lock` (pin of record).

After this monorepo's package mirror sync, `packages/*` snapshots must match the released
tag for path-repo/dev consumers.

## GitHub Secrets

| Secret                                                      | Purpose                                                                                 |
| ----------------------------------------------------------- | --------------------------------------------------------------------------------------- |
| `DEPLOY_TOKEN`                                              | Per-repo, must match `config('app.deploy_token')` on that server                        |
| `GH_TOKEN`                                                  | **ONE unified** fine-grained PAT (apps Read+Write, package repos Read) — never per-repo |
| `DB_DATABASE` / `DB_USERNAME` / `DB_PASSWORD`               | Production MySQL                                                                        |
| `ADMIN_EMAIL` / `ADMIN_PASSWORD` / `ADMIN_NAME`             | Initial admin bootstrap                                                                 |
| `FTP_SERVER` / `FTP_PORT` / `FTP_USERNAME` / `FTP_PASSWORD` | DirectAdmin FTP fallback (`laravel@tunispromo.tn`, home = project root)                 |

Full table + `gh` cheat sheet: [`docs/github-cli.md`](../github-cli.md).

## Rate Limiting

All deploy routes use `throttle:5,1` — 5 requests per 1 minute per IP. If you hit
the limit, wait 60 seconds and retry. The old `throttle:5,60` (5 per 60 **minutes**)
was fixed in v0.1.30.

## Known Issues

- **Cloudflare cuts large uploads (~100s):** handled by preferring `/deploy/check`
  (server-side GitHub download); upload is only a fallback.
- **Host blocks datacenter IPs:** the scheduled `deploy:auto` self-deploy picks the
  release up within ~5 minutes even when CI cannot reach the server.
- **`ADMIN_PASSWORD` not in build .env:** The `deploy-package.yml` generates `.env` without
  `ADMIN_PASSWORD`. The admin bootstrap during deploy always returns "Password is required."
  Use `/deploy/create-admin` after deploy to set a password.

## After Schema/Seeder Changes

```bash
php artisan migrate
php artisan db:seed --class=RolesAndPermissionsSeeder
```

Then run typecheck + build + phpunit and deliver results.
