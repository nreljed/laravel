# Cost Breaker 360

A SaaS dashboard for cost management, built with Laravel 13 + Inertia.js 3 + React 19.

## Goal

Cost Breaker 360 is a server-rendered single-page application that provides:

- **Dashboard** — greeting, stats, notification management
- **Admin panel** — user/role/permission management, system health, audit logs, settings
- **Blog** — CMS with posts, categories, tags, content blocks, scheduled publishing
- **Public pages** — about, services, contact
- **Auth** — login, registration, email verification, password reset, impersonation
- **Deploy** — zero-SSH CI/CD pipeline via GitHub Releases + token-protected endpoints

The app uses a **feature-based architecture** where each domain (Blog, future features) is self-contained with its own routes, controllers, models, migrations, and frontend pages.

## Package Model & Source of Truth (2026-08-24)

**`nreljed/dashboard-core` is now the standalone product repo and the single
source of truth.** This monorepo became a regular consumer on 2026-08-24:

- composer requires `nreljed/dashboard-core: ^0.1` from the VCS repo (was:
  path repo `packages/dashboard-core` + `@dev`).
- Frontend aliases `@` / `@dashboard` / `@dashboard-blog` resolve into
  `vendor/nreljed/dashboard-core/...` (vite.config.js + tsconfig.json).
- Develop core via PRs in `nreljed/dashboard-core`; release = semver tag →
  GitHub Release (auto) → `core-updated` dispatch to all consumer apps.
- Burn-in COMPLETE (2026-08-24): `packages/dashboard-core/`,
  `core-publish.yml`, `scripts/core-publish.php` and the `core:publish`
  script are deleted; vitest/eslint/prettier/pre-push configs, Inertia page
  globs, CSS sources and deploy-package core recording all read from
  `vendor/nreljed/dashboard-core` / `composer.lock`. Never re-create a local
  copy of core — package repo only.
- `composer.lock` is the pin of record — commit it whenever core updates.

## Tech Stack

| Layer         | Technology                    | Version                           |
| ------------- | ----------------------------- | --------------------------------- |
| Backend       | Laravel                       | 13.x (PHP 8.4+)                   |
| Frontend      | React                         | 19.2                              |
| SPA Bridge    | Inertia.js                    | 3.6                               |
| Build         | Vite                          | 8.x                               |
| Language      | TypeScript                    | 6.x                               |
| Styling       | Tailwind CSS                  | 4.x                               |
| UI Kit        | shadcn/ui                     | base-vega (Base UI), neutral base |
| Icons         | Lucide React                  | latest                            |
| Auth          | Laravel Breeze + Sanctum      | session-based                     |
| Database      | SQLite (local) / MySQL (prod) | —                                 |
| Notifications | Laravel Notifications         | database channel                  |
| Routing (JS)  | Ziggy                         | 2.6                               |
| AI SDK        | Laravel AI                    | 0.9                               |
| AI Ecosystem  | Laravel Boost + MCP           | 2.4 / 0.8                         |

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
- Aliases `@`, `@dashboard` → `vendor/nreljed/dashboard-core/resources/js` (CORE frontend); `@dashboard-blog` → `vendor/nreljed/dashboard-core/src/Blog/Resources/js`; `@test-posts` → `app/TestPosts/Resources/js` — see `vite.config.js`, `tsconfig.json`, `vitest.config.js`
- `asChild` / `Slot` pattern is standard for component composition

## Architecture

The app is a **platform consumer**: the dashboard platform lives in the
`nreljed/dashboard-core` package (installed from GitHub via Composer vcs into
`vendor/nreljed/dashboard-core`); this repo is a thin app shell + theme.

```
vendor/nreljed/dashboard-core/      # CORE platform (composer vcs, the product — see below)
  DashboardCoreServiceProvider.php  # Kernel: routes, middleware, rates, gates, schedules, mailer
  routes/
    web.php                         # Public, dashboard, admin, deploy, csp-report, maintenance
    auth.php                        # Login, register, password, verification
  config/                           # core.php, roles.php, admin.php, deploy.php, database-maintenance.php, features.php, media.php
    core.php                        # engine gates: load_migrations, github_repo, inertia_middleware, settings groups
    roles.php                       # base permission catalog + default roles (RoleCatalog merges + app delta + features)
  resources/
    js/                             # CORE frontend — components/ui, hooks, layouts, editor, Pages
    css/core.css                    # design tokens (shadcn base-vega) — apps override, never edit
    views/                          # Blade: app shell, errors/40x-50x, maintenance, mail, notifications, pagination
  src/
    Ai/                             # BYOK AI feature (agents, ai providers, usage logs)
    Blog/                           # FeatureProvider module (controllers, models, requests, migrations, Resources/js)
    Console/Commands/               # CreateAdmin, CreateRole, ListPermissions, PublishScheduledContent, ...
    Contracts/                      # FeatureProvider interface
    Database/                       # Factories, seeders, migrations
    Deploy/                         # DeployController (check, cacheClear, upload, reset, env)
    Enums/                          # AuditAction
    Http/                           # Middleware (HandleInertiaRequests, Admin, CheckPermission, ...), Controllers (Admin/Auth/Api), Requests
    Jobs/ Mail/ Models/             # SendEmail, DynamicMailable, User, Role, Permission, Notification, ...
    Notifications/ Observers/ Policies/ Providers/ PublicSite/
    Rules/ Services/ Support/ Traits/
    Support/FoundationCheck.php     # Boundary fence monitor
  package.json                      # npm peerDependencies + vite.dedupe.js (canonical dedupe list)
  vite.dedupe.js                    # resolve.dedupe list — apps wire: import { packageDedupes } from "./vendor/nreljed/dashboard-core/vite.dedupe.js"
  core-manifest.json                # Boundary fence manifest (app_owned_files, forbidden paths, overridable config)
  docs/                             # architecture, feature-provider, frontend, theme, modules, deploy, commands
  tests/                            # core test suite (runs in core CI; consumers opt-in via core's phpunit.xml.dist)
  .github/workflows/ci.yml          # core repo lint + phpunit; auto-release on v* tags
app/                                # APP shell (thin — most logic lives in the core package)
  Http/Controllers/Controller.php   # Base controller
  Policies/TestPostPolicy.php
  Providers/AppServiceProvider.php
  Providers/TelescopeServiceProvider.php
  TestPosts/                        # PROJECT feature demo (FeatureProvider: provider, controllers, models, routes, Resources/js, migration)
resources/js/                       # APP shell (master) — entry point + project-owned THEME
  app.tsx                           # Entry — imports pageConfig, calls createFoundationApp()
  page-config.ts                    # SINGLE SOURCE OF TRUTH for page resolution — buildPageMap(base + project + overrides)
  test-setup.ts                     # Vitest setup
  Layouts/StoreLayout.tsx           # Future store layout (project theme example)
  Pages/Public/                     # PROJECT-OWNED marketing theme — overrides core's public/welcome|about|services|contact
routes/                             # APP-only routes (most routing lives in the core package)
  web.php                           # Reserved for project-specific routes — public/dashboard/admin/deploy/csp-report live in vendor/nreljed/dashboard-core/routes
  channels.php
  console.php
```

> **Platform / Theme / Module (mental model)** — the core package is the _engine_
> (routes, controllers, shared props, RBAC, deploy, notifications, media) and ships a
> component KIT (ui/, hooks, layouts, editor) plus PLATFORM pages (auth/dashboard/
> admin/account/settings/error). Template/marketing pages are a **theme concern and
> live in the project** (or a fork-me starter). "Modules" are self-contained
> `FeatureProvider`s discovered by `FeatureRegistry` that plug routes, permissions,
> nav items, dashboard data and their own frontend pages into the platform. Keep
> engine code in core, keep project visuals in `resources/js`, keep project
> features in `app/`.
> **Platform skills** (feature-architecture, permission-system, lexical-editor-development)
> live globally at `~/.config/opencode/skills/` — they describe the dashboard-core
> platform, not this app. App/project conventions stay in this repo's `.agents/skills/`.

> **Frontend page resolution** — Inertia resolves pages by **lowercased** component name. `dashboard.ts` exposes `registerCoreTemplates()` — the opt-in CORE kit: PLATFORM pages from `./Pages/**/*.tsx`, feature pages from `../../src/*/Resources/js/Pages/**/*.tsx` prefixed `blog/`/`public/`. The app entry spreads it into `basePages`, then adds `@test-posts` pages under the `testposts/` prefix. Controllers render lowercase names, e.g. `Inertia::render('dashboard/index')`, `'public/about'`, `'blog/index'` (→ `Blog/Public/Index.tsx`).

> **Frontend ownership (opt-in page inventory)** — CORE never auto-registers pages. Each app's `page-config.ts` builds its own explicit pageMap:
> `basePages: registerCoreTemplates()` (the shared control panel + template pages it wants), plus `projectPages` for namespaced project pages (`projectPrefix`), plus `pageOverrides` to swap any CORE-owned page for a project page (applied last, wins — e.g. `"public/about": () => import("./Pages/Public/About")`). Routes + controllers stay in CORE (they render `public/*`, `dashboard/*`, `blog/*` …) — only the page component swaps. This is how Shop/Booking ship their own marketing sites without forking core. The client entry (`app.tsx`) imports `pageConfig` — never duplicate the config block.

> **Frontend theming** — consuming apps can re-theme the shared UI without forking core. Import `core.css` in their `resources/css/app.css`, then override any subset of CSS variables under `@layer base` (tokens only, keep the utility classes). All core components + pages read from the design tokens, so overriding `--primary`/`--radius`/fonts restyles every page. If a theme needs new tokens or components, add them to core (vendor the shared parts); a project can also add its own component + page files alongside `registerCoreTemplates()`. Don't edit core's `core.css` for one-off project tweaks.

> **Frontend dependency management** — the core package ships a `package.json` with `peerDependencies` declaring every runtime npm package it imports (React, Inertia, Lexical, Radix, shadcn, etc.). Consuming apps must install these as direct dependencies. The core also ships `vite.dedupe.js` — a canonical list of package names that must be added to Vite's `resolve.dedupe` to prevent duplicate bundles when multiple packages (dashboard-core, shop-module, etc.) share the same dependencies. Consuming apps import it (this repo wires it in `vite.config.js`):
>
> ```js
> import { packageDedupes } from "./vendor/nreljed/dashboard-core/vite.dedupe.js";
> ```
>
> When adding a new npm dependency to the core's frontend, update both the core's `package.json` (peerDependencies) and the core's `vite.dedupe.js`.

> **Core development model (vcs install — do NOT hand-edit vendor)** — everything under
> `vendor/nreljed/dashboard-core` is a Composer vcs install (dist zip pinned by
> `composer.lock`). It is overwritten on `composer update` — never edit it directly.
> Core source lives in the standalone repo **`C:\Users\nrelj\Local Sites\dashboard-core`**
> (origin `github.com/nreljed/dashboard-core`). Dev loop:
>
> 1. Edit the **core clone**, commit + push (branch → PR).
> 2. Tag `vX.Y.Z` → core CI (`ci.yml`) creates the GitHub Release automatically.
>    The publish step bumps **both** `composer.json` `version` and `package.json`
>    `version` — the two must never drift.
> 3. Consumers pull: `composer update nreljed/dashboard-core && npm run build`.
> 4. Automated propagation: push to core `main` fires `repository_dispatch`
>    (`core-updated`) to consumer repos; their `core-update.yml` opens a
>    `composer update` PR (Shop has this; merge → their deploy takes over). Weekly
>    Mon 09:00 UTC cron is the fallback; manual trigger:
>    `gh workflow run core-update.yml -R <consumer>`.
>
> - **Fast local iteration**: the vendor copy drives runtime/tests. To test core
>   changes live before tagging, temporarily switch this repo's composer entry to a
>   `path` repo, `composer update nreljed/dashboard-core`, and revert the entry
>   before pushing. There is **no `composer reinstall` shortcut** — that only worked
>   for the deleted path-repo model. Frontend consumes `vendor/...` too, so core JS
>   edits also need the updated vendor copy.
> - Consumer CI clones the private repo via `COMPOSER_AUTH` using `secrets.GH_TOKEN` —
>   that token needs **Contents: Read** on `nreljed/dashboard-core`.

## Feature Architecture

Each feature is self-contained and registered via `FeatureRegistry`.

### How It Works

1. **`FeatureRegistry`** (`vendor/nreljed/dashboard-core/src/Services/FeatureRegistry.php`) — singleton, delegates to feature service providers
2. **Feature Service Providers** (e.g., `BlogServiceProvider`) — register routes, permissions, nav items
3. **`HandleInertiaRequests`** calls `FeatureRegistry::getAllNavItems()` and `getAllSharedData()` to share props

### Current Features

| Feature | Slug    | Permissions                                                | Frontend Pages                           |
| ------- | ------- | ---------------------------------------------------------- | ---------------------------------------- |
| Blog    | `posts` | `posts.view`, `posts.create`, `posts.edit`, `posts.delete` | Index, Form, Dashboard, Categories, Tags |

### Adding a New Feature

1. Create `vendor/nreljed/dashboard-core/src/{Name}/` with Controllers/, Models/, Requests/, Resources/js/Pages/
2. Create `{Name}ServiceProvider.php` — register routes, permissions, nav items in `boot()`
3. Register the service provider in `config/features.php` (`providers` array)
4. Declare feature permissions in `{Name}ServiceProvider::permissions()`; grant them to roles via `config/roles.php` (package config catalog, per-app overridable)
5. Ship it via the core dev loop: edit the core clone → tag → `composer update nreljed/dashboard-core`

## Routes

| Prefix                   | Middleware                          | Pages                                                                                |
| ------------------------ | ----------------------------------- | ------------------------------------------------------------------------------------ |
| `/`                      | none (guest)                        | Welcome, About, Services, Contact                                                    |
| `/dashboard`             | auth+verified                       | Dashboard, Stats, Notifications, Account                                             |
| `/dashboard/admin`       | auth+verified+admin+throttle:60,1   | Users, Roles, Permissions, Settings, System Health, Audit Logs, Database Maintenance |
| `/dashboard/posts`       | auth+verified+permission:posts.view | Posts CRUD, Dashboard, Categories, Tags                                              |
| `/deploy/...`            | token                               | Deploy endpoints (incl. `/deploy/reset`)                                             |
| `/api/notifications/...` | auth                                | Notification JSON endpoints                                                          |

## AI Integration (BYOK)

Pure **Bring Your Own Key** model — zero platform AI cost. Users manage their own API keys via Account → AI Keys.

### Architecture

```
vendor/nreljed/dashboard-core/src/Ai/   # Routes registered by DashboardCoreServiceProvider (core routes/web.php)
  Agents/
    SeoAgent.php               # SEO metadata generation (structured output)
    ChatAgent.php              # Dashboard chat assistant (streaming)
    ContentAgent.php           # Content generation (structured output)
    Traits/
      ResolvesUserProvider.php # Dynamic BYOK resolution via provider() method
  Concerns/
    LogsAiUsage.php            # Best-effort usage logging trait
  Controllers/
    AiProviderController.php   # CRUD + test connection
  Exceptions/
    MissingAiProviderException.php
  Models/
    AiProvider.php             # Encrypted key storage, provider metadata
    AiUsageLog.php             # Token/cost tracking
  Policies/
    AiProviderPolicy.php       # Owner-only access
  Requests/
    StoreAiProviderRequest.php # Validated key creation
    TestAiProviderRequest.php  # Connection test validation
  Services/
    AiService.php              # Centralized provider resolution + usage logging
```

### SDK Pattern

Agent classes use the `provider()` method for dynamic per-user BYOK (not `#[Provider]` attributes). The method returns the **driver config key as a string**; the per-user key is injected into that provider's config via `config()->set()` (see `ResolvesUserProvider::injectProviderKey()`). It must NOT return `['driver' => ..., 'key' => ...]` — the SDK treats an array as a failover list, not inline credentials:

```php
class SeoAgent implements Agent, HasStructuredOutput
{
    use Promptable, ResolvesUserProvider;

    public function provider(): string
    {
        return $this->getResolvedProvider()['driver'];
    }

    public function model(): ?string
    {
        return $this->getResolvedProvider()['model'];
    }

    // Per-user key is injected via config()->set("ai.providers.{driver}.key", ...)
    // inside injectProviderKey() before prompting.

    public function schema(JsonSchema $schema): array { ... }
}
```

### Supported Providers

The authoritative model list lives in `AiProvider::supportedProviders()`. Current catalog:

| Slug        | Name           | Models                                                            |
| ----------- | -------------- | ----------------------------------------------------------------- |
| `openai`    | OpenAI         | gpt-5.6-sol, gpt-5.6-terra, gpt-5.6-luna, gpt-5, o4-mini          |
| `anthropic` | Anthropic      | claude-opus-4-8, claude-sonnet-5, claude-haiku-4-5                |
| `gemini`    | Google Gemini  | gemini-3.6-flash, gemini-3.5-flash-lite, gemini-2.5-pro           |
| `mistral`   | Mistral        | mistral-medium-latest, mistral-small-latest, mistral-large-latest |
| `deepseek`  | DeepSeek       | deepseek-chat, deepseek-reasoner                                  |
| `xai`       | xAI (Grok)     | grok-4.5, grok-4.3, grok-4.20-reasoning                           |
| `ollama`    | Ollama (Local) | configurable                                                      |

### Key Rules

- **BYOK key injection (required)**: The Laravel AI SDK's `provider()` returns a `Lab|array|string` _config key_ (e.g. `'openai'`). An array return is a **failover** list (provider→model pairs), NOT inline credentials — `\Laravel\Ai\Providers\Provider::providerCredentials()` reads `key` from the provider's **config**. So per-user keys MUST be set via `config()->set("ai.providers.{$driver}.key", $key)` before prompting (see `ResolvesUserProvider::injectProviderKey()`). This is safe per-request in PHP; "never use `config()->set()`" guidance is wrong for this SDK.
- **Encrypted storage**: Keys stored via `Crypt::encryptString()`, decrypted on demand
- **Owner-only access**: `AiProviderPolicy` ensures users can only manage their own keys
- **Usage logging**: Best-effort via `LogsAiUsage` trait — failures don't block requests

## Key Patterns

- **Shared props**: `HandleInertiaRequests` shares `auth.user`, `latestNotifications`, `notificationUnreadCount`, `flash`, `featureNavItems`, `userCapabilities`, `emailSettings`, `brand`, `impersonating`
- **Admin check**: `useAuth().isAdmin` + `admin` middleware + `User::isAdmin()`
- **Feature authorization**: Route middleware `permission:feature.view`, controller `$this->authorize()`, or FormRequest `authorize()`
- **Roles & permissions (config catalog)**: Custom slug-based RBAC, package-owned (`Nreljed\DashboardCore\Models\Role`/`Permission` — NOT Spatie). Core permission catalog + default roles live in `vendor/nreljed/dashboard-core/config/roles.php`; roles are `'*'`/slug-mapped, per-app overridable via a published `config/roles.php`. Gates auto-register from `FeatureRegistry::permissions()` + `permissions` table (`PermissionGateServiceProvider`). Seeder: `Nreljed\DashboardCore\Database\Seeders\RolesAndPermissionsSeeder` (idempotent, config-driven). Slug convention: kebab for global (`manage-users`), dot for features (`posts.view`) — never snake_case.
- **Rate limiting**: Login (5 attempts/60s), auth routes (5/min IP-based), admin (60/min)
- **Validation**: `$request->validate()` or FormRequest classes
- **Error handling**: Inertia renders `Error.tsx` for all HTTP exceptions
- **Password policy**: `MinPasswordLength` rule reads `password_min_length` from DB
- **Max login attempts**: `LoginRequest::ensureIsNotRateLimited()` reads `max_login_attempts` from DB
- **Session lifetime**: Enforced via `ConfigureSessionLifetime` middleware (extracted from AppServiceProvider)
- **Maintenance mode**: Enforced via `CheckMaintenanceMode` middleware — admins bypass, non-admins see 503 Blade template
- **SMTP config**: Backend complete (`EmailSettingsService` + `UpdateEmailSettingsRequest`), `SmtpSection.tsx` frontend
- **CSRF exemptions**: Narrow patterns (`profile/avatar`, `deploy/*`, specific `api/notifications/*` paths)

## Known Blockers (Do Not Re-Suggest)

These items were reviewed during the 2026-07-22 audit. Do not suggest them again unless the blocker is resolved.

### TypeScript 7 + @typescript-eslint

TypeScript 7.x is not supported by `@typescript-eslint/eslint-plugin` yet. The plugin throws at runtime:

> `typescript-eslint does not support TS 7.0.`

Tracking: https://github.com/typescript-eslint/typescript-eslint/issues/4904

**Action**: Keep TypeScript at `^6.0.0` until `@typescript-eslint` v9+ adds TS 7 support.

### React 19 Hooks vs Inertia Patterns

`useOptimistic` and `useActionState` are React 19 hooks designed for React Server Components and native form actions. They are **not compatible** with Inertia's architecture:

- `useOptimistic` — designed for RSC server mutations, not Inertia page visits. Inertia's `router.post()` with `onSuccess` callbacks is the correct pattern.
- `useActionState` — designed for React `<form action={}>`. Inertia's `useForm` hook provides better integration (validation, redirects, flash messages, processing state).

**Action**: Continue using Inertia `useForm` and `router.post()` with callbacks.

### useHttp vs fetch for Notification Polling

Inertia v3's `useHttp` hook is a form-centric API (validation, precognition, processing state). The notification polling system (`use-notifications.ts`) makes standalone GET/POST/DELETE requests that don't fit the form model. It uses **native `fetch()`** with `X-Requested-With` + `X-CSRF-TOKEN` headers (helpers from `@/lib/gallery-utils`).

**Action**: Keep native `fetch()` for notification polling and any standalone HTTP requests.

### SettingsService Static Methods

`SettingsService` uses static methods (`SettingsService::get()`, `SettingsService::set()`, etc.) with 38 call sites across the codebase. Converting to an injectable service would require changing every call site. The static pattern is stateless, works correctly with `Cache::remember`, and is testable with `RefreshDatabase`.

**Action**: Leave as-is unless a specific testability issue arises.

### ESLint Strict-Type-Checked Preset

The `@typescript-eslint/strict-type-checked` preset requires type-aware linting (parserOptions.project). This is heavyweight and slow for this project. The current setup (`no-explicit-any: warn`, `no-empty-object-type: warn`) provides sufficient type safety without the performance penalty.

**Action**: Keep current config. Enable strict preset only after TS 7 support lands.

## Dev Environment

- Runs via LocalWP (Local by Flywheel) serving production build
- `public/hot` should NOT exist — causes `ERR_CONNECTION_REFUSED` on Vite dev server
- Always run `npm run build` after code changes
- CSP middleware only applies in production
- Site: `https://laravel.local/`
- **Seed users**: All demo role users (editor/author/user/admin in `DemoUsersSeeder`) share the same password `azerty123/*` — never forget this. Admin: `nstudio.webdesign@gmail.com` (username `nstudio`).

## Playwright MCP

Playwright MCP is configured in `~/.config/opencode/opencode.jsonc` (NOT a separate `playwright-mcp.json`).

- **User data dir**: `C:\Users\nrelj\AppData\Local\Temp\opencode\playwright\profile-crm-dashboard` — persistent browser profile (cookies, cache, localStorage survive restarts via `--user-data-dir` + `--save-session`)
- **Output dir**: `C:\Users\nrelj\AppData\Local\Temp\opencode\playwright\output` — screenshots, logs, session state files
- **Session persistence**: `--save-session` saves cookies + localStorage on MCP server shutdown (opencode exit or reconnect). The `--user-data-dir` also persists browser profile data across sessions. Sessions survive opencode restarts automatically — **do not delete the output dir unless you want a fresh session**.
- **Dark mode**: Set via `browser_evaluate` (`localStorage.setItem("theme", "dark")`) before navigating. The `colorScheme: "dark"` context option is unreliable on Windows persistent contexts. Theme persists across sessions automatically via localStorage key `"theme"` (saved by `--save-session`).

### Session-Cleanup (only when explicitly requested)

When you need a completely fresh browser (new login, reset state), run:

```powershell
Remove-Item -LiteralPath "C:\Users\nrelj\AppData\Local\Temp\opencode\playwright" -Recurse -Force -ErrorAction SilentlyContinue; $null = New-Item -ItemType Directory -Path "C:\Users\nrelj\AppData\Local\Temp\opencode\playwright\profile-crm-dashboard" -Force; $null = New-Item -ItemType Directory -Path "C:\Users\nrelj\AppData\Local\Temp\opencode\playwright\output" -Force
```

**Do NOT run this for normal use** — it destroys all saved sessions and you'll have to re-login.

### Rules

- Never save screenshots or logs to the project root — always use `outputDir`
- `.gitignore` has `/*.png` as safety net, but output should never land in root
- `--no-sandbox`: Not used in our config. Chromium on Windows works fine with sandbox. If you see this message, it's from a previous config or environment variable (`PLAYWRIGHT_MCP_NO_SANDBOX`).

## Self-Hosted Runners

GitHub-hosted runners are billing-blocked account-wide, so CI/CD runs on seven self-hosted
runners on this laptop — one per repo, all under `C:\runners\<suffix>` (moved from flat
`C:\actions-runner-*` on 2026-08-31): `C:\runners\shop` (`win-runner-shop` → laravel.shop),
`C:\runners\laravel` (`win-runner-laravel` → laravel), `C:\runners\booking` (`win-runner-booking` →
laravel.booking), `C:\runners\core` (`win-runner-core` → dashboard-core), `C:\runners\shopmodule`,
`C:\runners\bookingmodule`, `C:\runners\projectwp`. Plain `run.cmd` processes (NO Windows services,
no UAC), label `self-hosted`. Runners do NOT auto-start — start them on-demand via
`pwsh -NoProfile -File C:\tools\gha-runner.ps1 <name|all>`.

### Runner switch (self-hosted ⇄ GitHub-hosted)

All nreljed workflows read `runs-on` from **per-repo Actions variables** (set on all 7 repos),
so choosing the runner is a variable flip, not a code change:

| Variable                                                                | self-hosted (default) | GitHub-hosted    |
| ----------------------------------------------------------------------- | --------------------- | ---------------- |
| `RUNNER_CI` (validators, push-deploy, update workflows, core/module CI) | `self-hosted`         | `ubuntu-latest`  |
| `RUNNER_PACKAGE` (Windows-bound build/packaging: pwsh, 7-Zip)           | `self-hosted`         | `windows-latest` |

- Flip all repos at once: `pwsh -NoProfile -File C:\tools\switch-runners.ps1 self|cloud`.
  Single repo: `gh variable set RUNNER_CI -R nreljed/laravel --body ubuntu-latest`.
- `runs-on` is `${{ vars.RUNNER_CI || 'self-hosted' }}` — missing var degrades safely.
- `workflow_call` reusables (consumer-ci.yml) resolve `vars` against the **calling** repo —
  laravel/shop/booking must each have `RUNNER_CI` set.
- `consumer-ci.yml` cache steps are gated `RUNNER_CI != 'self-hosted'` — cache is re-enabled
  only on GitHub-hosted (ephemeral) runners; self-hosted stays cache-free (upload-stall trap).
- Two vars (not one) because GitHub-hosted bills Linux 1x vs Windows 2x; validators run on
  cheap ubuntu, only pwsh `Compress-Archive`/7-Zip packaging is Windows-bound.
- GitHub has NO native auto-failover between runner types — this variable is the mechanism.
  `nreljed` is a User account → no org-level vars; per-repo only.
- **Project-WP caveat:** its workflows hardcode `C:\php` (preinstalled toolchain) — a cloud
  flip breaks those jobs until setup-php is added. Staged for consistency only.

> Full setup/reproduction steps, the `.env` PATH override, and the failure-modes table live in the
> **`self-hosted-runners` skill** (`~/.config/opencode/skills/self-hosted-runners/SKILL.md`) — read it
> before touching runner config. Never force-kill listeners repeatedly (ghost broker sessions = jobs
> stuck queued; fix is API-delete + re-register).

## Commands

```bash
npm run build            # tsc + vite build (production)
npm run dev              # Vite dev server with HMR
npm run typecheck        # tsc --noEmit
npm run lint             # ESLint
npm run format:check     # Prettier check
npm run test             # Vitest frontend tests
npm run check:ci         # Full CI pipeline (lint + typecheck + format + test + pint + phpunit)
vendor/bin/pint          # Laravel Pint (PSR-12)
php artisan test         # PHPUnit
php artisan test --compact  # PHPUnit (compact output)
php artisan test --parallel --compact  # PHPUnit (parallel, via paratest)
php artisan migrate      # Run migrations
php artisan permissions:list          # List all permissions
php artisan app:create-admin           # Bootstrap/update admin user
```

## Deployment

### CI/CD Pipeline (GitHub Actions → Release + Server Deploy)

Deploys automatically on push to `main`. Uses **reusable workflows** for cross-repo reuse:

```
push main → deploy.yml
  ├─ ci.yml (reusable workflow_call)    — RUNNER_CI, full validation
  ├─ deploy-package.yml (reusable)      — RUNNER_PACKAGE, builds deploy.zip
  ├─ release                            — creates GitHub Release
  └─ push-deploy                        — canonical check-first deploy (env-push → check → upload → FTP)

pull_request → pr.yml
  └─ ci.yml (reusable workflow_call)    — same validation as deploy
```

**ci.yml** (reusable `workflow_call`, `RUNNER_CI`):

- PHP 8.4 + Node 22, Composer + npm caches
- SQLite .env + migrate, then: lint, typecheck, format:check, vitest, build, phpunit, pint
- Inputs: `skip-php-tests`, `skip-npm-tests` (boolean, default false)

**deploy-package.yml** (reusable `workflow_call`, `RUNNER_PACKAGE`):

- git-cliff version bump → SQLite build .env → full build pipeline
- Strip dev deps → production .env → `deploy.zip` artifact
- Output: `version` string

**deploy.yml** (orchestrator, push main):

- Calls `ci.yml` → `deploy-package.yml` → creates GitHub Release → canonical `push-deploy`
- `push-deploy` job (shared shape with `laravel.shop`):
  1. **env-push** — `POST /deploy/env` with unified `GITHUB_TOKEN` (`if: always()`, `continue-on-error`)
  2. **Poll `/deploy/check`** (8 × 45s) — accept `"deployed"` or `"current"` matching the release tag
  3. **Fallback `/deploy/upload`** only if check didn't confirm, then re-confirm via check (response can be lost mid-extract)
  4. **FTP fallback** (`continue-on-error`) — DirectAdmin account (`laravel@tunispromo.tn`), home IS the project root, uploads `deploy.zip` to root. Skips itself when `FTP_*` secrets are unset.
- Resilience: if CI is blocked by the host's security layer (datacenter IPs), the server's
  scheduled `deploy:auto` (every 5 min) picks the release up itself within ~5 minutes.

**pr.yml** (pull_request to main):

- Calls `ci.yml` — identical validation, no secrets needed

**Server (`DeployController::check()`):**

1. Verify token + lock file (10 min TTL)
2. Fetch latest release from GitHub API (via `GITHUB_TOKEN` in server `.env`)
3. Download `deploy.zip`, extract, restore `.env`
4. Run: migrate, create-admin, cache, storage:link

**database-maintenance.yml** (`workflow_dispatch`, manual only):

- Inputs: `command` (`migrate` | `reset`), `confirm` (`yes` for migrate, `RESET` for reset),
  `with_demo`, `purge_media` (reset only)
- `migrate` curls `POST /deploy/migrate`; `reset` curls `POST /deploy/reset` with
  `secrets.DEPLOY_TOKEN`
- Requires `DB_MAINTENANCE_ENABLED=true` in production `.env` for reset (the endpoint itself is always
  available — it just calls `DatabaseMaintenanceService::resetDatabase()`)

### Database Maintenance Feature

`vendor/nreljed/dashboard-core` ships an environment-gated reset/reseed feature:

| Surface         | Gate                                                  | Route                                       |
| --------------- | ----------------------------------------------------- | ------------------------------------------- |
| Admin UI page   | `admin` + `manage-options` + `DB_MAINTENANCE_ENABLED` | `dashboard.admin.database(.reset\|.reseed)` |
| Deploy endpoint | `DEPLOY_TOKEN` + `confirm=RESET`                      | `POST /deploy/reset`                        |

- Shared logic lives in `Nreljed\DashboardCore\Services\DatabaseMaintenanceService` —
  `resetDatabase()` runs `migrate:fresh` + baseline seeders (roles/permissions + settings) +
  `app:create-admin`; demo-tier seeders (`demo_users`, `blog`) only run when `demo: true`
  (`with_demo` on the endpoint, UI always resets baseline-only). `reseedDatabase()` re-runs
  only the selected seeder groups via `db:seed --class`. Seeder groups are a config catalog
  (`config/database-maintenance.php` → `seeders`; baseline/destructive flags). Optional media
  purge of `storage/app/public`, `.maintenance-lock` file (15 min TTL), toggles maintenance
  mode, writes a `critical` audit log, and notifies all admins.
- `DB_MAINTENANCE_ENABLED` defaults to enabled everywhere **except** production. Keep it `false`
  in production unless you intend to use the GitHub Action reset. `.env.example` and
  `.env.production.example` both ship `DB_MAINTENANCE_ENABLED=false`.
- UI reset ends the session and redirects to `/login`; deploy reset returns JSON.
- Tests: `tests/Feature/Admin/DatabaseMaintenanceTest.php`, `tests/Feature/Admin/DatabaseMaintenanceServiceTest.php`, `tests/Feature/Deploy/DatabaseResetDeployEndpointTest.php`.

### GitHub Secrets

| Secret                                                      | Purpose                                                                                                           |
| ----------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------- |
| `DEPLOY_TOKEN`                                              | Must match `config('app.deploy_token')` — **per-repo** (this app's server)                                        |
| `GH_TOKEN`                                                  | ONE unified fine-grained PAT covering all four repos (`laravel`, `laravel.shop`, `dashboard-core`, `shop-module`) |
| `DB_DATABASE` / `DB_USERNAME` / `DB_PASSWORD`               | Production MySQL                                                                                                  |
| `ADMIN_EMAIL` / `ADMIN_PASSWORD` / `ADMIN_NAME`             | Initial admin bootstrap                                                                                           |
| `FTP_SERVER` / `FTP_PORT` / `FTP_USERNAME` / `FTP_PASSWORD` | DirectAdmin FTP fallback for this app's own domain (`laravel@tunispromo.tn`)                                      |

> Full secrets table + `gh` cheat sheet: [`docs/github-cli.md`](docs/github-cli.md).
> Deploy contract (config keys, endpoints, FeatureProvider checklist, publish flow):
> [`docs/core-dashboard-package.md`](docs/core-dashboard-package.md) §7.1.

### After Schema/Seeder Changes

```bash
php artisan migrate
php artisan db:seed --class=RolesAndPermissionsSeeder
```

Then run typecheck + build + phpunit and deliver results. Do not wait for user approval to deploy.

## Testing

### Structure

```
tests/
  Concerns/
    CreatesTestUsers.php          # Shared trait: admin(), editor(), contributor(), regularUser(), createUserWithRole(), seedRolesAndPermissions()
  Feature/
    Ai/
      AiProviderCrudTest.php      # Provider CRUD + validation (6 tests)
      AiProviderTestConnectionTest.php  # Connection test validation (4 tests)
    Admin/
      UserManagementTest.php      # Admin user CRUD (10 tests)
      UserCreationTest.php        # Default role assignment, audit logging (13 tests)
      RoleCreationTest.php        # Role CRUD + admin role protection (6 tests)
      ImpersonationTest.php       # Impersonation flows + edge cases (7 tests)
      BulkOperationsTest.php      # Bulk user actions (6 tests)
    Auth/
      AuthenticationTest.php, RegistrationTest.php, RateLimitingTest.php, PasswordUpdateTest.php, PasswordResetTest.php, PasswordConfirmationTest.php, EmailVerificationTest.php
    Blog/
      BlogCrudTest.php            # Post CRUD lifecycle (7 tests)
      BlogPermissionsTest.php     # Role-based access (6 tests)
      BlogCategoryTest.php        # Category CRUD (5 tests)
      BlogTagTest.php             # Tag CRUD (5 tests)
      BlogPublicTest.php          # Public blog routes (5 tests)
      BlogSeoTest.php, BlogBulkActionTest.php
    Console/
      CreateAdminCommandTest.php  # Admin bootstrap incl. email_verified_at (5 tests)
      CleanupOrphanMediaCommandTest.php  # Orphan media cleanup (3 tests)
      ListPermissionsCommandTest.php     # Permission listing + --role filter (5 tests)
      NotificationPurgeCommandTest.php   # Notification purge (2 tests)
      PruneAuditLogsCommandTest.php      # Audit pruning incl. --days (3 tests)
      PublishScheduledContentCommandTest.php  # Scheduled publishing (3 tests)
    Deploy/
      DeployEndpointTest.php      # Token auth, verification, upload, cache-clear pipeline (11 tests)
    Jobs/
      SendEmailTest.php           # Mailable dispatch + retry (3 tests)
      GenerateSeoForPostTest.php  # SEO generation incl. missing provider (4 tests)
    Maintenance/
      MaintenanceModeTest.php     # 503 Blade + bypass flows (19 tests)
    Media/
      MediaUploadTest.php         # Upload/destroy, traversal safety (9 tests)
    Notifications/
      NotificationPollTest.php    # Poll endpoints, auth, filters (8 tests)
      NotificationServiceTest.php # Cached count, purge (3 tests)
    Performance/
      DatabaseIndexTest.php       # Index presence assertions (6 tests)
      DatabasePerformanceTest.php # Cache TTL + N+1 guard (6 tests)
    Public/
      PublicPagesTest.php         # Welcome, about, services, contact (4 tests)
    Security/
      XssSanitizationTest.php     # HTML sanitizer allowlists (7 tests)
      MediaPathTraversalTest.php  # Path traversal guards (4 tests)
      ContentSecurityPolicyTest.php      # CSP headers (4 tests)
      ConfigureSessionLifetimeTest.php   # DB-driven session timeout (3 tests)
      MaintenanceBypassTest.php, IpAccessControlTest.php, PasswordComplexityTest.php
    TestPosts/
      TestPostCrudTest.php        # CRUD, title, slug (7 tests)
      TestPostFeaturedImageTest.php, TestPostImageUploadTest.php, TestPostGalleryE2ETest.php, TestPostBulkActionTest.php
    DataIsolationTest.php         # HTTP-level data isolation checks
    FeatureRegistryTest.php       # Singleton, nav items, permissions (5 tests)
```

### Factories

| Model          | File                                         | States                                             |
| -------------- | -------------------------------------------- | -------------------------------------------------- |
| `User`         | `database/factories/UserFactory.php`         | `admin()`, default: unverified                     |
| `Post`         | `database/factories/PostFactory.php`         | `published()`, `draft()`, `archived()`, `future()` |
| `PostCategory` | `database/factories/PostCategoryFactory.php` | —                                                  |
| `PostTag`      | `database/factories/PostTagFactory.php`      | —                                                  |

### Key Patterns

- **Shared trait**: `CreatesTestUsers` provides `admin()`, `regularUser()`, `createUserWithRole()` for consistent user creation across all tests
- **Permissions**: Use `seedRolesAndPermissions()` in `setUp()` to ensure roles exist before testing
- **Testing auth**: `$this->actingAs($user)` with `$user->forceFill(['email_verified_at' => now])->save()` for verified users
- **Testing permissions**: Create role with `Permission::create()`, assign via `$user->assignRole()`, then assert 403 for unauthorized
- **AI tests**: Validation-only (no API mocking — tests auth, required fields, invalid providers)
- **Deploy tests**: Token via POST body (`['token' => $this->token]`), unauthenticated returns 403 (not 401)
- **Notification tests**: Unauthenticated requests return 401 JSON (not redirect) due to JSON exception handler
- **Performance**: `$this->captureQueryCount(callable)` / `$this->assertQueryCount(int, callable)` helpers on `tests/TestCase.php` for cache-hit and N+1 assertions; `Cache::flush()` in `setUp()` to avoid cross-test pollution

### Running Tests

```bash
php artisan test --compact                           # All tests (compact output)
php artisan test --parallel --compact                # All tests, parallel via paratest
php artisan test --compact tests/Feature/Blog/       # All blog tests
php artisan test --compact --filter=testName         # Specific test
npm run test                                         # Vitest frontend tests
npm run check:ci                                     # Full CI pipeline
```

- `main` — production-ready
- `beta` — active development

===

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application and its main Laravel ecosystems package & versions are below. You are an expert with them all. Ensure you abide by these specific packages & versions.

- php - 8.4
- inertiajs/inertia-laravel (INERTIA_LARAVEL) - v3
- laravel/ai (AI) - v0
- laravel/framework (LARAVEL) - v13
- laravel/prompts (PROMPTS) - v0
- laravel/sanctum (SANCTUM) - v4
- tightenco/ziggy (ZIGGY) - v2
- laravel/boost (BOOST) - v2
- laravel/breeze (BREEZE) - v2
- laravel/mcp (MCP) - v0
- laravel/pail (PAIL) - v1
- laravel/pint (PINT) - v1
- laravel/telescope (TELESCOPE) - v5
- phpunit/phpunit (PHPUNIT) - v13
- react (REACT) - v19
- @inertiajs/react (INERTIA_REACT) - v3
- eslint (ESLINT) - v10
- prettier (PRETTIER) - v3
- tailwindcss (TAILWINDCSS) - v4

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Always use `search-docs` before making code changes. Do not skip this step. It returns version-specific docs based on installed packages automatically.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.

=== tests rules ===

# Test Enforcement

- Every change must be programmatically tested. Write a new test or update an existing test, then run the affected tests to make sure they pass.
- Run the minimum number of tests needed to ensure code quality and speed. Use `php artisan test --compact` with a specific filename or filter.

=== inertia-laravel/core rules ===

# Inertia

- Inertia creates fully client-side rendered SPAs without modern SPA complexity, leveraging existing server-side patterns.
- Components live in `resources/js/Pages` (unless specified in `vite.config.js`). Use `Inertia::render()` for server-side routing instead of Blade views.
- ALWAYS use `search-docs` tool for version-specific Inertia documentation and updated code examples.
- IMPORTANT: Activate `inertia-react-development` when working with Inertia client-side patterns.

# Inertia v3

- Use all Inertia features from v1, v2, and v3. Check the documentation before making changes to ensure the correct approach.
- New v3 features: standalone HTTP requests (`useHttp` hook), optimistic updates with automatic rollback, layout props (`useLayoutProps` hook), instant visits, simplified SSR via `@inertiajs/vite` plugin, custom exception handling for error pages.
- Carried over from v2: deferred props, infinite scroll, merging props, polling, prefetching, once props, flash data.
- When using deferred props, add an empty state with a pulsing or animated skeleton.
- Axios has been removed. Use the built-in XHR client with interceptors, or install Axios separately if needed.
- `Inertia::lazy()` / `LazyProp` has been removed. Use `Inertia::optional()` instead.
- Prop types (`Inertia::optional()`, `Inertia::defer()`, `Inertia::merge()`) work inside nested arrays with dot-notation paths.
- SSR works automatically in Vite dev mode with `@inertiajs/vite` - no separate Node.js server needed during development.
- Event renames: `invalid` is now `httpException`, `exception` is now `networkError`.
- `router.cancel()` replaced by `router.cancelAll()`.
- The `future` configuration namespace has been removed - all v2 future options are now always enabled.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== phpunit/core rules ===

# PHPUnit

- This application uses PHPUnit for testing. All tests must be written as PHPUnit classes. Use `php artisan make:test --phpunit {name}` to create a new test.
- If you see a test using "Pest", convert it to PHPUnit.
- Every time a test has been updated, run that singular test.
- When the tests relating to your feature are passing, ask the user if they would like to also run the entire test suite to make sure everything is still passing.
- Tests should cover all happy paths, failure paths, and edge cases.
- You must not remove any tests or test files from the tests directory without approval. These are not temporary or helper files; these are core to the application.

## Running Tests

- Run the minimal number of tests, using an appropriate filter, before finalizing.
- To run all tests: `php artisan test --compact`.
- To run all tests in a file: `php artisan test --compact tests/Feature/ExampleTest.php`.
- To filter on a particular test name: `php artisan test --compact --filter=testName` (recommended after making a change to a related file).

=== inertia-react/core rules ===

# Inertia + React

- IMPORTANT: Activate `inertia-react-development` when working with Inertia React client-side patterns.

</laravel-boost-guidelines>
