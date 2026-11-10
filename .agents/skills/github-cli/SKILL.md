---
name: github-cli
description: "GitHub CLI (gh) and infrastructure for the nreljed fleet. Activate when working with PRs, issues, releases, tags, branch protection, self-hosted runners, repository dispatch, secrets, or GitHub Actions workflows. Covers gh commands, runner management, the PR-based merge flow (branch protection blocks direct push to main), and the release/tag propagation pipeline."
license: MIT
metadata:
  author: nreljed
---

# GitHub CLI & Infrastructure

## Repos

Fleet: `nreljed/laravel` (this app), `nreljed/platform`, `nreljed/blog`,
`nreljed/shop-module`, `nreljed/booking-module`, `nreljed/marketing-module`,
`nreljed/laravel.shop`, `nreljed/laravel.booking`.

## Branch protection (all repos)

- `main` requires PRs; direct push is rejected (`remote: Changes must be made through a pull request`).
- Required status check: `validate / validate / validate` (from `ci.yml` via `pr.yml`).
- `paths-ignore` in `pr.yml` skips `docs/**`, `**.md`, `.gitignore` — docs-only PRs
  won't trigger `validate`, so they can't merge via required checks. Workaround: touch a
  non-ignored file (e.g. append a blank line to `eslint.config.js`) to trigger the check,
  then merge with `--admin`.

## Merge flow

```bash
gh pr create --title "..." --body "..." --base main --head <branch>
# wait for checks
gh pr checks <N> -R nreljed/<repo>
# merge (admin bypass for squash)
gh pr merge <N> -R nreljed/<repo> --squash --admin
```

## Self-hosted runners

- 9 runners on this laptop under `C:\runners\<suffix>` (shop, laravel, booking, platform,
  blog, shopmodule, bookingmodule, marketingmodule, projectwp), label `self-hosted`.
- They do NOT auto-start. Start on demand:
  `pwsh -NoProfile -File C:\tools\gha-runner.ps1 <name|all>`
- Status: `gha-runner.ps1 <name> status`
- Stop: `gha-runner.ps1 <name> stop` (skips busy unless `-Force`)
- Ghost broker sessions (job stuck queued, runner online+busy): kill the listener
  (`Runner.Listener.exe` PID), wait for GitHub to mark offline, then re-register.
  Or register a fresh runner with a new name in a new folder.
- Runner switch (self-hosted ↔ GitHub-hosted): flip `RUNNER_CI` / `RUNNER_PACKAGE`
  per-repo Actions variables. All repos: `pwsh -NoProfile -File C:\tools\switch-runners.ps1 self|cloud`

## Release propagation

- Platform/modules: tag `vX.Y.Z` → CI creates GitHub Release → fires
  `{module}-updated` repository_dispatch → consumers open `composer update` PRs.
- Consumer deploys: push to `main` → `deploy.yml` → ci + deploy-package + release + push-deploy.
- Deploy is check-first: poll `/deploy/check`, fallback `/deploy/upload`, then FTP.

## Secrets (per repo)

`DEPLOY_TOKEN`, `GH_TOKEN` (unified PAT, Contents: Read on platform/blog/modules),
`DB_*`, `ADMIN_*`, `FTP_*`. Full table: `docs/github-cli.md`.

## Common gh commands

```bash
gh pr list -R nreljed/<repo> --state open
gh pr checks <N> -R nreljed/<repo>
gh run list -R nreljed/<repo> --limit 5
gh run view <id> -R nreljed/<repo> --log
gh release list -R nreljed/<repo>
gh api repos/nreljed/<repo>/actions/runners --jq '.runners[].name'
```

## Key gotchas

- Branch protection blocks direct push to `main` — always use PRs.
- `paths-ignore` in `pr.yml` (docs/**, *.md, .gitignore) means docs-only PRs
  won't trigger `validate` — touch a non-ignored file to force it.
- Self-hosted runners don't auto-start; start them before relying on CI.
- Ghost sessions persist ~10–30 min after listener death; API delete fails while "busy".
- The `deploy` workflow's `push-deploy` uses `if: always()` for env-push.