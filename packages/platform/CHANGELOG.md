# Changelog

All notable changes to `nreljed/platform` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/), and this project adheres to [Semantic Versioning](https://semver.org/).

## [0.1.87] - 2026-10-21

### Fixed

- **Feature origin detection survives a linked install.** `FeatureRegistry::resolveProviderOrigin()` compared the provider's file path against `base_path()`. When the platform is installed through a path repository, a symlink or a Windows junction, PHP resolves the real path, so the file no longer sits under the application and every platform-bundled feature was reported as `unknown` on the system-health page. The registry now compares against the real path of the installed `nreljed/platform` package (and the workspace mirror) before falling back to the path-relative checks.
- **Every top-level nav item carries an icon.** A module without a collapsible group has its resource descriptors emitted flat, and those items had no icon - a blank row in the sidebar. The registry now falls back to the generic file icon for any nav item that arrives without one.

## [0.1.86] - 2026-10-20

### Added

- **AI Integrations UI, rebuilt** (ported from the Project-WP fork). The Account → Integrations page now shows per-provider accordion cards with a live **status pill** (Connected / Active / Failed / Disabled), a stored-key **Test connection** button, a **Replace key** flow, capability badges, per-provider usage bars with an 80% warning threshold, an **Available providers** discovery list, and a separate **Usage & Limits** accordion with an inline daily-limits form (replacing the old caps dialog).
- `POST /ai-keys/{id}/test` — tests a *saved* key through the same SDK driver stack the agents use and records `last_test_ok` + `last_tested_at` on the provider row (new migration `2026_10_20_000001_add_test_status_to_ai_providers_table`).
- `AiProvider::supportedProviders()` now ships `default_model`, `testable` and `capabilities` metadata for the UI (Ollama is the only untestable provider).
- `SettingsAccordionItem` gained an optional `trailing` slot rendered beside the title.
- **Generic form submissions.** One engine, one storage table, one admin inbox. Every form on any consumer is now a config entry (`config/forms.php`) plus, optionally, a route — not a bespoke controller, table, model and admin screen.
- `config/forms.php` — the form catalog. Each entry declares `label`, `description`, `permission`, `redirect`, `identity` (which fields map to `name` / `email` / `summary`), `fields`, `honeypot`, `rate_limit`, `dedupe_window`, `notify`, `retention_days` and `consent`.
- `Support\FormRegistry` — loads the catalog, merges runtime contributions (`Forms::register()`) from feature providers, and builds Laravel validation rules, attributes and searchable/identity mappings from a field's declared `type`. Config wins on key collision. `violations()` returns catalog problems so a misdeclared form fails loudly instead of silently.
- `Models\FormSubmission` — one `form_submissions` row per submission: a denormalised `form_key` / `name` / `email` / `summary` / `status` for querying, plus a JSON `payload` for the full declared field set, plus attribution (`ip`, `user_agent`, `referrer`, `utm_source`, `utm_medium`, `utm_campaign`, `utm_term`, `utm_content`).
- `Services\FormSubmissionService` — the single collect path: honeypot → validate → dedupe → persist → queue notification. Used by both the generic and pinned endpoints, so a new form inherits every guard for free.
- `Jobs\DispatchFormSubmissionNotification` — queued fan-out to the admins holding the form's `permission`; a failure is logged and never blocks the visitor.
- `Support\CsvExporter` — reusable CSV streaming with spreadsheet formula-injection guarding (cells starting `=`, `+`, `-`, `@`, tab or CR are prefixed with `'`). Used by every export surface.
- Public endpoints: `POST /forms/{form_key}` (404 on an unregistered key) and the existing `POST /contact`, which now runs on the generic engine.
- Admin inbox at `/dashboard/admin/submissions` — cross-form list with form/status/search/date-range filters, sortable columns, per-page control, checkbox selection with bulk mark-read / archive / delete, a filter-aware CSV export, and a registry-driven detail view that renders the payload in declared field order. Behind a new `manage-submissions` permission (Administration group).
- `AuditAction\FormSubmissionDeleted` (`form_submission_deleted`, severity `warning`) recorded on single and bulk deletes, and `DataExported` on every CSV download.
- Migration `2026_10_19_000001_create_form_submissions_table` — creates the table, indexes it for the inbox's query patterns, and copies any existing `contact_submissions` rows across so no message is lost.

### Changed

- The pre-save `test()` and the new stored-key `testStored()` share one `runConnectionTest()` runner so both paths validate against identical driver wiring.
- The AI caps dialog was replaced by an inline form in the Usage & Limits accordion, with UTC-reset and spend-estimate explanatory copy.
- `POST /contact` and the three `dashboard.admin.contact.*` routes now run on `FormSubmissionController` with a pinned `form_key` of `contact`, keeping every URL, flash key (`contact-sent`) and existing test green while the storage underneath moves to `form_submissions`.
- `config/roles.php` gained the `manage-submissions` base permission, excluded from the editor and author catch-all roles so the universal inbox stays admin-surface-only.
- The frontend page-inventory contract gained the two new submissions pages.

### Notes

- `ContactSubmission` and the `contact_submissions` table are **retained for this release**. The model and table drop in the next release once the copy-forward has been observed in production; the notification type, routes and templates are unchanged.
- `ContactRequest` is retained unchanged as the legacy FormRequest and is pinned by a contract test against the registry's rules, so the two cannot drift.

## [0.1.85] - 2026-10-19

### Added

- AI spend cap: fractional cost tracking and cap enforcement applied across every AI path.

## [0.1.84] - 2026-10-18

### Added

- Email settings: new `TemplateCatalogSection` on `/dashboard/admin/settings/email` listing every registered notification type grouped by area, with channel, required and severity badges, the template it renders, and explicit `Not wired` / `Missing template` flags for types that have no `email_template` or point at a Blade file that does not exist. A Preview button drives the existing simulation dialog.
- Email settings: `TemplateSimulationSection` is now controllable via optional `selectedTemplate` / `onSelectTemplate` props, so selecting a catalog entry opens its preview.
- Email templates: `EmailTemplateService` exposes public `layoutView()` and `globalData()`, so notifications render through the same template + layout pipeline the settings preview uses.
- Public site: a real contact form. `POST /contact` (throttled 3/min, honeypot-guarded, silently discarded when trapped) stores a `ContactSubmission` and notifies every admin via the new required `contact.received` type.
- Notifications: new core type `contact.received` (group Contact, required, in-app + email) bound to the new `contact-received` template.
- Admin: contact message triage at `/dashboard/admin/contact` — list with status/search filters and pagination, a detail view that marks messages read, an inline status changer and destructive delete — behind a new `manage-contact` permission (Administration group).

### Changed

- Auth emails (`VerifyEmail`, `ResetPassword`, `WelcomeNewUser`) now render through `EmailTemplateService` instead of hand-rolled Blade partials.
- `user.registered` now points at the `new-user-admin` template (was `new-user`, which is the user-facing login confirmation and never received that payload).
- Registration dispatches the previously dead `user.welcome` email to the new user and `user.registered` (in-app + email) to every admin.

### Fixed

- The email settings preview was fiction for auth mail: `VerifyEmail` and `ResetPassword` rendered their own partials, so what admins previewed never matched what recipients received.
- The `welcome` template now accepts a missing token and falls back to a log-in call to action, matching self-registration where no set-password token exists.

## [0.1.34] - 2026-09-10

### Changed

- Release housekeeping: realigned `package.json` version (`0.1.22` → `0.1.34`) with `composer.json` after several composer-only bumps left them drifting. Consumer `check:core-deps` parity guards validate both are bumped together on every release; this release restores that invariant.

## [0.1.31] - 2026-09-08

### Changed

- UI: normalized focus-ring widths — `ring-3` → `ring-[3px]` across the core ui kit (accordion, bubble, button, checkbox, combobox, input-group, input-otp, input, native-select, navigation-menu, radio-group, select, switch, textarea) for consistent focus-visible/invalid rings under Tailwind v4.
- Peer deps: dropped the now-unused `vaul` (drawer migrated to `@base-ui/react/drawer`).

## [0.1.29] - 2026-09-07

### Added

- UI: new `StatusBadge` component (`Components/ui/status-badge.tsx`) with token-driven variants — `success`, `warning`, `info`, `critical`, `destructive`, plus accent variants `accentPurple`, `accentRose`, `accentCyan`, `accentPink`.
- Theme: added the status/accent color token set (`--status-success/-error/-info`, `--accent-purple/-rose/-cyan/-pink`, `--warning`) to `core.css` for both light and dark mode, mapped in `@theme inline` so the new `bg-status-*` / `bg-accent-*` utilities resolve in every consuming app.
- Settings: `SmtpSection` now matches the Project-WP nested-collapsible structure — the SMTP form is split into three collapsible groups (Sender Settings / SMTP Configuration / Advanced) rendered as nested `SettingsAccordionItem`s inside the outer accordion, with the "encrypted at rest" badge kept above the groups.
- Style guide: badges tab gained a full `StatusBadge` showcase (all variants + semantic usage) and the Color Tokens card now swatches the warning/status/accent tokens.

## [0.1.28] - 2026-09-07

### Added

- Settings: applied the Force From Email / Force From Name / Reply-To / Return-Path envelope options to actual outgoing mail (previously stored but never used). A `MessageSending` listener now mirrors the Project-WP PHPMailer behaviour: force-from overrides each message's sender when enabled, the return-path prefers "Use From Email as Return-Path" over a manually entered address, and Reply-To is added when the message has none.
- Settings: SMTP section now exposes the previously-hidden `Auto TLS`, `Use From Email as Return-Path` (with Return-Path field disabled while enabled), a password show/hide toggle, and an "encrypted at rest" badge.
- Settings: template preview now renders in a sandboxed `<iframe srcDoc>` instead of `dangerouslySetInnerHTML` (isolates email styles, safer).

### Changed

- Settings nav: renamed the "Email" menu item to "SMTP" in both `AdminSettingsNav` and legacy `SettingsNav` (Project-WP parity).

## [0.1.27] - 2026-09-07

### Fixed

- AI keys routes: static routes (`/ai-keys/test`, `/ai-keys/usage`, `/ai-keys/usage-cap`) are now registered before the parameterised `{aiProvider}` update/destroy routes, so `PUT /dashboard/account/ai-keys/usage-cap` no longer falls through to model binding (`404`).

## [0.1.26] - 2026-09-07

### Fixed

- `AiProvider::$fillable` now includes `api_key`, so the `setApiKeyAttribute()` mutator runs on mass assignment (e.g. `AiProvider::create(['api_key' => ...])`) instead of silently dropping the raw key and failing on the `api_key_encrypted` NOT NULL column.

## [0.1.25] - 2026-09-07

### Added

- AI BYOK usage caps: `ai_usage_settings` table + `AiUsageSetting` model with per-user daily request/token caps (defaults 100 requests / 250k tokens, clamped 1..100k / 1k..1B).
- `AiService` usage/rollup layer: `getDailyUsage()`, `getAllTimeUsage()`, `getUsageSnapshot()`, `exceedsCaps()`, `getCaps()`, `updateCaps()`, `recordError()`; error rows are excluded from request/token rolls but surfaced as the per-provider "last error".
- `AiHttpClient` - dependency-free HTTP client for BYOK key validation (`test()`) and direct provider calls (`send()`) with per-provider payload builders and default models.
- `AiTaskRunner` - module-facing runner for `prompt()` / `stream()` / `queue()` with per-user key injection, soft-cap enforcement, 3-attempt linear-backoff retries on `RateLimitedException`, and best-effort usage/error logging with latency.
- `ai_usage_logs.latency_ms` + `ai_usage_logs.error` columns (migration 2026_07_13_180003).
- `AiProviderController::usage()` (GET snapshot) + `usageCap()` (PUT caps) routes `dashboard.account.ai-keys.{usage,usage-cap}`.
- Account → AI Keys UI: today's usage card (request/token progress vs caps with "limit reached" badges, per-provider model/latency/last-error, all-time totals) and an Edit Caps dialog; `integrations` page passes the `aiUsageSnapshot` prop.

## [0.1.24] - 2026-09-07

### Fixed

- `FeatureController::update()` no longer disables every feature when the save payload is missing or partial. It now reads the nested `modules` map, only toggles the slugs explicitly present in the request (unknown slugs are ignored), and looks providers up via the registry's `allProviders()` map instead of a nonexistent `get()` method.
- Modules settings page now sends its toggle state as a nested `{ modules: {...} }` payload so the backend can apply it correctly; the switches update the nested `modules` form key.



### Added

- Module-management admin page (`dashboard.admin.settings.modules`) — enables/disables registered features at runtime, gated by the admin role.
- `FeatureSettingsService` — persists per-feature enabled state in the `settings` table (group `core`, key `feature_enabled.<slug>`), above the `core.features_enabled` config default.
- `FeatureRegistry::getModuleList()` — full metadata for every registered feature (enabled or not) for the modules UI; `getAll()` now aliases it.
- `FeatureRegistry::missingDependencies()` and a disabled-dependency check in `validate()` — surfaces features whose required dependency is disabled.
- `FeatureProvider::enabled()` / `onDisable()` contract methods with safe trait defaults; providers now gate their own route loading on `enabled()` so a disabled feature's routes 404.
- Core-owned notification type tier (`NotificationTypeRegistry::getCoreTypes()`) with a `required` flag that bypasses user preferences (security/account notifications always deliver).
- Notification type surface surfaced on the notifications settings page, the notification test page, and the account notification preferences UI (required types hidden from user controls).

### Changed

- `FeatureRegistry` aggregators (nav, settings, permissions, shared data, dashboard data, analytics, email templates, orphan media targets, gallery/repeater/profile/meta configs) now only consider enabled providers.
- `NotificationTypeRegistry` aggregates from core types + all feature providers with safe defaults, and core types can no longer be overridden by modules.

## [0.1.11] - Unreleased

### Added

- `package.json` with peer dependency declarations for all frontend runtime packages.
- `vite.dedupe.js` — canonical deduplication list for consuming apps' Vite config.
- `createSSRFoundationApp()` in `app.tsx` — eliminates SSR/client entry point duplication.
- `CHANGELOG.md` — this file.
- `BuildPageMapTest` — Vitest unit tests for `buildPageMap()` page resolution logic.
- `CoreConfigMergeTest` — PHPUnit integration tests for config merge and FeatureRegistry.

### Changed

- Consuming apps now import `pageConfig` from a shared `page-config.ts` instead of duplicating the config block in both `app.tsx` and `ssr.tsx`.

## [0.1.10] - 2026-08-18

### Added

- Feature registry extensibility, conformance tests, scoped blog slugs.

## [0.1.9] - 2026-08-15

### Added

- Role catalog base + app delta merge, publish.

## [0.1.8] - 2026-08-12

### Added

- Deploy marker paths, database maintenance, core version tracking.

## [0.1.7] - 2026-08-10

### Added

- Server self-deploy via scheduled `deploy:auto`.

## [0.1.6] - 2026-08-08

### Changed

- Genericized platform — branding, dead code, AI routes, formatting, templates.

## [0.1.5] - 2026-08-05

### Fixed

- Allow project-owned `Pages/Public` (opt-in page inventory).

## [0.1.4] - 2026-08-03

### Added

- Opt-in page inventory (`registerCoreTemplates` + `basePages`).

## [0.1.3] - 2026-08-01

### Added

- System health git version + core package card.

## [0.1.2] - 2026-07-30

### Fixed

- Resolve autoloader in Composer VCS installs.

## [0.1.1] - 2026-07-28

### Added

- Publish platform as standalone VCS repo.

## [0.1.0] - 2026-07-25

### Added

- Initial release as a standalone Composer package.
- Dashboard, admin, auth, blog, AI, deploy, notifications, settings, media.
- FeatureProvider contract + InteractsWithFeatures trait.
- Frontend page registry with opt-in base pages and page overrides.
- CSS theming via design tokens (`core.css`).
- CI/CD deploy pipeline with GitHub Releases.
