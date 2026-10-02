---
description: Release an upstream package (version bump, tag, release) and propagate to consumers
---

# Release an upstream package

Releases originate in the OWNING repo, never in the mono. Applies to
`nreljed/platform`, `nreljed/blog`, `nreljed/shop-module`,
`nreljed/booking-module`.

## 0. Confirm the tree

```bash
cd "C:/Users/nrelj/Local Sites/platform"
git status --short
git branch --show-current
```

Refuse to release from a dirty tree or a non-`main` branch unless the user
explicitly says otherwise.

## 1. Decide whether a release is even warranted

Do not burn a version on a non-change. Re-check before bumping:

- Is there a real diff since the last tag? `git log --oneline v$(git describe --tags --abbrev=0)..HEAD`
- For an audit "gap" that turned out not to be a gap, publish NOTHING.

## 2. Bump both manifests, in lockstep

`composer.json` and `package.json` carry a `version` field. These two must never
differ — a mismatch is a release bug.

```bash
composer config version <X.Y.Z>
npm version <X.Y.Z> --no-git-tag-version --allow-same-version
```

## 3. Refresh and validate the lock

```bash
composer update --lock
composer validate --no-check-publish
```

`composer validate` must exit `0`.

## 4. Verify before tagging

Run the package's own gate (PHPUnit, Pint, lint, typecheck, build). Do not tag a
tree whose tests you did not run.

```bash
vendor/bin/pint --test
php artisan test --compact   # if the package hosts tests
```

## 5. Commit, tag, push

```bash
git add -A
git commit -m "release: v<X.Y.Z>"
git tag v<X.Y.Z>
git push origin main --follow-tags
```

Tagging `main` fires core CI, which creates the GitHub Release automatically and
dispatches `core-updated` to consumers. Do NOT hand-create a release if the tag
pipeline handles it.

## 6. Watch propagation

Consumer `core-update.yml` opens a **lock-only** PR.

## 7. CRITICAL — verify the PR actually passed

Automated merge has merged a PR whose consistency gate FAILED. Always confirm:

```bash
gh pr checks <PR> -R nreljed/laravel
gh run list -R nreljed/laravel --limit 3
```

If a check failed, fix it in the consumer. Do not treat a merged PR as a
successful sync.

## 8. Hand off to mirror sync

Once released, run `/sync-mirror` in the mono to refresh `packages/*` and all
consumer locks.

## Report

Version, tag, release URL, CI conclusion, dispatch status, and the consumer PR
numbers with their actual check results.