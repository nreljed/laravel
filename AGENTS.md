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

## Scoped References

This file is loaded for **every** session, so it stays at repo-wide essentials only.
Four subtrees have their own `AGENTS.md`. OpenCode resolves `AGENTS.md` by traversing
**up** from the cwd and stopping at the first match, so these are **not** auto-loaded —
and `instructions[]` is deliberately unused because it is a flat, always-loaded list
that would defeat the point. **Read the relevant one, on demand:**

| File | Covers | Read it when |
| --- | --- | --- |
| `packages/AGENTS.md` | Mirror boundary + upstream dev loop | **Before editing anything under `packages/**` or `apps/**`** |
| `resources/js/AGENTS.md` | TSX style, page resolution, theming, workspace deps | Touching `resources/js/**` or any TSX/Tailwind work |
| `scripts/AGENTS.md` | Runners, commands, deploy pipeline, CI wiring | Touching `scripts/**`, `.github/**`, hooks, or deploys |
| `tests/AGENTS.md` | PHPUnit + Vitest layout, factories, Playwright | Running, writing, or debugging tests |

Treat a referenced file's contents as mandatory once loaded, and follow its references
recursively if the task demands. Do not preload them preemptively.

**One-line rule that overrides everything below**: never hand-edit `vendor/nreljed/*`
or any mirror in `packages/**` / `apps/**`. Fix upstream, release, then re-sync.

## Package Model & Source of Truth (2026-08-24)

**`nreljed/platform` is now the standalone product repo and the single
source of truth.** This monorepo became a regular consumer on 2026-08-24:

- composer requires `nreljed/platform: ^0.1` from the VCS repo (was:
  path repo + `@dev`).
- Frontend aliases `@` / `@dashboard` resolve into
  `vendor/nreljed/platform/...`, and `@blog` into
  `vendor/nreljed/blog/...` (vite.config.js + tsconfig.json).
- Develop core via PRs in `nreljed/platform`; release = semver tag →
  GitHub Release (auto) → `core-updated` dispatch to all consumer apps.
- Blog is a sibling standalone package (`nreljed/blog`, slug `posts`):
  `registerBlogPages()` from `@blog/pages`, its own migrations/commands,
  `blog-updated` dispatch. Apps consume both via Composer vcs.
- Burn-in COMPLETE (2026-08-24): the old local core copy,
  `core-publish.yml`, `scripts/core-publish.php` and the `core:publish`
  script are deleted; vitest/eslint/prettier/pre-push configs, Inertia page
  globs, CSS sources and deploy-package core recording all read from
  `vendor/nreljed/platform` / `composer.lock`. Never re-create a local
  copy of core — package repo only.
- `composer.lock` is the pin of record — commit it whenever core updates.

> **Workspace (2026-09-14 — supersedes the frontend-vendor consumption)** — this repo's root is
> now a **pnpm workspace**: `packages/*` (platform, blog, shop-module, booking-module) +
> `apps/*` (laravel.shop, laravel.booking brought in; the mono app stays at root for now).
> The frontend kit is consumed as **workspace packages** (`@nreljed/platform`, `@nreljed/blog`)
> symlinked from `node_modules` → `packages/*` — NOT vendor. The single `pnpm-lock.yaml` at the
> root is the npm pin of record. `tsconfig.json` `paths` map `@nreljed/platform/*` →
> `packages/platform/resources/js/*` and `@nreljed/blog/*` → `packages/blog/src/Resources/js/*`
> (TS can't resolve the packages' `./*` wildcard `exports`); the packages' `.d.ts` globs are in
> `include` because `packages/platform/.../types/global.d.ts` ships the ambient `route` global +
> the `@inertiajs/core` PageProps augmentation the whole app relies on.
> **Composer/PHP side is unchanged**: `nreljed/platform`/`nreljed/blog` still resolve from VCS
> into `vendor/` (dev `path` repos are a pending increment). pnpm 11: `minimumReleaseAge: 0` and
> `allowBuilds:` live **top-level in `pnpm-workspace.yaml`**; there is deliberately no `.npmrc`
> and no `pnpm` field in `package.json` (pnpm 11 ignores both). The drift guard
> (`scripts/check-platform-consistency.js`) now reads pnpm-lock importer versions for workspace
> apps and `package-lock.json` for any remaining npm apps, plus each app's `composer.lock`.

> **App-vs-upstream fork check** — `apps/laravel.shop` and `apps/laravel.booking` are workspace
> members whose _code_ must match their standalone repos. Nothing in this repo's CI builds or
> tests `apps/**`, so the guard is what stops those copies from silently becoming forks. Point
> it at the standalone checkouts and it fails on any content drift, missing file, or
> dependency-manifest change:
>
> ```bash
> $env:CHECK_PLATFORM_SHOP_UPSTREAM     = 'C:\Users\nrelj\Local Sites\laravel.shop'
> $env:CHECK_PLATFORM_BOOKING_UPSTREAM = 'C:\Users\nrelj\Local Sites\laravel.booking'
> npm run check:platform-consistency
> ```
>
> Tolerated by design: `tsconfig.json` / `vite.config.js` (the workspace aliases `@nreljed/*`
> to `packages/*` instead of `vendor/*`), the lockfiles, and the explicit `APP_WORKSPACE_OVERLAY`
> list. `composer.json` / `package.json` **are** compared, but semantically — so a real version
> bump fails while key order, indentation and trailing blank lines do not, and `@nreljed/*`
> entries are ignored so `workspace:*` is not drift. Unset in CI (no second checkout) → reported
> as SKIPPED, never silently green.
>
> > **Manifest ranges must agree across BOTH deploy repos.** The two guard rules are mutually
> > constraining: "each app's `package.json` matches its deploy source" (semantic compare) and
> > "all workspace members resolve identical versions" (pnpm-lock importer compare). If
> > `laravel.shop` and `laravel.booking` declare different ranges for a guard key, no mono-side
> > edit satisfies both — bumping either app breaks that app's upstream parity, and `pnpm install`
> > will not unify two importers on its own (it keeps each importer's valid existing pin). Bump
> > the toolchain in **both standalone deploy repos** first, re-sync the app copies, then
> > `pnpm install` once at the root. Done 2026-10-02 for eslint, `@typescript-eslint/eslint-plugin`,
> > `@typescript-eslint/parser`, `@eslint-react/eslint-plugin`, vite, vitest, lucide-react.
> > Keep `@typescript-eslint/parser` in lockstep with `@typescript-eslint/eslint-plugin` — the
> > plugin pins the parser as a peer, so `pnpm peers check` fails otherwise (and `pnpm update
> > <pkg>` silently rewrites the *declared* range in only the importer you name, creating a new
> > drift). Run `pnpm peers check` after any toolchain bump.

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

## Architecture

The app is a **platform consumer**: the dashboard platform lives in the
`nreljed/platform` package (installed from GitHub via Composer vcs into
`vendor/nreljed/platform`); this repo is a thin app shell + theme.

```
vendor/nreljed/platform/      # CORE platform (composer vcs, the product — see below)
  PlatformServiceProvider.php  # Kernel: routes, middleware, rates, gates, schedules, mailer
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
    PublicSite/                     # Public template pages (welcome/about/services/contact, sitemap)
    Console/Commands/               # CreateAdmin, CreateRole, ListPermissions, ...
    Contracts/                      # FeatureProvider interface
    Database/                       # Factories, seeders, migrations
    Deploy/                         # DeployController (check, cacheClear, upload, reset, env)
    Enums/                          # AuditAction
    Http/                           # Middleware (HandleInertiaRequests, Admin, CheckPermission, ...), Controllers (Admin/Auth/Api), Requests
    Jobs/ Mail/ Models/             # SendEmail, DynamicMailable, User, Role, Permission, Notification, ...
    Notifications/ Observers/ Policies/ Providers/
    Rules/ Services/ Support/ Traits/
    Support/FoundationCheck.php     # Boundary fence monitor
  package.json                      # npm peerDependencies + exports map (./app, ./dashboard, ./*)
  core-manifest.json                # Boundary fence manifest (app_owned_files, forbidden paths, overridable config)
  docs/                             # architecture, feature-provider, frontend, theme, modules, deploy, commands
  tests/                            # core test suite (runs in core CI; consumers opt-in via core's phpunit.xml.dist)
  .github/workflows/ci.yml          # core repo lint + phpunit; auto-release on v* tags
vendor/nreljed/blog/                # BLOG feature package (composer vcs, standalone repo nreljed/blog)
  BlogServiceProvider.php           # slug posts, dashboard/posts + blog routes, schedule:publish, own commands/migrations
  src/                              # Nreljed\Blog — Controllers, Models, Requests, Routes, Services, Console, Jobs, Observers, Policies, Database
  database/migrations/              # 11 posts/categories/tags migrations (CANONICAL — apps must not re-own these)
  src/Resources/js/                 # Blog frontend (@blog) — pages.ts exports registerBlogPages()
  package.json                      # npm peerDependencies
  tests/                            # Structural/contract suite (blog CI; apps keep their own integration tests)
  scripts/frontend-boundary-check.php
app/                                # APP shell (thin — most logic lives in the platform/blog packages)
  Http/Controllers/Controller.php   # Base controller
  Providers/AppServiceProvider.php
  Providers/TelescopeServiceProvider.php
resources/js/                       # APP shell (master) — entry point + project-owned THEME
  app.tsx                           # Entry — imports pageConfig, calls createFoundationApp()
  page-config.ts                    # SINGLE SOURCE OF TRUTH for page resolution — buildPageMap(base + project + overrides)
  test-setup.ts                     # Vitest setup
  Layouts/StoreLayout.tsx           # Future store layout (project theme example)
  Pages/Public/                     # PROJECT-OWNED marketing theme — overrides core's public/welcome|about|services|contact
routes/                             # APP-only routes (most routing lives in the core package)
  web.php                           # Reserved for project-specific routes — public/dashboard/admin/deploy/csp-report live in vendor/nreljed/platform/routes
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
> live globally at `~/.config/opencode/skills/` — they describe the platform
> package, not this app. App/project conventions stay in this repo's `.agents/skills/`.

## Versioning & `/deploy/check` SBOM

- **App version** (`v0.x.y` here / laravel.shop / laravel.booking) = a release artifact
  (whole tree + vendor + `deploy.zip`); **package version** (`v0.1.x` on `nreljed/platform`
  and feature packages) = an engine bundle consumers pin via Composer. The two series are
  intentionally decoupled — a package release only notifies consumers, it deploys nothing.
- **`composer.lock` is the pin of record** — commit it on every package bump; the CI
  release records it so a release's SBOM is reproducible from git history.
- Every `/deploy/check` / `deploy:auto` response carries a `core` envelope (platform
  `name`/`version`/`sha`/`repo`, resolved from `vendor/composer/installed.json`); platform
  `>= v0.1.58` adds `bundled` (every installed `nreljed/*` package) — the runtime SBOM.
- Update chain: package tag → core CI auto-release + `repository_dispatch`
  (`core-updated`/`blog-updated`) → consumer `core-update.yml` (dispatch + weekly cron +
  manual) → validated **lock-only PR** (PAT-merged) → push → deploy. Full detail:
  `docs/workflow/deploy.md` § Versions & Update Chain.

## Feature Architecture

Each feature is self-contained and registered via `FeatureRegistry`.

### How It Works

1. **`FeatureRegistry`** (`vendor/nreljed/platform/src/Services/FeatureRegistry.php`) — singleton, delegates to feature service providers
2. **Feature Service Providers** (e.g., `BlogServiceProvider`) — register routes, permissions, nav items
3. **`HandleInertiaRequests`** calls `FeatureRegistry::getAllNavItems()` and `getAllSharedData()` to share props

### Current Features

| Feature | Slug    | Permissions                                                | Frontend Pages                           |
| ------- | ------- | ---------------------------------------------------------- | ---------------------------------------- |
| Blog    | `posts` | `posts.view`, `posts.create`, `posts.edit`, `posts.delete` | Index, Form, Dashboard, Categories, Tags |

### Adding a New Feature

1. Create `vendor/nreljed/platform/src/{Name}/` with Controllers/, Models/, Requests/, Resources/js/Pages/
2. Create `{Name}ServiceProvider.php` — register routes, permissions, nav items in `boot()`
3. Register the service provider in `config/features.php` (`providers` array)
4. Declare feature permissions in `{Name}ServiceProvider::permissions()`; grant them to roles via `config/roles.php` (package config catalog, per-app overridable)
5. Ship it via the core dev loop: edit the core clone → tag → `composer update nreljed/platform`

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
vendor/nreljed/platform/src/Ai/   # Routes registered by PlatformServiceProvider (core routes/web.php)
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

Supported slugs: `openai`, `anthropic`, `gemini`, `mistral`, `deepseek`, `xai`,
`ollama` (local). **The authoritative model list lives in
`AiProvider::supportedProviders()` — read it, never trust a copy here** (model
names churn far faster than this file).

### Key Rules

- **BYOK key injection (required)**: The Laravel AI SDK's `provider()` returns a `Lab|array|string` _config key_ (e.g. `'openai'`). An array return is a **failover** list (provider→model pairs), NOT inline credentials — `\Laravel\Ai\Providers\Provider::providerCredentials()` reads `key` from the provider's **config**. So per-user keys MUST be set via `config()->set("ai.providers.{$driver}.key", $key)` before prompting (see `ResolvesUserProvider::injectProviderKey()`). This is safe per-request in PHP; "never use `config()->set()`" guidance is wrong for this SDK.
- **Encrypted storage**: Keys stored via `Crypt::encryptString()`, decrypted on demand
- **Owner-only access**: `AiProviderPolicy` ensures users can only manage their own keys
- **Usage logging**: Best-effort via `LogsAiUsage` trait — failures don't block requests

## Key Patterns

- **Shared props**: `HandleInertiaRequests` shares `auth.user`, `latestNotifications`, `notificationUnreadCount`, `flash`, `featureNavItems`, `userCapabilities`, `emailSettings`, `brand`, `impersonating`
- **Admin check**: `useAuth().isAdmin` + `admin` middleware + `User::isAdmin()`
- **Feature authorization**: Route middleware `permission:feature.view`, controller `$this->authorize()`, or FormRequest `authorize()`
- **Roles & permissions (config catalog)**: Custom slug-based RBAC, package-owned (`Nreljed\Platform\Models\Role`/`Permission` — NOT Spatie). Core permission catalog + default roles live in `vendor/nreljed/platform/config/roles.php`; roles are `'*'`/slug-mapped, per-app overridable via a published `config/roles.php`. Gates auto-register from `FeatureRegistry::permissions()` + `permissions` table (`PermissionGateServiceProvider`). Seeder: `Nreljed\Platform\Database\Seeders\RolesAndPermissionsSeeder` (idempotent, config-driven). Slug convention: kebab for global (`manage-users`), dot for features (`posts.view`) — never snake_case.
- **Rate limiting**: `login` 30/min by IP, `register` 10/min by IP, `auth` 30/min by IP, admin 60/min — defined in `vendor/nreljed/platform/src/PlatformServiceProvider.php:279-283`
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

## Project Memory

Persistent, curated knowledge to avoid losing context:

- docs/memory/ - conventions, gotchas, glossary, modules, lessons learned
- docs/decisions/ - Architecture Decision Records (ADRs)
- docs/history/ - release/deploy history and curated session summaries
- .ai/rules/ - project rules and constraints
- .opencode/session-memory.json - session compaction (ephemeral; curate important insights to docs)

Use /save-decision, /save-memory, /save-gotcha commands to capture knowledge. When working on complex tasks, review relevant memory first.
