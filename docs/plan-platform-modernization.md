# Platform Modernization Plan

Date: 2026-09-14. Scope: nreljed platform + 3 consumer apps (mono, shop, booking).
Status: **APPROVED by owner — execution started**. Decisions locked:

1. **Architecture direction:** Option A — keep Inertia bridge; packages become real dual artifacts (composer + npm).
2. **Repo layout:** single pnpm+turbo monorepo, **converted from the existing `nreljed/laravel` repo** (`C:\Users\nrelj\Local Sites\laravel\app`).
3. **Deploy topology:** 3 deploy workflows with path filters (deploy only changed apps).
4. **Runners:** fold 7 self-hosted runners into 1 for the monorepo.
5. **Composer prod resolution:** dev = `path` repositories; production deploy = GitHub tags (self-contained artifact).

---

## Current State (audit 2026-09-14)

### Repos / remotes

| Dir                   | Remote                    | Role                                |
| --------------------- | ------------------------- | ----------------------------------- |
| `laravel\app`         | `nreljed/laravel`         | MONO app (will BECOME the monorepo) |
| `laravel.shop`        | `nreljed/laravel.shop`    | Shop consumer app                   |
| `laravel.booking`     | `nreljed/laravel.booking` | Booking consumer app                |
| `shop-laravel\app`    | `nreljed/laravel.shop`    | Shop TWIN (LocalWP-served root)     |
| `booking-laravel\app` | `nreljed/laravel.booking` | Booking TWIN (LocalWP-served root)  |
| `platform`            | `nreljed/platform`        | Package v0.1.48                     |
| `blog`                | `nreljed/blog`            | Package v0.1.0                      |
| `shop-module`         | `nreljed/shop-module`     | Package v0.1.39                     |
| `booking-module`      | `nreljed/booking-module`  | Package v0.1.39                     |

### Package ownership today (the good)

| Package        | Migrations                                | Factories                                       | Seeders                                          | Frontend                         |
| -------------- | ----------------------------------------- | ----------------------------------------------- | ------------------------------------------------ | -------------------------------- |
| platform       | 33 (`database/migrations`)                | `src/Database/Factories` (User/Role/Permission) | `src/Database/Seeders/RolesAndPermissionsSeeder` | 248 tsx, bare exports, no `dist` |
| blog           | 11 (`database/migrations`)                | 3 (`src/Database/Factories`)                    | —                                                | 12 tsx, bare exports             |
| shop-module    | 58 (`database/migrations`)                | 0                                               | 0                                                | 52 tsx, **no package.json**      |
| booking-module | under `src/{Booking,Properties}/database` | 0                                               | 0                                                | 64 tsx, **no package.json**      |

### Anti-patterns to eliminate

1. **Apps duplicate package code** — shop + booking each vendor their own `UserFactory`/`RoleFactory`/`PermissionFactory`; packages must own them.
2. **No npm artifact for modules** — `shop-module`/`booking-module` lack `package.json` entirely; consumed via hand-written vendor aliases.
3. **Version drift** (root cause = one `package-lock.json` per app, no workspace):
   - `laravel/framework`: mono 13.24.0 / booking 13.26.1 / shop 13.30.1
   - `tightenco/ziggy`: 2.6.3 / 2.6.4 / 2.6.4; `inertiajs/inertia-laravel`: 3.3.1 / 3.3.1 / 3.3.3
   - npm `react`: `^19.0.0` (mono+shop) vs `^19.2.8` (booking); `@base-ui/react` `^1.6.0` vs `^1.8.0`; eslint `^10.9.0` vs `^10.10.0`; parser drifting
4. **Twins stale** — `shop-laravel\app` + `booking-laravel\app` vendor still has `nreljed/dashboard-core 0.1.39` (pre-rename), no `vendor/nreljed/platform` or `vendor/nreljed/blog` installed; `composer install`/`npm install` never re-run post-rename.
5. **No API seam** — platform has no `routes/api.php`, no `Http/Resources`. All Inertia shared props. (Deferred: future Option C versioned HTTP API.)

---

## Target Architecture (single pnpm+turbo monorepo — converted from `nreljed/laravel`)

```
nreljed/laravel  (the monorepo)
├─ pnpm-workspace.yaml, turbo.json, tsconfig.base.json
├─ packages/
│  ├─ platform/        # composer pkg + @nreljed/platform (built npm kit)
│  ├─ blog/            # composer pkg + @nreljed/blog
│  ├─ shop-module/     # composer pkg + @nreljed/shop-module (NEW package.json)
│  └─ booking-module/  # composer pkg + @nreljed/booking-module (NEW package.json)
├─ apps/
│  ├─ laravel/         # MONO app (present at root → moved to apps/laravel)
│  ├─ laravel.shop/    # Shop app (moved in)
│  └─ laravel.booking/ # Booking app (moved in)
└─ .github/workflows/  # path-filtered CI/CD per app
```

### Modern packaging rules (non-negotiable once landed)

- Every package = **dual artifact**: composer package owns migrations + models + factories + seeders + services + policies + routes; npm package (`package.json` + `exports` map + built `dist` + declared `peerDependencies`) consumed via `workspace:*`.
- Apps are **thin shells**: theme (`resources/css/app.css`), `page-config.ts`, integration tests, demo-tier seeders only. No vendor-path aliases, no duplicated factories, no `import.meta.glob` over `vendor/`.
- Composer: dev = `path` (`../../packages/*`); prod release = resolved from GitHub tags → `deploy.zip` stays self-contained.
- Versioning: package tag semantic (composer == npm == tag, already enforced by platform CI). **App version** (e.g. `v0.26.9`) = snapshot of app shell + pinned package versions (composer.lock = pin of record) + bundled package versions reported by `/deploy/check`.

---

## Phase 0 — Version Convergence ✅ DONE (2026-09-14)

> 1 ✅ All 3 apps aligned to one dependency set (composer + npm — exact ranges below).
> 2 ✅ `composer update` + `npm install` per app; **0 drift across 24 key npm packages + 12 composer keys**.
>
> - Composer locked: framework 13.31.0, ziggy 2.6.4, inertia-laravel 3.3.4, sanctum 4.3.3, phpunit 13.2.6, platform 0.1.48, blog 0.1.0, laravel/ai 0.10.3, symfony/console 8.1.6, collision 8.9.5, telescope 5.24.0, aws-sdk 3.395.2.
> - npm locked: react 19.2.8, inertiajs 3.7.0, ziggy-js 2.6.4, base-ui 1.8.0, eslint 10.10.0, ts-eslint 8.70.0, @eslint-react 5.19.1, vite 8.2.2, ts 6.0.3, vitest 5.0.0, lexical 0.49.0, zod 4.5.4, tailwind 4.3.3.
> - **ESLint fix**: shop/booking used `eslint-plugin-react@7.37.5` (no eslint-10 support). Replaced with mono's `@eslint-react/eslint-plugin` setup — configs now byte-identical to mono (SHA-verified).
>   3 Twins refresh ✅: `composer update` + `npm install` in `shop-laravel\app`, `booking-laravel\app`. `dashboard-core` residue gone (platform verified in vendor); same eslint-plugin-react→@eslint-react swap + `eslint-plugin-import-x` added to both. Full validation green: typecheck/lint/build/vitest/phpunit identical to their main-app peers (shop twin 433+1risky/9skip, booking twin 113/9skip).
>   4 ✅ Drift guard `scripts/check-platform-consistency.js` created (composer 12 + npm 25 keys equal across mono/shop/booking) — passes from all 3 apps; wired into mono `check:ci` + platform `consumer-ci.yml`.
>   5 ✅ Validated all 3 apps: typecheck, lint, build, vitest, phpunit all green (mono 670, shop 433+1risky, booking 113 — 0 failures). GitHub root composer auth.json still stale; use `gh auth token` inline via COMPOSER_AUTH (or refresh the global auth.json).

Original plan:

Do first: safe, no structural change, unblocks everything.

1. Align all 3 apps to one dependency set in `composer.json` + `package.json`:
   - composer: `laravel/framework ^13.8` (lock → 13.30.1), `tightenco/ziggy ^2.6.4`, `inertiajs/inertia-laravel ^3.3.3`, unify phpunit (`^13.2.5 <13.3.0`), `symfony/console 8.1.6`, add direct `laravel/ai ^0.10` to booking (it's transitive today).
   - npm: react/react-dom `^19.2.8`, `@inertiajs/* ^3.7.0`, `ziggy-js ^2.6.4`, `@base-ui/react ^1.8.0`, eslint `^10.10.0`, parser `^8.62.1`, eslint-plugin `^8.70.0`, `@tailwindcss/vite ^4.0.0`, vite `^8.2.2`, ts `^6.0.3`, vitest `^5.0.0`, zod `^4.5.4`.
2. Run `composer update` + `npm install` per app to converge locks.
3. Refresh twins: `composer install` + `npm install` in `shop-laravel\app`, `booking-laravel\app` (kills `dashboard-core` residue).
4. Add a **drift guard**: `scripts/check-platform-consistency.js` — asserts composer.lock + package-lock of all 3 apps (later: workspace) match on a key-package manifest; wire into `check:ci` and CI workflows.
5. Validate: `npm run check:ci` equivalent on all apps + twins (lint, typecheck, format, vitest, pint, phpunit).

## Phase 1 — Monorepo + Real npm Packages

1. Add `package.json` + build (vite lib/tsup `dist`) + `exports` to all 4 packages; modules get platform/blog-style `peerDependencies`.
   - ✅ 2026-09-14: module artifacts DONE — `shop-module/package.json` + `booking-module/package.json` (v0.1.39, composer sync) + self-locating `shop.aliases.js` / `booking.aliases.js` (`@shop`; `@booking` + `@properties`), exports wired. platform/blog unchanged (already ship aliases).
2. Apps consume `@nreljed/*` via `workspace:*`; delete vendor-alias wiring (`platformAliases()`, `blogAliases()`, `@shop`, `@booking`, `@properties`, `@test-posts`, `dedupe` hacks) from the 3 `vite.config.js` files.
   - **GATED BY the alias-collapse** (last inventory, 2026-09-14): all cross-package kit imports happen through the app-mapped `@/` alias; packages are NOT self-contained. Contract:
     - `@/` inside `blog` / `shop-module` / `booking-module` = ALWAYS a platform-kit import (verified: 0 own-internal matches) → rewrite to `@nreljed/platform/X`.
     - `@/` inside `platform` = self-reference (808 refs / 186 files) → rewrite to relative imports (self-contained, no app mapping needed).
     - Secondary prefixes: shop `@shop/*` (own → relative), `@dashboard/lib` (platform → `@nreljed/platform/lib`); booking `@booking/*`, `@properties/*` (own → relative), `@dashboard/lib` (platform).

- `@/types` → `@nreljed/platform/types` (platform `resources/js/types/index.d.ts`). - Outcome: no package ever imports through an app-provided alias again.
  - ✅ 2026-09-14: alias-collapse APPLIED to all 4 package repos (`alias-collapse.mjs`): blog 103 refs/12 files, shop-module 368/52, booking-module 333/61, platform 811 refs/187 files self → relative. Spec-target verification 0/0 missing (blog 110, shop 384, booking 407, platform 963). `dashboard.ts`/`pages.ts` globs relative — survive.
  - ✅ 2026-09-14: VALIDATED end-to-end in all 3 apps. Bridge `@nreljed/platform[/]` + `@nreljed/blog[/]` added to mono + shop + booking `vite.config.js` + `tsconfig.json` (paths → vendor resources/js). Collapsed source synced into vendor copies (composer dist is stale). Result: mono tsc+build(5832 modules)+lint clean, shop tsc+build+vitest 7/7 clean, booking tsc+build clean.
  - ⚠️ Trap: composer-dist vendor dirs can carry stale nested trees (e.g. booking-module `Resources/js/js/` deep dup). When syncing package frontends into vendor, DELETE the target dir first, then copy — `Copy-Item -Recurse -Force` merges and leaves stale files that then surface as phantom TS errors.
  - ✅ 2026-09-14: WORKSPACE (mono root). `pnpm-workspace.yaml` (`packages: ["packages/*"]`, `minimumReleaseAge: 0`, `allowBuilds:` esbuild/unrs-resolver), `.npmrc` DELETED (npm warns, pnpm 11 reads only auth/registry from .npmrc), `pnpm` field REMOVED from package.json (pnpm 11 ignores it), `package-lock.json` DELETED → `pnpm-lock.yaml` (929 pkgs). Symlinks verified: `node_modules/@nreljed/platform` → `../../packages/platform`, `@nreljed/blog` → `../../packages/blog`. App deps switched to `workspace:*`; vite.config.js `refresh` lists `packages/*`; vitest.config.js uses `import.meta.dirname`.
  - ✅ Typecheck fix — the packages DO ship ambient types (`packages/platform/resources/js/types/global.d.ts`): tsconfig must re-add their type globs to `include` — `"packages/platform/resources/js/**/*.d.ts"` + `"packages/blog/src/Resources/js/**/*.d.ts"`. That `.d.ts` declares the runtime `var route: typeof ziggyRoute` + augments `@inertiajs/core` PageProps; no app-side ambient needed. Plus tsconfig `paths` `@nreljed/platform/*` → `./packages/platform/resources/js/*`, `@nreljed/blog/*` → `./packages/blog/src/Resources/js/*` — TS wildcard `./*` exports don't resolve (Node/vite fine); `ziggy-js` vendor path removed (npm `ziggy-js@^2.6.4` installed).
  - ✅ VALIDATED mono workspace: `pnpm install` exit 0; typecheck 0; build (tsc+vite) 7.44s 0; lint 0; vitest 0 (no test files).
  - ✅ 2026-09-15: APPS INTO WORKSPACE — `apps/laravel.shop` + `apps/laravel.booking` imported as workspace members (robocopy, no .git/vendor/node_modules/.github/public/build/storage; composer.lock kept; `"prepare": "husky"` removed). `pnpm-workspace.yaml` → `packages: ["packages/*", "apps/*"]`; the single root `pnpm-lock.yaml` now pins all 7 members (apps are importers). Both apps' tsconfig `paths` extended for `@shop`/`@booking`/`@properties`/`@dashboard-themes`/`@/*` → `../../packages/...` + the `.d.ts` `include` globs; manifests get `@nreljed/blog` + `@nreljed/platform` `workspace:*`. Guard script copied verbatim into both apps (absolute defaultDirs) — passes from all 3.
  - ⚠️ rolldown-vite alias TRAP (vite 8.3.0): build fails with `builtin:vite-alias rewrote @/Layouts/PublicLayout to /resources/js/... but was not an absolute path` when the config uses object alias keys ending in `/*` (`'@/*'`, `'@dashboard/*'`, `'@shop/*'`). Fix = mirror the external apps exactly: spread `platformAliases()` + `blogAliases()` (bare `@dashboard`/`@`/`@blog`) + bare `@dashboard-themes`/`@shop`/`@booking`/`@properties` + `@nreljed/platform[/]`/`@nreljed/blog[/]` wildcards, each `path.resolve(import.meta.dirname, '../../packages/...')`; keep `dedupe: packageDedupes`. No `/*` keys on app-namespace aliases.
  - ⚠️ pnpm strict-resolution lesson: module sources (`packages/shop-module`/`booking-module`) resolve runtime imports UP the tree to the ROOT `node_modules` — root `package.json` dependencies must be the UNION of all packages' runtime imports, not just the mono app's. Surfaced as `Rolldown failed to resolve import "X" from "packages/<pkg>/src/...` → added to root: `@stripe/stripe-js`, `@stripe/react-stripe-js`, `axios`, `@hookform/resolvers`, `leaflet-draw`, `leaflet.markercluster`, `react-image-crop`, `react-leaflet-markercluster`, `@shikijs/engine-javascript` (+ `@hookform/resolvers` in booking's manifest). App manifests already carried theirs from the import.
  - ⚠️ CSS depth trap: `resources/css/app.css` `@source`/`@import` reach packages via `../../../../packages/...` (4 `..` from `apps/<app>/resources/css`); vite/vitest/tsconfig relative paths from the app ROOT use 2 `..`.
  - ✅ VALIDATED apps in workspace (2026-09-15): shop typecheck+build (5880 modules ≈8s)+lint+vitest 7/7+guard green; booking typecheck+build (5940 modules ≈10s)+lint+guard green (no vitest tests, config exit 0). Drift guard now reports `12 composer + 25 npm keys identical across mono/shop/booking` from all 3 locations.
  - ⚠️ Pending commit-time decision: root `.gitignore` line `/packages/` currently ignores the workspace package sources — revisit before the first workspace commit (packages must be tracked).
  - ⚠️ pnpm 11 supply-chain quirks (do not re-litigate): `minimumReleaseAge` default 24h blackholes freshly-published packages — must set `minimumReleaseAge: 0` TOP-LEVEL in pnpm-workspace.yaml (`.npmrc` key + `settings:` wrapper DON'T work); build scripts need `allowBuilds:` (add via `pnpm approve-builds esbuild unrs-resolver`). Stale registry metadata cache at `C:\Users\nrelj\AppData\Local\pnpm-cache\v11\metadata` was the mdn-data/vite-ecosystem version conflict ROOT CAUSE (clear the dir, not overrides). `UND_ERR_DESTROYED` fetch flakiness → `--network-concurrency 6 --fetch-retries 10`.

3. Composer: dev `path` repos + a **sync script** that switches each app's composer.json between `path`/vcs per environment; document in deploy skill.
   - ✅ 2026-09-15: `scripts/switch-composer-repos.js` — standalone ESM, mirrors the drift-guard style. `node scripts/switch-composer-repos.js [status|path|vcs] [app...]` (env refs `SWITCH_COMPOSER_MONO/SHOP/BOOKING`). Rewrites each app's composer.json `repositories[].type` (`path` ∋ `packages/<pkg>` | `vcs` ∋ `nreljed/<pkg>.git`), preserves indent, prints next steps (`composer update nreljed/platform nreljed/blog […]`). Status: mono/shop/booking all vcs today.
   - Mono stays VCS at rest (pinned by lock); shop/booking are the flip targets in PATH mode for dev — verified `composer update` green in both, lock records `dist.type=path` / `dist.url=../../packages/<name>`, `vendor/nreljed/*` become junctions into the workspace packages.
   - ⚠️ `packages/*/composer.json` must keep the `version` field (path repos only respect `^0.1` matches if NPP `version` + root requires align — platform 0.1.48, blog 0.1.0, shop-module/booking-module 0.1.39 all carry it).
   - STOP on phpunit bootstrap: fresh app dirs imported WITHOUT `.env`/`.env.example`, `storage/`, or `database/database.sqlite` → tests die with `No application encryption key has been specified` → `Please provide a valid cache path`. Fix: per-app `.env` (`php artisan key:generate`), storage skeleton (`storage/app/public`, `framework/{cache/data,sessions,views,testing}`, `logs`, `bootstrap/cache`), touch sqlite. Plus per-app `APP_NAME` (brand prop falls back to `config('app.name')` → storefront `assertSee('Shop App')` fails with the default "Laravel").
   - ✅ Full `check:ci` green on all three (@2026-09-15): shop 442 tests, booking 122 tests, mono root full pipeline (lint/typecheck/format/vitest/pint/phpstan `passed`/rector/phpunit `--parallel`). Mono fixes that surfaced: prettier-bump reformat of 2 `app/TestPosts/*.tsx`; root `vendor/bin/pint` now formats `packages/**` too (they're in-tree source — 60 files one-time line-ending/import/braces fixes); rector `AddTypeToConstRector` on `tests/Feature/CoreVersionServiceTest.php` (`private const string PACKAGE`). phpstan: 0 errors.
   - LocalWP docroot (review 2026-09-15): `shop-laravel` / `booking-laravel` sites serve the OLD standalone checkouts at `{site}\app` — Apache `site.conf.hbs` `DocumentRoot "{{root}}"` = `…\shop-laravel\app`, and the root `.htaccess` rewrites everything to `public/` (that's why a repo-root docroot works). `apps/laravel.shop` + `apps/laravel.booking` carry the identical root `.htaccess`, so they're directly servoable.
   - ✅ APPLIED 2026-09-15 — LocalWP twins swapped to junctions: stopped-out (`app` → renamed `app.standalone`, preserving the old git checkouts) and fresh `app` ≈ **directory junction** → `…\laravel\app\apps\laravel.{shop,booking}` (`New-Item -ItemType Junction`; PHP resolves realpath so `base_path()` stays in `apps/*`). `APP_URL` set per app (.env) to the LocalWP domains. Verified: `artisan about` boots through the junction and live `php -S` smoke test returns **HTTP 200 + Inertia shell + correct brand on both** (`shop-laravel.local`/`booking-laravel.local`). DB context is now each app's **sqlite** (fresh — `artisan migrate`/seeders needed to repopulate), not the twins' LocalWP MySQL demo data. **Rollback**: stop the site, `Remove-Item {site}\app` (junction only), rename `app.standalone` → `app`. Twin-only artifacts left behind in `app.standalone`: `dist/`, `runner/`, `.playwright-mcp`, `sql/`, `AUDIT.md`, `.env`(MySQL).
4. CI/CD: path-filtered workflows (monorepo), fold 7 runners → 1, deploy.yml semantics preserved (3 deploy workflows).

## Phase 2 — Package Data Ownership

1. shop-module: add factories (Product/Order/Cart/Coupon/…). booking-module: add factories (Booking/Property/…) — namespaced in package.
2. Move baseline seeders into owning packages; app keeps demo-tier only.
3. Delete duplicated app factories (shop + booking `PermissionFactory`/`RoleFactory`/`UserFactory`).
4. Structural boundary tests (extend blog's `frontend-boundary-check.php` pattern): no `vendor/` imports from frontend, no duplicated package code in apps.

## Phase 3 — Clean Layer Discipline (Option A scope)

> STATUS: **VERIFIED IMPLEMENTED (2026-09-21)** — a full gap audit was run against the
> shop (`apps/laravel.shop`) and booking (`apps/laravel.booking`) app shells. The
> backend + frontend layers are already package-owned; the app shells are thin glue.
> Boundary is enforced by the P2.4 tests. Versioned HTTP API stays out of scope
> (Option C).

- Backend layer per package: routes/controllers/requests/policies/services/models/database.
- Frontend layer per package: `Resources/js` UI kit + `registerXPages()` export + shared Inertia props + exported TS types.
- Boundary enforced by tests. Versioned HTTP API = future work (Option C), explicitly out of scope now.

### Audit evidence (2026-09-21)

- **Backend per package.** Shop app shell = 5 PHP files; booking = 11. Everything else
  (routes, controllers, requests, policies, services, models, migrations, factories,
  seeders) lives in `nreljed/platform`/`packages/platform/src` or the feature packages
  (`shop-module`: `src/{Controllers,Routes,Services,Models,Policies,Requests,Database}`;
  `booking-module`: feature-first `src/Booking/*`). App shells carry only: auth-seam
  model subclasses (`User`, `Role`, `Permission`, `Setting`, `NotificationPreference`),
  providers (`AppServiceProvider`, `TelescopeServiceProvider`, shop
  `FrontendRouteServiceProvider` for the `/` route override), app-specific services/
  traits, and booking's `HandleInertiaRequests` (consumer-side shared-props override).
- **Frontend per package.** Each package ships its own kit + page-registration export:
  `packages/platform/resources/js/dashboard.ts` (`registerCoreTemplates()`),
  `packages/shop-module/src/Resources/js/pages.ts` (`registerShopPages()`), blog
  `@nreljed/blog/pages` (`registerBlogPages()`); booking ships feature scoped
  `src/Booking/Resources/js`. Apps import packages ONLY via `@nreljed/*` aliases and
  build their pageMap in `app/.../resources/js/page-config.ts` — never import from
  `vendor/` or `../packages`.
- **Boundary enforced by tests.** `frontend-boundary-check.php` + `BoundaryIntegrityTest`
  in both apps (P2.4) ban `Nreljed\*` namespace declarations in app PHP, vendor/package
  imports, `../packages` paths, and byte-identical duplicates of package sources
  (allowlist: app-owned `resources/js/test-setup.ts`).

### Closure decision — accepted app-owned files (NOT moved into packages)

| File (booking unless noted)                                            | Why it stays app-owned                                                                                                                                                                     |
| ---------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| `app/Models/{User,Role,Permission,Setting,NotificationPreference}.php` | Auth-seam subclasses — the app resolves its own model class (`AUTH_MODEL` seam); package model is the base.                                                                                |
| `app/Services/SettingsService.php`                                     | No-op subclass of `Nreljed\Platform\Services\SettingsService` — keeps `App\Services\SettingsService` call sites working; same seam pattern.                                                |
| `app/Traits/EditorContentSanitizer.php`                                | App-specific rich-text policy (booking content). Distinct from platform `SettingsSanitizer`. Candidate for a future booking-module move (needs a module release) — recorded, not blocking. |
| `app/Traits/PasswordValidationTrait.php`                               | App-local password-rule helper; a same-named trait exists in booking-module — they diverge. Consolidation into the module is a candidate follow-up (needs a module release).               |
| `app/Http/Middleware/HandleInertiaRequests.php` (booking)              | Consumer-side shared-props customization; not byte-identical to package copy.                                                                                                              |
| `app/Console/Commands/GenerateDemoData.php` (shop)                     | Consumer-specific demo seeding command (demo tier).                                                                                                                                        |
| `app/Providers/FrontendRouteServiceProvider.php` (shop)                | Consumer route override (`/` beats the package `home` route via `booted()`).                                                                                                               |

No Phase 3 gaps require code movement. Optional cleanups (sanitizer/trait consolidation)
are tracked as future booking-module release candidates, not acted on here.

## Phase 4 — Versioning + Update Flow (documentation + plumbing)

> STATUS: all items **DONE (2026-09-22)** — v0.1.58 released and live on all three hosts.

1. Define (write into AGENTS.md + deploy docs): app version vs package version; composer.lock = pin of record; SBOM-ish report from /deploy/check. **DONE** — see mono `AGENTS.md` § Versioning + `/deploy/check`; `docs/workflow/deploy.md` § Versions & update chain.
2. Extend `DeployController::check()` / release notes to list bundled package versions. **DONE (2026-09-22)** — `CoreVersionService::bundled()` enumerating every installed `nreljed/*` package from `vendor/composer/installed.json`, surfaced as `bundled` in every `DeployService::check()` response. Shipped as **`nreljed/platform` v0.1.58**: bump `d98f06d` (+ tag `v0.1.58`, release `2026-09-21T22:59:39Z`, tree diff v0.1.57..v0.1.58 = exactly 7 files incl. feature + contract test) → `repository_dispatch core-updated` → auto lock-bump PRs merged in **mono `475a354`/#68**, **shop `e3fbf8f`/#67**, **booking `40e920e`/#50** → mono mirror re-synced (`2ff3aef`) → **all three hosts deployed + CI-green on 0.1.58** (mono deploy `35668870201`, shop `35668924002`, booking `35668924351`).
3. Document full update chain: package tag → `core-updated`/`blog-updated` dispatch → `core-update.yml` weekly cron → consumer update PR → CI → deploy. **DONE** — see `docs/workflow/deploy.md` § Versions & update chain; mechanics verified against `.github/workflows/core-update.yml` (mono) and `.github/workflows/ci.yml` (platform, `release` job).

> **Release-time fixes along the way:** (a) `scripts/check-platform-consistency.js` had machine-path-hardcoded `defaultDirs` — consumer-repo deploys read the *runner's* local locks and failed "mono=0.1.57 but shop=0.1.51". Made `defaultDirs` repo-relative (`path.resolve(__dirname, '..')`) in **all three** repo copies (mono `7fc9c0b`, shop `0bb9de0`, booking `f405ae4`): standalone repos find no `apps/` subtree → reachable==1 → skip; the monorepo enforces real 3-app parity. (b) The `bundled` SBOM key was dropped at the HTTP layer (`DeployController::check()` never serialized it; v0.1.58's `/deploy/check` returned `bundled: null`). Fixed + released as **v0.1.60** (`067498e`, every response branch surfaces `bundled`); v0.1.59 was a version-only no-op release caused by an edit mis-placed in the mono mirror. Runtime-verified: `GET /deploy/check` → `core: 0.1.60` + `bundled` = `nreljed/platform 0.1.60` + `nreljed/blog 0.1.2`. Regression net in `tests/Feature/Deploy/DeployEndpointTest.php`. (c) mono `packages/platform` mirror is now **pint-normalized** under the default Laravel preset (`37869d3`) because root CI runs `vendor/bin/pint --test` repo-wide via platform `consumer-ci.yml`. Resolved at the source: platform repo normalized + **`Laravel Pint (style gate)` added to its CI validate** (`aecf7f0`), so released trees stay clean and mirror syncs stay byte-equal.

---

## Deferred / Explicitly Out Of Scope

- Full API-first backend (Option B) — rejected by owner 2026-09-14.
- Versioned HTTP JSON API (Option C) — future.
- Changing auth model (Inertia session + Sanctum stays).

## Owner decisions (locked 2026-09-14)

- A (Inertia + real kit) / monorepo conversion of `nreljed/laravel` / fix versions first / 3 path-filtered deploy workflows / 1 runner / dev=path prod=tags composer.
