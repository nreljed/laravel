# Deployment Guide

## Architecture

```
  push to main (Conventional Commits)
       ↓
 deploy.yml (runs-on via `RUNNER_CI`/`RUNNER_PACKAGE` Actions variables — `self-hosted` by default)
       • checkout (full depth + tags)
       • setup PHP 8.4 + extensions (fileinfo, gd, mbstring, pdo_mysql, pdo_sqlite, intl, bcmath, zip)
       • setup Node 22
       • git-cliff: parse commits → bump semver → generate CHANGELOG.md + release-notes.md
       • generate build .env (SQLite) + empty database.sqlite
       • [cached] composer install --prefer-dist --no-interaction --no-scripts (WITH dev deps)
       • php artisan package:discover
       • php artisan migrate --force
       • [cached] npm install --ignore-scripts --no-audit --no-fund
       • npm run typecheck (tsc --noEmit)
       • npm run build (vite → generates public/build/manifest.json)
       • php artisan test (99 tests, 216 assertions)
       • vendor/bin/pint (auto-fix PHP code style)
       • composer install --no-dev --optimize-autoloader --no-scripts (strip dev deps)
       • overwrite .env → production MySQL config
       • package deploy.zip (excludes bootstrap/cache/)
       • create GitHub Release with semver tag vX.Y.Z + changelog as body
       • curl /deploy/check?token= with admin headers → server ──────┐
                                                                      │
 /deploy/check endpoint (Laravel DeployController)                   │
       • verifies DEPLOY_TOKEN (403 if wrong)                        │
       • checks .deploy-lock (prevents concurrent runs, 10 min TTL)  │
       • reads GITHUB_TOKEN from .env                                │
       • fetches latest release from GitHub API                      │
       • compares with .deployed-version                             │
       • if new: downloads deploy.zip → extracts over project root   │
       • restores .env from backup (never overwritten)               │
       • runs: package:discover, migrate,                            │
         → app:create-admin (from ADMIN_EMAIL/PASSWORD headers),     │
         config:cache, route:cache, view:cache, storage:link         │
       • writes .deployed-version, releases lock                     │
       • returns "deployed" ✓                                        │
```

## Two-Phase Deployment Model

There are **two distinct phases**, and understanding the difference is critical:

| Phase | When | How | Frequency |
|-------|------|-----|-----------|
| **First-time setup** | Brand new server, domain, or database | **Manual** — upload full project, configure .env, run artisan | Once per server |
| **Auto-deploy updates** | Every push to `main` | **Automatic** — CI builds release, server pulls via GitHub API | Every push |

The auto-deploy pipeline (Phase 2) **assumes the project is already running** on the server. It does NOT set up from scratch — it only overlays new code on top of an existing installation.

---

## Phase 1: First-Time Setup (Manual)

### Step 1 — Prepare GitHub Secrets

These must exist before the first push triggers CI:

| Secret | Purpose |
|--------|---------|
| `DEPLOY_TOKEN` | Protects `/deploy/*` endpoints — must match `config('app.deploy_token')` in server `.env` |
| `GH_TOKEN` | GitHub fine-grained PAT with **Contents: Read and Write** — used by the server to fetch releases AND by CI to create releases |
| `DB_DATABASE` | Production MySQL database name |
| `DB_USERNAME` | Production MySQL username |
| `DB_PASSWORD` | Production MySQL password |
| `ADMIN_EMAIL` | Email for the initial admin user (created on first deploy) |
| `ADMIN_PASSWORD` | Password for the initial admin user |
| `ADMIN_NAME` | Display name for the initial admin user |
| `APP_KEY` | Unique Laravel `base64:...` key (generate locally: `php -r "echo 'base64:'.base64_encode(random_bytes(32));"`) |

Create the PAT at `https://github.com/settings/tokens?type=beta`:
- **Repository access**: "Only select repositories" → your repo
- **Permissions**: Repository permissions → **Contents: Read and Write**
- Copy token → add as `GH_TOKEN` secret

> `GITHUB_TOKEN` is reserved by GitHub Actions — always use `GH_TOKEN` as the secret name.

### Step 2 — Push to Trigger First Release

```bash
git push origin main
```

Wait for CI to complete (check GitHub Actions tab). It will:
1. Build the app, run tests, create `deploy.zip`
2. Attach `deploy.zip` to a new GitHub Release (e.g. `v0.1.0`)
3. Try to trigger `/deploy/check` on the server (this will fail on first-time setup since the project isn't deployed yet)

After CI succeeds, note the release version (e.g. `v0.1.0`).

### Step 3 — Upload Project Files to Server

Via cPanel File Manager, FTP, or wget:

**Option A — cPanel File Manager:**
1. Download `deploy.zip` from the GitHub Release page: `https://github.com/nreljed/laravel.shop/releases/download/v0.1.0/deploy.zip`
2. Upload to `public_html/` and extract
3. Move contents UP from the extracted subdirectory into `public_html/` directly
4. Result: `public_html/artisan`, `public_html/app/`, `public_html/public/index.php`, etc.

**Option B — wget directly on server (if available):**
```bash
cd /home/USER/domains/shop.example.com/public_html
wget https://github.com/nreljed/laravel.shop/releases/download/v0.1.0/deploy.zip
unzip deploy.zip
rm deploy.zip
```

### Step 4 — Configure .env

Create `.env` at the project root (`public_html/.env`) with production settings:

```ini
APP_NAME="Shop App"
APP_ENV=production
APP_KEY=base64:YOUR_GENERATED_KEY_HERE
APP_DEBUG=false
APP_URL=https://shop.example.com

DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=your_database_name
DB_USERNAME=your_database_user
DB_PASSWORD=your_database_password

SESSION_DRIVER=file
QUEUE_CONNECTION=sync
CACHE_STORE=file
FILESYSTEM_DISK=local

# Critical for deploys:
DEPLOY_TOKEN=must_match_github_secret
GITHUB_TOKEN=github_pat_... (the GH_TOKEN value)
```

**Important:** The `DEPLOY_TOKEN` value here must EXACTLY match the `DEPLOY_TOKEN` GitHub secret.

### Step 5 — Verify with debug.php

Visit `https://shop.example.com/debug.php?key=sha256_first_16_chars` — this confirms:
- PHP + Laravel boot correctly
- composer autoload works
- .env is parsed
- Database connection works
- All required directories are writable

The key is derived from `APP_KEY`: the first 16 hex characters of `sha256(APP_KEY . 'debug-access')`.

If the page shows 109/110 checks passing, the project is running.

> 🔑 **Keep debug.php** — it's invaluable for first-time setup and troubleshooting. Remove only if security policy requires it.

### Step 6 — Run Initial Artisan Commands

The deploy controller normally runs these automatically, but for first-time setup you need to trigger them:

**Option A — Trigger via browser (recommended):**
Visit `https://shop.example.com/deploy/check?token=YOUR_DEPLOY_TOKEN` in a browser.

The server will:
1. Fetch the latest release from GitHub API
2. Compare with `.deployed-version` (empty on fresh install)
3. Download `deploy.zip` from the release asset
4. Extract over the project root (re-extracts the same files)
5. Restore `.env` from backup (preserves your config)
6. Run: `package:discover`, `migrate --force`, `app:create-admin`, `config:cache`, `route:cache`, `view:cache`, `storage:link`
7. Create the admin user from `X-Admin-Email` / `X-Admin-Password` headers
8. Write `.deployed-version` with the release tag
9. Return `{"status":"deployed","tag":"v0.1.0",...}`

**Option B — Manual (if endpoint fails):**
```bash
cd /home/USER/domains/shop.example.com/public_html
php artisan key:generate  # if not in .env
php artisan migrate --force
php artisan db:seed --class=RolesAndPermissionsSeeder
php artisan storage:link --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
echo "v0.1.0" > .deployed-version
```

**Option C — Emergency reset (if something goes wrong):**
Visit `https://shop.example.com/deploy/cache-clear?token=YOUR_DEPLOY_TOKEN`
⚠️ This runs `migrate:fresh --seed` — it DESTROYS all data.

### Step 7 — Create Admin User

If `/deploy/check` ran successfully, the admin was created automatically. Otherwise:

```bash
cd /home/USER/domains/shop.example.com/public_html
php artisan app:create-admin --email=admin@example.com --password=your-password --name=Admin
```

### Step 8 — Verify

- Visit `https://shop.example.com/debug.php?key=...` — all checks pass
- Visit `https://shop.example.com/login` — login page loads
- Log in with admin credentials
- Visit `/dashboard/admin/system-health` — shows `Deployed: v0.1.0`, `Latest release: v0.1.0`, `Up to date`

### Step 9 — Verify Auto-Deploy Works

Make a small change to any file, commit, and push. The CI should:
1. Build and create a new release
2. Call `/deploy/check?token=...` (without file upload)
3. The server downloads the new release from GitHub API and deploys it
4. The system health page updates to show the new version

---

## Phase 2: Auto-Deploy Updates (Automatic)

After first-time setup, every push to `main` triggers:

```
push → CI builds app + runs tests → creates GitHub Release with deploy.zip
→ calls /deploy/check?token=... (no file, just the URL)
→ server verifies token, checks lock, downloads from GitHub API
→ extracts over project root, restores .env, runs migrate/cache/perm-sync
→ done
```

**Key detail:** The CI does NOT upload the zip file to the server. It calls `/deploy/check?token=...` as a plain GET request, and the server downloads the release asset directly from GitHub's API. This avoids file upload size limits (`post_max_size`, `upload_max_filesize`) entirely.

### Why /deploy/upload Failed

The previous approach sent `deploy.zip` via `curl -F "file=@deploy.zip"` to `/deploy/upload`. The server's `post_max_size` is 128M, and the deploy.zip (containing vendor, assets, etc.) can exceed this. PHP silently drops the POST body when exceeded, so `$request->hasFile('file')` returns false, yielding "No file uploaded" (HTTP 400).

**The fix:** Removed the file upload entirely. The CI now simply calls:
```bash
curl -sf --max-time 300 "https://shop.example.com/deploy/check?token=..." 
```
The server downloads the zip from GitHub API instead — no upload size limits.

### Admin User Bootstrap

On every deploy, the server runs `app:create-admin` after `migrate`. On the first deploy (empty database), it creates the admin user from the GitHub secrets passed as HTTP headers. If the admin already exists, the command skips gracefully.

### Cron-Based Fallback

The server auto-deploys via `deploy:auto` (every 5 min in `routes/console.php`). If a Release exists but the server hasn't deployed yet, hit `/deploy/check?token=...` once (or wait ≤5 min for `deploy:auto`). This replaces the former `.github/workflows/cron-deploy.yml` manual trigger, which was removed.

## Automated Quality Gates

Every push to `main` runs these checks before packaging:

| Step | What it checks | Tool |
|------|---------------|------|
| TypeScript | Type safety | `tsc --noEmit` |
| Build | Assets compile | `vite build` |
| PHP Tests | Unit & feature tests pass | `php artisan test` (PHPUnit) |
| Code Style | PHP follows PSR-12 | `vendor/bin/pint` (auto-fix) |

If tests fail, the build stops and no release is created. Style issues are auto-fixed.

## Versioning & Changelog

Tags follow **Semantic Versioning**: `vMAJOR.MINOR.PATCH`

| Commit type | Bump |
|-------------|------|
| `feat` | minor (1.0.0 → 1.1.0) |
| `fix` | patch (1.0.0 → 1.0.1) |
| `BREAKING CHANGE` or `!` | major (1.0.0 → 2.0.0) |
| `chore`, `docs`, `refactor`, etc. | patch |

Changelog is auto-generated by git-cliff from Conventional Commits. See [`CONTRIBUTING.md`](./CONTRIBUTING.md) for the full convention.

## Deploy Endpoints

All under `/deploy/` and protected by `?token=...`:

| Endpoint | Method | Purpose |
|----------|--------|---------|
| `/deploy/check` | GET | **Main entry point** — no file upload needed. Server fetches latest release from GitHub API and deploys it |
| `/deploy/cache-clear` | GET | Emergency reset — runs migrate:fresh + cache clear (DESTROYS all data) |
| `/deploy/update-env` | POST | Writes .env from POST body (emergency use only) |

## Automated Maintenance

### Dependabot (`.github/dependabot.yml`)

GitHub-native bot that scans your dependencies for updates every Monday. It opens PRs with version bumps so you can review and merge at your own pace.

| Ecosystem | Schedule | Label | Commit format |
|-----------|----------|-------|---------------|
| npm (`package.json` + lock) | Weekly (Monday) | `dependencies`, `npm` | `chore(deps):` / `chore(deps-dev):` |
| Composer (`composer.json` + lock) | Weekly (Monday) | `dependencies`, `composer` | `chore(deps):` |
| GitHub Actions (workflow versions) | Monthly | `dependencies`, `ci` | `chore(ci):` |

PRs include updated lock files (package-lock.json, composer.lock). Merge them to keep dependencies current and secure. These commits are conventional — they trigger a patch version bump.

### Manual Deploy Check (removed)

> `.github/workflows/cron-deploy.yml` was removed on 2026-09-10 as part of a workflow audit — the server's
> `deploy:auto` command (every 5 min) supersedes it. If a Release exists but the server didn't deploy,
> just hit `/deploy/check?token=...` once (or wait ≤5 min for `deploy:auto`).

### Issue & PR Templates

- Bug reports and feature requests use structured YAML forms
- PRs include a checklist for tests, typecheck, lint, and commit format

## Files Created on Server

| File | Role |
|------|------|
| `.env` | Production config with DB creds + `GITHUB_TOKEN` |
| `.deploy-lock` | Prevents concurrent runs (auto-clears after 10 min) |
| `.deployed-version` | Stores the last deployed release tag |
| `.deploy-log` | Log from `deploy.php` (cron fallback only) |
| `CHANGELOG.md` | Full release history (bundled in deploy.zip) |

## Files Handled During Deploy

| Path | How It's Handled |
|------|-----------------|
| `.env` | Included in zip (build env), but server's .env is backed up before extraction and restored after |
| `bootstrap/cache/` | Excluded from zip — moved aside before zipping, preserving server's runtime cache |
| `.deploy-*` | Runtime files (lock, version) — never included in zip |
| `node_modules/` | Dev only, never included |
| `vendor/` | Included — dev deps installed for testing, then stripped with `composer install --no-dev` before packaging |
| `CHANGELOG.md` | Included — generated by git-cliff from conventional commits |

## Troubleshooting

**Deploy not happening after push:**
- Check GitHub Actions → `deploy.yml` completed and created a Release
- Check the final "Trigger deploy on server" step — it should show the response from the server
- If the trigger step failed, run the **Manual Deploy Check** workflow from the Actions tab

**Server returns 403 on /deploy/check:**
- `DEPLOY_TOKEN` in GitHub Secrets doesn't match `.env` on server — update one to match

**Server returns 500 on /deploy/check:**
- Check `storage/logs/laravel.log` on server
- Run `/deploy/cache-clear?token=...` as emergency reset
- Verify `GITHUB_TOKEN` in server `.env` is valid (not expired)

**Site returns 500 after deploy:**
- Check `storage/logs/laravel.log` on server
- Most common cause: old `bootstrap/cache/packages.php` references a dev-only package (like `laravel/breeze`). The fix is already in the deploy pipeline (`package:discover` runs first), but you can manually:
  1. Delete `bootstrap/cache/packages.php` via cPanel
  2. Or hit `/deploy/cache-clear?token=...` for a full reset
- Another cause: `.env` backup/restore failed and `APP_KEY` is missing
