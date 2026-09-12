# Naming & Restructure Migration — `v1.0.0` Release Train

Status: PLANNED (2026-09-12)
Owner: nrelj
Scope: 7 repos (kernel, 2 modules, 3 apps) + runner infra + skills/docs

---

## 0. Decisions (LINKED, DO NOT REVISE)

| Surface      | Old                                                    | New                           | PHP namespace      | npm scope           |
| ------------ | ------------------------------------------------------ | ----------------------------- | ------------------ | ------------------- |
| Kernel       | `nreljed/dashboard-core`                               | **`nreljed/platform`**        | `Nreljed\Platform` | `@nreljed/platform` |
| Blog         | bundled in kernel (`src/Posts`)                        | **`nreljed/blog`** (new repo) | `Nreljed\Blog`     | `@nreljed/blog`     |
| Module: Shop | `nreljed/shop-module`                                  | **`nreljed/shop`**            | `Nreljed\Shop`     | `@nreljed/shop`     |
| Module: Bkng | `nreljed/booking-module`                               | **`nreljed/booking`**         | `Nreljed\Booking`  | `@nreljed/booking`  |
| App: CB360   | `nreljed/laravel` (composer `laravel/laravel`)         | **`nreljed/cost-breaker`**    | —                  | —                   |
| App: Shop    | `nreljed/laravel.shop` (composer `laravel/laravel`)    | **`nreljed/shop`**            | —                  | —                   |
| App: Bkng    | `nreljed/laravel.booking` (composer `laravel/laravel`) | **`nreljed/booking`**         | —                  | —                   |

- **AI stays in kernel** (BYOK provider catalog, AiProvider model, usage logs — core-coupled).
- **One feature = one `FeatureProvider`** rule. Booking keeps its two providers (`Booking`, `Properties`) inside the single `nreljed/booking` package (same domain vertical), documented — not split.
- **Froze `FeatureProvider` contract** at the current shape (see kernel `src/Contracts/FeatureProvider.php`). Any change = MAJOR bump (enforced by CI gate, below).
- **Coordinated `v1.0.0`** across kernel + blog + shop + booking. Breaking = MAJOR.

---

## 1. Principles

1. **Rename order is law**: kernel → blog → modules → apps. Apps' lockfiles pin module constraints that depend on kernel's new name; modules must resolve against the renamed kernel.
2. **Every phase ends deployable**: tag -> `composer update` in consumers -> `npm run build` -> `php artisan test` green.
3. **`v1.0.0` tags must exist BEFORE any app `composer update`** succeeds for the renamed package (vcs repo resolution by tag).
4. **No forced pushes to shared branches.** Core history moves via `git subtree split` (no rewrite), not `filter-repo`.
5. **Rename is a re-brand, not a refactor.** No behavioral changes during renames. Behavior changes (blog extraction touching routes/registration) only inside Phase 1, tested first.
6. **Skill/doc files are part of the deliverable** — a repo whose docs say `dashboard-core` is not migrated.

---

## 2. Verified rename surface (exact counts, pre-flight)

| Surface                      | Kind                                  | Count                                                                                                               |
| ---------------------------- | ------------------------------------- | ------------------------------------------------------------------------------------------------------------------- |
| Kernel `src/**/*.php`        | `DashboardCore` ns                    | 69 files, 159 refs (20 controllers, 25 Http, 22 Requests, 12 Console, 16 Ai, 17 Posts, ...)                         |
| Kernel `dashboard-core/` top | package name/psr-4/npm/docs           | 20 files                                                                                                            |
| Kernel tests                 | contract tests                        | update after freeze                                                                                                 |
| Module Shop                  | `Nreljed\ShopModule`                  | controllers up to 7 refs each (`ProductsController`, `CheckoutController`, ...), test `ModuleCoreCompatibilityTest` |
| Module Booking               | `Nreljed\BookingModule`               | `BookingGuestController` (6), `BookingServiceProvider` (5), ...                                                     |
| Apps                         | `nreljed/dashboard-core` in workflows | ci.yml `uses:` + core-update.yml (3) + deploy-package.yml (2) + deploy.yml (1)                                      |
| Apps                         | frontend alias mesh                   | vite.config.js + tsconfig.json vendor paths (3 apps)                                                                |
| Apps                         | `config/features.php`                 | provider class lists (imports + arrays)                                                                             |
| Skills                       | global `~/.config/opencode/skills`    | deploy, feature-architecture, lexical-editor, permission-system, self-hosted-runners                                |
| Skills                       | project `.agents/skills`              | ai-sdk-development (+ AGENTS.md core + app)                                                                         |
| Runner infra                 | `C:\runners\*` names                  | core, shopmodule, bookingmodule, laravel, shop, booking                                                             |

---

## 3. Phase-by-Phase Execution

> Each phase = [commands] + [verification]. Mark `[x]` when shipped.

### ORDER NOTE (2026-09-12, corrected)

Principle #1 says "rename order is law: kernel → blog → modules". The blog split
**moves AFTER the kernel rename** so extracted blog code references
`Nreljed\Platform\...` kernel classes directly — never getting renamed twice
(once as `DashboardCore\Posts`, then again on the kernel rename). Phases below
are numbered in corrected order; the cut-over sequence (§6) matches.

### Phase 1 — Kernel rename → `nreljed/platform` (mechanical, FIRST)

**Goal**: kernel becomes `nreljed/platform` / `Nreljed\Platform` while still
shipping blog + AI; blog split happens in Phase 2 on already-renamed code.

1. GitHub repo rename `dashboard-core` → `platform` (GH redirect keeps old URL working).
2. PHP namespace sweep `Nreljed\Platform\` → `Nreljed\Platform\` in `src/`, `tests/`, `routes/`, `config/` (159 refs); class rename `DashboardCoreServiceProvider` → `PlatformServiceProvider` (file + usages).
3. composer.json `name` → `nreljed/platform`, `autoload.psr-4` → `Nreljed\Platform\`, align `version`.
4. package.json: `@nreljed/dashboard-core` → `@nreljed/platform`, version aligned.
5. Runner: rename `C:\runners\core` subscription → `win-runner-platform`; update `C:\tools\gha-runner.ps1` default mapping + self-hosted skill table.
6. **Frontend alias mesh centralization** (single-source): kernel ships `platform.aliases.js` (paths + dedupe list) consumed by every app's vite.config + tsconfig — kills hand-written vendor paths. Preserve `@/ → kernel resources/js` behavior (documented Windows workaround; breaking it breaks every kernel component import).
7. Core CI: hardcoded `for repo in nreljed/laravel nreljed/laravel.shop nreljed/laravel.booking` → read `CORE_CONSUMERS` **GitHub secret** (space-separated), so adding app #4 edits no core code.
8. Core CI: add **contract-frozen gate** — a job that fails if `src/Contracts/FeatureProvider.php` changes without a `MAJOR` version hint in the tag/PR title.

**Verify (local, no app build yet — apps still install dashboard-core by tag)**: composer psr-4 autoloads, all kernel namespaces resolve, kernel test suite green against the renamed tree.

### Phase 2 — Split Blog out of kernel (behavioral)

**Goal**: `src/Posts` becomes standalone `nreljed/blog`; kernel keeps contract + registry + PublicSite + Ai. Blog code now references `Nreljed\Platform\...` (already renamed).

1. Freeze contract: copy `src/Contracts/FeatureProvider.php` byte-identical into blog package.
2. Create `github.com/nreljed/blog` (private, composer `nreljed/blog`, psr-4 `Nreljed\Blog\`, npm `@nreljed/blog`), clone into `C:\Users\nrelj\Local Sites\blog`.
3. Move the 43-file `src/Posts/` unit → blog `src/` (Models, Controllers, Requests, Routes, Services, Providers, migrations, views, frontend kit), changing `Nreljed\Platform\Posts\X` → `Nreljed\Blog\X`.
4. Relocate kernel artifacts with blog: `Observers\PostObserver`, `Policies\PostPolicy`, `Console\Commands\PublishScheduledContent|PostsClean|PostsSeed`, `Jobs\GenerateSeoForPost`, `Database\Factories\*Post*`, 3 kernel posts migrations (`create_posts_table`, `fix_posts_content_nullable_and_drop_tags`, `add_status_published_at_index`). Prune the blog-category index from the shared `add_fk_and_query_indexes` migration (it remains kernel-owned for role_user/permission_user/audit_logs only).
5. Blog provider: `PostsServiceProvider` → `BlogServiceProvider` under `Nreljed\Blog`, keeps slug `posts`, URL prefixes `dashboard/posts` + `/blog` (app-facing contract unchanged — zero route churn for consumers).
6. Kernel refactor (remove blog coupling, keep data-driven registry):
   - `SitemapController`: drop Post/PostCategory/PostTag hardcoding — registry-only (fixes existing double-listing bug where `getSitemapUrls()` duplicated the hardcoded entries).
   - `PlatformServiceProvider`: drop PostPolicy gate binding, blog commands (`PublishScheduledContent`, `PostsClean`, `PostsSeed`), blog schedules, blog migrations from publishes list.
   - `CleanupOrphanMedia`: default `['posts' => Post::class]` → read `FeatureRegistry::getAllOrphanMediaTargets()`.
   - `ImagePlugin.tsx`/editor hardcoded `posts` actions → read gallery config from the provider.
   - `Setting::KEY_GROUP_MAP` `show_word_count => posts` and `PermissionGroups::GROUP_ORDER` `Posts` remain (kernel catalog is name-stable; blog declares settings/permissions via registry).
7. Register `Nreljed\Blog\BlogServiceProvider` in the apps' `config/features.php` (all 3 use posts).
8. App vite/tsconfig: `@dashboard-posts` → `@blog` (`vendor/nreljed/blog/src/Resources/js`); page-config `registerPostsPages` → blog kit.
9. Move blog tests (kernel `tests/Feature/Blog/*`, `app/tests/Feature/Blog/*`) into blog repo; app keeps a thin smoke test.
10. Verify: app `composer require nreljed/blog`, build, blog pages render; kernel + blog + app suites green.

> Practical fallback if `subtree split` fights the mixed file layout: split the frontend kit to blog repo first, then move blog PHP by normal commits (history parity on PHP lost — acceptable). Decision point at execution time.

### Phase 3 — Module renames

1. `shop-module` → repo `nreljed/shop`; composer `nreljed/shop-module`→`nreljed/shop`; psr-4 `Nreljed\ShopModule\`→`Nreljed\Shop\`; npm `@nreljed/shop`.
2. `booking-module` → repo `nreljed/booking`; composer `nreljed/booking-module`→`nreljed/booking`; psr-4 `Nreljed\BookingModule\`→`Nreljed\Booking\`; npm `@nreljed/booking`. Both providers (`Booking`, `Properties`) keep names/namespaces inside package.
3. `scripts/frontend-boundary-check.php` in each — update path regexes from old vendor names.
4. `ModuleCoreCompatibilityTest`: `^0.x` → `^1.0` (both modules).
5. Dispatch wiring: module CI → `repos/nreljed/laravel.shop`→`repos/nreljed/shop`, `booking-module`→`repos/nreljed/booking`; event types unchanged (`shop-updated`, `booking-updated`).
6. Runners: `win-runner-shopmodule` → `win-runner-shop`, `win-runner-bookingmodule` → `win-runner-booking`, re-register, update `gha-runner.ps1` + skill.

**Verify**: module CI green on renamed repos; app that consumes module builds + draws module pages.

### Phase 4 — Coordinated `v1.0.0` release train

1. Order: `platform` v1.0.0 → `blog` v1.0.0 → `shop` v1.0.0 → `booking` v1.0.0.
2. Each release: composer.json + package.json `version` bump to `1.0.0`, commit, tag `v1.0.0`, push → CI validates + creates GitHub Release + dispatches `core-updated`/module-updated to consumers.
3. Apps: `composer require nreljed/platform:^1.0 nreljed/blog:^1.0` (+ modules); commit lockfiles; `npm run build`; `php artisan test`.
4. Enforce semver: any future MAJOR = coordinated tag bump across the train; `ModuleCoreCompatibilityTest` + consumer CI pins.

**Verify**: `composer validate`, `composer.lock` pins `1.0.0` in all apps, all `php artisan test` + `npm run test` green, all four deploys green.

### Phase 5 — App repo renames

1. GitHub renames: `laravel`→`cost-breaker`, `laravel.shop`→`shop`, `laravel.booking`→`booking` (GH redirects).
2. composer `name`: `laravel/laravel` → `nreljed/cost-breaker` / `nreljed/shop` / `nreljed/booking`.
3. Workflows self-reference sweep (deploy.yml, deploy-package.yml, core-update.yml, shop-update.yml, booking-update.yml, ci.yml `uses:` consumer-ci path).
4. Dispatch targets (kernel CI + module CI) updated to new repo names.
5. Runners re-label if desired; AGENTS.md + skills repo references updated.
6. Final full build+test on all three apps; deploy all.

### Phase 6 — Lock-in / cleanup

1. Sweep every repo for stale tokens: `dashboard-core`, `DashboardCore`, `shop-module`, `booking-module`, `laravel.shop`, `laravel.booking` (grep gate, zero hits).
2. Update 7 skill files + AGENTS.md (global + app) fully.
3. Delete obsolete doc references (`docs/core-dashboard-package*.md` → renamed forms or archive).
4. `C:\tools\gha-runner.ps1` runner table reflects new names.
5. Update this playbook's Status → DONE with date.

---

## 4. Registry — every place that must change (checklist)

### Kernel repo (nreljed/platform)

- [ ] `composer.json` name/psr-4/version
- [ ] `package.json` name/version
- [ ] `src/**` namespace sweep (159)
- [ ] provider class rename
- [ ] CI `consumer-ci.yml` uses path
- [ ] CI `ci.yml` dispatch list → `CORE_CONSUMERS` secret + contract gate
- [ ] docs/*.md
- [ ] README.md

### blog repo (nreljed/blog) — new

- [ ] created from subtree split
- [ ] contract file copy
- [ ] CI (consumer-ci pattern copy)
- [ ] version 1.0.0 aligned

### shop-module → nreljed/shop

- [ ] composer/psr-4/npm/README
- [ ] `ModuleCoreCompatibilityTest` → ^1.0
- [ ] boundary-check paths
- [ ] dispatch target → nreljed/shop
- [ ] CI runs-on var rename if changed

### booking-module → nreljed/booking

- [ ] same as shop
- [ ] both providers preserved

### Apps (×3)

- [ ] composer `name`
- [ ] require renames + lock commits
- [ ] `config/features.php` class lists
- [ ] vite.config.js + tsconfig.json (alias helper)
- [ ] `page-config.ts`/`pages` imports
- [ ] workflows (ci/core-update/deploy/deploy-package/module-update)
- [ ] .env comments / APP_NAME (already set)

### Runner infra

- [ ] `C:\runners\core|shopmodule|bookingmodule` re-register
- [ ] `C:\tools\gha-runner.ps1`
- [ ] self-hosted-runners skill table + `.env` PATH overrides

### Skills / docs / agents

- [ ] deploy, feature-architecture, lexical-editor, permission-system, self-hosted-runners (global)
- [ ] ai-sdk-development (+ app AGENTS.md, core AGENTS.md)
- [ ] this playbook → DONE

---

## 5. Risks & mitigations

| Risk                                                                                 | Mitigation                                                                                          |
| ------------------------------------------------------------------------------------ | --------------------------------------------------------------------------------------------------- |
| vcs composer can't resolve renamed pkg until tag                                     | Tag `v1.0.0` before any app `composer update` of it                                                 |
| `@/` alias points at kernel `resources/js`; breaking it breaks all kernel components | Alias-helper refactor preserves exact mapping; verify via `npm run build` in every app              |
| Blog frontend kit carved out incorrectly (Import paths `@dashboard-posts`, `@blog`)  | Phase 1 ships an app and builds it before starting Phase 2                                          |
| Two composer names for one train in same lock (old + new) during transition          | Sequence apps' `composer update` per phase; `composer remove` old name right after new require      |
| `git subtree split` fights mixed file layout for blog                                | Fallback: split frontend kit first, then PHP via regular commits (loses PHP git history parity)     |
| `convertFrom-Json` casing bug reading locks (dompdf PDF/Pdf)                         | Use `-AsHashtable` / jq / node for any lock inspection                                              |
| setup-php race on shared Windows runners                                             | Guarded already; keep `if: vars.RUNNER_CI != 'self-hosted'` pattern on any new CI added (blog repo) |

---

## 6. Cut-over sequence (chronological, with gates)

1. `[ ]` Phase 1: kernel renamed to `nreljed/platform` locally, kernel suite green
2. `[ ]` Phase 2: blog split ships + apps build/test green
3. `[ ]` Phase 3: modules renamed, module CIs green
4. `[ ]` Phase 4: v1.0.0 train tagged, lockfiles pinned, all tests green
5. `[ ]` Phase 5: app repos renamed, all deploys green
6. `[ ]` Phase 6: stale-token grep = 0, docs/skills updated, playbook DONE
