---
description: Release nreljed/platform (bump both manifests, verify, tag, confirm dispatch)
---

# Release nreljed/platform

This repo is the origin. Release from **here**, never from a consumer, and never
from the workspace mirror.

## 0. Confirm the tree

```bash
cd "C:/Users/nrelj/Local Sites/platform"
git status --short
git branch --show-current
```

Refuse on a dirty tree or a non-`main` branch unless told otherwise.

## 1. Is a release even warranted?

```bash
git log --oneline $(git describe --tags --abbrev=0)..HEAD
```

If the net diff is empty — or the "gap" you were chasing turned out not to be a
gap — **publish nothing**. A no-op version consumes a release number and forces
every consumer through a pointless lock update.

## 2. Bump both manifests, in lockstep

CI hard-fails if either disagrees with the tag, so this is not advisory.

```bash
composer config version <X.Y.Z>
npm version <X.Y.Z> --no-git-tag-version --allow-same-version
```

**MAJOR bump required** if `src/Contracts/FeatureProvider.php` changed — CI
enforces a contract freeze on minor/patch.

## 3. Verify (the exact CI commands)

```bash
composer validate --no-check-all
composer install --prefer-dist --no-interaction --no-scripts --no-progress
php vendor/phpunit/phpunit/phpunit
vendor/bin/pint --test
npm install --no-audit --no-fund
npx tsc --noEmit -p tsconfig.strict-gate.json
```

`pint --test` and the strict-gate typecheck matter more than they look: consumers
run both repo-wide, so anything unformatted or non-strict here breaks *their* CI.

## 4. Commit and tag

```bash
git add -A
git commit -m "release: v<X.Y.Z>"
git tag v<X.Y.Z>
git push origin main --follow-tags
```

The tag fires the `release` job, which verifies the tag against both manifests,
creates the GitHub Release, runs the contract-freeze gate, and dispatches
`core-updated` to every repo in `CORE_CONSUMERS`. **Do not create the release by
hand** — the tag pipeline owns it.

## 5. Confirm the release actually landed

```bash
gh run list -R nreljed/platform --limit 3
gh release view v<X.Y.Z> -R nreljed/platform
```

A dispatched event is not proof of a consumer update. Consumers answer with a
**lock-only** PR, and automated merge has already shipped one whose consistency
gate failed — so check each consumer PR's actual checks before calling it done:

```bash
gh pr checks <PR> -R nreljed/laravel
gh pr checks <PR> -R nreljed/laravel.shop
gh pr checks <PR> -R nreljed/laravel.booking
```

## 6. Consumers sync separately

Consumer `core-update.yml` opens the lock-only PR (dispatch + weekly Mon cron +
manual `workflow_dispatch`). You do not mirror or edit `packages/platform` from
here; the consumer does `composer update nreljed/platform`.

## Report

Version, tag, release URL, CI conclusion, dispatch targets, and every consumer
PR number with its real check results. Distinguish "ran and passed" from "not
run".