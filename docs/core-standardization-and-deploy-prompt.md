# Prompt: Same Deploy Improvements + Core Standardization (nreljed/laravel)

> Target repo: **`nreljed/laravel`** (`main`) — Cost Breaker 360 master, hosts `packages/dashboard-core`
> Work dir: `C:\Users\nrelj\Local Sites\laravel\app`
> Prepared: 2026-08-15 · Author: opencode
> Status: **✅ DONE — executed 2026-08-15**. This is a historical record. **Do not hand it
> to a fresh session as pending work.** All gaps G1–G7 are resolved and verified live.

---

## ✅ EXECUTED — 2026-08-15 (do not re-run)

Everything in this prompt was completed and verified the same day it was written:

| Deliverable                           | Commit / Evidence                                                                                                             |
| ------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------- |
| G1 check-first flow (monorepo)        | `80178d2` — `deploy.yml` polls `/deploy/check` first (8×45s), `/deploy/upload` only as fallback                               |
| G2 `continue-on-error` policy         | `80178d2` — kept only on best-effort steps (env-push, FTP); job fails honestly (comment at deploy.yml:64-71)                  |
| G3 FTP fallback + laravel account     | `80178d2` — `laravel@tunispromo.tn` exists, home IS the project root; both runs logged `FTP upload OK: 226 Transfer complete` |
| G4 shop `deploy:auto` parity          | `c566bf1` in `C:\Users\nrelj\Local Sites\shop-laravel\app` — `routes/console.php:19-21`; shop deploy green                    |
| G5 `docs/github-cli.md` + `AGENTS.md` | `6739a10` — `docs/github-cli.md` (178 lines), AGENTS.md deploy section refreshed                                              |
| G6 deploy skill                       | `~/.config/opencode/skills/deploy/SKILL.md` — monorepo rows + canonical check-first + core-publish flow                       |
| G7 core standardization docs          | `6739a10` — `docs/core-dashboard-package.md` §7.1 Standardization Contract                                                    |

**Verified live in CI:** runs `31892796425` (→ v0.15.1) and `31893485873` (→ v0.15.2) both green:
env-push returned `{"success":true,"updated":["GITHUB_TOKEN"]}`, deploy confirmed via
`Deployed via /deploy/check (GitHub API)`, and FTP fallback returned `226 Transfer complete`.
Releases `v0.15.0`/`v0.15.1`/`v0.15.2` published.

**Package boundary audit (item 6):** holds — no app-only code leaked into
`packages/dashboard-core/src/**` (TestPosts stays app-side), and none of the
`core-manifest.json` forbidden project paths exist in the app. Note: the
`core:check-boundary` command does not exist — the audit was manual (plan said "if present").

---

## 1. Mission

Apply the **same production-hardening improvements** we already shipped on `nreljed/laravel.shop`
(the working FTP fallback, env-push step, unified `GH_TOKEN`, secrets cleanup, docs, skill) to the
**monorepo**, and **narrow + standardize the `dashboard-core` package** because it is the shared
foundation for _all_ our projects (Shop, Booking, future ones).

Two deliverables:

1. **Deploy parity** — the monorepo's CI/CD must reach the same verified, self-healing state the shop is in.
2. **Core standardization** — the `dashboard-core` package + consumer contract must be clean, consistent,
   and documented so every project deploys and extends the same way.

**Golden rule:** changes to `packages/dashboard-core/**` auto-publish to `nreljed/dashboard-core` via
`core-publish.yml` and dispatch `core-updated` to consumers. Do **not** break the publish pipeline,
and verify a core change end-to-end before leaving it.

---

## 2. Current State (verified 2026-08-15)

### Repos & infra

| Repo                     | Branch   | Server                             | Deploy token        | Status                                      |
| ------------------------ | -------- | ---------------------------------- | ------------------- | ------------------------------------------- |
| `nreljed/laravel`        | `main`   | https://laravel.tunispromo.tn      | per-server (secret) | LIVE v0.15.0, deploy green                  |
| `nreljed/laravel.shop`   | `master` | https://shop.laravel.tunispromo.tn | per-server (secret) | LIVE v0.5.5, deploy green, FTP verified     |
| `nreljed/dashboard-core` | (mirror) | —                                  | —                   | package repo, published by core-publish.yml |
| `nreljed/shop-module`    | —        | —                                  | —                   | consumer VCS dep                            |

- **Unified `GH_TOKEN`** (fine-grained PAT, Contents R+W on laravel + laravel.shop + dashboard-core,
  Read on shop-module) is set in BOTH app repos and BOTH servers' `.env`. Never per-repo tokens.
- **DeployController/DeployService live in the package** (`packages/dashboard-core/src/Deploy/**`) —
  shared by every app. App-side copies are forbidden (see `core-manifest.json`).
- `/deploy/*` routes (upload, check, env, cache-clear, migrate, create-admin, reset) are defined in
  `packages/dashboard-core/routes/web.php`, throttle `5,1`.
- Server self-deploy fallback: `deploy:auto` (package `DeployAuto` command) scheduled every 5 min in
  `routes/console.php`. **The shop does NOT have this scheduled** (its console.php has shop jobs only).

### Already done in monorepo (do NOT redo)

- `735ecbb` — env-push step added to `.github/workflows/deploy.yml` (verified in v0.15.0 deploy:
  `{"success":true,"updated":["GITHUB_TOKEN"]}` on the server).
- `13ba8d4` — `nanoid` override in `package.json` + lockfile (npm audit now 0 vulnerabilities).
- `1fd0282` — docs point monorepo `GH_TOKEN` notes at the shared token structure.
- Secrets: `GH_TOKEN` unified, `FTP_*` currently set (mirror of shop values) but **no FTP step exists**
  in the monorepo workflow.

### Known gaps to fix (this prompt)

| #   | Gap                                                                                                                                                                                                                                  | Evidence                                                | Desired end-state                                                                                                                                                                                                                                                                                                                      |
| --- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ | ------------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| G1  | Monorepo `deploy.yml` prefers `/deploy/upload` first, then `/deploy/check`; shop prefers `/deploy/check` first. Inconsistent and the upload path returned HTTP 500 "Failed to open ZIP" (Cloudflare cuts large uploads).             | shop deploy.yml poll_check() vs monorepo upload-first   | **Canonicalize** to check-first flow (poll `/deploy/check` 8×45s, confirm tag, then upload as fallback). One shared pattern in both repos.                                                                                                                                                                                             |
| G2  | Monorepo `push-deploy` job has `continue-on-error: true`; shop's does not (only FTP step does).                                                                                                                                      | deploy.yml:67 vs shop deploy.yml                        | Decide one policy: keep `continue-on-error` only on best-effort steps (env-push, FTP). Job itself should **fail** on genuine deploy failure so CI is honest — but reconcile with the "host blocks datacenter IPs" comment. Document the decision in both repos.                                                                        |
| G3  | Monorepo has **no FTP fallback step** and no scheduled `deploy:auto` parity question for the shop.                                                                                                                                   | deploy.yml (no FTP) vs shop deploy.yml:209-244          | Verify whether `laravel.tunispromo.tn` has a DirectAdmin FTP account (the known account home IS the shop project root — likely shop-specific). If a laravel FTP account exists, add the identical home-root FTP step + secrets. If not, **remove** `FTP_*` from the monorepo secrets to avoid drift and document why FTP is shop-only. |
| G4  | Shop is missing `deploy:auto` scheduled self-deploy (monorepo has it).                                                                                                                                                               | shop routes/console.php vs monorepo routes/console.php  | Add `deploy:auto` everyFiveMinutes + withoutOverlapping to shop's `routes/console.php` (consumer parity with core's resilience path).                                                                                                                                                                                                  |
| G5  | Docs drift: monorepo has NO `docs/github-cli.md` (shop has one, updated with 13 secrets + unified token + FTP home-root quirk). Monorepo `AGENTS.md` deploy section (lines ~422-448) predates env-push + nanoid + token unification. | file listing + AGENTS.md                                | Add `docs/github-cli.md` (monorepo variant), update `AGENTS.md` deploy pipeline steps, reference the deploy skill.                                                                                                                                                                                                                     |
| G6  | The `deploy` skill (in `~/.config/opencode/skills/deploy/SKILL.md`) documents the shop. Monorepo specifics (core-publish, deploy:auto, check-first, no FTP) aren't captured.                                                         | skill file                                              | Extend the skill with a monorepo table/rows + the canonical check-first pattern + core-publish flow.                                                                                                                                                                                                                                   |
| G7  | Core package lacks a "consumer contract" doc beyond README — settings groups, config overrides (`config/deploy.php`, `config/core.php`), FeatureProvider methods, publish/dispatch flow not all in one place.                        | `docs/core-dashboard-package.md` (master plan) + README | Add/refresh `docs/core-dashboard-package.md` with a **Standardization section**: config keys table, FeatureProvider method checklist, deploy endpoint contract, publish flow, per-app overrides.                                                                                                                                       |

---

## 3. Work Plan

### Phase A — Deploy parity (repo workflows)

1. **Canonicalize the upload/check flow** in `.github/workflows/deploy.yml`:
   - Copy the shop's `poll_check()` + `confirm_deployed()` pattern (poll `/deploy/check` first,
     8 attempts × 45s, accept `"status":"deployed"` or `"current"` with the expected tag).
   - Keep `/deploy/upload` only as the fallback when check doesn't confirm.
   - Keep the env-push step exactly as-is (already correct).
2. **Reconcile `continue-on-error`**: keep it on env-push only. On the `push-deploy` job, remove it —
   but preserve the "host may block datacenter IPs" resilience by relying on the server's `deploy:auto`
   cron + a final failure message pointing to the release URL. Document the tradeoff in a comment.
3. **FTP decision**:
   - Ask/verify: does a DirectAdmin FTP account exist whose home is the `laravel.tunispromo.tn` project
     root? Use `curl.exe -T` (with `--ftp-port -` for passive) or FtpWebRequest against the candidate
     account.
   - If yes → add the identical FTP fallback step (home-root URL `ftp://server:port/deploy.zip`,
     passive FtpWebRequest, `if: always()` + `continue-on-error`), update `FTP_*` secrets to the real
     laravel account values.
   - If no → `gh secret delete FTP_USERNAME FTP_PASSWORD FTP_SERVER FTP_PORT -R nreljed/laravel`, and
     add a comment in deploy.yml explaining FTP fallback is shop-only.
4. **Verify**: push to `main`, watch the run, confirm env-push + `Deployed via /deploy/check (GitHub API)`,
   server responds `{"status":"current","tag":"vX.Y.Z"}`. Do NOT merge if red.

### Phase B — Consumer parity (shop repo, low risk)

5. Add `Schedule::command('deploy:auto')->everyFiveMinutes()->withoutOverlapping();` to
   `C:\Users\nrelj\Local Sites\shop-laravel\app\routes\console.php` (mirrors monorepo resilience).
   Push to `main`, verify shop deploy still green.

### Phase C — Core package standardization

6. **Audit the package boundary** against `core-manifest.json` and `docs/core-dashboard-package-phase0.md`:
   - Confirm no app-only code leaked into `packages/dashboard-core/src/**` (e.g. TestPosts is
     app-side, not package — verify).
   - Confirm `forbidden_project_paths` (DeployController, PublicController, Page) hold in the monorepo
     itself (the master app must not redefine them).
7. **Write the Standardization section** in `docs/core-dashboard-package.md`:
   - `config/deploy.php`, `config/core.php`, `config/admin.php`, `config/roles.php` override contract
     (each key + default + when a project overrides it).
   - `FeatureProvider`/`InteractsWithFeatures` method checklist (name, slug, nav, shared data,
     email templates, notification types, permissions, settings route).
   - Deploy endpoint contract (each `/deploy/*` route, method, auth, purpose).
   - Publish flow: `core-publish.yml` (mirror → bump → tag → release → dispatch `core-updated`),
     and consumer update flow (`update-core.sh` style: `composer update` → `npm install` →
     `npm run build` → `php artisan migrate`).
   - Settings groups: core defaults vs consumer additions (shop adds `['localization','shop']`).
8. **Docs parity**: create `docs/github-cli.md` (monorepo variant of the shop file) covering: the 13
   secrets table, unified `GH_TOKEN` design, `gh` cheat sheet, FTP note (per the Phase A decision),
   fork checklist.

### Phase D — Skill + AGENTS.md

9. Extend `~/.config/opencode/skills/deploy/SKILL.md`:
   - Add the monorepo row (repo, branch, URL, latest verified, publish path via core-publish).
   - Add the **canonical check-first deploy pattern** and note the upload-500 quirk.
   - Add a "Core publish flow" subsection (mirror repo, dispatch, consumer cron fallback).
10. Update monorepo `AGENTS.md`:
    - Deployment section: reflect env-push step, check-first flow, unified token, deploy:auto,
      FTP decision, and point to the skill + docs.

### Phase E — Verification (must all pass)

- [ ] `npm run typecheck`, `npm run lint`, `npm run format:check`, `php vendor/bin/pint`, phpunit green.
- [ ] `php artisan core:check-boundary` (if present) or equivalent passes.
- [ ] Pushing to `main` produces a green `ci / validate` + `package` + `release` + `push-deploy`
      with env-push confirmed and `Deployed via /deploy/check (GitHub API)`.
- [ ] Server `GET https://laravel.tunispromo.tn/deploy/check?token=<DEPLOY_TOKEN>` returns
      `{"status":"current","tag":"vX.Y.Z"}`.
- [ ] If a core source change was made: `core-publish.yml` publishes a new dashboard-core version,
      dispatch `core-updated` fires, and the shop's weekly cron/composer update path is unaffected.
- [ ] Docs/skill files written and committed.

---

## 4. Constraints & Conventions

- **Never** commit secrets or echo token values into logs/docs. Reference secret names only.
- Keep `DEPLOY_TOKEN` per-repo (matches each server's `app.deploy_token`). Never unify it.
- Never create a second GH_TOKEN. Use the single unified PAT in both repos.
- Windows shell is PowerShell; use `gh` CLI for all GitHub admin.
- Commit style: `fix(deploy): ...` / `docs(core): ...` / `chore(ci): ...`. Push to `main`/`master`
  auto-deploys — sequence work so each push is green.
- If blocked (e.g. FTP account question), stop and ask rather than guessing.

## 5. Out of scope

- No new features for the package beyond standardization/docs.
- No changes to the unified token itself.
- No migration of the master app's own code into/out of the package except drift fixes found in C6.
