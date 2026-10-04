# Lexical Editor

The rich text editor (Lexical + React 19) is **owned by the `nreljed/blog` package** — its

full documentation lives in the blog repo, not here:

- **Canonical docs**: `C:\Users\nrelj\Local Sites\blog\docs\lexical\`
  (or the `nreljed/blog` GitHub repo → `docs/lexical/`), starting at `index.md`.
- **Source**: `nreljed/blog` → `src/Resources/js/Components/editor/` (standalone blog repo,
  mirrored into `packages/blog/src/Resources/js/Components/editor/`).

The platform core no longer ships the editor; the blog package packs it and the post form
pages reach it via `PostEditor.tsx` (lazy-imports `./editor`), spread into consumer
`page-config.ts` through `registerBlogPages()` from `@nreljed/blog/pages`.