# Starter Theme (fork-me)

A template theme that ships with the platform so consuming apps don't start
from a blank page map. It is **never auto-registered** — apps opt in by
spreading `registerStarterThemePages()` into their `pageConfig` `basePages`
(importable as `@nreljed/platform/themes/starter` via the package export map),
or (the normal path) fork the `Pages/` tree into the project and register
their own files.

## What lives here

| Page key            | File                      | Purpose             |
| ------------------- | ------------------------- | ------------------- |
| `public/welcome`    | `Pages/Public/welcome`    | Marketing home      |
| `public/about`      | `Pages/Public/about`      | Marketing about     |
| `public/services`   | `Pages/Public/services`   | Marketing services  |
| `public/contact`    | `Pages/Public/contact`    | Marketing contact   |
| `blog/public/index` | `Pages/Blog/Public/Index` | Public blog list    |
| `blog/public/show`  | `Pages/Blog/Public/Show`  | Public blog article |

## Rules

- Template pages live here or in the project — **never** in
  `resources/js/Pages` (platform pages only).
- Theme pages import from the core kit via package-relative paths (self
  contained, works from any consumer build). When you **fork** a page into
  your project, switch the imports to your own alias convention (e.g.
  `@nreljed/platform/*`).
- Keep the platform's `registerCoreTemplates()` free of marketing pages.
