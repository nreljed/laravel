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
component swaps. Feature packages register their pages via `import.meta.glob("@shop/Pages/**/*.tsx")`
with `projectPrefix: "shop"` (see the Shop package docs).

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

| Area        | Pages                                                                                                                                                                                                                                 |
| ----------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Auth        | `Auth/{Login,Register,RegisterClosed,Forgot-Password,Reset-Password,Verify-Email,Confirm-Password,Email-Verified}`                                                                                                                    |
| Dashboard   | `dashboard/{index,stats}`, `dashboard/notifications/{index,quick-send-section,system-status-cards,test}`                                                                                                                              |
| Account     | `dashboard/account/{profile,security,notifications,advanced,integrations}`                                                                                                                                                            |
| Admin       | `dashboard/admin/{users/{index,form,sessions},roles/{index,form},permissions/manage,audit-logs/index,settings/{general,security,registration,email,notifications,maintenance,ip-access,dev},system-health,database-maintenance,logs}` |
| Public blog | `Blog/Public/{Index,Show}`                                                                                                                                                                                                            |
| Developer   | `dashboard/developer/style-guide`                                                                                                                                                                                                     |
| Errors      | `Error.tsx`                                                                                                                                                                                                                           |

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

## Feature Icons

Nav item icons resolve by name through `lib/icons.ts` (`registerFeatureIcons`); unknown names
fall back to a generic icon in `app-sidebar`/`nav-main`.

## Related

- [Architecture](architecture.md) — how pages/props are wired from the provider.
- [Feature Provider](feature-provider.md) — `getSharedData()` lazy closures behind
  `props.sharedData`.
