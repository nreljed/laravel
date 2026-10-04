---
description: Run the full verification gate for this repo and report honestly
---

# Fleet check

Run the gates that actually prove this repo is coherent. Report failures as
failures.

Scope: the root `laravel` app **plus all six upstream mirrors** it consumes -
`packages/platform`, `packages/blog`, `packages/shop-module`,
`packages/booking-module`, `apps/laravel.shop`, `apps/laravel.booking`. Every one is
owned by a standalone repo; see the "This repo's role" / ownership sections in
`AGENTS.md`.

This command covers the **application**. For the agent harness
(`.opencode/**`, `.ai/rules/**`, `AGENTS.md`), run `/verify-harness`.

## 1. Cross-app lock parity + mirror drift

```bash
node scripts/check-platform-consistency.js
```

Compares Composer + pnpm pins across apps **and** diffs every
`packages/*` mirror against `vendor/nreljed/*`. A non-zero exit is real drift.

## 2. Upstream package declares every peer the app imports

```bash
npm run check:core-deps
```

## 3. Package-lock parity (catches path-repo drift)

```bash
php artisan test --compact --filter=PackageLockParityTest
```

A Composer `path` repository makes `composer update` reuse a checkout and report
success while the lock claims a release. This test fails CI on that.

## 4. Platform boundary fence

```bash
composer validate --no-check-publish
php scripts/foundation-check.php
```

Confirms app-owned files (`bootstrap/app.php`, `routes/web.php`) still carry
their core-manifest markers and no forbidden core path has been re-created in
the app.

## 5. Agent plugins

```bash
bun run .opencode/test/mirror-boundary.test.ts
bun run .opencode/test/session-context.test.ts
```

"Plugin appears in `opencode debug config`" only proves discovery. These prove
behaviour.

## 6. App-owned tree correctness

```bash
vendor/bin/pint --test          # excludes packages/ ONLY -- apps/ IS included
npm run typecheck
npm run typecheck:packages
npm run lint
npm run format:check
```

These cover the trees this repo actually owns (`bootstrap/app.php`, `routes/web.php`,
`resources/js/**`, `app/**`) plus the `apps/**` toolchain. They do **not** prove the
mirrors are correct - step 1 does that.

## 7. Tests

```bash
php artisan test --parallel --compact
npm run test
```

## Reporting rules

- Quote each gate's actual output. Do not paraphrase a failure as success.
- If a gate cannot run, say which and why — never silently skip.
- A skipped test that self-reports success is a FAILURE to report.
- Distinguish "ran and passed" from "not run".

## Known limitations to surface

- Root CI runs Vitest with `pass-with-no-tests: true`, so an empty frontend
  suite reports green. Cross-check `npm run test` output for a real test count.
- `npm run lint` and `npm run format:check` only cover `resources/js/**`;
  `apps/**` and `packages/**` are excluded because they are upstream mirrors that
  run their own toolchains in their own repos.
- The workspace app suites under `apps/**` are not executed by root CI; run them
  explicitly if you are changing those trees.
