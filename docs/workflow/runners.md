# CI/CD Runners — self-hosted ⇄ GitHub-hosted switch

> Covers the runner-placement mechanism for all nreljed repos, the current state
> (self-hosted default), and the exact flip procedure. Related: [`deploy.md`](deploy.md)
> (pipeline + server flow), the `self-hosted-runners` skill
> (`~/.config/opencode/skills/self-hosted-runners/SKILL.md`), and the flip script
> `C:\tools\switch-runners.ps1`.

## Why two variables, not one

GitHub-hosted runners bill by minute and **Linux is 1× vs Windows 2×**, so jobs are
split into what can run on cheap Linux and what is genuinely Windows-bound:

| Variable         | Cloud value      | Jobs                                                                      | Why cloud Linux/Windows                                                       |
| ---------------- | ---------------- | ------------------------------------------------------------------------- | ----------------------------------------------------------------------------- |
| `RUNNER_CI`      | `ubuntu-latest`  | validators, push-deploy, update workflows, core/module CI, PW reusable-ci | Bash-portable, cheap                                                          |
| `RUNNER_PACKAGE` | `windows-latest` | deploy-package, shop/booking build, Project-WP release/build              | pwsh `Compress-Archive`, `C:/Program Files/7-Zip/7z.exe`, `C:\php` preinstall |

`nreljed` is a **GitHub User account (no org)** → there are **no org-level
variables**, so the switch is enforced via **per-repo** Actions variables
(`RUNNER_CI`, `RUNNER_PACKAGE`), currently set to `self-hosted` on all 7 repos.
GitHub has **no native auto-failover** between runner types — the variable value IS
the mechanism, flipped manually (web UI or `gh variable set`).

## How `runs-on` reads them

Every job declares:

```yaml
runs-on: ${{ vars.RUNNER_CI || 'self-hosted' }}
# or for packaging jobs:
runs-on: ${{ vars.RUNNER_PACKAGE || 'self-hosted' }}
```

The `|| 'self-hosted'` fallback means a missing/unset variable degrades safely to
today's behavior (no job can silently land in the wrong place).

**Reusable workflows (`workflow_call`)** resolve `vars` against the **calling**
repo — `consumer-ci.yml` lives in `platform` but reads `RUNNER_CI` from the
consumer (laravel/shop/booking), so each consumer must have the variable set.
All 7 repos carry both variables.

## Current state (as of 2026-09-12)

- `RUNNER_CI = self-hosted` and `RUNNER_PACKAGE = self-hosted` on **all** repos:
  nreljed/laravel, laravel.shop, laravel.booking, platform, blog, shop-module,
  booking-module, Project-WP.
- All `runs-on:` lines reference the vars (audited — zero literal `self-hosted`
  remain in workflow files).
- Reason: GitHub-hosted runners are **billing-blocked account-wide**; self-hosted
  is the only option until the block lifts. The variable indirection exists so the
  flip to cloud is config-only.

### Cache rules follow the runner

Persistent self-hosted runners have their own local disk caches, so GitHub cache
upload stalls the runner (see `.ai/rules/workflows.md`). Cache steps in
`consumer-ci.yml` are therefore **re-enabled only on cloud**:

```yaml
- if: vars.RUNNER_CI != 'self-hosted'
  uses: actions/cache@v6
  ...
- uses: actions/setup-node@v6
  with:
    cache: ${{ vars.RUNNER_CI != 'self-hosted' && 'npm' || '' }}
```

`deploy-package.yml` stays cache-free (single slow Windows job). Do not re-add cache
save-steps unconditionally.

## Flipping runners

### All repos at once (recommended)

```powershell
pwsh -NoProfile -File C:\tools\switch-runners.ps1 self   # → self-hosted (default)
pwsh -NoProfile -File C:\tools\switch-runners.ps1 cloud  # → ubuntu-latest / windows-latest
```

### Single repo

```bash
gh variable set RUNNER_CI       -R nreljed/laravel --body ubuntu-latest
gh variable set RUNNER_PACKAGE  -R nreljed/laravel --body windows-latest
# flip back:
gh variable set RUNNER_CI       -R nreljed/laravel --body self-hosted
```

### Verify

```powershell
# vars present + values on all repos
gh variable get RUNNER_CI -R nreljed/laravel
# jobs resolved to the intended label:
gh api repos/nreljed/laravel/actions/runs/<run-id>/jobs --jq '.jobs[].labels'
# recent run health:
gh run list -R nreljed/laravel --limit 5
```

## Caveats

- **Project-WP** hardcodes a preinstalled `C:\php` toolchain in its workflows — a
  cloud flip breaks its PHP jobs until `setup-php` is added. It is staged with the
  variable indirection for consistency only. Do **not** set its `RUNNER_*` vars to
  cloud values until that is fixed.
- `vars` do **not** exist in `workflow_dispatch` inputs or on forks (fork PRs have
  no access to repo vars) — the `|| 'self-hosted'` fallback covers that.
- Self-hosted runners do **not** auto-start — boot them on demand via
  `pwsh -NoProfile -File C:\tools\gha-runner.ps1 <name|all>` after a reboot (see
  the `self-hosted-runners` skill).
