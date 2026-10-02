---
paths:
  - vite.config.js
  - pnpm-workspace.yaml
---

# General

## Sync package frontends into vendor: delete before copy
Composer dist vendor frontends can carry stale nested trees (e.g. booking-module `Resources/js/js/` double-nesting). To test un-tagged package changes, DELETE the target `vendor/nreljed/<pkg>/<resources-dir>` first, then re-copy from the source repo — `Copy-Item -Recurse` merges and leaves stale files that surface as phantom TS2307 errors. Add the `@nreljed/platform[/]` bridge to vite.config.js + tsconfig.json paths when validating collapsed package imports.

## pnpm 11 workspace modernization facts (mono app)
pnpm 11 (mono app): set packages[/packages/*, apps/*]; supply-chain keys ONLY work top-level in pnpm-workspace.yaml — minimumReleaseAge: 0 (default 24h blackholes fresh packages), allowBuilds for postinstall scripts (pnpm approve-builds esbuild unrs-resolver). package.json "pnpm" field + .npmrc are ignored/misread. App deps are workspace:* symlinks to packages/platform+blog; tsconfig includes packages/*/.../*.d.ts (they ship the ambient `route` global + @inertiajs/core PageProps augmentation) and paths-maps @nreljed/platform|blog/* to packages source (TS wildcard exports don't resolve). Stale pnpm registry cache at AppData\Local\pnpm-cache\v11\metadata causes phantom version-conflict errors; network flakiness needs --network-concurrency 6.

## pnpm strict resolution: module runtimes must be ROOT deps
Package sources (`packages/*`) resolve runtime imports UP the tree to the ROOT `node_modules`, so root package.json dependencies = the UNION of every package's runtime imports, not just the mono app's. Missing ones fail at vite build as `Rolldown failed to resolve import "X" from "packages/<pkg>/src/...`. Add any runtime a module source imports to the root deps (apps' manifests still declare their own direct deps). Known extras beyond the mono app: @stripe/stripe-js, @stripe/react-stripe-js, axios, @hookform/resolvers, leaflet-draw, leaflet.markercluster, react-image-crop, react-leaflet-markercluster.

## rolldown-vite aliases: no `/*` keys on app namespaces (vite 8.3.0)
vite 8.3.0 (rolldown) breaks object alias keys with trailing `/*` for app-namespace aliases (`'@/*'` → rewrites to `/resources/js/...`, "not an absolute path"). Keep the external-app proven shape: spread the package alias helpers (bare `@dashboard`, `@`, `@blog`) + explicit bare `@dashboard-themes`/`@shop`/`@booking`/`@properties` + `@nreljed/platform|blog[/]` wildcards, each `path.resolve(import.meta.dirname, '../../packages/...')`; keep `dedupe: packageDedupes`. CSS `@source`/`@import` inside `apps/<app>/resources/css` need `../../../../packages/...` (4 `..`); vite/vitest/tsconfig relative paths from the app ROOT use 2 `..`.

## Composer dev loop in the workspace: path repos via sync script
`scripts/switch-composer-repos.js` flips each app's composer.json `repositories` between dev `path` (`packages/<pkg>`) and release `vcs` (`nreljed/<pkg>.git`) — mono stays VCS at rest, shop/booking are the flip targets. After switching: `composer update nreljed/platform nreljed/blog nreljed/shop-module` (booking: ...booking-module); `vendor/nreljed/*` become junctions to `packages/*`. Never edit `vendor/` — edits go to `packages/*` and boards in with `composer update`. `packages/*/composer.json` keeps the `version` field (path repos need NPP version ⊂ root require range). Fresh app scaffolds miss `.env`/`storage/`/`database/database.sqlite` — tests die `No application encryption key` then `Please provide a valid cache path`; fix = `.env` + `key:generate` + storage skeleton + touch sqlite, and set per-app `APP_NAME` (the `brand` shared prop falls back to `config('app.name')`).
