# Frontend

The core frontend ships as **TypeScript source** in `resources/js/` — React 19 + Inertia 3 +
Vite + Tailwind CSS v4 + shadcn/ui (Radix + Lucide). Apps consume it via the `@dashboard`
alias; core never auto-registers pages.

## Consuming in a Host App

1. **Vite alias:** `alias: { '@dashboard': 'vendor/nreljed/dashboard-core/resources/js' }`
2. **tsconfig paths:** `"@dashboard/*": ["vendor/nreljed/dashboard-core/resources/js/*"]`
3. **Tailwind:** add `vendor/nreljed/dashboard-core/resources/js` to `@source`
4. **CSS:** `@import` `resources/css/core.css` (header comment documents per-app token
   overrides)

### Page Inventory (opt-in)

Each app builds an explicit page map in `app.tsx` — spread the core kit, add namespaced
project pages, shadow any core page:

```ts
import { createFoundationApp, type FoundationAppConfig } from "@dashboard/app";
import { registerCoreTemplates } from "@dashboard/dashboard";

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
│   ├── blog/                 # ProseContent (blog article renderer)
│   ├── dashboard/            # KpiCard, Sparkline, RevenueChart, RecentOrdersTable, ...
│   ├── editor/               # Lexical rich text editor (image, layout, tables, YouTube,
│   │                         #   slash commands, TOC, find/replace, word count)
│   ├── frontend/             # SiteHeader, SiteFooter, BrandLogo, CurrencySwitcher
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
- **No ShadCN `asChild`**: avoid `Slot`; use plain `<Link>`.
- **Forms**: Inertia `useForm` (server-side validation) + optional Zod client-side.
- **Routes**: Ziggy `route()` helper — no hardcoded URLs.
- **Dark mode**: `next-themes` `<ThemeProvider>`; theme toggle in nav-user.
- **Toasts**: `sonner` `toast()` for flash messages.
- **Editor**: Lexical-based `rich-text-editor.tsx` with a full plugin ecosystem
  (`editor/plugins/*`, `editor/toolbar/*`, `editor/floating/*`).

## Feature Icons

Nav item icons resolve by name through `lib/icons.ts` (`registerFeatureIcons`); unknown names
fall back to a generic icon in `app-sidebar`/`nav-main`.

## Related

- [Architecture](architecture.md) — how pages/props are wired from the provider.
- [Feature Provider](feature-provider.md) — `getSharedData()` lazy closures behind
  `props.sharedData`.
