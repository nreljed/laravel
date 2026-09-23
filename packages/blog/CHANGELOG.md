# Changelog

All notable changes to this project will be documented in this file.

## [0.1.3] - 2026-09-23

- Made all database migrations idempotent (guarded create/column/index operations) so
  re-running `migrate --force` on partial/historical schemas no longer aborts. Fixes the
  prod `post_tags.order` duplicate-column drift that blocked later migrations.

## [0.1.2] - 2026-09-21

- Packed the Lexical post editor into this package: `src/Resources/js/Components/editor/`
  (moved from the platform core), reached via `PostEditor.tsx` lazy-import `./editor`.
- Dropped the frozen `src/Contracts/FeatureProvider.php` copy (platform-kernel owns the
  contract) and removed the `contract-freeze` CI step + contract-copy tests.
- Docs: full lexical editor docs now live in `docs/lexical/`; `blog.md` updated.
- package.json: added `./pages` + `./*` exports and editor peer deps (lexical, floating-ui,
  pragmatic-drag-and-drop).

## [0.1.1] - 2026-09-15

- Replace `@/` alias imports with relative paths (self-contained kit).

## [0.1.0] - 2026-09-12

- Extracted the blog feature from `nreljed/platform` (`src/Posts`) into the
  standalone `nreljed/blog` package.
- Provider renamed `PostsServiceProvider` → `BlogServiceProvider`
  (`Nreljed\Blog`); slug `posts`, URL prefixes `dashboard/posts` + `/blog`,
  view namespace `posts` unchanged — no route churn for consumers.