# GitHub CLI (`gh`) — Repository Management Guide

All GitHub administration for this project uses the official [GitHub CLI (`gh`)](https://cli.github.com/), not the web UI. This ensures every operation is scriptable, auditable, and reproducible.

---

## Setup

```bash
# Install (Windows)
winget install --id GitHub.cli

# Or via npm
npm install -g gh

# Authenticate (requires a GitHub PAT with repo scope)
gh auth login
# → Login via web browser, or paste a token
```

---

## Secrets Management

All repository secrets are stored and managed via `gh` — never in local `.env` files or CI configs.

```bash
# Set a single secret
gh secret set SECRET_NAME -b "value" -R nreljed/laravel

# Set a secret from a local file
cat key.pem | gh secret set MY_KEY -R nreljed/laravel

# List all secrets (shows name + last updated date, not values)
gh secret list -R nreljed/laravel

# Delete a secret
gh secret delete SECRET_NAME -R nreljed/laravel
```

### Current Secrets (13)

| Secret           | Purpose                                                                                                               | Set via                                 |
| ---------------- | --------------------------------------------------------------------------------------------------------------------- | --------------------------------------- |
| `APP_KEY`        | Laravel encryption key (`base64:...`)                                                                                 | `gh secret set APP_KEY -b "$(...)"`     |
| `DEPLOY_TOKEN`   | Server-side deploy authentication (matches `app.deploy_token` in this server's `.env`)                                | `gh secret set DEPLOY_TOKEN -b "..."`   |
| `GH_TOKEN`       | **ONE unified fine-grained PAT** covering all seven repos (`laravel`, `laravel.shop`, `laravel.booking`, `platform`, `blog`, `shop-module`, `booking-module`) | `gh secret set GH_TOKEN -b "..."`       |
| `DB_DATABASE`    | Production MySQL database                                                                                             | `gh secret set DB_DATABASE -b "..."`    |
| `DB_USERNAME`    | Production MySQL user                                                                                                 | `gh secret set DB_USERNAME -b "..."`    |
| `DB_PASSWORD`    | Production MySQL password                                                                                             | `gh secret set DB_PASSWORD -b "..."`    |
| `ADMIN_EMAIL`    | Bootstrap admin email                                                                                                 | `gh secret set ADMIN_EMAIL -b "..."`    |
| `ADMIN_PASSWORD` | Bootstrap admin password                                                                                              | `gh secret set ADMIN_PASSWORD -b "..."` |
| `ADMIN_NAME`     | Bootstrap admin display name                                                                                          | `gh secret set ADMIN_NAME -b "..."`     |
| `FTP_SERVER`     | DirectAdmin FTP hostname (`ftp.tunispromo.tn`)                                                                        | `gh secret set FTP_SERVER -b "..."`     |
| `FTP_PORT`       | DirectAdmin FTP port (`21`)                                                                                           | `gh secret set FTP_PORT -b "21"`        |
| `FTP_USERNAME`   | DirectAdmin FTP user (`laravel@tunispromo.tn`)                                                                        | `gh secret set FTP_USERNAME -b "..."`   |
| `FTP_PASSWORD`   | DirectAdmin FTP password                                                                                              | `gh secret set FTP_PASSWORD -b "..."`   |

> **Unified `GH_TOKEN` design**: Composer VCS-clones the private package repos (`platform`, `blog`,
> `shop-module`) in the same install, but `COMPOSER_AUTH` accepts only ONE token per host —
> so a single fine-grained PAT covers all seven repos and is stored as `GH_TOKEN` in BOTH
> `nreljed/laravel` and `nreljed/laravel.shop`, and in BOTH servers' `.env`. The deploy
> workflow's "Push GITHUB_TOKEN to server .env" step keeps server copies fresh on every deploy.
> Never create per-repo tokens; always reuse the one unified PAT.

> **Per-app `DEPLOY_TOKEN`**: unlike `GH_TOKEN`, `DEPLOY_TOKEN` must stay **per-repo** — each
> value matches that server's `app.deploy_token`, so a release built for one app cannot be
> pushed to another app's server.

> **FTP is per-app**: `FTP_*` secrets on `nreljed/laravel` point at the `laravel.tunispromo.tn`
> DirectAdmin account; `nreljed/laravel.shop`'s point at its own account. Each FTP account's home
> IS its app's project root.

> **Security**: Never hardcode `APP_KEY`, `DEPLOY_TOKEN`, `GH_TOKEN`, or any DB credentials in source code. Always use `gh secret set` in CI and `${{ secrets.NAME }}` in workflow files.

---

## Repository Operations

```bash
# View repo details
gh repo view nreljed/laravel

# Fork this repo
gh repo fork nreljed/laravel

# Clone
gh repo clone nreljed/laravel
```

## CI/CD

```bash
# Watch workflow runs
gh run list                           # Recent runs
gh run watch <run-id>                 # Watch a specific run live
gh run view <run-id> --log            # View full logs

# Re-run a failed workflow
gh run rerun <run-id>

# Download artifacts
gh run download <run-id> -n deploy-package
```

## Releases

```bash
# List all releases
gh release list

# Create a new release (auto-generates notes from commits)
gh release create v1.2.3 --generate-notes

# Create with a custom changelog file
gh release create v1.2.3 -F release-notes.md

# Upload assets to an existing release
gh release upload v1.2.3 deploy.zip

# Download the latest release asset
gh release download -p "deploy.zip"
```

## Workflow Management

```bash
# Enable/disable a workflow
gh workflow enable deploy.yml
gh workflow disable deploy.yml

# Trigger a workflow_dispatch
gh workflow run deploy.yml

# List workflow runs for a specific workflow
gh run list --workflow=deploy.yml
```

## Core Package Development (platform — separate repo)

> The `packages/dashboard-core` subtree-split + `core-publish.yml` model is **gone** (2026-08-24).
> `nreljed/platform` is the standalone product repo and the single source of truth. Develop core via
> PRs in `nreljed/platform`; tag `vX.Y.Z` → core CI auto-creates the GitHub Release → dispatches
> `core-updated` to consumers. Apps pull with `composer update nreljed/platform && npm run build`.

```bash
gh pr create -R nreljed/platform            # develop in the platform clone
gh release create vX.Y.Z -R nreljed/platform  # (or push a tag; CI auto-releases)
gh run list --workflow=ci.yml -R nreljed/platform   # run history
gh release list -R nreljed/platform                    # published platform tags
```

## Forking Checklist

When forking this project for a new deployment (e.g. Booking):

1. **Install & auth** `gh` CLI
2. **Set all 13 secrets** via `gh secret set` (see table above) — use that app's own
   `DEPLOY_TOKEN` and that domain's own `FTP_*` account
3. **Override** `config/deploy.php` → `github_repo` set to the new repo (or the server will
   self-deploy the wrong project)
4. **Update** `.github/workflows/deploy.yml`:
   - `APP_URL` and `SESSION_DOMAIN`
   - FTP account home is the project root — upload to root (`ftp://server:port/deploy.zip`), not `domains/.../public_html/...`
5. **Update** config files: `config/features.php` (add the app's `FeatureProvider`), `config/media.php`, settings groups in `config/core.php`
6. **Push to the default branch** — the CI pipeline runs automatically

## Why `gh` over Web UI?

| Task                | Web UI                              | `gh` CLI                                    |
| ------------------- | ----------------------------------- | ------------------------------------------- |
| Set a secret        | 5 clicks + form fill                | `gh secret set KEY -b "val"`                |
| List all secrets    | Click through Settings → Secrets    | `gh secret list`                            |
| Create a release    | Repository → Releases → Draft       | `gh release create v1.2.3 --generate-notes` |
| Watch CI run        | Actions tab → click workflow → wait | `gh run watch <id>`                         |
| Re-run a failed job | 3 clicks                            | `gh run rerun <id>`                         |
| Automation          | Not possible                        | Scriptable in CI/CD pipelines               |
