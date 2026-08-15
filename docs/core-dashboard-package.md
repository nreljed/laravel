# Cost Breaker 360 → Shared Core Package

> Status: **Approved** — 2026-08-02
> Model: Cost Breaker 360 (`nreljed/laravel`) is the master. Shop (`nreljed/laravel.shop`) and Booking (`nreljed/laravel.booking`) are separate apps that consume the core package and plug their domain features into its extension layer.

## 1. Vision & Model

**Model (WordPress/Next.js-style):** Cost Breaker 360 is both the reference app and the source of a shared core package. Shop and Booking install the core and keep only app-specific code on top.

**Direction is one-way:** core → consumers. Core updates propagate via `composer update` → `migrate` → `npm run build`.

**What's CORE** (lives in the package, standard everywhere): dashboard/admin, auth, account (incl. API Tokens), notifications, settings, email, blog, AI (BYOK), UI kit (source-shipped).

**What's APP** (lives in each project): public pages, domain features (Shop's `Shop/`, Booking's `Booking/`/`Properties/`/`Posts/`), custom dashboard _pages_ (optional overrides).

**Why:** Cost Breaker is a product deployed for external clients — the core must stay clean, separable, and versioned. Today changes flow by manual copy-paste; this eliminates that entirely.

## 2. Confirmed Decisions

| #   | Decision                                                                                                                                                                                                                                                                                                                                                  |
| --- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| D1  | **Monorepo** — Cost Breaker stays app at repo root; package at `packages/dashboard-core/`; published via subtree-split                                                                                                                                                                                                                                    |
| D2  | **Scope** — everything: core + Blog + AI                                                                                                                                                                                                                                                                                                                  |
| D3  | **Frontend = single channel** — ALL dashboard frontend (pages + components + hooks + lib) ships as TSX source inside the Composer package, consumed via the `@dashboard` Vite alias. No separate npm package for now. The package ships a `package.json` declaring peer deps as a contract; split `@nreljed/ui` later only if a real dep conflict appears |
| D4  | **Master-wins** — consumers delete duplicated core code on cutover                                                                                                                                                                                                                                                                                        |
| D5  | **AI is core** (BYOK architecture unchanged)                                                                                                                                                                                                                                                                                                              |
| D6  | **Public pages per-app**                                                                                                                                                                                                                                                                                                                                  |
| D7  | **Roles = config catalog**, per-app                                                                                                                                                                                                                                                                                                                       |
| D8  | **Page override** for rich dashboards                                                                                                                                                                                                                                                                                                                     |
| D9  | **API Tokens + ForceInertiaCacheHeaders adopted into core**                                                                                                                                                                                                                                                                                               |
| D10 | **Single package now**, structured to split into per-feature packages later (sub-namespaces `Nreljed\DashboardCore\Blog`, `.Ai`, ...)                                                                                                                                                                                                                     |
| D11 | **Feature-toggle mechanism NOT adopted** — Booking's broken `ModuleController` dropped                                                                                                                                                                                                                                                                    |
| D12 | **Composer-only distribution** — `nreljed/dashboard-core` on GitHub Packages is the _only_ artifact. One version tag drives everything                                                                                                                                                                                                                    |
| D13 | **Dev loop via `path` repository** — Shop/Booking resolve core from a local master clone (`"type":"path","url":"../laravel/packages/dashboard-core"`). Edit master → test in all three apps instantly, zero tags/publishing during dev. Tagged VCS used only for production                                                                               |
| D14 | **One-command update** — each consumer gets `update-core.sh`: `composer update` → `npm install` → `npm run build` → `php artisan migrate`                                                                                                                                                                                                                 |
| D15 | **Auto-release** — merge to `main` touching `packages/` auto-bumps (patch/minor) via git-cliff and publishes. Releasing is never manual                                                                                                                                                                                                                   |

## 3. Architecture

```
nreljed/laravel  (master = Cost Breaker 360)
├─ app/            # the reference app (uses the package)
├─ packages/dashboard-core/
│  ├─ composer.json          # nreljed/dashboard-core, PSR-4 Nreljed\DashboardCore\ => src/
│  ├─ package.json           # peer-dependency contract for the shipped TSX
│  ├─ src/
│  │  ├─ DashboardCoreServiceProvider.php   # routes, middleware, migrations, config, commands, scheduler, gates
│  │  ├─ Blog/                           # migrated verbatim from app/Blog
│  │  ├─ Ai/                             # migrated verbatim from app/Ai
│  │  ├─ Services/FeatureRegistry.php    # now dynamic (extension point)
│  │  ├─ Contracts/Feature.php
│  │  ├─ Http/Controllers/               # dashboard, admin, auth, account, settings, email
│  │  ├─ Models/                         # User, Notification, Post, ...
│  │  ├─ Notifications/ · Policies/ · Requests/ · Rules/ · Jobs/ · Mail/ · Traits/ · Enums/
│  │  └─ ...
│  ├─ database/migrations/    # VERBATIM copies (same basenames → existing DBs skip)
│  ├─ config/                 # core.php, roles.php, settings.php, ai-providers.php
│  ├─ resources/js/           # @dashboard pages, components, hooks, lib
│  ├─ routes/
│  ├─ tests/
│  └─ .nreljed/               # build/config metadata for publish pipeline
```

**Consumer wiring (each app):**

- `composer require nreljed/dashboard-core` (production: GitHub Packages VCS; dev: `path` repo)
- `vite.config.js`: alias `@dashboard → vendor/nreljed/dashboard-core/resources/js`
- `tailwind.config.js`: `@source` into vendor
- `tsconfig.json`: path alias `@dashboard`
- `resources/js/app.tsx`: explicit page inventory — `basePages: registerCoreTemplates()` + `pageOverrides` replaces page globbing
- `config/core.php` override file: roles, settings defaults, feature registration

**Overrides (consumer wins, 3 levels):**

1. **Page** — consumer's `Pages/{feature}/X.tsx` shadows core's by Inertia name (for rich dashboards)
2. **Component** — shadow by path
3. **Service** — container rebinding (`$app->instance()`)

## 4. Distribution

**Single channel (D3/D12):** Composer `nreljed/dashboard-core` via GitHub Packages (Packagist-style, authenticated). Contains PHP + migrations + config + routes + the full dashboard frontend as TSX source. No npm registry, no second version stream.

**Dev:** `path` repository pointing at a local master clone. **Prod:** tagged VCS.

## 5. Extension Layer — the Plugin Contract

**`Feature` interface** (in `Nreljed\DashboardCore\Contracts\Feature`):

```php
public function slug(): string;
public function getNavItems(): array;
public function getSharedData(): array;
public function getDashboardData(): array;
public function getAnalyticsSeries(): array;
public function permissions(): array;
public function getNotificationTypes(): array;
public function getEmailTemplates(): array;
public function getGalleryConfigs(): array;
public function onUserDelete(int $userId): void;
```

`FeatureRegistry` discovers **registered providers** (via `config/core.php`) — **no core edits to add a feature**. Core consumers (Blog, AI) implement it; app features (Shop, Properties, Posts) implement it too.

## 6. Specificity Coverage Matrix

| #   | Divergence                                                | Mechanism                                                                         |
| --- | --------------------------------------------------------- | --------------------------------------------------------------------------------- |
| 1   | Roles differ                                              | `config/roles.php` catalog, per-app override                                      |
| 2   | `default_role` differs                                    | `config('roles.default_role')` fallback + DB settings seed                        |
| 3   | Settings keys differ                                      | config-driven settings seed                                                       |
| 4   | Feature permissions                                       | `FeatureRegistry::permissions()` + shared `RolesAndPermissionsSeeder`             |
| 5   | Rich dashboards                                           | Page override (D8) + `getDashboardData()`                                         |
| 6   | Blog editor divergence                                    | Consumers gain Lexical; schema compat verified in Phase 0 ⚠️                      |
| 7   | Blog migration deltas                                     | Per-migration diff + `hasColumn` guards; adopt master's 8 ⚠️                      |
| 8   | Middleware union                                          | `ForceInertiaCacheHeaders` in core; maintenance/session/IP inherited by consumers |
| 9   | Shared props stubs                                        | Extension points only, **no stubs**                                               |
| 10  | API Tokens                                                | Adopted into core (D9)                                                            |
| 11  | Console commands union                                    | Core command set + domain commands app-side                                       |
| 12  | User model/trait parity                                   | Phase-0 check; union ⚠️                                                           |
| 13  | Feature toggles                                           | **Out of scope** (D11)                                                            |
| 14  | Stats page hard-codes Users+Posts                         | Generic from `getAnalyticsSeries()`                                               |
| 15  | Deploy/domains/public pages/email templates/notifications | Per-app or Feature contract                                                       |

## 7. Execution Phases

### Phase 0 — Classification & Reconciliation ✅ COMPLETE (2026-08-02)

> Full report: [`core-dashboard-package-phase0.md`](core-dashboard-package-phase0.md)

1. **Core/App classification table** built for every master file — 60+ app files → CORE, ~15 → APP (Deploy, Public, TestPosts, Page model excluded, public frontend)
2. **Migration diff** — cataloged master's 8 blog migrations vs shop/booking's 5; 3 extra blog + 3 root blog-touching migrations are additive/drop-only
3. **User model / trait parity check** — `UserDataTrait` identical; User divergences unioned (AI, reset override, nickname dropped); consumers extend core User
4. **npm-dep scan** — core peer-dependency contract listed; sonner→toast.add swap identified
5. Produced `docs/core-dashboard-package.md` (this doc) + `docs/core-dashboard-package-phase0.md`
6. **Gate: blog content schema compat VERIFIED** (with 1 flagged behavior decision: `posts.status` default)

### Phase 1 — Monorepo Restructure ✅ COPY COMPLETE (2026-08-02)

> Execution report: [`core-dashboard-package-phase0.md`](core-dashboard-package-phase0.md) §0.10

1. Create `packages/dashboard-core/` with the full tree (PHP + migrations + JS)
2. **Rename `App\` → `Nreljed\DashboardCore\`** — done via deterministic PowerShell text replace (146 files) + Pint, NOT Rector; rector's `RenameClassRector` prefix-mapping was considered and rejected because the copy is self-contained (every `App\` resolves to a copied class; zero TestPosts/Telescope/Page refs remain)
3. Migrations moved **verbatim** (same basenames → existing DBs skip); 5 master-only migrations excluded (Pages, Telescope, modules, nickname, test-posts)
4. Composer: `nreljed/dashboard-core`, PSR-4, package tests wired (5/5 pass) — master suite still 431/431 green
5. `git subtree` split branch `split/dashboard-core` for the publish pipeline (Phase 3)

### Phase 2 — Extension Layer + Frontend (Cost Breaker green = proof of concept)

> Status: **Phase 2a (backend cutover) + Phase 2b (frontend `@dashboard` cutover) COMPLETE** — master now consumes all CORE code (PHP + TSX) from the package; gate green (typecheck/lint/format/vitest/build/phpunit 431). See §10 for known limitations.

1. Dynamic `FeatureRegistry` + `Feature` contract
2. Config-driven roles & settings (`config/roles.php`, `config/settings.php`)
3. `registerCoreTemplates()` (opt-in Inertia page kit) + `pageOverrides` shadowing
4. Generic `stats.tsx` via `getAnalyticsSeries()`
5. Adopt API Tokens + `ForceInertiaCacheHeaders` into core
6. Frontend import rewrite (`@blog`, `@test-posts` → `@dashboard`)
7. `permissions:sync` command
8. **Green gate:** full `npm run check:ci` passes; Cost Breaker still works end-to-end

### Phase 3 — Publish Pipeline

1. Subtree-split GitHub Action (on `dashboard-core/vX.Y.Z` tags)
2. Composer package on GitHub Packages
3. **SemVer policy: master drives minor/patch; breaking core changes only in majors; consumers update when ready**
4. Consumer update runbook: `./update-core.sh` (composer update → npm install → build → migrate)

### Phase 4 — Shop Cutover

1. Delete duplicated core code (master-wins); add `@dashboard` alias + explicit page inventory (`registerCoreTemplates()` + `pageOverrides`)
2. Keep app-side: `Shop/` feature, public pages, domain dashboard, rich dashboard as **page override**
3. Keep Shop's `crm_user`→`customer` **one-time migration** on cutover
4. **Green gate:** shop.laravel.tunispromo.tn verified

### Phase 5 — Booking Cutover

1. Same as Phase 4; drop broken `ModuleController`/`ModuleSection`
2. Keep app-side: `Booking/`, `Properties/`, `Posts/`, public pages
3. **Green gate:** booking.laravel.tunispromo.tn verified

### Phase 6 — Ongoing Workflow

1. Core changes in master → auto-release (D15) → consumers update on their schedule (`./update-core.sh`)
2. Core→consumer channel for feature requests
3. Documented "how to add a feature" (implements `Feature`)

## 7.1 Standardization Contract (Deploy & Feature Convention) — 2026-08-15

> All projects (master, Shop, Booking) run the SAME deploy architecture because
> `DeployController`, `DeployService`, `deploy:auto`, and the `/deploy/*` routes all
> ship from this package. This section is the canonical contract — keep it in sync
> when anything below changes.

### 7.1.1 Config contract

| Config file         | Ships in | Consumer override   | Keys                                                                                                       |
| ------------------- | -------- | ------------------- | ---------------------------------------------------------------------------------------------------------- |
| `config/deploy.php` | package  | **yes** (see below) | `token` (`env('DEPLOY_TOKEN')`), `github_repo` (`env('GITHUB_REPO', 'nreljed/laravel')`), `lock_ttl` (600) |
| `config/core.php`   | package  | yes                 | `features`, `load_migrations`, `inertia_middleware`, `orphan_media`, `settings.groups`                     |
| `config/admin.php`  | package  | yes                 | admin UI settings                                                                                          |
| `config/roles.php`  | package  | yes                 | per-app role catalog (D7)                                                                                  |

**Consumer `config/deploy.php` MUST override `github_repo`** to its own repo, or the
server will self-deploy the wrong project:

```php
// e.g. laravel.shop/app/config/deploy.php
return [
    'token' => env('DEPLOY_TOKEN'),
    'github_repo' => env('GITHUB_REPO', 'nreljed/laravel.shop'),
    'lock_ttl' => 600,
];
```

### 7.1.2 Deploy endpoint contract (all shipped from the package)

| Endpoint                                                   | Method   | Auth    | Purpose                                                        |
| ---------------------------------------------------------- | -------- | ------- | -------------------------------------------------------------- |
| `/deploy/upload`                                           | POST     | `token` | Upload `deploy.zip` directly, extract over root                |
| `/deploy/check`                                            | GET/POST | `token` | Server pulls latest release from GitHub API, self-deploys      |
| `/deploy/env`                                              | POST     | `token` | Update server `.env` values (rejects `APP_KEY`/`DEPLOY_TOKEN`) |
| `/deploy/cache-clear`                                      | GET      | `token` | Emergency reset: `migrate:fresh` + cache clear                 |
| `/deploy/migrate`, `/deploy/create-admin`, `/deploy/reset` | —        | `token` | Deploy helpers                                                 |

All routes: throttle `5,1`. `DeployService::check()` reads `GITHUB_TOKEN` from the
server `.env`; lock file `.deploy-lock` TTL 600s.

### 7.1.3 Canonical CI/CD deploy flow (check-first)

Both `nreljed/laravel` and `nreljed/laravel.shop` use the SAME `push-deploy` job shape:

1. **env-push**: `POST /deploy/env` with `GITHUB_TOKEN` (unified PAT) — runs `if: always()`, `continue-on-error`.
2. **Poll `/deploy/check`** (8 × 45s) — accept `"status":"deployed"` or `"status":"current"` matching the release tag.
3. **Fallback `/deploy/upload`** only if check didn't confirm, then **confirm via check** again (response can be lost mid-extract).
4. **FTP fallback** (optional, `continue-on-error`): DirectAdmin account whose **home IS the app project root** → upload `deploy.zip` to `ftp://server:21/deploy.zip`. Skips itself when `FTP_*` secrets are unset.

Resilience: the server's scheduled `deploy:auto` (every 5 min, in `routes/console.php`)
picks up any release CI couldn't push (hosting firewall blocks datacenter IPs).

### 7.1.4 FeatureProvider contract checklist

A feature provider must implement `Nreljed\DashboardCore\Contracts\FeatureProvider`
(+ `InteractsWithFeatures` trait). Only `name()` and `slug()` are required; the trait
supplies no-op defaults for everything else. Common overrides:

| Method                                          | Purpose                                   |
| ----------------------------------------------- | ----------------------------------------- |
| `description()`, `version()`, `dependencies()`  | registry metadata                         |
| `getNavGroupLabel()`                            | sidebar group heading ("Shop", "Content") |
| `getNavItems()`                                 | sidebar entries                           |
| `getDashboardData()` / `getAnalyticsSeries()`   | dashboard stats                           |
| `getSharedData()`                               | Inertia shared props                      |
| `getSettingsRoute()` + `hasSettings()`          | settings link                             |
| `getEmailTemplates()`, `getNotificationTypes()` | email/notification registration           |
| `permissions()`                                 | feature permission slugs                  |

Add a feature with `php artisan make:feature {Name}` (scaffolds a discoverable
provider) then register it in `config/core.php` `features`.

### 7.1.5 Publish flow (core → consumers)

```
push to main touching packages/dashboard-core/**  ──▶  core-publish.yml
    ├── subtree-split to nreljed/dashboard-core
    ├── version bump (patch/minor, git-cliff) + tag
    ├── GH release (deploy.zip of the package)
    └── dispatch core-updated to consumers
Consumers: ./update-core.sh  (composer update → npm install → npm run build → php artisan migrate)
```

Rules: one `GH_TOKEN` (unified PAT) in every repo + server `.env`; one `DEPLOY_TOKEN`
per app (matches that server's `app.deploy_token`); never commit either.

## 8. Risks

- **Blog schema drift** — mitigated in Phase 0 gate
- **Role/permission data migration** on cutover — one-time migrations + `permissions:sync`
- **GitHub Packages auth** for consumers (Shop/Booking CI) — token setup documented
- **Breaking changes** — SemVer majors only; consumers update independently
- **Windows FS + long paths** in monorepo — keep `packages/dashboard-core` shallow

## 9. Open Items (for later)

- GitHub token setup for consumers
- First version tag (`dashboard-core/v1.0.0`)
- Blog content schema compat verification — ✅ done (see §0.3); flagged: `posts.status` default decision (§0.9.1)

## 10. Notes & Known Limitations (Phase 2a/2b cutover)

- **Inertia test finder vs blog feature pages** — `config/inertia.php` `pages.paths` now includes the CORE and Blog feature `Pages/` roots so `assertInertia()->component(...)` can locate files. Blog _admin_ pages render as `Blog/Index`-style names but live at `src/Blog/Resources/js/Pages/Index.tsx` — the `Blog` segment is a feature namespace, not a folder under `Pages/`, so the test finder can't resolve those names. No test asserts them today (only `Blog/Public/Show`, which resolves via the CORE root). If you ever add such an assertion, extend the finder or assert props instead.
- **`@dashboard-blog` alias** — reserved for future feature pages. `registerCoreTemplates()` currently globs feature pages by relative path, so no import uses the alias yet.
- **Path-repo refresh** — `composer update` won't refresh the vendor mirror; after editing package code run `composer reinstall nreljed/dashboard-core`.
- **Prism highlighting** — `resources/js/lib/prism-highlight.ts` uses `import(\`prismjs/components/prism-${lang}\`)` (bare specifier), which browsers can't resolve at runtime → non-fatal console error on public blog pages; code blocks render un-highlighted. Pre-existing (identical code in pre-cutover master). Fix: static imports in the package.
- **`packages/` is committed with the master repo** (path repo, not a submodule) — CI checkout and `composer install` depend on it.
