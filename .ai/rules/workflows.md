---
paths:
  - '.github/workflows/**'
  - .github/workflows/restore.yml
---

# Workflows

## runs-on must use the RUNNER_CI / RUNNER_PACKAGE variables
Every job declares `runs-on: ${{ vars.RUNNER_CI || 'self-hosted' }}` (or `RUNNER_PACKAGE`
for Windows-bound packaging jobs). The per-repo variables (all 7 nreljed repos) drive the
self-hosted ⇄ GitHub-hosted switch — never write a literal `runs-on: [self-hosted]`.
Reusable workflows resolve `vars` against the *calling* repo. See
`docs/workflow/runners.md` for the full mechanism + flip script.

## Never use actions/cache save-steps on self-hosted runners
On self-hosted persistent runners (no GitHub-hosted runner available), NEVER use `actions/cache@v6` save-steps or `setup-node cache: "npm"`. The post-job cache save tars vendor/node_modules (~600MB) and stalls at 0 bytes uploading over the home uplink, holding the single runner for 10-50 min. Persistent disks already keep vendor/node_modules and composer/npm keep their own local disk caches, so GitHub cache reload is pure overhead. Removed from consumer-ci.yml (dashboard-core) + all deploy.yml / deploy-package.yml. Core's own ci.yml never had it. Cache steps may ONLY be re-added gated `if: vars.RUNNER_CI != 'self-hosted'` for GitHub-hosted (ephemeral) runners.

## Grep runner JSON with spaced AND compact forms
deploy-runner.php responds with json_encode(..., JSON_PRETTY_PRINT) — every "key": value has a space after the colon. Any bash grep that checks runner output must match BOTH forms, e.g. '"ok":true\|"ok": true'. The compact-only grep was a real bug (first release-install succeeded but the loop misjudged it as busy, then failed on no_deploy_zip). This applies to the mirrored copies in laravel.shop and laravel.booking too.

## Create GitHub releases with gh CLI, not softprops/Actions artifacts
For deploy workflows that upload deploy.zip as GitHub release assets, use the `gh` CLI (`gh release create ... deploy.zip`, `shell: pwsh`, env `GH_TOKEN: ${{ secrets.GH_TOKEN }}`, delete-and-recreate on a failed rerun), NOT `softprops/action-gh-release` (uploads.github.com rejects its streamed body: "Error creating asset temp dir", 2026-09-17) and NOT `actions/upload-artifact` (deploy.zip stays in release storage to avoid the free-plan 0.5GB Actions artifact quota). Verified across nreljed/laravel, laravel.shop, laravel.booking.
