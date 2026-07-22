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

## Tech Stack

| Layer         | Technology                    | Version                |
| ------------- | ----------------------------- | ---------------------- |
| Backend       | Laravel                       | 13.x (PHP 8.4+)        |
| Frontend      | React                         | 19.2                   |
| SPA Bridge    | Inertia.js                    | 3.6                    |
| Build         | Vite                          | 8.x                    |
| Language      | TypeScript                    | 6.x                    |
| Styling       | Tailwind CSS                  | 4.x                    |
| UI Kit        | shadcn/ui                     | new-york, neutral base |
| Icons         | Lucide React                  | latest                 |
| Auth          | Laravel Breeze + Sanctum      | session-based          |
| Database      | SQLite (local) / MySQL (prod) | —                      |
| Notifications | Laravel Notifications         | database channel       |
| Routing (JS)  | Ziggy                         | 2.6                    |
| AI SDK        | Laravel AI                    | 0.9                    |
| AI Ecosystem  | Laravel Boost + MCP           | 2.4 / 0.8              |

## Code Style

- Functional components with hooks, strict TypeScript
- 4 spaces indentation (PHP), 2 spaces (TSX)
- Use `cn()` from `@/lib/utils` for class merging
- All routes via Ziggy's `route()` helper — no hardcoded URLs
- Inertia `useForm` for all form state
- `useAuth` hook from `@/hooks/use-auth` for auth state
- `next-themes` `<ThemeProvider>` for dark mode
- `toast()` from `sonner` for flash messages
- Component imports use `@/Components/...` alias (capital C — Windows FS workaround)
- `asChild` / `Slot` pattern is standard for component composition

## Architecture

```
app/
  Ai/                              # Self-contained AI feature (BYOK)
    AiServiceProvider.php          # Routes, policies, registration
    Agents/                        # SeoAgent, ChatAgent, ContentAgent
    Concerns/                      # HasAiProvider, LogsAiUsage
    Controllers/                   # AiProviderController
    Exceptions/                    # MissingAiProviderException
    Models/                        # AiProvider, AiUsageLog
    Policies/                      # AiProviderPolicy
    Requests/                      # StoreAiProviderRequest, TestAiProviderRequest
    Routes/                        # web.php (auth+verified)
    Services/                      # AiService
  Blog/                          # Self-contained blog feature
    BlogServiceProvider.php      # Routes, permissions, nav items
    Controllers/                 # Post, PostCategory, PostTag, BlogPublic
    Models/                      # Post, PostCategory, PostTag, ContentBlock
    Routes/                      # web.php (admin + public)
    Resources/js/Pages/          # Index, Form, Dashboard, Categories, Tags, Public
    database/migrations/
  Console/Commands/              # CreateAdmin, CreateRole, ListPermissions, etc.
  Enums/                         # AuditAction
  Http/
    Controllers/
      Admin/                     # User, Role, Permission, AuditLog, Session, Bulk, Impersonation
      Api/                       # NotificationPollController (JSON)
      Auth/                      # Login, Register, Password, Email verification
      AdminController.php        # Settings, System Health
      AvatarController.php
      DashboardController.php    # Index, Stats, Notifications, SwitchMode
      DeployController.php       # CI/CD endpoints (check, cacheClear, upload)
      EmailSettingsController.php
      NotificationTestController.php
      UserAccountController.php  # Consolidated profile + account management
      PublicController.php       # About, Services, Contact
      SettingsController.php     # Edit, update, logo upload
    Middleware/
      HandleInertiaRequests.php  # Shared props: auth, notifications, flash, features, brand
      AdminMiddleware.php        # 403 if not admin
      CheckPermission.php       # user.permission:slug
      CheckRole.php             # user.role:slug
      CheckMaintenanceMode.php  # 503 Blade template (admins bypass)
      ConfigureSessionLifetime.php  # DB-driven session timeout
      ContentSecurityPolicy.php  # CSP headers (production only)
    Requests/                    # FormRequest classes by domain
      UpdateContactRequest.php   # Contact page validation (DB-driven rules)
  Jobs/
    SendEmail.php               # Mailable dispatch with retry (tries=3, backoff)
  Mail/                          # DynamicMailable
  Models/                        # User, Notification, etc.
  Notifications/                 # Database notification classes
  Policies/                      # PostPolicy, AiProviderPolicy
  Providers/                     # AppServiceProvider, PermissionGateServiceProvider, TelescopeServiceProvider
  Rules/
    MinPasswordLength.php        # Reads password_min_length from DB
  Services/
    AccountService.php           # deleteAccount()
    AiService.php               # Centralized AI provider resolution + usage logging
    DashboardService.php         # Stats + analytics cache (5-min TTL via Cache::remember)
    EmailSettingsService.php
    FeatureRegistry.php          # Singleton — delegates to feature service providers (use app())
    FormattingService.php        # Price/surface formatting
    NotificationService.php      # Cached unread count, preference-aware dispatch, purge
    SettingsService.php          # DB-driven app config (Cache::remember groups, 5-min TTL)
  Traits/
    RelativeTime.php             # Shared relative time formatting
resources/js/
  Components/
    ui/                          # shadcn/ui components (40+)
    shared/                      # FeaturedImageSelector, TermsSelector, GalleryUploader
    dashboard/                   # KpiCard, Sparkline, RevenueChart, etc.
    frontend/                    # Public-facing components
    notifications/               # NotificationBell, NotificationPanel, NotificationItem, NotificationDetailDialog
    app-sidebar.tsx              # Dynamic sidebar from featureNavItems
    nav-main.tsx                 # Collapsible nav
    nav-user.tsx                 # User dropdown
    command-search.tsx           # Ctrl+K palette
    NotificationBell.tsx         # Adaptive polling (10s/30s/60s)
    ThemeSwitcher.tsx
  hooks/
    use-auth.ts                  # user, isAdmin, getUserInitials
    use-notifications.ts         # Axios-based polling (10s/30s/60s adaptive)
  Layouts/                       # AuthenticatedLayout, AdminLayout, GuestLayout, PublicLayout
  Pages/
    Auth/                        # Login, Register, Forgot/Reset Password, Verify Email
    dashboard/                   # index, stats, profile, account, notifications, admin
    Public/                      # welcome, about, services, contact
    Error.tsx                    # Handles 401/403/404/419/429/500/503
  lib/
    utils.ts                     # cn() helper
    icons.ts                     # featureIcons map
    notifications.ts             # Severity type, normalizeType, getSeverity, getTypeLabel
    schemas/                     # Zod schemas
  types/                         # TypeScript type definitions
routes/
  web.php                        # Dashboard, admin, public, deploy routes
  auth.php                       # Breeze auth routes + profile
```

## Feature Architecture

Each feature is self-contained and registered via `FeatureRegistry`.

### How It Works

1. **`FeatureRegistry`** (`app/Services/FeatureRegistry.php`) — singleton, delegates to feature service providers
2. **Feature Service Providers** (e.g., `BlogServiceProvider`) — register routes, permissions, nav items
3. **`HandleInertiaRequests`** calls `FeatureRegistry::getAllNavItems()` and `getAllSharedData()` to share props

### Current Features

| Feature | Slug    | Permissions                                                | Frontend Pages                           |
| ------- | ------- | ---------------------------------------------------------- | ---------------------------------------- |
| Blog    | `posts` | `posts.view`, `posts.create`, `posts.edit`, `posts.delete` | Index, Form, Dashboard, Categories, Tags |

### Adding a New Feature

1. Create `app/{Name}/` with Controllers/, Models/, Routes/, Resources/js/Pages/
2. Create `{Name}ServiceProvider.php` — register routes, permissions, nav items in `boot()`
3. Register in `bootstrap/providers.php`
4. Add permission slugs to `database/seeders/RolesAndPermissionsSeeder.php`
5. Register the service provider in `FeatureRegistry`

## Routes

| Prefix                   | Middleware                          | Pages                                                          |
| ------------------------ | ----------------------------------- | -------------------------------------------------------------- |
| `/`                      | none (guest)                        | Welcome, About, Services, Contact                              |
| `/dashboard`             | auth+verified                       | Dashboard, Stats, Notifications, Account                       |
| `/dashboard/admin`       | auth+verified+admin+throttle:60,1   | Users, Roles, Permissions, Settings, System Health, Audit Logs |
| `/dashboard/posts`       | auth+verified+permission:posts.view | Posts CRUD, Dashboard, Categories, Tags                        |
| `/deploy/...`            | token                               | Deploy endpoints                                               |
| `/api/notifications/...` | auth                                | Notification JSON endpoints                                    |

## AI Integration (BYOK)

Pure **Bring Your Own Key** model — zero platform AI cost. Users manage their own API keys via Account → AI Keys.

### Architecture

```
app/Ai/
  AiServiceProvider.php       # Routes, policies, registration
  Agents/
    SeoAgent.php               # SEO metadata generation (structured output)
    ChatAgent.php              # Dashboard chat assistant (streaming)
    ContentAgent.php           # Content generation (structured output)
    Traits/
      ResolvesUserProvider.php # Dynamic BYOK resolution via provider() method
  Concerns/
    HasAiProvider.php          # User-facing trait for provider resolution
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
  Routes/
    web.php                    # AI key routes (auth+verified)
  Services/
    AiService.php              # Centralized provider resolution + usage logging
```

### SDK Pattern

Agent classes use `provider()` method for dynamic per-user BYOK (not `#[Provider]` attributes):

```php
class SeoAgent implements Agent, HasStructuredOutput
{
    use Promptable, ResolvesUserProvider;

    public function provider(): array
    {
        return ['driver' => $provider->provider, 'key' => $provider->getDecryptedKey()];
    }

    public function schema(JsonSchema $schema): array { ... }
}
```

### Supported Providers

| Slug        | Name           | Models                                           |
| ----------- | -------------- | ------------------------------------------------ |
| `openai`    | OpenAI         | gpt-4o, gpt-4.1, o3-mini                         |
| `anthropic` | Anthropic      | claude-opus-4, claude-sonnet-4, claude-haiku-4-5 |
| `gemini`    | Google Gemini  | gemini-2.5-pro, gemini-2.5-flash                 |
| `mistral`   | Mistral        | mistral-large-latest, mistral-small-latest       |
| `deepseek`  | DeepSeek       | deepseek-chat, deepseek-reasoner                 |
| `xai`       | xAI (Grok)     | grok-3, grok-3-mini                              |
| `ollama`    | Ollama (Local) | configurable                                     |

### Key Rules

- **Thread safety**: Never use `config()->set()` at runtime — pass `provider:` + `key:` arrays to `agent()->prompt()` or `agent()->stream()`
- **Encrypted storage**: Keys stored via `Crypt::encryptString()`, decrypted on demand
- **Owner-only access**: `AiProviderPolicy` ensures users can only manage their own keys
- **Usage logging**: Best-effort via `LogsAiUsage` trait — failures don't block requests

## Key Patterns

- **Shared props**: `HandleInertiaRequests` shares `auth.user`, `latestNotifications`, `notificationUnreadCount`, `flash`, `featureNavItems`, `userCapabilities`, `emailSettings`, `brand`, `impersonating`
- **Admin check**: `useAuth().isAdmin` + `admin` middleware + `User::isAdmin()`
- **Feature authorization**: Route middleware `permission:feature.view`, controller `$this->authorize()`, or FormRequest `authorize()`
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

### useHttp vs axios for Notification Polling

Inertia v3's `useHttp` hook is a form-centric API (validation, precognition, processing state). The notification polling system (`use-notifications.ts`) makes standalone GET/POST/DELETE requests that don't fit the form model. `axios` is the correct choice here as a lightweight HTTP client.

**Action**: Keep axios for notification polling and any standalone HTTP requests.

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

## Playwright MCP

- Output dir: `C:\Users\nrelj\AppData\Local\Temp\opencode\playwright-mcp`
- Config: `C:\Users\nrelj\.config\opencode\playwright-mcp.json`
- `outputMaxSize`: 10MB (auto-evicts old files)
- `saveSession: true` — session persists to output dir on disconnect
- `imageResponses: "allow"` — images are returned to the client (not omitted)
- `snapshotMode: "full"` — full accessibility snapshots
- **Session cleanup**: Before first `navigate` call in a new browser session, always run:
  ```powershell
  Remove-Item -LiteralPath "C:\Users\nrelj\AppData\Local\Temp\opencode\playwright-mcp" -Recurse -Force -ErrorAction SilentlyContinue; New-Item -ItemType Directory -Path "C:\Users\nrelj\AppData\Local\Temp\opencode\playwright-mcp" -Force | Out-Null
  ```
- Never save screenshots or logs to the project root — always use `outputDir`
- `.gitignore` has `/*.png` as safety net, but output should never land in root

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
php artisan migrate      # Run migrations
php artisan permissions:list          # List all permissions
php artisan app:create-admin           # Bootstrap/update admin user
```

## Deployment

### CI/CD Pipeline (GitHub Actions → Release + Server Deploy)

Deploys automatically on push to `main`. Uses **reusable workflows** for cross-repo reuse:

```
push main → deploy.yml
  ├─ ci.yml (reusable workflow_call)    — ubuntu-latest, full validation
  ├─ deploy-package.yml (reusable)      — windows-latest, builds deploy.zip
  ├─ release                            — creates GitHub Release
  └─ push-deploy                        — uploads deploy.zip to server

pull_request → pr.yml
  └─ ci.yml (reusable workflow_call)    — same validation as deploy
```

**ci.yml** (reusable `workflow_call`, ubuntu-latest):

- PHP 8.4 + Node 22, Composer + npm caches
- SQLite .env + migrate, then: lint, typecheck, format:check, vitest, build, phpunit, pint
- Inputs: `skip-php-tests`, `skip-npm-tests` (boolean, default false)

**deploy-package.yml** (reusable `workflow_call`, windows-latest):

- git-cliff version bump → SQLite build .env → full build pipeline
- Strip dev deps → production .env → `deploy.zip` artifact
- Output: `version` string

**deploy.yml** (orchestrator, push main):

- Calls `ci.yml` → `deploy-package.yml` → creates GitHub Release → uploads to server
- All jobs run sequentially with `needs:` dependencies

**pr.yml** (pull_request to main):

- Calls `ci.yml` — identical validation, no secrets needed

**Server (`DeployController::check()`):**

1. Verify token + lock file (10 min TTL)
2. Fetch latest release from GitHub API
3. Download `deploy.zip`, extract, restore `.env`
4. Run: migrate, create-admin, cache, storage:link

### GitHub Secrets

| Secret                                          | Purpose                                 |
| ----------------------------------------------- | --------------------------------------- |
| `DEPLOY_TOKEN`                                  | Must match `config('app.deploy_token')` |
| `GH_TOKEN`                                      | Fine-grained PAT (Contents: Read+Write) |
| `DB_DATABASE` / `DB_USERNAME` / `DB_PASSWORD`   | Production MySQL                        |
| `ADMIN_EMAIL` / `ADMIN_PASSWORD` / `ADMIN_NAME` | Initial admin bootstrap                 |

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
    CreatesTestUsers.php          # Shared trait: admin(), regularUser(), createUserWithRole(), seedRolesAndPermissions()
  Feature/
    Ai/
      AiProviderCrudTest.php      # Provider CRUD + validation (6 tests)
      AiProviderTestConnectionTest.php  # Connection test validation (4 tests)
    Blog/
      BlogCrudTest.php            # Post CRUD lifecycle (7 tests)
      BlogPermissionsTest.php     # Role-based access (6 tests)
      BlogCategoryTest.php        # Category CRUD (5 tests)
      BlogTagTest.php             # Tag CRUD (5 tests)
      BlogPublicTest.php          # Public blog routes (5 tests)
    Deploy/
      DeployEndpointTest.php      # Token auth, verification, upload (6 tests)
    FeatureRegistryTest.php       # Singleton, nav items, permissions (5 tests)
    Notifications/
      NotificationPollTest.php    # Poll endpoints, auth, filters (8 tests)
      NotificationServiceTest.php # Cached count, purge (3 tests)
    Public/
      PublicPagesTest.php         # Welcome, about, services, contact (4 tests)
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

### Running Tests

```bash
php artisan test --compact                           # All tests (compact output)
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
