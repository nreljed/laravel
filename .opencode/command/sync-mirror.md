---
description: Refresh package mirrors and every consumer lock, then prove fleet parity
---

# Sync mirrors + locks

Bring `packages/*` and all consumer `composer.lock` files back onto the released
upstream tree, then prove it. Upstream clones:

| Package | Clone | Mirror |
| --- | --- | --- |
| `nreljed/platform` | `C:/Users/nrelj/Local Sites/platform` | `packages/platform` |
| `nreljed/blog` | `C:/Users/nrelj/Local Sites/blog` | `packages/blog` |

## 1. Establish ground truth

Identify the version to sync to (default: the newest release tag):

```bash
gh release list -R nreljed/platform --limit 5
gh release list -R nreljed/blog --limit 5
```

Do NOT infer the version from a stale lock. Confirm it against the upstream
repo's `composer.json` and the published tag.

## 2. Confirm upstream before touching the mirror

```bash
cd "C:/Users/nrelj/Local Sites/platform"
git log --oneline -3
composer validate --no-check-publish
```

A `path`-repository install will silently reuse a local working tree while the
lock claims a release. That is the failure this whole task exists to prevent.

## 3. Refresh the mirror

Copy the upstream tree into `packages/<pkg>`, preserving `composer.json`,
`composer.lock`, `package.json`, `src/`, `database/`, `config/`, `resources/`.
Never hand-edit version strings to make a check pass.

## 4. Update every consumer lock

Consumers that must agree (the root app is the primary; the workspace-only
`apps/*` follow automatically once they are on the same version):

- root `composer.lock` (required)
- `apps/laravel.shop/composer.lock` (if consuming the package)
- `apps/laravel.booking/composer.lock` (if consuming the package)
- `packages/*/composer.lock` (each mirror's own lock)

```bash
cd "C:/Users/nrelj/Local Sites/laravel/app"
composer update nreljed/platform --lock
composer update nreljed/blog --lock
```

## 5. Prove parity — do not skip this

```bash
cd "C:/Users/nrelj/Local Sites/laravel/app"
node scripts/check-platform-consistency.js
npm run check:core-deps
```

Both MUST pass. The consistency gate also diffs each `packages/*` mirror against
`vendor/nreljed/*`, so it will catch a half-finished copy. If a gate fails, fix
the source of truth; never loosen the gate to make it green.

## 6. Verify the mirror is byte-identical

```bash
git diff --stat -- packages/
```

Any difference that is not an intended version bump means the mirror drifted.

## Report

State the version synced, every lock touched, both gate results verbatim, and
anything left inconsistent. If a gate fails, say so plainly rather than
reporting success.