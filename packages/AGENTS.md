# Mirrors & Upstream Development (`packages/**`, `apps/**`)

> **How this file loads**: not auto-loaded (traversal stops at the repo-root
> `AGENTS.md`). Referenced from the root `AGENTS.md` — **read it BEFORE editing any
> file under `packages/**` or `apps/**`.** Writes there are blocked at the agent
> level by `.opencode/plugins/mirror-boundary.ts`.

## These trees are mirrors, not ours

Every directory in `packages/**` and `apps/**` is a **byte-for-byte mirror** of a
standalone repository with its own CI, its own releases, and its own deploys. This
repo consumes them; it does not own them. Fixes authored here **do not ship** —
they are overwritten on the next `composer update` / `pnpm install`.

| Mirror | Upstream source of truth |
| --- | --- |
| `packages/platform` | `C:\Users\nrelj\Local Sites\platform` (`github.com/nreljed/platform`) |
| `packages/blog` | `C:\Users\nrelj\Local Sites\blog` (`github.com/nreljed/blog`) |
| `packages/shop-module` | `C:\Users\nrelj\Local Sites\shop-module` |
| `packages/booking-module` | `C:\Users\nrelj\Local Sites\booking-module` |
| `apps/laravel.shop` | `C:\Users\nrelj\Local Sites\laravel.shop` |
| `apps/laravel.booking` | `C:\Users\nrelj\Local Sites\laravel.booking` |

## The loop

1. Edit the **upstream clone**, not the mirror.
2. Test, commit, push (branch → PR), tag `vX.Y.Z`. CI cuts the GitHub Release.
3. Re-sync this repo: `composer update <pkg>` **and** `pnpm install` at the root.
4. Commit both lockfiles — they are the consumer pins of record.
5. Verify parity: `npm run check:platform-consistency`.

Never hand-edit `vendor/nreljed/*` either — it is a Composer vcs install pinned by
`composer.lock` and is overwritten by `composer update`.

> **Core development model (vcs install — do NOT hand-edit vendor)** — everything under
> `vendor/nreljed/platform` is a Composer vcs install (dist zip pinned by
> `composer.lock`). It is overwritten on `composer update` — never edit it directly.
> Core source lives in the standalone platform repo, cloned locally at
> **`C:\Users\nrelj\Local Sites\platform`** (origin `github.com/nreljed/platform`). Dev loop:
>
> 1. Edit the **core clone**, commit + push (branch → PR).
> 2. Tag `vX.Y.Z` → core CI (`ci.yml`) creates the GitHub Release automatically.
>    The publish step bumps **both** `composer.json` `version` and `package.json`
>    `version` — the two must never drift.
> 3. Consumers pull: `composer update nreljed/platform && npm run build`.
> 4. Automated propagation: push to core `main` fires `repository_dispatch`
>    (`core-updated`) to consumer repos; their `core-update.yml` opens a
>    `composer update` PR (Shop has this; merge → their deploy takes over). Weekly
>    Mon 09:00 UTC cron is the fallback; manual trigger:
>    `gh workflow run core-update.yml -R <consumer>`.
>
> - **Fast local iteration**: the vendor copy drives runtime/tests. To test core
>   changes live before tagging, temporarily switch this repo's composer entry to a
>   `path` repo, `composer update nreljed/platform`, and revert the entry
>   before pushing. There is **no `composer reinstall` shortcut** — that only worked
>   for the deleted path-repo model. Frontend consumes `vendor/...` too, so core JS
>   edits also need the updated vendor copy.
> - Consumer CI clones the private repo via `COMPOSER_AUTH` using `secrets.GH_TOKEN` —
>   that token needs **Contents: Read** on `nreljed/platform`.
