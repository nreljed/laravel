# Cost Breaker 360 — Documentation

> Laravel 13 + Inertia.js 3.4 + React 19 SaaS application

## Core Architecture

| Document | Description | Status |
|----------|-------------|--------|
| [Architecture Overview](core/architecture.md) | Laravel boot process, middleware stack, service providers | ✅ Current |
| [Module System](core/modules.md) | Module discovery, lifecycle, permissions, nav items | ✅ Current |
| [Route Architecture](core/routes.md) | Route registration, middleware, naming conventions | ✅ Current |
| [Authentication](core/auth.md) | Breeze auth, login/register/password, email verification | ✅ Current |
| [Authorization](core/authorization.md) | RBAC, permissions, policies, rate limiting | ✅ Current |
| [Settings](core/settings.md) | DB-driven settings system | ✅ Current |
| [Notifications](core/notifications.md) | Zero-infrastructure polling notification system | ✅ Current |
| [Email/SMTP](core/email.md) | Multi-layered email system, templates, encryption | ✅ Current |
| [Account Management](core/account.md) | Profile, email/password change, account deletion | ✅ Current |
| [Formatting & Dashboard](core/formatting.md) | FormattingService, DashboardService, stats cache | ✅ Current |

## Modules

| Module | Documents | Slug | Dependencies |
|--------|-----------|------|-------------|
| [Properties](modules/properties/index.md) | [Controllers](modules/properties/controllers.md) · [Services](modules/properties/services.md) · [Frontend](modules/properties/frontend.md) | `properties` | none |
| [Booking](modules/booking/index.md) | [Architecture](modules/booking/architecture.md) · [Controllers](modules/booking/controllers.md) · [Services](modules/booking/services.md) · [Frontend](modules/booking/frontend.md) · [Settings](modules/booking/settings.md) · [Guest Portal](modules/booking/guest-portal.md) | `bookings` | `properties` |
| [Shop](modules/shop/index.md) | [Controllers](modules/shop/controllers.md) · [Frontend](modules/shop/frontend.md) | `shop` | none |
| [Posts](modules/posts/index.md) | [Controllers](modules/posts/controllers.md) · [Frontend](modules/posts/frontend.md) | `posts` | none |

## Frontend

| Document | Description |
|----------|-------------|
| [Frontend Architecture](frontend/architecture.md) | Build tooling, module discovery, TS conventions |
| [Navigation](frontend/navigation.md) | Sidebar, nav items, host/guest mode, icons |
| [Shared Props](frontend/shared-props.md) | HandleInertiaRequests, userCapabilities, flash messages |
| [Patterns](frontend/patterns.md) | useForm, useAuth, toast, theme, Ziggy routes |
| [shadcn/ui](frontend/shadcn-ui.md) | UI component library integration |
| [React](frontend/react.md) | React 19 patterns and conventions |
| [Inertia](frontend/inertia.md) | Inertia.js v3 integration |

## Infrastructure

| Document | Description |
|----------|-------------|
| [Middleware](infrastructure/middleware.md) | Middleware inventory and configuration |
| [Artisan Commands](infrastructure/commands.md) | Custom commands reference |

## Database

| Document | Description |
|----------|-------------|
| [Schema Reference](database/schema.md) | Table definitions, relationships, column types |
| [Migration Conventions](database/migrations.md) | Naming, structure, module migrations |

## Testing

| Document | Description |
|----------|-------------|
| [Testing Infrastructure](testing/index.md) | PHPUnit setup, BootsModules trait, patterns |

## Workflow

| Document | Description |
|----------|-------------|
| [Setup Guide](workflow/setup.md) | Local development environment setup |
| [Development](workflow/development.md) | Commands, conventions, type safety |
| [CI Pipeline](workflow/ci.md) | GitHub Actions validation workflow |
| [Release](workflow/release.md) | Versioning, changelog, release process |
| [Deployment](workflow/deploy.md) | Server deploy, admin bootstrap, troubleshooting |

## Audits & Plans

| Document | Description |
|----------|-------------|
| [Audit Index](auditing/index.md) | Master reference for all audits |
| [Upgrade Plan](auditing/upgrade-plan.md) | 8-phase codebase improvement plan |
| [CRM Migration Opportunities](auditing/crm-migration-opportunities.md) | Cross-project comparison |

## Progress

| Document | Description |
|----------|-------------|
| [Progress Summary](progress-summary.md) | Development progress log |
