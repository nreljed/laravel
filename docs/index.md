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

## Lexical Editor

| Document                                | Description                                                                                       |
| --------------------------------------- | ------------------------------------------------------------------------------------------------- |
| [Architecture](lexical/architecture.md) | Plugin composition, data flow, HTML serialization, component tree                                 |
| [Custom Nodes](lexical/nodes.md)        | ImageNode, YouTubeNode, LayoutContainerNode, LayoutItemNode                                       |
| [Toolbar](lexical/toolbar.md)           | EditorToolbar, block formatting, inline formatting, color picker, insert actions                  |
| [Table](lexical/table.md)               | TableActionMenuPlugin, TableHoverActionsPlugin, TableCellResizerPlugin, column drag-reorder, sort |
| [Image](lexical/image.md)               | ImageNode, upload pipeline, resize handles, insert/edit dialogs, floating toolbar                 |
| [Layout](lexical/layout.md)             | LayoutContainerNode, LayoutItemNode, ColumnsPlugin, LayoutControlsPlugin, insert dialog           |
| [Plugins](lexical/plugins.md)           | Full plugin inventory — Lexical built-ins and custom plugins                                      |
| [Theme](lexical/theme.md)               | Tailwind CSS class mappings, dark mode, table cell styling                                        |

## Workflow

| Document                               | Description                                   |
| -------------------------------------- | --------------------------------------------- |
| [Setup](workflow/setup.md)             | Local dev environment, LocalWP, install       |
| [Development](workflow/development.md) | Daily workflow, conventions, git branching    |
| [Deployment](workflow/deploy.md)       | CI/CD pipeline, GitHub Actions, server deploy |
| [Testing](workflow/testing.md)         | PHPUnit, Vitest, ESLint, CI pipeline          |

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
