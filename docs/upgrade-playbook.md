# Playbook — dashboard-core upgrade & propagation

Resume of the 2026-08-30 platform migration to `nreljed/dashboard-core` **v0.1.18**, and a
reusable runbook for applying the same upgrade to any consumer app.

---

## Resume — what the upgrade was

### Architecture (already shipped)

`nreljed/dashboard-core` is the standalone product repo; every app is a thin **vcs consumer**
(`composer.json` requires `^0.1`, npm aliases resolve into `vendor/nreljed/dashboard-core`).
Model: **Platform** (engine in core: routes, RBAC, deploy, notifications, media, settings) /
**Theme** (project `pageOverrides` + `@layer base` tokens) / **Module** (`FeatureProvider`
auto-discovered, own `registerXPages()`).

### Core v0.1.18 (released 2026-08-30, tag `v0.1.18`, auto GitHub Release)

- **P1 theme decoupling** — starter theme in core (`resources/themes/starter/`, fork-me),
  `registerCoreTemplates()` = platform-only kit (auth/dashboard/admin/account/settings/error),
  apps opt in explicitly via `page-config.ts`.
- **P2 module SDK** — `Ai`, `PublicSite`, `Deploy` → `FeatureProvider`s;
  `FeatureRegistry::configuredProviders()` = config-listed **plus**
  `app()->getProviders(FeatureProvider::class)` (deduped by slug) → `composer require` + register
  provider = done. Core default features: `[PublicSite, Blog, Ai]` (overridable per app).
- **Version parity** — composer `version` + npm `package.json` version bumped in the same commit
  (`e5b2c20`) and kept in lockstep by the publish pipeline.
- **CI provenance** — `COMPOSER_AUTH` on composer steps, `check-core-deps` parity guard,
  `FoundationCheck` boundary fence (`core-manifest.json`), gitignore of `/packages/`.
- **Frontend hygiene** — SSR scaffold removed, Vite `refresh` for vendor resources,
  `resolve.dedupe` wired from `vendor/nreljed/dashboard-core/vite.dedupe.js`.

### Propagation model (pull-based)

Consumers update themselves via `core-update.yml` (weekly cron Mon 09:00 UTC + `workflow_dispatch`):
`composer update nreljed/dashboard-core` → full validate → **lock-only PR** → bot-merge. There is
NO `repository_dispatch` from core — consumer updates are pull-based. `composer.lock` is the pin of
record and is committed.

Deploys are push-triggered (`deploy.yml`), so bot-merged lock PRs need a **real push** to fire one
(empty-commit promote).

### 2026-08-30 execution

| Step                      | App (`laravel`)             | Shop (`laravel.shop`) | Booking (`laravel.booking`)                                                                                                                                                |
| ------------------------- | --------------------------- | --------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Pin 0.1.18 (lock-only PR) | — (own commit `7ca9192`)    | PR #29                | PR #25                                                                                                                                                                     |
| Validate                  | 640 tests / 2138 assertions | green                 | green                                                                                                                                                                      |
| Promote (real push)       | pushed `33a62bc..7ca9192`   | `9b18e68`             | `c66a87e`                                                                                                                                                                  |
| Deploy                    | release **v0.18.0** ✓       | ✓                     | failed check/upload → **server self-pulled v1.0.22** via `deploy.php` cron ✓; then fixed `cron-deploy.yml` (Bash-in-pwsh) + merged → **v1.0.23 green** via `/deploy/check` |

---

## Runbook — apply the same upgrade to another project

Copy the block below into a fresh session:

```
You are upgrading a Laravel app that consumes the platform package nreljed/dashboard-core
(a composer vcs install into vendor/nreljed/dashboard-core, published from the standalone
repo). The product repo, not this app, is the source of truth.

1. CONFIRM CONSUMER WIRING (only if it was never a consumer):
   - composer.json: requires "nreljed/dashboard-core": "^0.1" (NO "path repo" or packages/ dir)
   - vite.config.js: import { packageDedupes } from "./vendor/nreljed/dashboard-core/vite.dedupe.js"
     and add resolve.dedupe; Vite refresh glob for vendor dashboard-core resources
   - tsconfig aliases: "@dashboard" -> vendor/nreljed/dashboard-core/resources/js and
     "@dashboard-blog" -> vendor/src/Blog/Resources/js
   - resources/js/app.tsx: createFoundationApp(pageConfig); page-config.ts builds
     basePages: registerCoreTemplates() + projectPages + pageOverrides
   - app publishes its own config('core.features') override list OR accepts core defaults
     - every provider slug in the list MUST resolve (php artisan config:show core.features)

2. UPDATE the pin:
   run: composer update nreljed/dashboard-core && npm run build
   - If composer fails "Could not authenticate against github.com", override the stale global
     auth.json: $env:COMPOSER_AUTH = '{"github-oauth":{"github.com":"' + (gh auth token) + '"}}'
   - NEVER use --with-all-dependencies (drifts ~28 unrelated lock packages); commit composer.lock
   - Verify: vendor/nreljed/dashboard-core/composer.json version == package.json version
   - Run: the app's check:core-deps, foundation-check, npm run build, php artisan test

3. CI/PROMOTE/DEPLOY (release mechanics):
   - deployment = push on the deploy branch -> build (ci+package) -> GitHub Release -> push-deploy:
     env-push -> poll POST /deploy/check (8x) -> fallback upload -> FTP fallback (DirectAdmin)
   - Bot-merged PRs (core-update / gh pr merge inside a run) do NOT fire on:push deploys.
     Promote with a real push: git commit --allow-empty -m "chore: promote dashboard-core <V> pin"
   - Watch: gh run watch <id>; success = "Confirmed on vX via /deploy/check"

4. SELF-HOSTED RUNNERS (every repo's CI only runs here):
   - Start before pushing: pwsh -NoProfile -File C:\tools\gha-runner.ps1 <name> (else jobs queue forever)
   - One runner per repo => jobs FIFO: a deploy queues behind an in-progress validate. Wait,
     or gh run cancel the in-progress PR-checks if the deploy is urgent.
   - gha-runner.ps1 <name> status now shows WHY busy (in-progress job name + id).

5. SERVER-SIDE GOTCHA (booking taught us):
   - POST /deploy/check non-JSON + /deploy/upload HTTP 500 = server still on OLD core (POST check
     unsupported) and/or host post_max_size exceeded (~25MB zip). NOT a pipeline bug.
   - The server's own scheduled deploy.php (deploy:auto, every ~5 min) self-pulls any new release
     from GitHub. Verify with GET /deploy/check -> {"status":"current","tag":"vX","core":{...}}.
   - If a manual recovery button (cron-deploy.yml) fails fast, check its explicit `shell: bash`
     (self-hosted default is pwsh; Bash syntax parse-errors silently).

6. DONE WHEN: every consumer's /deploy/check reports the new core version in the `core` block
   (resolved from vendor/composer/installed.json + composer.lock — no build-time record anymore).
```
