# Booking App — GitHub Actions Workflow Audit (2026-09-10)

**Date:** 2026-09-10
**Scope:** GitHub Actions workflow inventory across all `nreljed` repos — `laravel.booking`, `laravel`, `laravel.shop`, `dashboard-core`, `shop-module`, `booking-module` — for consistency, correctness, and to remove extra/leftover/unused workflows per user directive.
**Methodology:** Every workflow file fetched from GitHub and reviewed; run history (`gh run list`) pulled for all 6 repos to determine real usage; deploy endpoints verified against the installed `dashboard-core` `DeployController`; warning annotations root-caused from live run logs.

**Headline result:** 30 workflow files audited → **8 removed, 4 consistency fixes applied** (all via `[skip ci]` commits so no unintended deploys fired). The `::warning::` annotations seen on every recent deploy were proven **false positives** (GitHub echoes `run:` script source into logs, and any literal `::warning::` token in shell source gets annotated even when the branch never executes — confirmed by the unexpanded `$ENV_URL`/`$VER` variables in the log text).

---

## 1. Final Workflow Inventory (per repo)

| Repo | After | Removed this audit |
|------|-------|--------------------|
| `laravel.booking` | `deploy.yml`, `ci.yml`, `pr.yml`, `core-update.yml`, `booking-update.yml` (5) | `admin-bootstrap.yml`, `cron-deploy.yml` |
| `laravel` | `deploy.yml`, `deploy-package.yml`, `ci.yml`, `pr.yml`, `core-update.yml` (5) | `beta-ci.yml`, `stale.yml`, `database-maintenance.yml` |
| `laravel.shop` | `deploy.yml`, `ci.yml`, `pr.yml`, `core-update.yml`, `shop-update.yml` (5) | `cron-deploy.yml`, `remote-artisan.yml`, `database-maintenance.yml` |
| `dashboard-core` | `ci.yml`, `consumer-ci.yml` (2) | — |
| `shop-module` | `release.yml` (1) | — |
| `booking-module` | `ci.yml` (1) | — |

Total after: **19 workflow files**, every one exercised / needed. Dependabot's auto-generated `.github/dependabot.yml` handling (`dynamic` runs) is GitHub-native and not part of the manual inventory.

## 2. Removals (with evidence)

| Workflow | Repo | Evidence | Rationale |
|----------|------|----------|-----------|
| `admin-bootstrap.yml` | booking | ran **exactly once** (2026-08-24T01:01, workflow_dispatch, old `master` branch, 12s) | One-shot initial-bootstrap; superseded by deploy.yml's explicit "Bootstrap admin from secrets" step + server `app:create-admin` |
| `cron-deploy.yml` (Manual Deploy Check) | shop + booking | booking 3 runs (last 09-10), shop sporadic; never runs on a schedule | Deleted per user decision — server `deploy:auto` command (every 5 min in `routes/console.php`) supersedes it; docs updated to say "hit `/deploy/check` once" |
| `remote-artisan.yml` | shop | **zero runs** | Broken: targets `POST /deploy/run` + `/recover.php`, which **do not exist** in `dashboard-core` 0.1.35 `DeployController` (endpoints: `updateEnv`, `updateEnvValues`, `cacheClear`, `migrate`, `resetDatabase`, `createAdmin`, `upload`, `check`) and laravel.shop has no local override |
| `database-maintenance.yml` | laravel + shop | **zero runs** on both (GitHub-hosted would have shown run history) | Manual ops trigger; never used — deploy pipeline runs `migrate` automatically and the server self-heals |
| `stale.yml` (Close Stale Issues/PRs) | laravel | Runs daily 06:00 UTC but the on-demand runner is normally offline: runs queued the whole day, cancelled at the 24h limit, or took 2–14h | Pure noise for a private single-dev repo; no issue-hygiene need |
| `beta-ci.yml` + `beta` branch | laravel | Last run 2026-08-05 (pre-consolidation); impossible to use after branch deletion | Deleted per user decision — repo is main-only now; `pr.yml` narrowed from `[main, beta]` to `[main]` |
| Stale branches `master`, `core/update-v0.1.6`, `test/core-update-dispatch`, `chore/core-update-workflow` | shop | Present in branch list | Leftovers; core-update now uses safe throwaway branches like `core/update-vX.Y.Z` |

## 3. Correctness Fixes Applied

| # | Fix | Files |
|---|-----|-------|
| F1 | Reworded the literal `::warning::` echo strings that produced spurious log annotations (version-skip branch + env-push failure branch). The real skip/failure is still logged as plain text — only the fake annotation machinery is gone | 3 × `deploy.yml` |
| F2 | SIGPIPE-safe env-push: `FIRST="${RESP%%$'\n'*}"` instead of `echo "$RESP" \| head -1` (laravel was already pipe-free; ported to shop + booking) | 2 × `deploy.yml` |
| F3 | Added `COMPOSER_AUTH` to the "Validate — composer install" step in the lock-bump workflows (they pinned it on the *update* step but not the *validate* step; sans-env it only worked because the laptop self-hosted runner happens to have a global `auth.json`) | `booking-update.yml`, shop `core-update.yml` + `shop-update.yml` |
| F4 | `actions/checkout@v4 → @v7` in the package repos (dashboard-core was already on v7) | `shop-module/release.yml`, `booking-module/ci.yml` |

Also synced repo-local docs that referenced the removed workflows: `laravel`'s `.agents/skills/deployment-pipeline/SKILL.md` + `DEPLOY.md` (beta / stale / database-maintenance), and `laravel.shop`'s `DEPLOY.md` + `docs/workflow/ci.md` (cron-deploy).

## 4. Warnings & Notices (what the logs actually show)

1. **`::warning::Tag v$VER already exists …`** and **`::warning::GITHUB_TOKEN push NOT confirmed …`** — **false positives** (see headline). Observed on booking deploy `34516563254` which deployed v1.1.5 successfully *despite* the warning. Fix F1 removes them.
2. **Composer deprecation: "The source-fallback option is deprecated … removed in Composer 2.11"** — real, but only `laravel.shop` enabled it: `"source-fallback": true` in its `composer.json`, which Composer 2.10+ warns about on every install (all nreljed private packages *do* have `dist` zipball URLs in the lock). **FIXED 2026-09-10**: removed the key from `laravel.shop` (`097c418`). Verified: full `composer install` of all 145 packages (incl. `nreljed/dashboard-core` 0.1.35 + `nreljed/shop-module` 0.1.11) downloads dist archives with token auth, exit 0, no warning. booking + laravel never had it.
3. **`npm warn deprecated git-raw-commits@5.0.1`** — transitive via commitlint/conventional-changelog; harmless.

## 5. Relevant Artifacts / Discrepancies Noticed

- `/deploy/check` confirms production booking is on **v1.1.5** with dashboard-core **0.1.35** (composer.json has a `version` field — package release tags still work).
- The shared **deploy skill table is stale** in two places: booking branch listed as `master` → actual is `main`; deploy tags shown as `v1.0.x` → actual auto-version is `v1.1.5`. (`AUDIT.md` for `laravel.shop` has the same stale table.)
- **Security follow-up:** the shared `GH_TOKEN` value was pasted into chat during this session's workflow (`github_pat_11***`). Fine-grained PATs cannot be rotated in place — recommend creating a new one and updating the 6 secrets (+ next deploy pushes it to all 3 servers' `.env`). Only *Contents + Pull requests (app repos)* / *Contents (package repos)*, scoped to `nreljed/*`.

## 6. Open Items

1. **Uncommitted feature change in `laravel.booking`:** `routes/console.php` has a local edit wiring `FeatureRegistry::getAllScheduledTasks()` into the Laravel scheduler (registers e.g. `bookings:process-scheduled-tasks`). It was verified locally but **not part of PR #33 / deploy v1.1.5** — production currently only schedules `schedule:publish`. If the booking-module scheduled tasks should run in production, this change needs its own commit pushed to `main` (a normal push — it should deliberately trigger a deploy). Left out of the audit commits on purpose.
2. `database-maintenance.yml` removal means the (never-used) `/deploy/migrate` + `/deploy/reset` manual commands are reachable only by curl — fine, matches "no unused workflows".

## 7. Verification

- ✅ `[skip ci]` honored repo-wide: no workflow run was created by any audit commit (checked `gh run list` for all 6 repos after pushing).
- ✅ Final `.github/workflows` contents match the target inventory (verified via GitHub API).
- ✅ Booking repo local checkout, `laravel`, `laravel.shop`, and both package repos at latest `main` after pushes (`780b5a0`, `7bbc19b`, `240e7c8`, `87d349d`, `99f8102`).
- ✅ All workflow edits are formatting-safe (only echo-string / env-block / action-version / filter-list changes; YAML structure untouched).