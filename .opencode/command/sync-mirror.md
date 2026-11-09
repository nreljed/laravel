---
description: (Obsolete) Mirror sync is no longer needed
---

# Sync mirror — OBSOLETE

This repository no longer keeps `packages/*` mirrors or `apps/*` copies.
Platform and modules are consumed directly from `vendor/nreljed/*` (Composer VCS)
via Vite/TS aliases. There is nothing to sync locally.

To update platform/modules, run `composer update nreljed/<package>` and
`npm run build` in the consumer app, or merge the `{package}-updated`
repository dispatch PR. See `docs/packages.md`.
