# Changelog

All notable changes to `nreljed/platform` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/), and this project adheres to [Semantic Versioning](https://semver.org/).

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
