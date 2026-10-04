# Cost Breaker 360 — Documentation

> Laravel 13 + Inertia.js 3 + React 19 SaaS application

## Stack

| Document                        | Description                                    |
| ------------------------------- | ---------------------------------------------- |
| [Laravel](stack/laravel.md)     | Laravel 13 architecture, patterns, conventions |
| [Inertia.js](stack/inertia.md)  | Inertia.js 3 integration, forms, navigation    |
| [React](stack/react.md)         | React 19 patterns, hooks, file structure       |
| [shadcn/ui](stack/shadcn-ui.md) | Component library, config, conventions         |

## Core

| Document                               | Description                                                  |
| -------------------------------------- | ------------------------------------------------------------ |
| [Architecture](core/architecture.md)   | Boot process, middleware, service providers                  |
| [Features](core/features.md)           | Feature architecture, adding new features                    |
| [Dashboard](core/dashboard.md)         | Dashboard pages, services, shared props                      |
| [Routes](core/routes.md)               | Route structure, middleware, naming                          |
| [Notifications](core/notifications.md) | Notification system — architecture, API, polling, components |

## Package Architecture

| Document                                                                              | Description                                                                                 |
| ------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------- |
| [Shared Core Package](core-dashboard-package.md)                                      | Master plan: monorepo → `nreljed/dashboard-core` for Shop/Booking                           |
| [Phase 0 — Classification & Reconciliation](core-dashboard-package-phase0.md)         | Core/App classification, migration diff, User parity, npm-dep scan                          |
| [Core Standardization & Deploy (executed)](core-standardization-and-deploy-prompt.md) | Historical execution record — deploy parity + core standardization, **all done 2026-08-15** |

> **Core standardization + deploy parity are DONE (2026-08-15).** The consumer contract
> lives in [`core-dashboard-package.md`](core-dashboard-package.md) §7.1; day-to-day ops
> (secrets, releases, fork checklist) live in [`github-cli.md`](github-cli.md).

## Lexical Editor

The editor is **owned by the `nreljed/blog` package**. Full docs live in the blog repo:

- Canonical: **`nreljed/blog` → `docs/lexical/`** (start at `index.md`)
- Source: `src/Resources/js/Components/editor/` in the blog package (mirrored to `packages/blog`)

This monorepo's [`lexical/index.md`](lexical/index.md) is a stub pointing at the canonical docs; the editor is not shipped by the platform core.

## Workflow

| Document                               | Description                                                       |
| -------------------------------------- | ----------------------------------------------------------------- |
| [Setup](workflow/setup.md)             | Local dev environment, LocalWP, install                           |
| [Development](workflow/development.md) | Daily workflow, conventions, git branching                        |
| [Deployment](workflow/deploy.md)       | CI/CD pipeline, GitHub Actions, server deploy                     |
| [Runners](workflow/runners.md)         | self-hosted ⇄ GitHub-hosted switch (`RUNNER_CI`/`RUNNER_PACKAGE`) |
| [GitHub CLI](github-cli.md)            | Secrets, releases, runs, fork checklist (`gh` cheat sheet)        |
| [Testing](workflow/testing.md)         | PHPUnit, Vitest, ESLint, CI pipeline                              |

## Component Structure

```
Components/
  ui/           # shadcn/ui primitives (56 components)
  shared/       # Reusable app components
  layout/       # Layout components (sidebar, banners)
  navigation/   # Nav components (nav-logo, nav-main, nav-user, command-search)
  notifications/# Notification components
  frontend/     # Public-facing components
  account/      # Account page sections
  profile/      # Profile page sections
  settings/     # Settings page sections
  editor/       # Lexical rich-text editor (32 files, 7 subdirectories)
  blog/         # Blog-specific components
  seo/          # SEO components
```
