# Frontend

The core frontend ships as **TypeScript source** in `resources/js/` — React 19 + Inertia 3 +
Vite + Tailwind CSS v4 + shadcn/ui (Base UI + Lucide, base-vega style). Apps consume it via
the `@nreljed/platform` workspace package (export map `./app`, `./dashboard`, `./*`); core
never auto-registers pages.

## Consuming in a Host App

Consume via pnpm workspace (e.g. `@nreljed/platform: "workspace:*"`), then wire the source
paths once:

1. **Vite alias:** `"@nreljed/platform": path.resolve(__dirname, "../packages/platform/resources/js")` + a `@nreljed/platform/*` wildcard (bare + `/*` pair — rolldown-vite rejects `/*`-only object keys)
2. **tsconfig paths:** `"@nreljed/platform/*": ["../../packages/platform/resources/js/*"]` (and include the package's `types/global.d.ts` for the ambient `route` global)
3. **Tailwind:** add `packages/platform/resources/js` to `@source`
4. **CSS:** `@import` `resources/css/core.css` (header comment documents per-app token overrides)

### Page Inventory (opt-in)

Each app builds an explicit page map in `app.tsx` — spread the core kit, add namespaced
project pages, shadow any core page:

```ts
import { createFoundationApp, type FoundationAppConfig } from "@nreljed/platform/app";
import { registerCoreTemplates } from "@nreljed/platform/dashboard";

const config: FoundationAppConfig = {
  basePages: registerCoreTemplates(), // the standard core kit
  projectPages: import.meta.glob("./Pages/**/*.tsx"),
  projectPrefix: "shop", // namespaced project pages
  pageOverrides: {
    // swap core pages, wins last
    "public/about": () => import("./Pages/Public/About"),
  },
};

createFoundationApp(config);
```

Routes + controllers stay in core (they render `public/*`, `dashboard/*`, …) — only the page
component swaps. Feature packages register their own admin page kits via their registry
exports (`@nreljed/blog/pages` → `registerBlogPages()`, `@nreljed/shop-module/pages` →
`registerShopPages()`, …); their **public/template pages are theme-owned and ship in the
starter theme or the app, never in the module**.

### Starter Theme (fork-me)

`@nreljed/platform/themes/starter` exports `registerStarterThemePages()` — the opt-in,
**never auto-registered** template kit (public marketing + `blog/public/*`). Consuming apps
either spread it into `basePages`, or fork the `Pages/` tree into the project (the normal
path, see `resources/themes/starter/README.md`). Public pages **must never** be added to the
core `resources/js/Pages` kit — that table is a contract (see
`tests/FrontendPageInventoryContractTest.php`).

## Directory Layout

```
resources/js/
├── app.tsx / dashboard.ts    # createFoundationApp + registerCoreTemplates
├── Components/
│   ├── account/              # account nav + sections (profile, contact, email, password,
│   │                         #   notification prefs, avatar, security, integrations, danger zone)
│   ├── blog/                 # ProseContent (blog article renderer — shared kit, imported by nreljed/blog pages via `@/`)
│   ├── dashboard/            # KpiCard, Sparkline, RevenueChart, RecentOrdersTable, ...
│   ├── editor/               # Lexical rich text editor (image, layout, tables, YouTube,
│   │                         #   slash commands, TOC, find/replace, word count)
│   ├── frontend/             # SiteHeader, SiteFooter, BrandLogo
│   ├── layout/               # app-sidebar, impersonation banner
│   ├── navigation/           # command-search (Ctrl+K palette)
│   ├── shared/               # FeaturedImageSelector, TermsSelector, GalleryUploader, ...
│   ├── ui/                   # shadcn/ui components (button, input, dialog, select, ...)
│   └── (theme-switcher, notification bell, ...)
├── hooks/                    # use-auth, use-notifications, ...
├── Layouts/                  # AdminLayout, AuthenticatedLayout, GuestLayout, PublicLayout
├── lib/                      # cn(), icons registry, format helpers
├── Pages/                    # the core kit (see below)
└── types/                    # shared Inertia prop types
```

## Core Page Kit (`Pages/`)

| Area      | Pages                                                                                                                                                                                                                                 |
| --------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Auth      | `Auth/{Login,Register,RegisterClosed,Forgot-Password,Reset-Password,Verify-Email,Confirm-Password,Email-Verified}`                                                                                                                    |
| Dashboard | `dashboard/{index,stats}`, `dashboard/notifications/{index,quick-send-section,system-status-cards,test}`                                                                                                                              |
| Account   | `dashboard/account/{profile,security,notifications,advanced,integrations}`                                                                                                                                                            |
| Admin     | `dashboard/admin/{users/{index,form,sessions},roles/{index,form},permissions/manage,audit-logs/index,settings/{general,security,registration,email,notifications,maintenance,ip-access,dev},system-health,database-maintenance,logs}` |
| Developer | `dashboard/developer/style-guide`                                                                                                                                                                                                     |
| Errors    | `Error.tsx`                                                                                                                                                                                                                           |

> **Theme-owned pages are NOT core pages.** `public/*` (welcome/about/
> services/contact), `blog/public/*` (Index/Show), and `shop/storefront/*`
> are **project-owned overrides** — they live in the app's `resources/js/Pages`
> (or the fork-me starter theme, `@nreljed/platform/themes/starter`), never in
> core `resources/js/Pages`. The platform only ships the control-panel kit
> above; controllers still render `public/*`, `blog/*`, `shop/storefront/*`
> and the app swaps the page component via `pageOverrides`.

## Key Patterns

- **Shared props**: `HandleInertiaRequests` shares `auth.user`, `latestNotifications`,
  `notificationUnreadCount`, `flash`, `userMode`, `canSwitchMode`, `sharedData` (aggregated
  from feature providers), `featureNavItems`, `userCapabilities`. Hosts subclass it via
  `core.inertia_middleware`.
- **Component imports**: `@/Components/...` (NOT `@/components/...`) — Windows
  case-insensitive filesystem workaround.
- **Composition**: Base UI primitives accept a `render` prop instead of Radix `asChild`
  (removed). `<Button asChild><Link/></Button>` becomes
  `<Button render={<Link/>}>label</Button>`; triggers such as
  `<DialogTrigger render={<Button/>}>label</DialogTrigger>`.
- **Forms**: Inertia `useForm` (server-side validation) + optional Zod client-side.
- **Routes**: Ziggy `route()` helper — no hardcoded URLs.
- **Dark mode**: `next-themes` `<ThemeProvider>`; theme toggle in nav-user.
- **Toasts**: `toast` from `@/Components/ui/toast` — `toast.add({ title, type })`
  (`success | error | info | warning`), rendered by the app's `<Toaster />`.
- **Editor**: Lexical-based `rich-text-editor.tsx` with a full plugin ecosystem
  (`editor/plugins/*`, `editor/toolbar/*`, `editor/floating/*`).

## Browser E2E

The kernel is validated headlessly with Playwright. Each app owns its runnable
harness (`playwright.config.ts` + `e2e/` at the app/workspace root — see the
workspace repo root for the reference implementation). Conventions:

- **Config**: `testDir "./e2e"`, `baseURL` from `APP_URL` (default
  `http://127.0.0.1:8000`), one chromium project, single worker, retry once on CI.
- **Credentials**: `E2E_EMAIL` / `E2E_PASSWORD`, defaulting to the shared demo
  admin (`nstudio.webdesign@gmail.com` / `azerty123/*`). The platform auth form
  uses name-less inputs — match by type/position and await the Inertia redirect
  with `page.waitForURL(/\/dashboard/)`.
- **Commands**: `pnpm test:e2e` (run), `pnpm test:e2e:install` (once, installs
  Chromium). A machine-wide CLI is installed globally (`playwright`), so
  `npx playwright test --config <app>/playwright.config.ts` works in any repo.
- **Ad-hoc checks**: agents write throwaway browser scripts with the global
  `webapp-testing` skill (Python Playwright, output under
  `%TEMP%\opencode\playwright`).

## Feature Icons

Nav item icons resolve by name through `lib/icons.ts` (`registerFeatureIcons`); unknown names
fall back to a generic icon in `app-sidebar`/`nav-main`.

## Related

- [Architecture](architecture.md) — how pages/props are wired from the provider.
- [Feature Provider](feature-provider.md) — `getSharedData()` lazy closures behind
  `props.sharedData`.
