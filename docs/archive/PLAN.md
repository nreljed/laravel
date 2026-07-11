# Dashboard Core Extraction — Multi-Product Architecture Plan

**Version:** 1.0
**Date:** 2026-07-11
**Goal:** Extract a reusable dashboard core from Cost Breaker 360, enabling rapid bootstrap of 20+ different Laravel products sharing the same foundation.

---

## Table of Contents

- [Executive Summary](#1-executive-summary)
- [Architecture](#2-architecture)
- [Pre-Extraction Cleanup](#3-phase-1-pre-extraction-cleanup-week-1)
- [Dashboard Core Package](#4-phase-2-create-nreljeddashboard-core-week-2-3)
- [UI Package](#5-phase-3-create-nreljedui-week-4)
- [Migrate Cost Breaker 360](#6-phase-4-migrate-cost-breaker-360-week-5)
- [Project Template](#7-phase-5-project-template-week-5-6)
- [Security Architecture](#8-security-architecture)
- [Core Update Propagation](#9-core-update-propagation)
- [File Inventory](#10-file-inventory)
- [Audit Findings](#11-audit-findings)
- [Open Questions](#12-open-questions)

---

## 1. Executive Summary

### Current State

Cost Breaker 360 is a monolithic Laravel + React + Inertia app containing:

| Layer          | Count                                | Examples                                                                     |
| -------------- | ------------------------------------ | ---------------------------------------------------------------------------- |
| Core Services  | 19                                   | Settings, Mail, Notification, Formatting, Audit, Media, SystemHealth         |
| Module System  | 4 infrastructure files               | ModuleInterface (27 methods), ModuleRegistry, ModuleLoader, BookableRegistry |
| Domain Modules | 4 (Shop, Booking, Properties, Posts) | 42 models, 41 controllers, 43 services, 56 migrations                        |
| UI Components  | 79                                   | 50 shadcn/ui + 20 custom + 5 layouts                                         |
| Middleware     | 7                                    | Admin, CheckPermission, CheckRole, CSP, Inertia, ModuleEnabled, CacheHeaders |
| Hooks          | 14                                   | use-auth, use-notifications, use-currency, use-formatting, etc.              |

### The Problem

Everything is entangled:

- `HandleInertiaRequests` (398 lines) shares 25+ props mixing core concerns (auth, flash, notifications) with domain concerns (cart, wishlist, currencies, email templates)
- `DashboardService::getAnalytics()` directly queries Shop models (Order, Product, Cart) — no other product can use it
- `app-sidebar.tsx` has hardcoded admin/dev nav items plus domain-specific guest mode overrides
- `buildAuthUser()` returns WordPress-style field names and hardcoded module profiles
- Admin layout and authenticated layout are 90% identical copies
- 3 near-identical cart pages (~880 lines of duplication)
- 4 different pagination implementations across modules
- 8 major frontend inconsistencies between Shop and Booking

### The Solution

Extract 2 packages + create a project template:

| Package                   | Registry                   | Purpose                                                         |
| ------------------------- | -------------------------- | --------------------------------------------------------------- |
| `nreljed/dashboard-core`  | GitHub Packages (Composer) | Backend: services, middleware, module system, controllers, RBAC |
| `@nreljed/ui`             | GitHub Packages (npm)      | Frontend: layouts, components, hooks, sidebar                   |
| `nreljed/laravel-starter` | GitHub                     | Template: wires everything together for new products            |

### Result

- New product bootstrap: **~1 day** (vs weeks)
- Core security patches: **`composer update` + `npm update`** across all products
- Consistent architecture: **identical patterns** across all products
- Independent customization: **config-driven** shared props, sidebar, analytics

---

## 2. Architecture

### Three-Layer Model

```
┌─────────────────────────────────────────────────────────┐
│  LAYER 3: PRODUCTS (your specific apps)                 │
│                                                         │
│  Cost Breaker 360    CRM           LMS                  │
│  ├─ Shop module      ├─ Contacts   ├─ Courses           │
│  ├─ Booking module   ├─ Deals      ├─ Quizzes           │
│  ├─ Properties       ├─ Pipeline   ├─ Certificates      │
│  └─ Posts            └─ Reports    └─ Enrollments       │
│                                                         │
│  Each product provides:                                 │
│  • Domain modules (app/Modules/*)                       │
│  • DashboardAnalyticsProvider implementation            │
│  • Config overrides (core.php, modules.php)             │
│  • Inertia pages (resources/js/Pages/*)                 │
└──────────────────────┬──────────────────────────────────┘
                       │ depends on
┌──────────────────────▼──────────────────────────────────┐
│  LAYER 2: DASHBOARD CORE                                │
│                                                         │
│  nreljed/dashboard-core (Composer):                     │
│  ├─ Middleware: Admin, Permission, Role, CSP, Inertia   │
│  ├─ Services: 17 (Settings, Mail, Notification, etc.)   │
│  ├─ Models: User, Role, Permission, Setting, etc.       │
│  ├─ Module System: Interface, Registry, Loader, Routes  │
│  ├─ Controllers: Dashboard, Admin (Users/Roles/etc.)    │
│  ├─ Traits: HasInertiaResponses, HasFilters, Pagination │
│  ├─ Security: CSP, CSRF, Rate Limiting, RBAC            │
│  ├─ Config: core.php, modules.php (publishable)         │
│  └─ Migrations: 17 core tables                          │
│                                                         │
│  @nreljed/ui (npm):                                     │
│  ├─ Layouts: Authenticated, Guest, Public, Store        │
│  ├─ UI: 50 shadcn/ui components                         │
│  ├─ Shared: 12 (PageHeader, TableList, FilterBar, etc.) │
│  ├─ Dashboard: KpiCard, Sparkline, RevenueChart, etc.    │
│  ├─ Notifications: Bell, Panel, DetailDialog             │
│  ├─ Sidebar: data-driven AppSidebar, NavMain, NavUser    │
│  ├─ Hooks: 14 (use-auth, use-notifications, etc.)       │
│  └─ Lib: utils, icons, country-names                     │
└──────────────────────┬──────────────────────────────────┘
                       │ depends on
┌──────────────────────▼──────────────────────────────────┐
│  LAYER 1: INFRASTRUCTURE                                │
│  Laravel 13 │ React 19 │ Inertia.js 3 │ TypeScript 6   │
│  Vite 8 │ Tailwind 4 │ Radix UI │ shadcn/ui            │
└─────────────────────────────────────────────────────────┘
```

### Package Dependencies

```
nreljed/dashboard-core (Composer)
  requires: laravel/framework ^13, inertiajs/inertia-laravel ^3, laravel/sanctum ^4

@nreljed/ui (npm)
  peerDependencies: react ^19, react-dom ^19, @inertiajs/react ^3, tailwindcss ^4

Each Product (Composer)
  requires: nreljed/dashboard-core ^1.0

Each Product (npm)
  requires: @nreljed/ui ^1.0
```

### Registry

Both packages published to GitHub Packages (free for private repos).

---

## 3. Phase 1: Pre-Extraction Cleanup (Week 1)

All changes within the current Cost Breaker 360 codebase. No packages yet. This phase fixes inconsistencies and de-risks extraction.

### 3.1 Standardize Frontend Patterns

| #    | Fix                                        | Current State                                                             | Target State                          | Files                                                  | Hours |
| ---- | ------------------------------------------ | ------------------------------------------------------------------------- | ------------------------------------- | ------------------------------------------------------ | ----- |
| 1.1  | Merge AdminLayout into AuthenticatedLayout | 2 near-identical layouts (90% same)                                       | Single layout with `variant` prop     | AuthenticatedLayout.tsx, AdminLayout.tsx               | 2h    |
| 1.2  | Fix tsconfig.json                          | Missing @modules-shop, @modules-posts aliases; module TS not type-checked | All aliases present, include expanded | tsconfig.json                                          | 1h    |
| 1.3  | Shop Tags/Brands use destructure props     | `usePage<PageProps>()`                                                    | Destructure in function params        | Tags/Index.tsx, Brands/Index.tsx                       | 1h    |
| 1.4  | Shop Categories/Tags/Brands use TableList  | Raw `<table>` elements                                                    | `TableList` component                 | Categories/Index.tsx, Tags/Index.tsx, Brands/Index.tsx | 3h    |
| 1.5  | Webhooks uses AuthenticatedLayout          | `AdminLayout`                                                             | `AuthenticatedLayout`                 | Webhooks/Index.tsx                                     | 30m   |
| 1.6  | Extract Booking STATUS_TABS constants      | Duplicated in Index.tsx + MyBookings.tsx                                  | Shared constants file                 | NEW: booking-constants.ts                              | 30m   |
| 1.7  | Extract BookingRecord interface            | Duplicated in Index.tsx + MyBookings.tsx                                  | Shared types file                     | NEW: booking-types.ts                                  | 30m   |
| 1.8  | Consolidate 3 Cart pages                   | 3 near-identical pages (~880 lines)                                       | Single `CartContent` + layout prop    | ShoppingCart.tsx, Cart/Index.tsx, My/Cart/Index.tsx    | 4h    |
| 1.9  | Create shared PaginationLinks              | 4+ different pagination implementations                                   | Single component in shared/           | NEW: shared/pagination-links.tsx                       | 2h    |
| 1.10 | Create shared FilterBar                    | 3 different filter implementations                                        | Single component in shared/           | NEW: shared/filter-bar.tsx                             | 2h    |

**Total: ~16 hours**

### 3.2 Security Hardening

| #    | Fix                                     | Priority | Detail                                                     | Hours |
| ---- | --------------------------------------- | -------- | ---------------------------------------------------------- | ----- |
| 1.11 | Deploy: POST-only + rate limit          | CRITICAL | Change `GET /deploy/check` to `POST`, add `throttle:5,60`  | 2h    |
| 1.12 | Deploy: Remove header-based admin creds | CRITICAL | Admin bootstrap via env vars, not HTTP headers             | 1h    |
| 1.13 | Session: Enable encryption              | HIGH     | `encrypt: true` in production config                       | 30m   |
| 1.14 | CSRF: Narrow exemptions                 | HIGH     | Replace `booking/*` wildcard with specific guest endpoints | 2h    |
| 1.15 | CSP: Restrict WebSocket                 | HIGH     | Change `ws://* wss://*` to application domain              | 1h    |
| 1.16 | Rate limiting: Module routes            | MEDIUM   | Add `throttle:60,1` to `HasRoutes::loadRoutes()`           | 1h    |

**Total: ~7.5 hours**

### 3.3 Verification

After all cleanup:

```bash
php artisan test              # All 176+ tests pass
npm run typecheck             # TypeScript clean
vendor/bin/pint               # PSR-12 clean
npm run build                 # Production build succeeds
php artisan route:list        # All routes registered
php artisan modules:debug     # Modules booting correctly
```

---

## 4. Phase 2: Create `nreljed/dashboard-core` (Week 2-3)

### 4.1 Repository Setup

```bash
gh repo create nreljed/laravel-core --private
git clone https://github.com/nreljed/laravel-core.git
```

### 4.2 composer.json

```json
{
  "name": "nreljed/dashboard-core",
  "description": "Shared Laravel dashboard: auth, RBAC, module system, services, middleware",
  "type": "library",
  "license": "proprietary",
  "require": {
    "php": "^8.3",
    "laravel/framework": "^13.0",
    "laravel/sanctum": "^4.0"
  },
  "autoload": {
    "psr-4": {
      "Nreljed\\DashboardCore\\": "src/"
    }
  },
  "extra": {
    "laravel": {
      "providers": ["Nreljed\\DashboardCore\\CoreServiceProvider"]
    }
  }
}
```

### 4.3 Package Structure

```
nreljed/dashboard-core/
├── composer.json
├── src/
│   ├── CoreServiceProvider.php
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── ModuleController.php          # Abstract base with traits
│   │   │   ├── DashboardController.php       # With DashboardDataProvider pattern
│   │   │   ├── AdminController.php           # Settings + System Health
│   │   │   └── Admin/
│   │   │       ├── UserController.php
│   │   │       ├── RoleController.php
│   │   │       ├── PermissionController.php
│   │   │       ├── AuditLogController.php
│   │   │       ├── BulkUserController.php
│   │   │       ├── SessionController.php
│   │   │       └── ImpersonationController.php
│   │   ├── Middleware/
│   │   │   ├── AdminMiddleware.php
│   │   │   ├── CheckPermission.php
│   │   │   ├── CheckRole.php
│   │   │   ├── ContentSecurityPolicy.php
│   │   │   ├── ForceInertiaCacheHeaders.php
│   │   │   ├── HandleInertiaRequests.php     # Base class, config-driven
│   │   │   └── ModuleEnabled.php
│   │   └── Traits/
│   │       ├── HasInertiaResponses.php       # renderPage(), redirectWithStatus()
│   │       ├── HasFilters.php                # applySearch(), applyStatusFilter()
│   │       └── HasPagination.php             # paginateOrAll()
│   ├── Services/
│   │   ├── AccountService.php
│   │   ├── AuditService.php
│   │   ├── CacheService.php
│   │   ├── DashboardService.php              # Base (without getAnalytics)
│   │   ├── EmailSettingsService.php
│   │   ├── EmailTemplateService.php
│   │   ├── FormattingService.php
│   │   ├── GalleryService.php
│   │   ├── MailService.php
│   │   ├── MailerConfigurator.php
│   │   ├── MediaService.php
│   │   ├── NotificationService.php
│   │   ├── NotificationTypeRegistry.php
│   │   ├── PermissionCacheService.php
│   │   ├── PushNotificationService.php
│   │   ├── RepeaterService.php
│   │   ├── SettingsSanitizer.php
│   │   ├── SettingsService.php
│   │   └── SystemHealthService.php
│   ├── Models/
│   │   ├── Concerns/
│   │   │   ├── HasRoles.php
│   │   │   └── HasPermissions.php
│   │   ├── Role.php
│   │   ├── Permission.php
│   │   ├── Setting.php
│   │   ├── AuditLog.php
│   │   ├── Notification.php
│   │   ├── NotificationPreference.php
│   │   ├── Page.php
│   │   ├── PushSubscription.php
│   │   └── RepeaterData.php
│   ├── Modules/
│   │   ├── Contracts/
│   │   │   ├── ModuleInterface.php           # 27 methods
│   │   │   ├── Bookable.php
│   │   │   └── DashboardDataProvider.php     # NEW
│   │   ├── ModuleRegistry.php
│   │   ├── ModuleLoader.php
│   │   ├── BookableRegistry.php
│   │   └── Traits/
│   │       └── HasRoutes.php
│   ├── Providers/
│   │   ├── CoreServiceProvider.php
│   │   ├── ModuleServiceProvider.php
│   │   └── PermissionGateServiceProvider.php
│   └── Console/Commands/
│       ├── CoreInstall.php
│       ├── SyncPermissions.php
│       ├── ModulesDebug.php
│       └── PermissionsList.php
├── config/
│   ├── core.php                             # Publishable
│   └── modules.php                          # Publishable
├── database/
│   ├── migrations/                          # 17 core migrations
│   └── seeders/
│       └── RolesAndPermissionsSeeder.php
└── README.md
```

### 4.4 Key Design: Config-Driven Shared Props

```php
// config/core.php
return [
    'shared_props' => [
        // Products register additional shared props:
        'cart' => [
            'resolve' => fn(Request $r) => Cart::forUser($r->user()),
            'auth_only' => true,
        ],
    ],

    'sidebar' => [
        'core_items' => [
            ['title' => 'Dashboard', 'url' => '/dashboard', 'icon' => 'LayoutDashboard'],
            ['title' => 'Stats', 'url' => '/dashboard/stats', 'icon' => 'BarChart3'],
            ['title' => 'Profile', 'url' => '/dashboard/profile', 'icon' => 'User'],
            ['title' => 'Account', 'url' => '/dashboard/account', 'icon' => 'Settings'],
            ['title' => 'Notifications', 'url' => '/dashboard/notifications', 'icon' => 'Bell'],
        ],
        'admin_items' => [
            ['title' => 'Users', 'url' => '/dashboard/admin/users', 'icon' => 'Users', 'permission' => 'manage-users'],
            ['title' => 'Roles', 'url' => '/dashboard/admin/roles', 'icon' => 'Shield', 'permission' => 'manage-roles'],
            ['title' => 'Permissions', 'url' => '/dashboard/admin/permissions', 'icon' => 'Key', 'permission' => 'manage-roles'],
            ['title' => 'Settings', 'url' => '/dashboard/admin/settings', 'icon' => 'Settings', 'permission' => 'manage-settings'],
            ['title' => 'Audit Logs', 'url' => '/dashboard/admin/audit-logs', 'icon' => 'FileText', 'permission' => 'manage-users'],
            ['title' => 'System Health', 'url' => '/dashboard/admin/system-health', 'icon' => 'Activity', 'permission' => 'view-system-health'],
        ],
    ],

    'dashboard_provider' => null,

    'permission_cache_ttl' => 300, // 5 minutes (configurable)
];
```

### 4.5 Key Design: HandleInertiaRequests Base Class

```php
class HandleInertiaRequests extends Middleware
{
    public function share(Request $request): array
    {
        return array_merge(
            $this->coreSharedProps($request),
            $this->getConfiguredSharedProps($request),
        );
    }

    protected function coreSharedProps(Request $request): array
    {
        return [
            'flash' => fn() => [...],
            'csp_nonce' => fn() => $this->cspNonce(),
            'brand' => fn() => [...],
            'app_url' => fn() => config('app.url'),
            'debug' => fn() => config('app.debug'),
            'auth' => ['user' => fn() => $this->buildAuthUser($request)],
            'latestNotifications' => Inertia::lazy(fn() => ...),
            'notificationUnreadCount' => Inertia::lazy(fn() => ...),
            'userCapabilities' => fn() => $this->getUserCapabilities($request),
            'moduleNavItems' => fn() => ModuleRegistry::getAllNavItems(),
            'modules' => fn() => [...],
            'impersonating' => fn() => ...,
            'impersonator' => fn() => ...,
        ];
    }

    protected function getConfiguredSharedProps(Request $request): array
    {
        $props = [];
        foreach (config('core.shared_props', []) as $key => $definition) {
            if (is_string($definition)) {
                $props[$key] = $this->{$definition}($request);
            } elseif (is_array($definition) && isset($definition['resolve'])) {
                if (isset($definition['auth_only']) && $definition['auth_only'] && !$request->user()) {
                    continue;
                }
                $props[$key] = Inertia::lazy(fn() => $definition['resolve']($request));
            }
        }
        return $props;
    }

    protected function buildAuthUser(Request $request): ?array
    {
        $user = $request->user();
        if (!$user) return null;

        return [
            'id' => $user->id,
            'name' => $user->getDisplayName(),
            'email' => $user->email,
            'avatar' => $user->avatar_url,
            'roles' => $user->roles->pluck('slug'),
            'role' => $user->roles->first()?->slug,
            'is_admin' => $user->isAdmin(),
        ];
    }
}
```

### 4.6 Key Design: DashboardDataProvider Interface

```php
interface DashboardDataProvider
{
    public function getKpis(): array;
    public function getCharts(): array;
    public function getRecentItems(): array;
    public function getQuickActions(): array;
}

// Default (no-op) implementation
class DefaultDashboardProvider implements DashboardDataProvider
{
    public function getKpis(): array { return []; }
    public function getCharts(): array { return []; }
    public function getRecentItems(): array { return []; }
    public function getQuickActions(): array { return []; }
}
```

### 4.7 Key Design: ModuleController Base

```php
abstract class ModuleController extends Controller
{
    use HasInertiaResponses, HasFilters, HasPagination;

    protected function redirectWithStatus(string $route, string $message, mixed $param = null): RedirectResponse
    {
        return redirect()->route($route, $param)->with('status', $message);
    }

    protected function renderPage(string $component, array $data = []): Response
    {
        return Inertia::render($component, $data);
    }
}
```

### 4.8 Implementation Tasks

| #    | Task                                                                                      | Hours    |
| ---- | ----------------------------------------------------------------------------------------- | -------- |
| 2.1  | Create GitHub repo + composer.json + package skeleton                                     | 2h       |
| 2.2  | Move PHP files (middleware, services, models, controllers) with namespace update          | 4h       |
| 2.3  | Refactor HandleInertiaRequests → base class + config-driven shared props                  | 6h       |
| 2.4  | Refactor DashboardController → provider pattern                                           | 3h       |
| 2.5  | Create DashboardDataProvider interface + DefaultDashboardProvider                         | 2h       |
| 2.6  | Move module system (ModuleInterface, Registry, Loader, BookableRegistry, HasRoutes)       | 2h       |
| 2.7  | Create CoreServiceProvider with publishable config + migrations                           | 3h       |
| 2.8  | Move 17 core migrations + seeder                                                          | 1h       |
| 2.9  | Create Artisan commands (core:install, sync-permissions, modules:debug, permissions:list) | 2h       |
| 2.10 | Security hardening (CSP, CSRF, session, deploy)                                           | 3h       |
| 2.11 | Write tests for core services and middleware                                              | 4h       |
| 2.12 | Tag v1.0.0                                                                                | 1h       |
|      | **Total**                                                                                 | **~33h** |

---

## 5. Phase 3: Create `@nreljed/ui` (Week 4)

### 5.1 Repository Setup

```bash
gh repo create nreljed/shadcn-ui --private
```

### 5.2 package.json

```json
{
  "name": "@nreljed/ui",
  "version": "1.0.0",
  "type": "module",
  "exports": {
    ".": "./src/index.ts",
    "./components/*": "./src/components/*",
    "./layouts/*": "./src/layouts/*",
    "./hooks/*": "./src/hooks/*",
    "./lib/*": "./src/lib/*"
  },
  "peerDependencies": {
    "react": "^19.0.0",
    "react-dom": "^19.0.0",
    "tailwindcss": "^4.0.0",
    "@inertiajs/react": "^3.0.0"
  },
  "dependencies": {
    "class-variance-authority": "^0.7.0",
    "clsx": "^2.0.0",
    "tailwind-merge": "^2.0.0",
    "lucide-react": "*",
    "@radix-ui/react-dialog": "*",
    "@radix-ui/react-dropdown-menu": "*",
    "@radix-ui/react-select": "*",
    "@radix-ui/react-popover": "*",
    "@radix-ui/react-tabs": "*",
    "sonner": "*",
    "cmdk": "*",
    "vaul": "*",
    "embla-carousel-react": "*"
  }
}
```

### 5.3 Package Structure

```
@nreljed/ui/
├── package.json
├── tsconfig.json
├── src/
│   ├── index.ts                            # Barrel exports
│   ├── components/
│   │   ├── ui/                             # 50 shadcn/ui components
│   │   │   ├── button.tsx
│   │   │   ├── card.tsx
│   │   │   ├── dialog.tsx
│   │   │   ├── input.tsx
│   │   │   ├── table.tsx
│   │   │   └── ... (45 more)
│   │   └── shared/
│   │       ├── page-header.tsx             # Title + subtitle + actions slot
│   │       ├── table-list.tsx              # Card-wrapped table with columns
│   │       ├── filter-bar.tsx              # Search + filter dropdowns + clear
│   │       ├── pagination-links.tsx        # Laravel paginated links
│   │       ├── action-cell.tsx             # Row action dropdown
│   │       ├── delete-dialog.tsx           # Standardized delete confirmation
│   │       ├── status-tabs.tsx             # Status filter tabs with counts
│   │       ├── confirm-dialog.tsx          # Generic confirmation dialog
│   │       ├── publish-section.tsx         # Publish/draft/schedule UI
│   │       ├── featured-image-selector.tsx # Drag-drop image upload
│   │       ├── gallery-uploader.tsx        # Multi-image upload
│   │       └── terms-selector.tsx          # Searchable multi-select
│   ├── dashboard/
│   │   ├── kpi-card.tsx
│   │   ├── sparkline.tsx
│   │   ├── revenue-chart.tsx
│   │   ├── recent-orders-table.tsx
│   │   └── top-products-table.tsx
│   ├── notifications/
│   │   ├── notification-bell.tsx
│   │   ├── notification-panel.tsx
│   │   └── notification-detail-dialog.tsx
│   ├── sidebar/
│   │   ├── app-sidebar.tsx                 # Data-driven from config + modules
│   │   ├── nav-main.tsx
│   │   ├── nav-user.tsx                    # Generic (no host/guest)
│   │   └── command-search.tsx
│   ├── layouts/
│   │   ├── AuthenticatedLayout.tsx         # With variant prop
│   │   ├── GuestLayout.tsx
│   │   ├── PublicLayout.tsx
│   │   └── StoreLayout.tsx
│   ├── hooks/
│   │   ├── use-auth.ts
│   │   ├── use-notifications.ts
│   │   ├── use-currency.ts
│   │   ├── use-formatting.ts
│   │   ├── use-format-date.ts
│   │   ├── use-field-validation.ts
│   │   ├── use-error-handler.ts
│   │   ├── use-repeater-config.ts
│   │   ├── use-password-verification.ts
│   │   ├── use-mobile.ts
│   │   ├── use-tab-state.ts
│   │   ├── use-as-ref.ts
│   │   ├── use-lazy-ref.ts
│   │   └── use-isomorphic-layout-effect.ts
│   └── lib/
│       ├── utils.ts                        # cn(), formatDate(), zodErrorsToMap()
│       ├── icons.ts
│       └── country-names.ts
├── README.md
```

### 5.4 Implementation Tasks

| #    | Task                                                                            | Hours    |
| ---- | ------------------------------------------------------------------------------- | -------- |
| 3.1  | Create GitHub repo + package.json + tsconfig                                    | 1h       |
| 3.2  | Move shadcn/ui components (50)                                                  | 2h       |
| 3.3  | Move shared components (7 existing + 4 new) + dashboard (5) + notifications (3) | 2h       |
| 3.4  | Move layouts (4, after merging Admin+Authenticated)                             | 1h       |
| 3.5  | Move hooks (14) + lib (3)                                                       | 1h       |
| 3.6  | Create data-driven app-sidebar.tsx                                              | 3h       |
| 3.7  | Create configurable app.tsx (page resolution)                                   | 2h       |
| 3.8  | Create generic nav-user.tsx (no host/guest)                                     | 1h       |
| 3.9  | Create barrel exports (index.ts)                                                | 1h       |
| 3.10 | Publish to GitHub Packages                                                      | 1h       |
|      | **Total**                                                                       | **~15h** |

---

## 6. Phase 4: Migrate Cost Breaker 360 (Week 5)

### 6.1 Steps

| Step | Task                                                                                       | Hours    |
| ---- | ------------------------------------------------------------------------------------------ | -------- |
| 4.1  | `composer require nreljed/dashboard-core:^1.0`                                             | 1h       |
| 4.2  | `npm install @nreljed/ui`                                                                  | 1h       |
| 4.3  | Delete extracted files from Cost Breaker 360                                               | 1h       |
| 4.4  | Create `ShopDashboardProvider` (implements `DashboardDataProvider`)                        | 2h       |
| 4.5  | Create `CostBreakerHandleInertiaRequests` (extends base, adds cart/wishlist/currencies)    | 1h       |
| 4.6  | Publish and customize `config/core.php` with product-specific shared props + sidebar items | 1h       |
| 4.7  | Publish and customize `config/modules.php` with module paths                               | 30m      |
| 4.8  | Update `app.tsx` to use configurable page resolution                                       | 1h       |
| 4.9  | Update imports across all module pages (new package paths)                                 | 1h       |
| 4.10 | Run full verification: test + typecheck + pint + build                                     | 2h       |
| 4.11 | Tag as first product using the new architecture                                            | 30m      |
|      | **Total**                                                                                  | **~12h** |

### 6.2 What Stays in Cost Breaker 360

```
app/
├── Modules/
│   ├── Shop/                 (29 models, 28 controllers, 13 services)
│   ├── Booking/              (2 models, 8 controllers, 24 services)
│   ├── Properties/           (7 models, 2 controllers, 6 services)
│   └── Posts/                (4 models, 3 controllers, 0 services)
├── Http/Controllers/
│   ├── AccountController.php
│   ├── ProfileController.php
│   ├── AvatarController.php
│   ├── DeployController.php                  (security-hardened)
│   ├── EmailSettingsController.php
│   └── ModuleController.php
├── Http/Middleware/
│   └── CostBreakerHandleInertiaRequests.php  (NEW — extends base)
├── Policies/                                 (12 policies)
├── Providers/
│   ├── AppServiceProvider.php
│   └── ShopDashboardProvider.php             (NEW — implements DashboardDataProvider)
resources/js/
├── Pages/                                    (all Inertia pages)
├── Components/                               (product-specific)
│   ├── settings/sections/                    (14 sections)
│   ├── account/sections/                     (6 sections)
│   ├── frontend/                             (4 frontend components)
│   └── ...
config/
├── core.php                                  (published, customized)
└── modules.php                               (published, customized)
```

---

## 7. Phase 5: Project Template (Week 5-6)

### 7.1 Template Structure

```
nreljed/laravel-starter/
├── composer.json
│   "require": {
│       "nreljed/dashboard-core": "^1.0"
│   }
├── package.json
│   "dependencies": {
│       "@nreljed/ui": "^1.0"
│   }
├── app/
│   ├── Http/Controllers/
│   │   └── DashboardController.php
│   ├── Modules/                            # Empty
│   └── Providers/
│       └── AppServiceProvider.php
├── resources/js/
│   ├── Pages/
│   ├── hooks/
│   └── app.tsx
├── routes/web.php
└── config/
    ├── core.php
    └── modules.php
```

### 7.2 Bootstrap Command

```bash
git clone https://github.com/nreljed/laravel-starter.git my-new-product
cd my-new-product
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan core:install
php artisan serve
```

### 7.3 Implementation Tasks

| #   | Task                                         | Hours   |
| --- | -------------------------------------------- | ------- |
| 5.1 | Create nreljed/laravel-starter template repo | 2h      |
| 5.2 | Wire core packages + publish configs         | 1h      |
| 5.3 | Create example module (simple CRUD)          | 2h      |
| 5.4 | Create example DashboardDataProvider         | 1h      |
| 5.5 | Write README with setup instructions         | 1h      |
|     | **Total**                                    | **~7h** |

---

## 8. Security Architecture

### 8.1 Core-Enforced Security (Non-Negotiable)

| Control            | Implementation                                        | Module Bypass?                                |
| ------------------ | ----------------------------------------------------- | --------------------------------------------- |
| Authentication     | `auth` middleware on all dashboard routes             | NO — HasRoutes always includes `auth`         |
| Authorization      | `permission:{slug}` middleware + `$this->authorize()` | NO — HasRoutes enforces permission middleware |
| Admin isolation    | `admin` middleware + `Gate::before()`                 | NO — core controls the Gate                   |
| CSRF               | Laravel CSRF with configurable exemptions             | NO — modules cannot modify exemptions         |
| CSP                | ContentSecurityPolicy middleware (all routes)         | NO — modules cannot modify headers            |
| Rate limiting      | `throttle:60,1` on admin + module routes              | Modules can add their own throttling          |
| Session encryption | `config('session.encrypt')` = true                    | NO — core config                              |
| Module trust       | Modules must implement ModuleInterface                | Core validates the contract                   |

### 8.2 Security Fixes Applied During Extraction

| Fix                                     | Priority | Detail                                |
| --------------------------------------- | -------- | ------------------------------------- |
| Deploy: POST-only + rate limit          | CRITICAL | Change GET to POST, add throttle:5,60 |
| Deploy: Remove header-based admin creds | CRITICAL | Admin bootstrap via env vars          |
| Session: Enable encryption              | HIGH     | encrypt: true in production           |
| CSRF: Narrow exemptions                 | HIGH     | Replace booking/* wildcard            |
| CSP: Restrict WebSocket                 | HIGH     | Application domain only               |
| CSP: Apply to storefront                | HIGH     | All routes, not just /dashboard       |
| Rate limiting: Module routes            | MEDIUM   | throttle:60,1 in HasRoutes            |
| Permission cache: Configurable TTL      | LOW      | config('core.permission_cache_ttl')   |

### 8.3 Module Security Contract

The core validates modules at registration:

```php
// ModuleLoader::load()
foreach ($this->discoverModules($path) as $module) {
    if (!$module instanceof ModuleInterface) {
        Log::warning("Module {$className} does not implement ModuleInterface, skipping");
        continue;
    }
    Log::info("Module registered: {$module->slug()} v{$module->version()}");
    $this->registry->register($module);
}
```

---

## 9. Core Update Propagation

### 9.1 How Security Patches Reach All Products

```
Security fix in nreljed/dashboard-core v1.0.1
  │
  ├── Tag new version: git tag v1.0.1 && git push --tags
  │
  ├── Script runs across all products:
  │   for project in cost-breaker my-crm my-lms; do
  │     cd ~/repos/$project
  │     composer update nreljed/dashboard-core --with-dependencies
  │     npm update @nreljed/ui
  │     php artisan test
  │     npm run typecheck
  │     npm run build
  │     git add . && git commit -m "chore: update core to v1.0.1"
  │     git push
  │   done
  │
  └── Each product deploys independently
```

### 9.2 Versioning Rules

| Version               | When                             | Example                                          |
| --------------------- | -------------------------------- | ------------------------------------------------ |
| Patch (1.0.0 → 1.0.1) | Bug fix, security patch          | Fix CSP header, patch XSS vector                 |
| Minor (1.0.0 → 1.1.0) | New feature, backward compatible | Add HasFilters trait, new shared component       |
| Major (1.0.0 → 2.0.0) | Breaking change                  | Remove deprecated method, change ModuleInterface |

### 9.3 Backward Compatibility Guarantees

| Guarantee                            | How Enforced                                     |
| ------------------------------------ | ------------------------------------------------ |
| Existing shared props keep working   | New props added, never removed                   |
| ModuleInterface methods keep working | New methods always have default implementations  |
| HasRoutes behavior stays same        | New methods added, existing signatures unchanged |
| Config keys keep working             | New keys added, never renamed                    |
| Middleware stacks stay same          | New middleware optional, never forced            |

---

## 10. File Inventory

### 10.1 Moves to `nreljed/dashboard-core`

```
src/Http/Middleware/         (7 files)
src/Http/Controllers/        (9 files)
src/Http/Traits/             (3 files)
src/Services/                (17 files)
src/Models/                  (10 files)
src/Modules/                 (6 files)
src/Providers/               (3 files)
src/Console/Commands/        (4 files)
config/                      (2 files)
database/migrations/         (17 files)
database/seeders/            (1 file)
```

### 10.2 Moves to `@nreljed/ui`

```
src/components/ui/           (50 files)
src/components/shared/       (12 files)
src/components/dashboard/    (5 files)
src/components/notifications/ (3 files)
src/components/sidebar/      (4 files)
src/layouts/                 (4 files)
src/hooks/                   (14 files)
src/lib/                     (3 files)
src/app.tsx                  (1 file)
```

### 10.3 Stays in Cost Breaker 360

```
app/Modules/Shop/            (70+ files)
app/Modules/Booking/         (40+ files)
app/Modules/Properties/      (20+ files)
app/Modules/Posts/           (10+ files)
app/Providers/AppServiceProvider.php
app/Providers/ShopDashboardProvider.php     (NEW)
app/Http/Middleware/CostBreakerHandleInertiaRequests.php (NEW)
app/Http/Controllers/DeployController.php
app/Policies/                (12 files)
resources/js/Pages/          (all Inertia pages)
resources/js/Components/     (product-specific)
config/core.php
config/modules.php
```

---

## 11. Audit Findings

### 11.1 Frontend Overlap (Shop vs Booking)

**Identical patterns (both modules, same code):**

- AuthenticatedLayout wrapper — 100% identical
- DataTable (tanstack) — same component, same props
- Inertia useForm — identical form pattern
- Dialog / AlertDialog — same shadcn/ui modals
- toast() from sonner — identical
- router.post/get/delete + preserveScroll — identical
- Empty components — identical

**Divergent patterns (same concept, different implementation):**

- Table engine: Shop uses DataTable + TableList + raw table (5 patterns); Booking uses DataTable
- Row actions: Shop uses ActionCell; Booking builds custom DropdownMenu
- Status badges: Shop uses Badge variants; Booking uses custom CSS map
- Filter bar: Shop has FilterBar template; Booking has custom BookingFilterBar
- Status tabs: Shop uses shadcn Tabs; Booking builds custom pill buttons
- Page header: Shop uses PageHeader (5 pages); Booking uses hand-rolled divs
- Delete confirmation: Shop uses Dialog (6) + confirm() (1); Booking uses AlertDialog (2)
- Prop access: Shop destructures; Booking mixes destructure + usePage()

### 11.2 Backend Overlap (Shop vs Booking)

**Identical patterns:**

- Controller base class (extends Controller)
- $this->authorize() calls via Policy
- Inertia::render() response pattern
- redirect()->route()->with('status', ...) flash pattern
- Constructor DI pattern
- HasRoutes trait usage
- getNavItems(), getDashboardData(), getFrontendPages(), permissions() structures

**Divergent patterns:**

- FormRequest: Shop uses 9 classes; Booking uses 0 (all inline)
- Authorization: Shop = flat permission; Booking = permission + ownership
- Service layer: Shop has 0-3 services per controller; Booking has 3-7
- Query building: Shop inline in controller; Booking delegates to service
- Route naming: Shop = dashboard.shop.{resource}.{action}; Booking = dashboard.bookings.{action}

### 11.3 Core Services Dependency

| Service                  | Shop | Booking | Both Need?   |
| ------------------------ | ---- | ------- | ------------ |
| SettingsService          | Yes  | Yes     | YES          |
| FormattingService        | Yes  | Yes     | YES          |
| MailService              | Yes  | Yes     | YES          |
| NotificationService      | Yes  | Yes     | YES          |
| PermissionCacheService   | Yes  | Yes     | YES          |
| EmailTemplateService     | Yes  | Yes     | YES          |
| NotificationTypeRegistry | Yes  | Yes     | YES          |
| AuditService             | No   | Yes     | Booking only |
| MediaService             | Yes  | No      | Shop only    |
| DashboardService         | Yes  | No      | Shop only    |
| CacheService             | No   | Yes     | Booking only |

---

## 12. Open Questions

| #   | Question                                           | Recommendation                                                         |
| --- | -------------------------------------------------- | ---------------------------------------------------------------------- |
| Q1  | Should the core provide a generic dashboard page?  | Each product provides its own — current dashboard is too Shop-specific |
| Q2  | Should host/guest mode switch be in core?          | No — Booking-specific                                                  |
| Q3  | Should we add module sandboxing?                   | No — all modules are trusted                                           |
| Q4  | Should AdminLayout merge into AuthenticatedLayout? | Yes — 90% identical                                                    |
| Q5  | Should buildAuthUser() be minimal or full legacy?  | Minimal core — products extend                                         |
| Q6  | Should deploy endpoint move to core?               | No — keep in product                                                   |
| Q7  | Should permission cache TTL be configurable?       | Yes — in config/core.php                                               |
| Q8  | Should CSP apply to storefront?                    | Yes — all routes                                                       |

---

## Timeline Summary

| Week      | Phase                    | Deliverable                           | Hours    |
| --------- | ------------------------ | ------------------------------------- | -------- |
| Week 1    | Pre-Extraction Cleanup   | Standardized codebase, security fixes | ~24h     |
| Week 2-3  | `nreljed/dashboard-core` | Composer package v1.0.0               | ~33h     |
| Week 4    | `@nreljed/ui`            | npm package v1.0.0                    | ~15h     |
| Week 5    | Migrate Cost Breaker 360 | First product on new architecture     | ~12h     |
| Week 5-6  | Project Template         | `nreljed/laravel-starter`             | ~7h      |
| **Total** |                          |                                       | **~91h** |

After extraction: new product in ~1 day, core patches via `composer update`.
