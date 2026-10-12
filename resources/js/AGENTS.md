# Frontend (`resources/js`)

Scoped context for this app's frontend shell + theme. Read before editing pages, layouts,
the site kit, or `page-config.ts`.

## Platform / Theme / Module (mental model)

The core package is the _engine_ (routes, controllers, shared props, RBAC, deploy,
notifications, media) and ships a component KIT (ui/, hooks, layouts, editor) plus PLATFORM
pages (auth/dashboard/admin/account/settings/error). Template/marketing pages are a **theme
concern and live in the project** (or a fork-me starter). "Modules" are self-contained
`FeatureProvider`s discovered by `FeatureRegistry` that plug routes, permissions, nav items,
dashboard data and their own frontend pages into the platform. Keep engine code in core,
keep project visuals in `resources/js`, keep project features in `app/`.

**Platform skills** (feature-architecture, permission-system, lexical-editor-development)
describe the platform package, not this app. App/project conventions stay in this repo's
`.agents/skills/`.

## Frontend page resolution

Inertia resolves pages by **lowercased** component name. `dashboard.ts` exposes
`registerCoreTemplates()` — the opt-in CORE kit: PLATFORM pages from `./Pages/**/*.tsx`,
feature pages from `../../src/*/Resources/js/Pages/**/*.tsx` prefixed `blog/`/`public/`. The
blog package ships `@nreljed/blog/pages` → `registerBlogPages()`. The app entry spreads these
into `basePages`, then applies its `pageOverrides` (project theme swaps). Controllers render
lowercase names, e.g. `Inertia::render('dashboard/index')`, `'public/about'`, `'blog/index'`
(→ `Blog/Public/Index.tsx`).

## Frontend ownership (opt-in page inventory)

CORE never auto-registers pages. Each app's `page-config.ts` builds its own explicit pageMap:
`basePages: registerCoreTemplates()` (the shared control panel + template pages it wants),
plus `projectPages` for namespaced project pages (`projectPrefix`), plus `pageOverrides` to
swap any CORE-owned page for a project page (applied last, wins — e.g.
`"public/about": () => import("./frontend/pages/public/About")`). Routes + controllers stay in
CORE (they render `public/*`, `dashboard/*`, `blog/*` …) — only the page component swaps. This
is how Shop/Booking ship their own marketing sites without forking core. The client entry
(`app.tsx`) imports `pageConfig` — never duplicate the config block.

## Frontend theming

Consuming apps can re-theme the shared UI without forking core. Import `core.css` in their
`resources/css/app.css`, then override any subset of CSS variables under `@layer base` (tokens
only, keep the utility classes). All core components + pages read from the design tokens, so
overriding `--primary`/`--radius`/fonts restyles every page. If a theme needs new tokens or
components, add them to core (vendor the shared parts); a project can also add its own
component + page files alongside `registerCoreTemplates()`. Don't edit core's `core.css` for
one-off project tweaks.

## Code style (frontend)

- Functional components with hooks, strict TypeScript; 2-space indent.
- Use `cn()` from the `cn` package for class merging.
- All routes via Ziggy's `route()` helper — no hardcoded URLs.
- Inertia `useForm` for all form state.
- `useAuth` hook from `@nreljed/platform/hooks/use-auth` for auth state.
- `next-themes` `<ThemeProvider>` for dark mode.
- `toast.add()` from `@nreljed/platform/Components/ui/toast` (Base UI) for flash messages —
  `toast.add({ title: "msg", type: "success" | "error" | "info" | "warning" })`.
- Component imports use the `@nreljed/platform/...` / `@nreljed/blog/...` workspace aliases.
- Aliases (workspace-mapped in `tsconfig.json`, NOT vite): `@nreljed/platform/*` →
  `packages/platform/resources/js/*`; `@nreljed/blog/*` → `packages/blog/src/Resources/js/*`.
  The legacy vendor aliases (`@`, `@dashboard`, `@blog`) are gone — package sources import
  each other via `@nreljed/*` and relative paths.
- Base UI composition uses the `render` prop — never `asChild` / `Slot`.

## Key shared props

`HandleInertiaRequests` shares `auth.user`, `latestNotifications`, `notificationUnreadCount`,
`flash`, `featureNavItems`, `userCapabilities`, `emailSettings`, `brand`, `impersonating`.

## Dev notes

- `public/hot` should NOT exist — causes `ERR_CONNECTION_REFUSED` on the Vite dev server.
- Always run `npm run build` after code changes; CSP middleware only applies in production.
