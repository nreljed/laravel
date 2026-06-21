# Deployment Guide

## Architecture

```
┌──────────────────────────────────────────────────────────────┐
│  git push origin main                                        │
│       ↓                                                      │
│  build-deploy.yml (self-hosted GitHub runner)                │
│       • composer install --no-dev                            │
│       • npm ci + npm run build                               │
│       • generate .env from secrets                           │
│       • copy scripts/deploy-cron.php → deploy.php            │
│       • package deploy.zip (excludes bootstrap/cache)        │
│       • create GitHub Release with deploy.zip attached       │
└──────────────────────────┬───────────────────────────────────┘
                           │ Release published
┌──────────────────────────▼───────────────────────────────────┐
│  cron-deploy.yml (GitHub-hosted runner, every 5 min)         │
│       • calls GET https://site.com/deploy/check?token=...    │
└──────────────────────────┬───────────────────────────────────┘
                           │ HTTP request
┌──────────────────────────▼───────────────────────────────────┐
│  /deploy/check endpoint (on the server, in Laravel)          │
│       • checks .deploy-lock (prevents concurrent runs)       │
│       • reads GITHUB_TOKEN from .env                         │
│       • fetches latest release from GitHub API               │
│       • compares with .deployed-version                      │
│       • if new: downloads deploy.zip → extracts → artisan    │
│       • writes .deployed-version, releases lock              │
└──────────────────────────────────────────────────────────────┘
```

## First-Time Setup

### 1. GitHub Secrets

| Secret | Purpose |
|--------|---------|
| `DEPLOY_TOKEN` | Protects deploy endpoints — must match `config('app.deploy_token')` |
| `GH_TOKEN` | GitHub fine-grained PAT with **Contents: Read** — used by server to fetch releases |

Create the PAT at `https://github.com/settings/tokens?type=beta`:
- **Repository access**: "Only select repositories" → your repo
- **Permissions**: Repository permissions → **Contents: Read**
- Copy token → add as `GH_TOKEN` secret

> `GITHUB_TOKEN` is reserved by GitHub — always use `GH_TOKEN` as the secret name.

### 2. First Deploy

1. Push to `main` → workflow creates a Release with `deploy.zip`
2. Download `deploy.zip` from the Release page on GitHub
3. Upload to cPanel and extract at `public_html/`
4. Verify: `curl "https://yoursite.com/deploy/check?token=YOUR_TOKEN"` → `{"status":"current"}`

That's it. Every future push auto-deploys within ~5-10 minutes.

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

## Deploy Endpoints

All under `/deploy/` and protected by `?token=...`:

| Endpoint | Method | Purpose |
|----------|--------|---------|
| `/deploy/check` | GET | Main entry point — checks GitHub, downloads, extracts, runs artisan |
| `/deploy/cache-clear` | GET | Emergency reset — runs migrate:fresh + cache clear (manual safety net) |
| `/deploy/update-env` | POST | Writes .env from POST body (emergency use only) |

## Troubleshooting

**Deploy not happening after push:**
- Check GitHub Actions → `build-deploy.yml` completed and created a Release
- Check `cron-deploy.yml` ran (Actions tab) — it runs every 5 min on GitHub-hosted runners
- Manually trigger `cron-deploy.yml` from Actions tab to bypass the 5-min wait

**Server returns 403 on /deploy/check:**
- `DEPLOY_TOKEN` in GitHub Secrets doesn't match `.env` on server — update one to match

**Server returns 500 on /deploy/check:**
- Check `storage/logs/laravel.log` on server
- Run `/deploy/cache-clear?token=...` as emergency reset
- Verify `GITHUB_TOKEN` in server `.env` is valid (not expired)

**bootstrap/cache/ overwritten:**
- Run `/deploy/cache-clear?token=...` to regenerate cached config, routes, views
- (Fixed in deploy.yml — exluded from zip since commit 82c6aa2)

## Files Not Included in deploy.zip

| Path | Reason |
|------|--------|
| `.env` | Restored from backup during deploy |
| `bootstrap/cache/` | Moved aside before zipping — preserves server's runtime cache |
| `.deploy-*` | Runtime files (lock, log, version) |
| `node_modules/` | Dev only, not needed |
| `storage/` | Logs, sessions, cache — server-specific