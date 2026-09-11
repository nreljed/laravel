# Deployment

> Canonical flow (shared with `laravel.shop`) — see also [`../github-cli.md`](../github-cli.md),
> [`runners.md`](runners.md) (runner placement: self-hosted default, cloud switch
> via `RUNNER_CI`/`RUNNER_PACKAGE`), and the `deploy` skill
> (`~/.config/opencode/skills/deploy/SKILL.md`). Server-side
> `DeployController`/`DeployService`/`deploy:auto` all ship from `nreljed/dashboard-core`
> (see `docs/core-dashboard-package.md` §7.1).

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

## Endpoints

All deploy endpoints are token-protected, CSRF-exempt, and rate-limited
(5 requests per 1 minute per IP). Defined in `packages/dashboard-core/routes/web.php`.

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

## GitHub Secrets

| Secret                                                      | Purpose                                                                                 |
| ----------------------------------------------------------- | --------------------------------------------------------------------------------------- |
| `DEPLOY_TOKEN`                                              | Per-repo, must match `config('app.deploy_token')` on that server                        |
| `GH_TOKEN`                                                  | **ONE unified** fine-grained PAT (Contents: Read+Write on all 4 repos) — never per-repo |
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
