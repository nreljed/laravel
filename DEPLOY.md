# Deployment Guide

## Architecture

```
┌──────────────────────────────────────────────────────────────────┐
│  git push origin main  (use Conventional Commits)               │
│       ↓                                                          │
│  deploy.yml (GitHub-hosted runner, windows-latest)               │
│       • git-cliff: parse commits → bump semver → generate CHANGES│
│       • generate build .env (SQLite) + empty sqlite file         │
│       • [cached] composer install --no-dev --no-scripts          │
│       • php artisan package:discover                             │
│       • overwrite .env with production MySQL config              │
│       • [cached] npm install --ignore-scripts                    │
│       • php artisan test + php artisan pint --test               │
│       • npm run typecheck + npm run build                        │
│       • package deploy.zip (excludes bootstrap/cache)            │
│       • create GitHub Release with semver tag vX.Y.Z             │
│         + auto-generated changelog as release body               │
│       • curl /deploy/check directly on the server ──────────┐    │
└──────────────────────────────────────────────────────────────│───┘
                                                               │
┌──────────────────────────────────────────────────────────────▼──┐
│  /deploy/check endpoint (on the server, in Laravel)             │
│       • checks .deploy-lock (prevents concurrent runs)           │
│       • reads GITHUB_TOKEN from .env                             │
│       • fetches latest release from GitHub API                   │
│       • compares with .deployed-version                          │
│       • if new: downloads deploy.zip → extracts → artisan        │
│       • writes .deployed-version, releases lock                  │
└──────────────────────────────────────────────────────────────────┘
```

## First-Time Setup

### 1. GitHub Secrets

| Secret | Purpose |
|--------|---------|
| `DEPLOY_TOKEN` | Protects deploy endpoints — must match `config('app.deploy_token')` |
| `GH_TOKEN` | GitHub fine-grained PAT with **Contents: Read and Write** — used by server to fetch releases AND by build workflow to create releases |

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

## What the Endpoint Does

```
/deploy/check?token=...
       ↓
┌─────────────────────────────┐
│  Verify DEPLOY_TOKEN        │
│  (403 if wrong)             │
└──────────┬──────────────────┘
           ▼
┌─────────────────────────────┐
│  Read GITHUB_TOKEN from .env │
│  (needed for private repos)  │
└──────────┬──────────────────┘
           ▼
┌─────────────────────────────┐
│  Check .deploy-lock file    │
│  → "locked" if < 10 min old │
└──────────┬──────────────────┘
           ▼ (no lock)
┌─────────────────────────────┐
│  Fetch latest release       │
│  from GitHub API            │
└──────────┬──────────────────┘
           ▼
┌─────────────────────────────┐
│  Compare .deployed-version  │
│  → "current" if same        │
└──────────┬──────────────────┘
           ▼ (new version)
┌─────────────────────────────┐
│  Download deploy.zip asset  │
│  Extract over project root  │
└──────────┬──────────────────┘
           ▼
┌─────────────────────────────┐
│  Restore .env from backup   │
│  (never overwrite settings) │
└──────────┬──────────────────┘
           ▼
┌─────────────────────────────┐
│  Run artisan commands:      │
│  • package:discover (first!)│
│  • migrate                  │
│  • config:cache             │
│  • route:cache              │
│  • view:cache               │
│  • storage:link             │
└──────────┬──────────────────┘
           ▼
┌─────────────────────────────┐
│  Write .deployed-version    │
│  Release .deploy-lock       │
│  Return "deployed" ✓        │
└─────────────────────────────┘
```

## Files Created on Server

| File | Role |
|------|------|
| `.env` | Production config with DB creds + `GITHUB_TOKEN` |
| `.deploy-lock` | Prevents concurrent runs (auto-clears after 10 min) |
| `.deployed-version` | Stores the last deployed release tag |
| `.deploy-log` | Log from `deploy.php` (cron fallback only) |
| `CHANGELOG.md` | Full release history (bundled in deploy.zip) |

## Deploy Endpoints

All under `/deploy/` and protected by `?token=...`:

| Endpoint | Method | Purpose |
|----------|--------|---------|
| `/deploy/check` | GET | Main entry point — checks GitHub, downloads, extracts, runs artisan |
| `/deploy/cache-clear` | GET | Emergency reset — runs migrate:fresh + cache clear (manual safety net) |
| `/deploy/update-env` | POST | Writes .env from POST body (emergency use only) |

## Automated Quality Gates

As part of the CI pipeline, every push to `main` runs these checks before building:

| Step | What it checks | Tool |
|------|---------------|------|
| PHP Tests | Unit & feature tests pass | `php artisan test` (PHPUnit) |
| Code Style | PHP follows PSR-12 | `php artisan pint --test` |
| TypeScript | Type safety | `npm run typecheck` (tsc) |
| Build | Assets compile | `npm run build` (vite) |

If any step fails, the build stops and no release is created.

## Versioning & Changelog

Tags follow **Semantic Versioning**: `vMAJOR.MINOR.PATCH`

| Commit type | Bump |
|-------------|------|
| `feat` | minor (1.0.0 → 1.1.0) |
| `fix` | patch (1.0.0 → 1.0.1) |
| `BREAKING CHANGE` or `!` | major (1.0.0 → 2.0.0) |
| `chore`, `docs`, `refactor`, etc. | patch |

See [`CONTRIBUTING.md`](./CONTRIBUTING.md) for the full commit convention.

## Automated Maintenance

### Dependabot (`.github/dependabot.yml`)
Runs weekly (Mondays) and auto-creates PRs for:

| Ecosystem | What it checks |
|-----------|---------------|
| npm | `package.json` + `package-lock.json` |
| Composer | `composer.json` + `composer.lock` |
| GitHub Actions | Workflow action versions |

PRs are labeled `dependencies` + ecosystem. Merge at your own pace.

### Stale Bot (`.github/stale.yml`)
- Issues/PRs with 60 days of inactivity get the `stale` label
- Closed after 14 more days if no activity
- Exempts `pinned`, `security`, and `blocked` labels

### Issue & PR Templates
- Bug reports and feature requests use structured YAML forms
- PRs include a checklist for tests, typecheck, lint, and commit format

## Troubleshooting

**Deploy not happening after push:**
- Check GitHub Actions → `deploy.yml` completed and created a Release
- Check the final "Trigger deploy on server" step — it should show the response from the server
- If the trigger step failed, run `Manually Deploy Check` from the Actions tab (workflow_dispatch)

**Server returns 403 on /deploy/check:**
- `DEPLOY_TOKEN` in GitHub Secrets doesn't match `.env` on server — update one to match

**Server returns 500 on /deploy/check:**
- Check `storage/logs/laravel.log` on server
- Run `/deploy/cache-clear?token=...` as emergency reset
- Verify `GITHUB_TOKEN` in server `.env` is valid (not expired)

**bootstrap/cache/ overwritten:**
- Run `/deploy/cache-clear?token=...` to regenerate cached config, routes, views
- (Fixed in deploy.yml — exluded from zip since commit 82c6aa2)

## Files Handled During Deploy

| Path | How It's Handled |
|------|-----------------|
| `.env` | Included in zip (build env), but server's .env is backed up before extraction and restored after |
| `bootstrap/cache/` | Excluded from zip — moved aside before zipping, preserving server's runtime cache |
| `.deploy-*` | Runtime files (lock, version) — never included in zip |
| `node_modules/` | Dev only, never included |
| `vendor/` | Included — built with `composer install --no-dev --no-scripts`, dev packages removed |
| `CHANGELOG.md` | Included — generated by git-cliff from conventional commits |

## Troubleshooting

**Site returns 500 after deploy:**
- Check `storage/logs/laravel.log` on server
- Most common cause: old `bootstrap/cache/packages.php` references a dev-only package (like `laravel/breeze`). The fix is already in the deploy pipeline (`package:discover` runs first), but you can manually:
  1. Delete `bootstrap/cache/packages.php` via cPanel
  2. Or hit `/deploy/cache-clear?token=...` for a full reset
- Another cause: `.env` backup/restore failed and `APP_KEY` is missing