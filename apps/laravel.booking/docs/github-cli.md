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
gh secret set SECRET_NAME -b "value" -R nreljed/laravel.booking

# Set a secret from a local file
cat key.pem | gh secret set MY_KEY -R nreljed/laravel.booking

# List all secrets (shows name + last updated date, not values)
gh secret list -R nreljed/laravel.booking

# Delete a secret
gh secret delete SECRET_NAME -R nreljed/laravel.booking
```

### Current Secrets (12)

| Secret | Purpose | Set via |
|--------|---------|---------|
| `APP_KEY` | Laravel encryption key (`base64:...`) | `gh secret set APP_KEY -b "$(...)"` |
| `DEPLOY_TOKEN` | Server-side deploy authentication | `gh secret set DEPLOY_TOKEN -b "..."` |
| `GH_TOKEN` | GitHub PAT (Contents R&W) | `gh secret set GH_TOKEN -b "..."` |
| `DB_DATABASE` | Production MySQL database | `gh secret set DB_DATABASE -b "..."` |
| `DB_USERNAME` | Production MySQL user | `gh secret set DB_USERNAME -b "..."` |
| `DB_PASSWORD` | Production MySQL password | `gh secret set DB_PASSWORD -b "..."` |
| `ADMIN_EMAIL` | Bootstrap admin email | `gh secret set ADMIN_EMAIL -b "..."` |
| `ADMIN_PASSWORD` | Bootstrap admin password | `gh secret set ADMIN_PASSWORD -b "..."` |
| `ADMIN_NAME` | Bootstrap admin display name | `gh secret set ADMIN_NAME -b "..."` |
| `FTP_SERVER` | DirectAdmin FTP hostname | `gh secret set FTP_SERVER -b "..."` |
| `FTP_PORT` | DirectAdmin FTP port (21) | `gh secret set FTP_PORT -b "21"` |
| `FTP_USERNAME` | DirectAdmin FTP user | `gh secret set FTP_USERNAME -b "..."` |
| `FTP_PASSWORD` | DirectAdmin FTP password | `gh secret set FTP_PASSWORD -b "..."` |

> **Security**: Never hardcode `APP_KEY`, `DEPLOY_TOKEN`, or any DB credentials in source code. Always use `gh secret set` in CI and `${{ secrets.NAME }}` in workflow files.

---

## Repository Operations

```bash
# View repo details
gh repo view nreljed/laravel.booking

# Fork this repo
gh repo fork nreljed/laravel.booking

# Clone
gh repo clone nreljed/laravel.booking
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
gh run download <run-id> -n deploy.zip
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

## Local Workflow Testing (`act`)

For testing GitHub Actions locally, see `docs/workflow/ci.md` — "Local Testing with `act`" section. Quick start:

```powershell
cd "C:\Users\nrelj\Local Sites\booking-laravel\app"
act -l                                          # List workflows
act pull_request -W ".github/workflows/pr.yml" --secret-file .secrets  # Run PR checks
```

Requires Docker Desktop running + `.secrets` file in project root.

---

## Forking Checklist

When forking this project for a new deployment:

1. **Install & auth** `gh` CLI
2. **Set all 13 secrets** via `gh secret set` (see table above)
3. **Update** `.github/workflows/deploy.yml`:
   - Change `FTP_SERVER`, FTP paths, deploy URL to match your server
   - Update `APP_URL` and `SESSION_DOMAIN`
4. **Update** `.github/workflows/ci-validation.yml`:
   - Adjust PHP/Node versions if needed
5. **Push to `main`** — the CI pipeline runs automatically

## Why `gh` over Web UI?

| Task | Web UI | `gh` CLI |
|------|--------|----------|
| Set a secret | 5 clicks + form fill | `gh secret set KEY -b "val"` |
| List all secrets | Click through Settings → Secrets | `gh secret list` |
| Create a release | Repository → Releases → Draft | `gh release create v1.2.3 --generate-notes` |
| Watch CI run | Actions tab → click workflow → wait | `gh run watch <id>` |
| Re-run a failed job | 3 clicks | `gh run rerun <id>` |
| Automation | Not possible | Scriptable in CI/CD pipelines |

