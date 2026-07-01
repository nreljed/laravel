# Deployment Guide

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

## First-Time Setup

### 1. GitHub Secrets

| Secret | Purpose |
|--------|---------|
| `DEPLOY_TOKEN` | Protects deploy endpoints — must match `config('app.deploy_token')` |
| `GH_TOKEN` | GitHub fine-grained PAT with **Contents: Read and Write** — used by server to fetch releases AND by build workflow to create releases |
| `ADMIN_EMAIL` | Email for the initial admin user (created on first deploy via `app:create-admin`) |
| `ADMIN_PASSWORD` | Password for the initial admin user |
| `ADMIN_NAME` | Display name / username for the initial admin user |

Create the PAT at `https://github.com/settings/tokens?type=beta`:
- **Repository access**: "Only select repositories" → your repo
- **Permissions**: Repository permissions → **Contents: Read and Write**
- Copy token → add as `GH_TOKEN` secret

> `GITHUB_TOKEN` is reserved by GitHub — always use `GH_TOKEN` as the secret name.

### 2. First Deploy

1. Push to `main` → workflow creates a Release with `deploy.zip`
2. Download `deploy.zip` from the Release page on GitHub
3. Upload to cPanel and extract at `public_html/`
4. Verify: `curl "https://yoursite.com/deploy/check?token=YOUR_TOKEN"` → `{"status":"current"}`

That's it. Every future push auto-deploys within seconds of the build finishing.

### 3. Admin User Bootstrap

On every deploy, the server runs `app:create-admin` after `migrate`. On the first deploy (empty database), it creates the admin user from the GitHub secrets passed as `X-Admin-*` headers:

| Header | GitHub Secret | Example |
|--------|--------------|---------|
| `X-Admin-Email` | `ADMIN_EMAIL` | `admin@example.com` |
| `X-Admin-Password` | `ADMIN_PASSWORD` | `your-secure-password` |
| `X-Admin-Name` | `ADMIN_NAME` | `Admin` |

If the admin already exists, the command skips gracefully. You can also run it manually:

```bash
php artisan app:create-admin --email=admin@example.com --password=secret
```

> **Important**: `ADMIN_EMAIL` and `ADMIN_PASSWORD` must be added as GitHub secrets before the first push. The `PASSWORD` is only transmitted over HTTPS (as a header) and is never stored in the codebase.

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
| `/deploy/check` | GET | Main entry point — checks GitHub, downloads, extracts, runs artisan |
| `/deploy/cache-clear` | GET | Emergency reset — runs migrate:fresh + cache clear (manual safety net) |
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

### Manual Deploy Check (`.github/workflows/cron-deploy.yml`)

A workflow you can trigger manually from the GitHub Actions tab (no automatic schedule). It sends a single curl request to `/deploy/check?token=...` on the server, telling it to check GitHub for a new release and deploy.

When to use:
- The auto-trigger (final step in deploy.yml) failed but the Release was created
- You want to re-deploy the latest existing Release without rebuilding
- Server needs a kick after a transient network error

### Stale Bot (`.github/stale.yml`)

- Issues/PRs with 60 days of inactivity get the `stale` label
- Closed after 14 more days if no activity
- Exempts `pinned`, `security`, and `blocked` labels

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
