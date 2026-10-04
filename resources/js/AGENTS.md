# Frontend Conventions (`resources/js/`)

> **How this file loads**: OpenCode resolves `AGENTS.md` by traversing **up** from the
> cwd and stopping at the first match, so only the repo-root `AGENTS.md` loads
> automatically. `instructions[]` in `.opencode/opencode.json` is a **flat,
> always-loaded** list, so scoped files are *not* registered there. The root
> `AGENTS.md` points at this file — **read it when the task touches `resources/js/**`,
> `packages/*/resources/js/**`, or any TSX/Tailwind work.**

Scope: the app's own frontend shell. The shared component kit lives in the
`nreljed/platform` package; project visuals and overrides live here.

## Code Style

- Functional components with hooks, strict TypeScript
- 4 spaces indentation (PHP), 2 spaces (TSX)
- Use `cn()` from `@/lib/utils` for class merging
- All routes via Ziggy's `route()` helper — no hardcoded URLs
- Inertia `useForm` for all form state
- `useAuth` hook from `@/hooks/use-auth` for auth state
- `next-themes` `<ThemeProvider>` for dark mode
- `toast.add()` from `@/Components/ui/toast` (Base UI) for flash messages — `toast.add({ title: "msg", type: "success" | "error" | "info" | "warning" })`
- Component imports use `@/Components/...` alias (capital C — Windows FS workaround)
- Aliases (workspace-mapped in `tsconfig.json`, NOT vite): `@nreljed/platform/*` → `packages/platform/resources/js/*`; `@nreljed/blog/*` → `packages/blog/src/Resources/js/*`. The legacy vendor aliases (`@`, `@dashboard`, `@blog`) are gone — package sources import each other via `@nreljed/*` and relative paths
- `asChild` / `Slot` pattern is standard for component composition

> **Frontend page resolution** — Inertia resolves pages by **lowercased** component name. `dashboard.ts` exposes `registerCoreTemplates()` — the opt-in CORE kit: PLATFORM pages from `./Pages/**/*.tsx`, feature pages from `../../src/*/Resources/js/Pages/**/*.tsx` prefixed `blog/`/`public/`. The blog package ships `@nreljed/blog/pages` → `registerBlogPages()`. The app entry spreads these into `basePages`. Controllers render lowercase names, e.g. `Inertia::render('dashboard/index')`, `'public/about'`, `'blog/index'` (→ `Blog/Public/Index.tsx`).

> **Frontend ownership (opt-in page inventory)** — CORE never auto-registers pages. Each app's `page-config.ts` builds its own explicit pageMap:
> `basePages: registerCoreTemplates()` (the shared control panel + template pages it wants), plus `projectPages` for namespaced project pages (`projectPrefix`), plus `pageOverrides` to swap any CORE-owned page for a project page (applied last, wins — e.g. `"public/about": () => import("./Pages/Public/About")`). Routes + controllers stay in CORE (they render `public/*`, `dashboard/*`, `blog/*` …) — only the page component swaps. This is how Shop/Booking ship their own marketing sites without forking core. The client entry (`app.tsx`) imports `pageConfig` — never duplicate the config block.

> **Frontend theming** — consuming apps can re-theme the shared UI without forking core. Import `core.css` in their `resources/css/app.css`, then override any subset of CSS variables under `@layer base` (tokens only, keep the utility classes). All core components + pages read from the design tokens, so overriding `--primary`/`--radius`/fonts restyles every page. If a theme needs new tokens or components, add them to core (vendor the shared parts); a project can also add its own component + page files alongside `registerCoreTemplates()`. Don't edit core's `core.css` for one-off project tweaks.

> **Frontend dependency management (workspace era)** — each package's `package.json` declares
> `peerDependencies` for every runtime npm package it imports (React, Inertia, Lexical, Base UI,
> shadcn, etc.) and consumes apps declare them as direct dependencies. Resolution parity
> between apps is guaranteed by the **single root `pnpm-lock.yaml`** (workspace members cannot
> drift from siblings) — the old per-app `package-lock.json` + `vite.dedupe.js` setup is gone
> **everywhere**, including `apps/laravel.booking` (converted 2026-10-02: no vendor imports, no
> `dedupe`, `tsconfig.json` + `vite.config.js` resolve `@nreljed/*` from `packages/*`). All three
> workspace members — root app, `apps/laravel.shop`, `apps/laravel.booking` — now use the same
> convention. Note `packages/booking-module` exports **only** `./booking-aliases`, so its
> `@nreljed/booking-module/{booking,properties}/*` subpaths need explicit entries in BOTH
> `tsconfig.json` `paths` and `vite.config.js` `resolve.alias` (the platform/blog/shop-module
> wildcard paths resolve on their own). Keeping one resolved copy of React/Inertia is pnpm's job
> now, not `resolve.dedupe` — dropping it produced no duplicate vendor chunk.
> To add a runtime npm dependency to a package, update that package's `package.json` peers AND
> list it as a direct dependency in every app that imports it; then `pnpm install` once at the
> root and commit the lockfile. Run `pnpm workspace <name> add <pkg>` / `pnpm -w add <pkg>`
> to modify the root/workspace manifests cleanly.
