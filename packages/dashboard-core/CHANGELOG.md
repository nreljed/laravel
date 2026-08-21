# Changelog

All notable changes to `nreljed/dashboard-core` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/), and this project adheres to [Semantic Versioning](https://semver.org/).

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

- Publish dashboard-core as standalone VCS repo.

## [0.1.0] - 2026-07-25

### Added

- Initial release as a standalone Composer package.
- Dashboard, admin, auth, blog, AI, deploy, notifications, settings, media.
- FeatureProvider contract + InteractsWithFeatures trait.
- Frontend page registry with opt-in base pages and page overrides.
- CSS theming via design tokens (`core.css`).
- CI/CD deploy pipeline with GitHub Releases.
