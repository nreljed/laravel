---
description: Run the full fleet verification gate and report honestly
---

# Fleet check

Run the gates that actually prove the fleet is coherent. Report failures as
failures.

## 1. Cross-app lock parity

```bash
node scripts/check-platform-consistency.js
```

## 2. Upstream core peers (deploy tree)

```bash
cd "C:/Users/nrelj/Local Sites/laravel.shop"
node scripts/check-core-deps.js
```

## 3. Which tree am I in?

```bash
php artisan shop:env-check
```

Exits non-zero if this is not the served tree. Both trees can `artisan` and both
look plausible — only one is served.

## 4. Package-lock parity (catches path-repo drift)

```bash
php artisan test --compact --filter=PackageLockParityTest
```

A Composer `path` repository makes `composer update` reuse a checkout and report
success while the lock claims a release. This test fails CI on that.

## 5. Mono-owned correctness

```bash
cd "C:/Users/nrelj/Local Sites/laravel/app"
vendor/bin/pint --test          # excludes packages/ ONLY -- apps/ IS included
npm run typecheck
npm run lint
npm run format:check
```

## 6. Tests

```bash
php artisan test --compact
npm run test
```

## 7. Mirror drift

```bash
git diff --stat -- packages/
```

## Reporting rules

- Quote each gate's actual output. Do not paraphrase a failure as success.
- If a gate cannot run, say which and why — never silently skip.
- A skipped test that self-reports success is a FAILURE to report.
- Distinguish "ran and passed" from "not run".

## Known limitations to surface

- Root CI runs Vitest with `pass-with-no-tests: true`, so an empty frontend
  suite reports green. Cross-check `npm run test` output for a real test count.
- The workspace-only app PHP suites are not currently executed by mono CI; run
  them explicitly if you are changing `apps/**`.