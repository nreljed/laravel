---
name: self-hosted-runners
description: Self-hosted GitHub Actions runners on the dev laptop for all nreljed repos. Use when setting up runners, diagnosing CI jobs stuck "queued", runners offline, billing-blocked hosted runners ("recent account payments have failed"), broker/session conflicts, renaming or re-registering a runner, or anything mentioning win-runner*, C:\runners\*, or self-hosted labels. Covers the no-service run.cmd design, .env PATH overrides, ghost-session recovery, and Windows-specific gotchas.
---

# Self-Hosted Runners Guide (nreljed repos)

Eight GitHub Actions runners run on this dev laptop — one registration per repo
(GitHub rule: repo-scoped runners can't serve other repos). They exist because
**GitHub-hosted runners are billing-blocked account-wide** ("recent account payments
have failed or your spending limit needs to be increased" kills every hosted job in ~5s).

## Inventory

> **Layout (2026-08-31):** all eight install folders live under `C:\runners\<suffix>` (`C:\runners\shop`,
> `C:\runners\laravel`, `C:\runners\booking`, `C:\runners\platform`, `C:\runners\blog`, `C:\runners\shopmodule`,
> `C:\runners\bookingmodule`, `C:\runners\projectwp`) — they were moved from `C:\actions-runner-*` flat on
> `C:\` root. Each runner still needs its OWN directory (GitHub constraint — one install dir can't serve
> multiple registrations; each has its own `.runner` + `_work`). Moving a folder wholesale **preserves the
> registration** (`.runner`/`.credentials`/`_work` are relative to the folder) — no re-register needed;
> just stop the listener first and update the launcher + Defender exclusions.

| Folder | Runner name | Serves repo | Label used by workflows |
|---|---|---|---|
| `C:\runners\shop` | `win-runner-shop` | `nreljed/laravel.shop` | `self-hosted` |
| `C:\runners\laravel` | `win-runner-laravel` | `nreljed/laravel` | `self-hosted` |
| `C:\runners\booking` | `win-runner-booking` | `nreljed/laravel.booking` | `self-hosted` |
| `C:\runners\platform` | `win-runner-platform` | `nreljed/platform` | `[self-hosted, local]` |
| `C:\runners\blog` | `win-runner-blog` | `nreljed/blog` | `[self-hosted, local]` |
| `C:\runners\shopmodule` | `win-runner-shopmodule` | `nreljed/shop-module` | `[self-hosted]` |
| `C:\runners\bookingmodule` | `win-runner-bookingmodule` | `nreljed/booking-module` | `[self-hosted]` |
| `C:\runners\projectwp` | `win-runner-projectwp` | `nreljed/Project-WP` | `[self-hosted]` |

All auto-generate default labels (`self-hosted`, `Windows`, `X64`) plus custom label `local`.
The two module runners were added 2026-08-24 (shop-module release.yml + booking-module ci.yml).
The Project-WP runner was added 2026-08-29 (version 2.336.0); its CI was flipped from
`ubuntu-latest` to `[self-hosted]` + `defaults.run.shell: bash` at the same time (the
workflows are Linux-authored: sed/cp in `run: |` blocks, node -e multiline, etc.).
Validated fully green 2026-08-29 (run 33240186982): all 5 reusable CI jobs pass on
`win-runner-projectwp` (php-tests, js-tests, php-checks, lint-js, build).

The `platform` runner was renamed 2026-09-12 as part of the dashboard-core → platform
rename: API-delete → folder `C:\runners\core` → `C:\runners\platform` → re-point the `bin`
junction (it survives folder moves pointing at the OLD path) → delete `.runner`/`.runner_migrated`
(v2 flow state) → re-register as `win-runner-platform`. The `blog` runner was added 2026-09-12
(v2.337.0) for the newly created `nreljed/blog` package repo.

**Project-WP Windows-EOL gotcha:** `.gitattributes * text=auto eol=lf` did NOT force LF
working copies inside the runner's persistent `_work` workspace (git kept CRLF even after
force checkout), but an identical fresh clone elsewhere WAS LF — actions/checkout workspaces
are not reproducible. Fix that stuck: `.prettierrc.json` `endOfLine` set to `"auto"` so
`prettier --check` never chokes on the runner's CRLF checkout. Pushing
`.github/workflows/*` changes works directly: all nreljed remotes are plain https URLs and the
active gh keyring token (logged in via `gh auth login`) has `workflow` scope.

**Account artifact-quota gotcha:** `actions/upload-artifact` on this account failed with
"Artifact storage quota has been hit" even though this repo held 0 artifacts — usage is
account-wide across repos and stale builds from the legacy `WP-CRM-dashboard-react` repo
(~185MB) had filled it. Even after deleting them, GitHub recalcs quota only every 6-12h, so
the reliable fix was removing the upload step from the reusable build (releases get their ZIP
via GitHub Release asset, which is separate/unlimited). Check/quota-clean via
`gh api repos/<owner>/<repo>/actions/artifacts`.

## Design: NO Windows services, NO UAC

Services were attempted and abandoned:

- `config.cmd --runasservice` installs fine but the service **fails to start with Win32 error 1068**
  ("dependency service failed") on this machine — known upstream issue class
  (actions/runner#1368), never resolved.
- Service route also requires elevation for every lifecycle operation.

**Official alternative per GitHub docs**: run the runner application directly via `run.cmd`.
No admin rights needed for configure/start/stop.

- **On-demand only (since 2026-08-22)**: runners do NOT auto-start. The startup script
  (`%APPDATA%\Microsoft\Windows\Start Menu\Programs\Startup\start-gha-runners.cmd`) was DELETED
  by user request. Use the launcher instead — it starts a runner, waits for `online`, and
  refuses to kill busy listeners:
  ```powershell
  pwsh -NoProfile -File C:\tools\gha-runner.ps1 booking          # start (waits for online)
  pwsh -NoProfile -File C:\tools\gha-runner.ps1 all status       # local + github state; busy runners list which in-progress job holds them
  pwsh -NoProfile -File C:\tools\gha-runner.ps1 booking stop     # skip if busy (-Force overrides)
  pwsh -NoProfile -File C:\tools\gha-runner.ps1 booking status   # -> local=running github=online/busy -> Deploy to Production#33316... [master]
  pwsh -NoProfile -File C:\tools\gha-runner.ps1 booking start -Force  # kill wedged listener + restart (ghost remediation below)
  ```
- **Workflow implication**: before pushing anything that triggers CI, or when a job is stuck
  `queued`, run the launcher first — offline runners queue jobs indefinitely.
- Consequence: CI only works while opencode (or the user) explicitly started a runner.

## Single runner per repo = serialized jobs (deploy behind a validate is EXPECTED)

Each repo has exactly ONE runner, so webhook-triggered jobs run **FIFO**. The classic surprise:
open a PR (fires `pull_request` → full `validate` CI) and push/merge to the deploy branch at the
same time → the deploy run sits `queued` behind the validate until the runner frees up (~15-18 min).
This is normal, NOT a stuck runner.

Diagnose before touching anything:
```powershell
gh run list -R <repo> --limit 8   # which run is in_progress vs queued
# or the launcher already shows it:
pwsh -NoProfile -File C:\tools\gha-runner.ps1 booking status
```

Remedies when a deploy is blocked by a lower-priority job:
- **Wait** — the default; deploy runs automatically once the validate drains.
- **Cancel the in-progress run** if the deploy is urgent and you accept losing that validate's
  test coverage: `gh run cancel <in_progress_run_id>` frees the runner immediately (the deploy's
  own `build` job still runs `npm run build`, so type safety survives).
- This hit the booking flow 2026-08-30: merging cron-deploy fix #26 fired PR-checks before the
  deploy; deploy sat queued ~30 min until the validate finished, then succeeded via the check
  path. No launcher fix needed — just `status` visibility + this expectation.

## Reproduce a runner from scratch

Run from a normal (non-elevated) shell. `<REPO>` = `owner/name`, `<NAME>` = runner name,
`<FOLDER>` = install dir matching the naming convention above.

```powershell
# 1. Download + extract (version from https://github.com/actions/runner/releases, currently 2.336.0)
mkdir C:\runners\<suffix>; cd C:\runners\<suffix>
# download actions-runner-win-x64-<ver>.zip, expand into folder

# 2. Registration token (expires after 1 hour!)
$t = gh api -X POST repos/<REPO>/actions/runners/registration-token --jq .token

# 3. Register PLAIN (no --runasservice!). MUST go through cmd /c with < NUL:
cmd /c "cd /d C:\runners\<suffix> && config.cmd --unattended --url https://github.com/<REPO> --token $t --name <NAME> --labels local --work _work < NUL"
```

> **Why `cmd /c ... < NUL`:** invoking `config.cmd` from PowerShell with a pipe (`| Select-String`)
> deadlocks its internal `powershell -Sta` unblock step and hangs forever. Closing stdin makes any
> prompt fail fast instead of hanging. Never pipe config.cmd output.

```powershell
# 4. Write the PATH override file (see next section), then start via the launcher:
pwsh -NoProfile -File C:\tools\gha-runner.ps1 <suffix>          # starts + waits for online

# 5. Verify
gh api repos/<REPO>/actions/runners --jq '.runners[] | .name + " " + .status'   # expect: online
```

## The `.env` PATH override (CRITICAL — every runner needs it)

File `<FOLDER>\.env` (one line) — read by Runner.Listener at startup, inherited by job processes.
Current content pattern:

```
PATH=C:\Program Files\Git\bin;C:\php;C:\tools\pwsh;<machine PATH>;C:\Program Files\Git\usr\bin
```

Each element solves a specific failure:

| Entry | Why |
|---|---|
| `C:\Program Files\Git\bin` FIRST | otherwise `shell: bash` resolves to the WSL stub `C:\WINDOWS\system32\bash.EXE` (no distro → instant fail) |
| `C:\php` | composer exists only as `composer.bat` there; Git Bash can't execute `.bat`. A shim file `C:\php\composer` (no extension, `#!/bin/sh exec php "C:/php/composer.phar" "$@"`) plus `composer.phar` copied to `C:\php\` makes composer callable from bash |
| `C:\tools\pwsh` | the only real PowerShell 7; the `WindowsApps\pwsh.EXE` store alias throws EACCES when the runner stats it |
| `...Git\usr\bin` LAST | unix tools (`printf` etc.) used by setup-php scripts; appended last so it never shadows Windows tools |

After ANY `.env` edit: restart the listener (kill `Runner.Listener.exe` for that folder, rerun
`run.cmd`) — env is captured at process start.

## Operations cheat sheet

```powershell
# Status of all eight (GitHub side)
foreach ($repo in 'nreljed/laravel','nreljed/laravel.shop','nreljed/laravel.booking','nreljed/platform','nreljed/blog','nreljed/shop-module','nreljed/booking-module','nreljed/Project-WP') {
  gh api "repos/$repo/actions/runners" --jq '.runners[] | .name+" "+.status+" busy="+(.busy|tostring)' }

# Restart ONE runner (graceful-ish): kill only its listener, then relaunch via run.cmd
Get-Process Runner.Listener | ? { $_.Path -eq 'C:\runners\shop\bin\Runner.Listener.exe' } | Stop-Process -Force
Start-Process cmd -ArgumentList "/c","`"C:\runners\shop\run.cmd`"" -WindowStyle Hidden -WorkingDirectory C:\runners\shop

# RENAME a runner (name+folder are immutable once registered): delete → move folder → clean state files → re-register
$id = gh api repos/<REPO>/actions/runners --jq '.runners[0].id'
gh api -X DELETE "repos/<REPO>/actions/runners/$id"     # API delete is the reliable removal path
Move-Item C:\runners\<old> C:\runners\<new>
Remove-Item C:\runners\<new>\.runner, C:\runners\<new>\.credentials, C:\runners\<new>\.credentials_rsaparams -Force -ErrorAction SilentlyContinue
# then register fresh (steps 3–5 above) with the new --name, update launcher + Defender exclusions
```

Do NOT use `config.cmd remove` for cleanup: it requires a REMOVAL token (not a registration token),
rejects `--unattended`, and its server call 404s intermittently. API DELETE + local file cleanup is deterministic.

## Failure modes (all encountered 2026-08-21 — do not rediscover)

| Symptom | Cause | Fix |
|---|---|---|
| Job stuck `queued`, runner online+idle, diag shows `TaskAgentSessionConflictException: A session for this runner already exists` | Ghost broker sessions from force-killed listeners | Delete runner via API + re-register fresh (see rename flow). Waiting does NOT clear it reliably |
| Job `queued`, runner **busy=true** | Single-runner FIFO serialization — an `in_progress` job from another trigger holds it | NOT a fault. See "Single runner per repo" section: wait, or `gh run cancel` the in-progress run if the queued job is a priority deploy |
| Diag: repeated `SocketException (995)` / backoff loops on `broker.actions.githubusercontent.com` | Same ghost-session cycle tearing down connections | Same fix as above |
| Step error: `Unable to locate executable file: pwsh` / EACCES on `WindowsApps\pwsh.EXE` | Store alias stub shadows real pwsh | `C:\tools\pwsh` before WindowsApps in `.env` PATH |
| Step error: `Second path fragment must not be a drive or UNC name` | Custom `shell:` containing SPACES (`C:\Program Files\Git\bin\bash.exe ...`) — runner splits on whitespace (actions/runner#1825) | Use plain `shell: bash` + Git bin on PATH. NEVER quote spaced paths in `shell:` |
| `bash: composer: command not found` in jobs | composer is `.bat`-only | `C:\php\composer` shim (see .env section) |
| `printf: not recognized` warnings inside setup-php | `Git\usr\bin` missing from PATH | Append it last in `.env` PATH |
| CI takes ~50 min instead of ~12 | npm cache upload over home uplink (`setup-node cache: npm`) on persistent runner | Remove `cache: 'npm'` from workflows — self-hosted disks persist, caching buys nothing |
| Hosted jobs die in seconds with payment/spending-limit error | Account-wide billing block on hosted runners | Use these self-hosted runners (`runs-on: [self-hosted]`) |

## Switch: self-hosted ⇄ GitHub-hosted (RUNNER_CI / RUNNER_PACKAGE vars)

Since 2026-09-11 every nreljed workflow reads its `runs-on` from a **repo-level Actions
variable** instead of a literal label, so the hosted↔self-hosted choice is a config flip,
not a code change:

| Variable | self-hosted (=default) | GitHub-hosted |
|---|---|---|
| `RUNNER_CI` (validators, push-deploy, update workflows, core/module CI) | `self-hosted` | `ubuntu-latest` |
| `RUNNER_PACKAGE` (Windows-bound build/packaging: deploy-package pwsh, 7z) | `self-hosted` | `windows-latest` |

- Two vars (not one) because GitHub-hosted bills **Linux 1x, Windows 2x** — validators are
  bash-portable and must run on cheap ubuntu; only the pwsh `Compress-Archive`/`Set-Content`
  and 7-Zip `7z.exe` packaging jobs are Windows-bound.
- Every `runs-on:` is written `${{ vars.RUNNER_CI || 'self-hosted' }}` (same for PACKAGE) so a
  missing/empty var degrades safely to today's behavior instead of failing the run.
- **Reusable workflows (`workflow_call`)** resolve `vars` against the **CALLING** repo — so
  `consumer-ci.yml` (lives in platform) reads the var from laravel/shop/booking. Each
  consumer repo must have `RUNNER_CI` set for its own CI to work.
- Flip everything across all 8 repos: `pwsh -NoProfile -File C:\tools\switch-runners.ps1 self|cloud`
  (creates/updates both vars per repo, verifies, prints caveats). A browser edit of one repo's
  var flips just that repo.
- GitHub has **no native auto-failover** between runner types (a mixed label list never matches),
  so this variable is the mechanism. `nreljed` is a User account → no org-level vars exist.

**Cache behavior flips with the mode too.** In `consumer-ci.yml`, `actions/cache` + `setup-node
cache:` are gated `if/only when vars.RUNNER_CI != 'self-hosted'` — restored just for ephemeral
GitHub-hosted runners (fast round-trip, saves minutes), kept OFF on the persistent self-hosted
disk where the ~600MB upload stalls. `deploy-package.yml` stays cache-free regardless.

**Project-WP caveat:** its workflows hardcode `C:\php` (preinstalled toolchain) — flipping
`RUNNER_CI`/`RUNNER_PACKAGE` to cloud values will break those jobs until setup-php is added.
It's the only repo not cloud-ready today; the var switch is staged anyway for consistency.

## Performance tuning already applied

- **Runner PHP ini**: `C:\php\php.ini` `memory_limit = 512M` (2026-08-25) so ParaTest
  workers under `--parallel` inherit a safe limit (`-d` flags don't propagate to spawned
  processes; verified with a probe test). Parent `-d` overrides still work per-step.
- **Defender exclusions** (huge win, zip bench 144s → 4.7s): `C:\runners`,
  `%LOCALAPPDATA%\npm-cache`. If adding a runner folder, add its exclusion too
  (admin required). Keep exclusions limited to build dirs.
- Realistic full-deploy time on this hardware (i5-1135G7, 8GB RAM): **~12–18 min** vs ~38 min untuned.

## Token gotchas

- ONE gh account (`nreljed`) with the unified OAuth token (`gho_…`) that covers ALL repos and
  has `repo` + `workflow` scopes — no per-repo PATs, no embedded tokens in remotes. If `gh api
  repos/nreljed/<repo>` 404s or `git push` of workflow files is rejected, the keyring token got
  swapped for a repo-scoped one (`GitHub CLI > 403/404 on other repos`) — fix via
  `gh auth login` with the unified token. There is deliberately NO fine-grained-PAT URL
  workaround anymore.
- Repo setting "Allow GitHub Actions to create and approve pull requests" was enabled via
  `gh api -X PUT repos/nreljed/laravel.shop/actions/permissions/workflow -F can_approve_pull_request_reviews=true`
  so core-update.yml can open its PRs unattended.

## Workflow-authoring gotchas (learned 2026-08-25 while fixing the monorepo)

| Trap | Fix |
|---|---|
| `on: workflow_call` outputs REQUIRE both `description` AND `value`; omitting `description` invalidates the whole workflow file AND every caller (startup_failure, 0s, no logs) | Always write both keys on `workflow_call.outputs.*` and `jobs.<id>.outputs` |
| pwsh steps implicitly end with `exit $LASTEXITCODE` — a non-zero exit from a command inside an `if` condition (e.g. `git ls-remote --exit-code` when the tag is absent) fails the step even though logic was correct | Capture `$LASTEXITCODE -eq 0` into a variable, then `$global:LASTEXITCODE = 0`. Or use `shell: bash`, where condition commands are exempt |
| `php -d memory_limit=...` does NOT reach ParaTest workers under `--parallel`; `--passthru-php="'-d' ..."` quoting breaks cross-shell (verified empirically 2026-08-25) | Runner PHP `C:\php\php.ini` now has `memory_limit = 512M` machine-wide (worker inheritance proven via probe test). Don't re-lower it |

## Cross-references

- Deploy pipeline details, secrets, endpoints: see the `deploy` skill.
- Core-package publish/propagation flow: `docs/core-dashboard-package.md` §7.1.5 in the monorepo.
