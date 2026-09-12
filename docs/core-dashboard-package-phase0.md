# Phase 0 — Classification & Reconciliation Report

> Date: 2026-08-02 · Master: `nreljed/laravel` · Consumers: `laravel.shop`, `laravel.booking`
> This report is the living Phase 0 deliverable. It feeds Phase 1 (package tree) and Phase 2 (extension layer).

## 0.1 Executive Summary

- **Blog schema gate: VERIFIED COMPATIBLE** — consumers gain 3 blog migrations + 3 root blog-touching migrations, all additive or drops of already-unused legacy columns.
- **User model: divergent but unionable** — core User = master's version (AI + reset override); consumers extend it for domain relations. Consumers lose the unused `nickname` column reference.
- **`ForceInertiaCacheHeaders` is DEAD CODE in both consumers** (defined, never registered) and absent in master — D9 must be re-confirmed (recommend: ship file in core, register or drop).
- **TestPosts is a master-only demo feature** — stays app-side, implements the Feature contract.
- **Page model is dead code in master** — excluded from the package.
- **Booking has no FeatureRegistry** (static provider helpers instead); its providers must be refactored to the instance-based Feature contract on cutover. Booking's `crm_user` migration references a nonexistent `ModuleRegistry` (inert trap) — cleaned on cutover.
- **Toast drift confirmed**: consumers use `sonner`, master uses Base UI `toast.add()` — killed by single-channel source-ship (D3).

## 0.2 Core/App Classification (master files)

Legend: **CORE** = moves into `Nreljed\Platform\` (package) · **APP** = stays in master app.

### PHP — app/

| Path                                                                                                                                                                                                                                                                                 | Class                                                                |
| ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ | -------------------------------------------------------------------- |
| `Ai/**` (all 17 files)                                                                                                                                                                                                                                                               | CORE                                                                 |
| `Blog/**` (all files incl. 8 migrations)                                                                                                                                                                                                                                             | CORE                                                                 |
| `TestPosts/**` (all)                                                                                                                                                                                                                                                                 | APP (demo feature)                                                   |
| `Console/Commands/*` (7 commands)                                                                                                                                                                                                                                                    | CORE                                                                 |
| `Enums/AuditAction.php`                                                                                                                                                                                                                                                              | CORE                                                                 |
| `Http/Controllers/Admin/*`, `Api/NotificationPollController.php`, `Auth/*`                                                                                                                                                                                                           | CORE                                                                 |
| `Http/Controllers/AdminController.php`, `AvatarController.php`, `DashboardController.php`, `EmailSettingsController.php`, `MediaController.php`, `NotificationTestController.php`, `RecoveryController.php`, `SettingsController.php`, `UserAccountController.php`, `Controller.php` | CORE                                                                 |
| `Http/Controllers/DeployController.php`                                                                                                                                                                                                                                              | APP (per-app deploy, matrix #15)                                     |
| `Http/Controllers/PublicController.php`                                                                                                                                                                                                                                              | APP (public pages, D6)                                               |
| `Http/Middleware/*` (all 8)                                                                                                                                                                                                                                                          | CORE                                                                 |
| `Http/Requests/**` (all)                                                                                                                                                                                                                                                             | CORE (incl. `UpdateContactRequest` — dashboard account contact form) |
| `Jobs/*`, `Mail/*`, `Notifications/*`, `Rules/*`                                                                                                                                                                                                                                     | CORE                                                                 |
| `Models/*` — **except** `Page.php` (dead code)                                                                                                                                                                                                                                       | CORE                                                                 |
| `Policies/PostPolicy.php`                                                                                                                                                                                                                                                            | CORE                                                                 |
| `Policies/TestPostPolicy.php`                                                                                                                                                                                                                                                        | APP (TestPosts)                                                      |
| `Providers/AppServiceProvider.php` → becomes package `DashboardCoreServiceProvider` (core parts) + slim app provider                                                                                                                                                                 | CORE/APP split                                                       |
| `Providers/PermissionGateServiceProvider.php`                                                                                                                                                                                                                                        | CORE                                                                 |
| `Providers/TelescopeServiceProvider.php`                                                                                                                                                                                                                                             | APP (dev tooling)                                                    |
| `Services/*` (all 26)                                                                                                                                                                                                                                                                | CORE                                                                 |
| `Traits/RelativeTime.php`, `Traits/UserDataTrait.php`                                                                                                                                                                                                                                | CORE                                                                 |

### PHP — database/migrations/

| Migration                                                                                                               | Class                        |
| ----------------------------------------------------------------------------------------------------------------------- | ---------------------------- |
| `2026_07_11_000000_drop_modules_table.php`                                                                              | APP (master-only historical) |
| `2026_07_13_180000/180001_create_ai_*`                                                                                  | CORE (AI)                    |
| `2026_07_22_191132_add_location_to_test_posts_table.php`                                                                | APP (TestPosts)              |
| `2026_08_01_000000_add_status_published_at_index_to_posts_table.php`                                                    | CORE (Blog)                  |
| All others (users, roles, settings, notifications, audit, push, repeater, ip-rules, recovery, tokens, telescope-except) | CORE                         |

### PHP — routes/, config/, bootstrap/, resources/views/

| Path                                                                                       | Class                         |
| ------------------------------------------------------------------------------------------ | ----------------------------- |
| `routes/auth.php`                                                                          | CORE                          |
| `routes/web.php` — dashboard/admin/account/notifications/media/api/middleware-wiring parts | CORE                          |
| `routes/web.php` — public + deploy + csp-report parts                                      | APP                           |
| `routes/console.php` (scheduler)                                                           | CORE                          |
| `config/core.php` → becomes package config (roles.php, settings.php)                       | CORE                          |
| `bootstrap/providers.php`                                                                  | APP (per-app wiring)          |
| `resources/views/app.blade.php`, `errors/*`, `maintenance.blade.php`, `recovery/*`         | CORE                          |
| `resources/views/email/**`                                                                 | CORE                          |
| `resources/views/vendor/**`                                                                | CORE (mail/pagination vendor) |

### Frontend — resources/js/

| Path                                                                                                                       | Class                                                |
| -------------------------------------------------------------------------------------------------------------------------- | ---------------------------------------------------- |
| `app.tsx` → replaced by `registerDashboard()` helper in consumers                                                          | APP (consumer shell)                                 |
| `Components/ui/**` (56)                                                                                                    | CORE                                                 |
| `Components/shared/**`                                                                                                     | CORE                                                 |
| `Components/dashboard/**` (if any)                                                                                         | CORE                                                 |
| `Components/layout/**`, `navigation/**`, `notifications/**`, `account/**`, `settings/**`, `seo/**`, `editor/**`, `blog/**` | CORE                                                 |
| `Components/frontend/**` (SiteHeader/Footer/BrandLogo/CurrencySwitcher)                                                    | CORE (required by blog public pages, decision 0.9.3) |
| `hooks/**`, `lib/**`, `types/**`, `Layouts/AuthenticatedLayout.tsx`, `AdminLayout.tsx`, `GuestLayout.tsx`, `Error.tsx`     | CORE                                                 |
| `Layouts/PublicLayout.tsx`                                                                                                 | CORE (required by blog public pages, decision 0.9.3) |
| `Pages/Auth/**`                                                                                                            | CORE                                                 |
| `Pages/dashboard/**` (incl. account, admin, notifications, stats, developer)                                               | CORE                                                 |
| `Pages/Public/**` (welcome/about/services/contact)                                                                         | APP                                                  |
| `Pages/Blog/Public/**`                                                                                                     | CORE (decision 0.9.3 — into the package)             |
| `Pages/Error.tsx`                                                                                                          | CORE                                                 |

## 0.3 Blog Migration Diff & Schema Gate

### Consumers' 5 blog migrations (identical basenames — skip on cutover)

`create_post_categories`, `create_post_tags`, `add_seo_columns_to_posts`, `create_post_tag_post`.

### Master's 3 extra blog migrations (run fresh on consumers — all additive)

1. `2026_07_23_191000_add_focus_keyword_to_posts` — adds `focus_keyword` (string 100, nullable)
2. `2026_07_26_000001_add_depth_and_seo_to_post_categories` — adds `depth` (guarded by `hasColumn`), `meta_title`, `meta_description`; backfills depth by parent chain
3. `2026_07_26_000002_add_description_to_post_tags` — adds `description` (text, nullable)

### Master's 3 root migrations touching blog tables (run fresh on consumers)

1. `2026_07_12_000000_fix_posts_content_nullable_and_drop_tags` — makes `content` nullable, **drops `tags` json** (consumers still have it; unused since pivot migration → safe)
2. `2026_07_26_175642_add_order_to_post_tags` — adds `order`
3. `2026_08_01_000000_add_status_published_at_index_to_posts` — adds `posts_user_id_index` + composite `posts_status_published_at_index`

### ⚠️ Behavioral notes (no migration runs — document only)

- `posts.status` default: master `published`, consumers `draft`. Consumers keep `draft` after cutover (no migration changes the default). If master behavior is desired, add a one-time migration at cutover — **decision needed**.
- Consumers' `add_seo_columns_to_posts` also drops `category` string column — same on both sides, no conflict.

**GATE: VERIFIED COMPATIBLE** — all new migrations are additive or drop already-unused legacy columns; `hasColumn` guard present on the categories backfill.

## 0.4 User Model / Trait Parity

`UserDataTrait`: **identical across all three apps** → CORE verbatim.

`User` model divergences:

| Aspect                                          | Master                      | Shop/Booking                         | Union decision                                           |
| ----------------------------------------------- | --------------------------- | ------------------------------------ | -------------------------------------------------------- |
| AI (HasConversations, aiProviders, aiUsageLogs) | ✅                          | ❌                                   | CORE (AI is core)                                        |
| `sendPasswordResetNotification` override        | ✅                          | ❌                                   | CORE (adopt)                                             |
| `HasApiTokens`                                  | ✅ (unused UI)              | ✅ (used UI)                         | CORE (D9 — add UI)                                       |
| `$fillable`/`$hidden` style                     | classic props               | `#[Fillable]`/`#[Hidden]` attributes | CORE (adopt master)                                      |
| `username` boot                                 | `= name` (no slug)          | `= slug(name)`                       | CORE (adopt master)                                      |
| `getDisplayNameOptions`                         | `username` key, no nickname | `nickname` key                       | CORE (adopt master; drop nickname)                       |
| `hasRole` variadic                              | flattens arrays             | no flatten                           | CORE (adopt master's robust version)                     |
| `casts` phone                                   | `=> 'string'`               | absent                               | CORE (adopt master)                                      |
| domain relations (`orders()`)                   | ❌                          | Shop: `orders()`                     | **APP** — consumer's `App\Models\User` extends core User |

**Pattern for consumers:** `app/Models/User.php` = `class User extends Nreljed\Platform\Models\User` adding domain relations. Auth binding (`config/auth.php` providers.users.model) points at the consumer's model. No core edits.

## 0.5 npm-dep Scan & Peer-Dependency Contract

### Master deps consumers LACK (must install at cutover)

`@lexical/*` (17 pkgs), `lexical`, `@shadcn/react`, `@geoman-io/leaflet-geoman-free`, `@atlaskit/pragmatic-drag-and-drop`.

### Consumer deps master LACK

`@stripe/*`, `axios`, `sonner`, `radix-ui`, `react-hook-form`, `react-image-crop`, `leaflet-draw`, `leaflet.markercluster`, `react-leaflet-markercluster`, `@hookform/resolvers`.

- `sonner` → **removed** at cutover (replaced by core Base UI `toast.add()`).
- Shop/Booking keep `@stripe/*`, `react-hook-form`, `react-image-crop`, leaflet-draw/cluster, `axios` for their domain features.
- Booking's map uses leaflet-draw/markercluster — different from master's geoman; stays app-side.

### Core peer-dependency contract (`packages/dashboard-core/package.json`, shipped for reference)

`react`, `react-dom`, `@inertiajs/react`, `next-themes`, `@base-ui/react`, `tailwindcss`, `tailwind-merge`, `clsx`, `class-variance-authority`, `lucide-react`, `recharts`, `@tanstack/react-table`, `date-fns`, `zod`, `ziggy-js`, `cmdk`, `vaul`, `input-otp`, `embla-carousel-react`, `react-phone-number-input`, `@dnd-kit/*` (4), `@zxcvbn-ts/*` (3), `lexical` + `@lexical/*` (17), `@shadcn/react`, `react-day-picker`, `react-resizable-panels`, `tw-animate-css`.
_(Exact versions pinned from master package.json — update step documented in consumer `update-core.sh`.)_

## 0.6 FeatureRegistry & Extension-Layer Findings

| App     | Mechanism                                                                                  | Provider API     |
| ------- | ------------------------------------------------------------------------------------------ | ---------------- |
| Master  | `FeatureRegistry` singleton, hard-codes `BlogServiceProvider` + `TestPostsServiceProvider` | instance methods |
| Shop    | own `FeatureRegistry`, hard-codes `ShopServiceProvider` + `BlogServiceProvider`            | instance methods |
| Booking | **NO FeatureRegistry** — static helpers in `HandleInertiaRequests`                         | static methods   |

**Actions (Phase 2):**

1. Core `FeatureRegistry` discovers providers from `config/core.php` (`features` array) — no hard-coded list.
2. Master/Shop/Booking providers refactored to the shared `Feature` contract (instance methods, D-uniform).
3. Booking's static helper set + `crm_user` migration's `ModuleRegistry` reference cleaned on cutover.
4. Shared-prop union from master (already feature-driven): `featureNavItems`, `featureData`, `emailSettings`, `emailTemplates`, `brand`, `seo`, `app_url`, `registration`, `userCapabilities`, `userMode`, `canSwitchMode`, `formattingSettings`, `impersonating`, `impersonator`, plus notifications. App-specific props (shop: `cart`, `wishlistIds`, `activeCurrencies`, `selectedCurrency`; booking: `wishlistResult`) stay in consumer HandleInertiaRequests.

## 0.7 Dashboard & Stats Findings

- Master `dashboard/index.tsx` = **24-line greeting stub** (rich dashboard in Shop = 310 lines, Booking = 145 lines) → **page override** mechanism validated (D8).
- Master `stats.tsx`: data pipeline **already generic** (`FeatureRegistry::getAllAnalyticsSeries()` + `Inertia::defer()`), but the two chart components are hard-coded `UserGrowthChart` (users series) + `PostActivityChart` (posts series). Phase 2 change: render series generically from the analytics shape; or keep core charts + let consumers override the stats page.
- Shop `dashboard/index.tsx` bug: calls `route("dashboard.index")` (period switch) but no such named route exists → latent Ziggy failure in Shop today. Core must name the route `dashboard.index` (add named route) so the pattern works. **Action for Phase 4.**

## 0.8 Middleware & Deploy Findings

- Master middleware set (8): includes `CheckMaintenanceMode`, `ConfigureSessionLifetime`, `CheckIpAccess` — **consumers lack all three**; they gain them from core on cutover (matrix #8).
- `ForceInertiaCacheHeaders`: dead file in Shop/Booking (never registered), absent in master. **D9 re-confirmation needed** → recommend: ship it in core and actually register it (real Inertia cache win), or drop entirely.
- Master `DeployController` = 521 lines (env update, releases, backup, cache); Shop/Booking have separate implementations → deploy stays **per-app** (matrix #15).
- Master notification polling: native `fetch()` + `api.notifications.*` JSON routes (CSRF-exempt for the 2 GET poll routes) → **CORE**, consistent across apps.

## 0.9 Decisions — RESOLVED ✅

| #   | Decision                             | Resolution                                                                                                                                                                   |
| --- | ------------------------------------ | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| 1   | `posts.status` default divergence    | **Align to `published`** — one-time migration at Shop/Booking cutover                                                                                                        |
| 2   | `ForceInertiaCacheHeaders` dead code | **Adopt + activate** — ship in core AND register in middleware stack                                                                                                         |
| 3   | Blog public pages                    | **Into the package** — `BlogPublicController`, `routes/public.php`, `Pages/Blog/Public/**` move to core                                                                      |
| 4   | `UpdateContactRequest`               | **CORE** — it is the dashboard account contact-info form (`dashboard.account.contact`), not the public contact form; public `ContactPageRequest` stays APP with public pages |
| 5   | Package version start                | `dashboard-core/v1.0.0`                                                                                                                                                      |
| 6   | Consumers' `nickname` column         | Leave in DB (unused); drop column migration optional later                                                                                                                   |

---

## 0.10 Phase 1 Execution — Copy Approach (2026-08-02)

Phase 1 is executed as a **namespace-rewritten COPY** into `packages/dashboard-core/` so master stays green and deployable until Phase 2 switches consumers over. Status as of this writing: **PHP + frontend + views + migrations copied, renamed, and package-tested (5/5 pass). Master test suite still 431/431 green.**

### What went into the package

- **`src/`** — 155 PHP files: `Blog/**` + `Ai/**` features wholesale, `Console/Commands`, `Enums`, `Http/{Controllers,Middleware,Requests}` (incl. all `Auth/*`, `Api/NotificationPollController`, `Admin/*`), `Jobs`, `Mail`, `Models` (Page.php pruned), `Notifications`, `Policies/PostPolicy.php`, `Providers/PermissionGateServiceProvider.php`, `Rules`, `Services`, `Traits`, plus `DashboardCoreServiceProvider.php` and `Database/Factories/*`.
- **`database/migrations/`** — 32/37 core migrations; 5 excluded (see §0.2): `create_pages_table`, `telescope_entries`, `drop_modules_table`, `drop_nickname_from_users`, `add_location_to_test_posts`. Blog feature migrations ride inside `src/Blog/database/migrations/`.
- **`routes/`** — `web.php` (new, `Nreljed\Platform\` namespace, maintenance/dashboard/notifications/impersonation/account incl. ai-keys/media/admin/settings/users/roles/audit-logs/permissions/developer/ip-rules + `require auth.php`), `auth.php`, `console.php` (schedule:publish, notifications:purge, app:prune-audit-logs).
- **`config/core.php`** — package default: `load_migrations => true` (apps with their own migrations set `false`), `features => [BlogServiceProvider::class]`, `orphan_media => ['posts' => Post::class]`.
- **`resources/js/**`** — Components, hooks, lib, types, Layouts (minus StoreLayout), Pages (Auth, dashboard/**, Blog/Public/**, Error.tsx), test-setup. Public pages + StoreLayout stay APP.
- **`resources/views/`** — 52 files (app, errors, email, recovery, vendor).
- **Factories** — package-local `Nreljed\Platform\Database\Factories\` (User, Role, Permission, Post, PostCategory, PostTag); models resolve via `newFactory()`/docblock.
- **`composer.json`** — `nreljed/dashboard-core`, PSR-4 `Nreljed\Platform\` → `src/`, requires mirror master runtime deps (inertia 3, intervention/image-laravel 4, laravel/ai 0.9, sanctum 4, web-push, ziggy), no spatie (custom Role/Permission models), auto-discovers `DashboardCoreServiceProvider`.

### APP couplings removed in the copy

- `FeatureRegistry` → **config-driven** (`config('core.features')`), no hard-coded `TestPostsServiceProvider`.
- `CleanupOrphanMedia` → renamed to `core:cleanup-orphan-media`, iterates `config('core.orphan_media')` (default `posts` → `Post`); `Post::getReferencedPaths()` added (mirrors its `deleting` hook).
- `SystemHealthService::getScheduleInfo()` now matches consumer app namespace OR `Nreljed\Platform\`.
- `notifications.ts` `normalizeNotificationType()` strips both `Nreljed\Platform\Notifications\` and `App\Notifications\`.

### Rename mechanics (recorded for the record)

- PowerShell `-replace 'App\\' → 'Nreljed\Platform\\'` over 146 files, then collapsed the resulting double-backslashes, then stripped UTF-8 BOMs (PS 5.1 `-Encoding utf8` adds BOMs — PHP fatal). `php -l` clean on all 206 package files; Pint `--dirty` applied (80 files fixed).
- Rector's `RenameClassRector` prefix-mapping was considered; the direct text replace was deterministic because every `App\` occurrence in the package resolved to a copied class (verified zero references to TestPosts/Telescope/Page remain).

### Package tests

- `phpunit.xml.dist` + `tests/bootstrap.php` (resolves monorepo or standalone vendor/autoload).
- `tests/NamespaceIntegrityTest.php` (5 tests): no `App\` refs, every file declares `Nreljed\Platform\`, PSR-4 path parity, no BOMs, composer autoload maps `src/`.
- Run with: `vendor\bin\phpunit -c packages\dashboard-core\phpunit.xml.dist` → 5/5 pass.

### Not yet done (Phase 1 remainder / Phase 2)

- README for the package; `update-core.sh` consumer update script (Phase 3).
- Phase 2: register package in master via path-repo, delete `app/` duplicates, slim `AppServiceProvider`, `registerDashboard()` app shell, page/route overrides per D8, `posts.status` align migration for consumers.
- Root `rector.php` still paths `app/` etc. — add `packages/dashboard-core` when Phase 2 deletes the app copies (or the package becomes the source of truth).
