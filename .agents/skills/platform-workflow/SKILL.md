---
name: platform-workflow
description: Read or change nreljed platform/blog (and laravel.shop / laravel.booking core) and keep the fleet in sync. Activate when the user mentions the platform package, "update platform", "release platform", "core update", "core-updated", "core-update PR", CORE_CONSUMERS, composer.lock platform pin, drift guard, "Enforce platform core version", "fleet single-core", "upload release assets"/gh CLI, or ANY work on the nreljed/laravel / laravel.shop / laravel.booking consumer apps (edit app code, deploy a consumer, update a consumer's composer.lock by hand). Covers the release→gh-CLI core-update loop, the composer vcs/path-repo local iteration loop, and the drift-guard step.
license: MIT
metadata:
  author: laravel
---

# Platform ↔ Consumer Workflow

Single source of truth: the **nreljed/platform** repo (origin github.com/nreljed/platform, branch main). The three consumers — nreljed/laravel, nreljed/laravel.shop, nreljed/laravel.booking — are composer consumers: they pin nreljed/platform in composer.lock and never own the core source.

## Decide the loop FIRST ("update platform" is ambiguous)

- **Loop A = platform core**: a change that belongs in nreljed/platform and ships to all consumers.
- **Loop B = one consumer app**: a change scoped to laravel / laravel.shop / laravel.booking only (that app's code, branding, app-level composer deps).

### Loop A — platform core
1. Edit the platform clone `C:\Users\nrelj\Local Sites\platform` (src/, routes/, resources/js/...). NEVER hand-edit the vendor copy in a consumer.
2. Local iteration: in ONE consumer, temporarily add a composer `path` repo → `composer update nreljed/platform` → test → **revert the path repo + lock before pushing** (consumers only consume via vcs).
3. Commit + push main → platform ci.yml validates.
4. Tag `vX.Y.Z` → push → release job creates GitHub Release + dispatches `core-updated` to all repos in the `CORE_CONSUMERS` Actions variable (currently `nreljed/laravel nreljed/laravel.shop nreljed/laravel.booking`; root cause of the missing-booking sync was this var overriding the workflow default).
5. Each consumer's `core-update.yml` auto-opens a composer-update PR → auto-merged → its composer.lock bump pins the new core version → its deploy.yml drift-guard passes → deploys to host. Do NOT hand-bump every lock or manually merge each PR — the dispatch loop owns it. Re-dispatch manually only when a consumer was missing from the dispatch list (and fix `CORE_CONSUMERS`, not the workflow).

### Loop B — one consumer app
1. Edit that app's clone only (app/, resources/js, app-only composer deps). Never touch vendor/nreljed/**.
2. Commit + push main → that app's deploy.yml deploys to host. No manual host step.

## The drift-guard (most-misdiagnosed step)
`Enforce platform core version (fleet single-core)` fails ONLY when a consumer's composer.lock pins an OLDER platform (e.g. 0.1.49) than the latest release (e.g. 0.1.51). That is **drift detection, not a deploy bug** — heal it by publishing the newer tag (lock == latest) or re-dispatching. Do NOT rewrite deploy.yml / gh-CLI asset code to silence it.

## Hard rules
- Never hand-edit `vendor/nreljed/**` in a consumer — composer owns it.
- Never manually bump every consumer's lock / hand-merge PRs when the dispatch loop exists.
- Do not rewrite consumer `deploy.yml` to quiet the drift-guard — publishing the tag fixes the pin.
- There is NO stale "clean up gh release assets" comment in the consumer deploy.yml files — do not invent comment cleanup or edit release-asset code that isn't broken.

## Local layout (ground truth)
- platform clone: `C:\Users\nrelj\Local Sites\platform` (source of truth)
- blog clone: `C:\Users\nrelj\Local Sites\blog` (source of truth)
- monorepo consumers: `C:\Users\nrelj\Local Sites\laravel\app` (root), `...\apps\laravel.shop`, `...\apps\laravel.booking`; separate clones `C:\Users\nrelj\Local Sites\laravel.shop` / `...\laravel.booking` are legacy layout.
- **Frontend is a pnpm workspace**: `packages/*` (platform, blog, shop-module, booking-module) are **one-way mirrors** of the standalone repos; the JS kit is consumed via `@nreljed/*` workspace packages (NOT vendor). Keep mirrors in sync with `scripts/sync-package-mirror.mjs` / inspect with `scripts/diff-package-dir.mjs` — both reference the upstream's **released** tree (`git archive` of the latest `v*` tag, export-ignore honored), matching `vendor/nreljed/*`, so a change reaches a mirror only after it is committed **and tagged/released** upstream.
- **Composer/PHP side unchanged**: nreljed/platform + nreljed/blog resolve from VCS into `vendor/` (pinned in composer.lock); no committed path repos.
