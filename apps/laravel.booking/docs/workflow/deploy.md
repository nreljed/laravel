# Deployment

## Architecture

```
  push to main (Conventional Commits)
       ↓
 deploy.yml (windows-latest, GitHub-hosted runner)
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
       • php artisan test
       • vendor/bin/pint (auto-fix PHP code style)
       • composer install --no-dev --optimize-autoloader --no-scripts (strip dev deps)
       • overwrite .env → production MySQL config
       • package deploy.zip (excludes bootstrap/cache/)
       • create GitHub Release with semver tag vX.Y.Z + changelog as body
       • curl /deploy/check?token= with admin headers → server
```

## Two-Phase Deployment Model

| Phase | When | How | Frequency |
|-------|------|-----|-----------|
| **First-time setup** | Brand new server, domain, or database | **Manual** — upload full project, configure .env, run artisan | Once per server |
| **Auto-deploy updates** | Every push to `main` | **Automatic** — CI builds release, server pulls via GitHub API | Every push |

The auto-deploy pipeline assumes the project is already running on the server. It only overlays new code on top of an existing installation.

---

## Phase 1: First-Time Setup (Manual)

### Step 1 — Prepare GitHub Secrets

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

### Step 2 — Push to Trigger First Release

```bash
git push origin main
```

Wait for CI to complete (check GitHub Actions tab). It will:
1. Build the app, run tests, create `deploy.zip`
2. Attach `deploy.zip` to a new GitHub Release (e.g. `v0.1.0`)
3. Try to trigger `/deploy/check` on the server (will fail on first-time setup since the project isn't deployed yet)

### Step 3 — Upload Project Files to Server

Via cPanel File Manager, FTP, or wget:

**Option A — cPanel File Manager:**
1. Download `deploy.zip` from the GitHub Release page
2. Upload to `public_html/` and extract
3. Move contents UP from the extracted subdirectory into `public_html/` directly

**Option B — wget directly on server (if available):**
```bash
cd /home/USER/domains/example.com/public_html
wget https://github.com/YOUR_ORG/YOUR_REPO/releases/download/v0.1.0/deploy.zip
unzip deploy.zip
rm deploy.zip
```

### Step 4 — Configure .env

```ini
APP_NAME="Booking App"
APP_ENV=production
APP_KEY=base64:YOUR_GENERATED_KEY_HERE
APP_DEBUG=false
APP_URL=https://example.com

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

### Step 5 — Verify

Visit `https://example.com/debug.php?key=sha256_first_16_chars` to confirm:
- PHP + Laravel boot correctly
- composer autoload works
- .env is parsed
- Database connection works
- All required directories are writable

### Step 6 — Run Initial Artisan Commands

**Option A — Trigger via browser:**
Visit `https://example.com/deploy/check?token=YOUR_DEPLOY_TOKEN` in a browser.

The server will:
1. Fetch the latest release from GitHub API
2. Compare with `.deployed-version` (empty on fresh install)
3. Download `deploy.zip` from the release asset
4. Extract over the project root
5. Restore `.env` from backup
6. Run: `package:discover`, `migrate --force`, `app:create-admin`, `db:seed --class=RolesAndPermissionsSeeder`, `config:cache`, `route:cache`, `view:cache`, `storage:link`
7. Create the admin user from `X-Admin-Email` / `X-Admin-Password` headers
8. Write `.deployed-version` with the release tag

**Option B — Manual (if endpoint fails):**
```bash
cd /home/USER/domains/example.com/public_html
php artisan key:generate
php artisan migrate --force
php artisan db:seed --class=RolesAndPermissionsSeeder
php artisan storage:link --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
echo "v0.1.0" > .deployed-version
```

**Option C — Emergency reset:**
Visit `https://example.com/deploy/cache-clear?token=YOUR_DEPLOY_TOKEN`
⚠️ This runs `migrate:fresh --seed` — it DESTROYS all data.

### Step 7 — Create Admin User

If `/deploy/check` ran successfully, the admin was created automatically. Otherwise:
```bash
php artisan app:create-admin --email=admin@example.com --password=your-password --name=Admin
```

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

The previous approach sent `deploy.zip` via `curl -F "file=@deploy.zip"` to `/deploy/upload`. The server's `post_max_size` can be exceeded, and PHP silently drops the POST body when exceeded. The fix: removed the file upload entirely. The CI now simply calls:
```bash
curl -sf --max-time 300 "https://example.com/deploy/check?token=..."
```

### Admin User Bootstrap

On every deploy, the server runs `app:create-admin` after `migrate`. On first deploy (empty database), it creates the admin user from the GitHub secrets passed as HTTP headers. If the admin already exists, the command skips gracefully.

### Cron-Based Fallback

`.github/workflows/cron-deploy.yml` — manually triggerable from GitHub Actions tab. Calls `/deploy/check?token=...` on demand. Useful if:
- The auto-trigger in deploy.yml failed but the Release was created
- You want to re-deploy an existing Release without rebuilding

## Automated Quality Gates

Every push to `main` runs these checks before packaging:

| Step | What it checks | Tool |
|------|---------------|------|
| TypeScript | Type safety | `tsc --noEmit` |
| Build | Assets compile | `vite build` |
| PHP Tests | Unit & feature tests pass | `php artisan test` (PHPUnit) |
| Code Style | PHP follows PSR-12 | `vendor/bin/pint` (auto-fix) |

## Versioning & Changelog

Tags follow **Semantic Versioning**: `vMAJOR.MINOR.PATCH`

| Commit type | Bump |
|-------------|------|
| `feat` | minor |
| `fix` | patch |
| `BREAKING CHANGE` or `!` | major |
| `chore`, `docs`, `refactor`, etc. | patch |

Changelog is auto-generated by git-cliff from Conventional Commits. See [Contributing](../contributing.md) for the full convention.

## Server Endpoints

| Endpoint | Method | Purpose |
|----------|--------|---------|
| `/deploy/check` | GET | Main entry point — server fetches latest release from GitHub API and deploys it |
| `/deploy/cache-clear` | GET | Emergency reset — runs migrate:fresh + cache clear (DESTROYS all data) |
| `/deploy/update-env` | POST | Writes .env from POST body (emergency use only) |

## Automated Maintenance

### Dependabot (`.github/dependabot.yml`)

GitHub-native bot that scans dependencies for updates every Monday.

| Ecosystem | Schedule | Label |
|-----------|----------|-------|
| npm | Weekly (Monday) | `dependencies`, `npm` |
| Composer | Weekly (Monday) | `dependencies`, `composer` |
| GitHub Actions | Monthly | `dependencies`, `ci` |

### Manual Deploy Check (`.github/workflows/cron-deploy.yml`)

Trigger manually from the GitHub Actions tab. Sends a single curl request to `/deploy/check?token=...` on the server.

### Stale Bot (`.github/stale.yml`)

- Issues/PRs with 60 days of inactivity get the `stale` label
- Closed after 14 more days if no activity
- Exempts `pinned`, `security`, and `blocked` labels

## Files Created on Server

| File | Role |
|------|------|
| `.env` | Production config with DB creds + `GITHUB_TOKEN` |
| `.deploy-lock` | Prevents concurrent runs (auto-clears after 10 min) |
| `.deployed-version` | Stores the last deployed release tag |
| `.deploy-log` | Log from deploy.php (cron fallback only) |
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
- Run the Manual Deploy Check workflow from the Actions tab

**Server returns 403 on /deploy/check:**
- `DEPLOY_TOKEN` in GitHub Secrets doesn't match `.env` on server

**Server returns 500 on /deploy/check:**
- Check `storage/logs/laravel.log` on server
- Run `/deploy/cache-clear?token=...` as emergency reset
- Verify `GITHUB_TOKEN` in server `.env` is valid

**Site returns 500 after deploy:**
- Check `storage/logs/laravel.log` on server
- Most common cause: old `bootstrap/cache/packages.php` references a dev-only package
- Delete `bootstrap/cache/packages.php` or run `/deploy/cache-clear?token=...`
