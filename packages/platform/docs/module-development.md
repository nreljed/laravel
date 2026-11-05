# Module Development

This document is the entry point for building a feature module that plugs into the platform.
A module is a self-contained package (controllers, models, migrations, frontend pages, permissions,
nav items) registered through `FeatureRegistry`.

## When to use PublicSite vs marketing-module

- **PublicSite** (core) is the minimal public template shipped with core: `public/welcome`,
  `public/about`, `public/services`, `public/contact`, `public/sitemap`. It is always available and
  is the right choice for a simple marketing site.
- **marketing-module** is an opt-in site builder with its own page kit under `marketing/*`. Register it
  only when you need its workflows (sync-health, builder, ...). The mono app is the reference
  implementation of opting in.

A consumer that does not register marketing must not resolve any `marketing/*` page component.

## Capability interfaces

Features implement capability interfaces under `src/Contracts/Capabilities/`. Core dispatches on
capabilities, not on feature identity, so a feature that does not implement a capability is simply
skipped for that surface. The fat `FeatureProvider` composite is retained for backwards compatibility
and is removed in v1.0.0.

Common capabilities: `HasPages`, `HasPermissions`, `HasNavItems`, `HasSharedData`, `HasRoutes`,
`HasMigrations`, `HasScheduledTasks`, `HasCommands`, `HasMedia`, `HasSettings`, `HasNotifications`,
`HasAuditEvents`, `HasDataBackup`, `HasSearch`.

## Scaffolding

- `php artisan make:module {Name}` - generates a full standalone package skeleton.
- `php artisan make:feature {Name}` - generates a single feature class inside an existing app.

## Page kit convention

Each feature ships a `register{Name}Pages()` export (e.g. `registerMarketingPages()`) returning a
`Record<string, PageImport>`. Consuming apps spread the returned map into their `pageConfig.basePages`
alongside `registerCoreTemplates()`. Core never auto-registers module pages.

## Wiring checklist (all four must move together)

1. Register the service provider (`bootstrap/providers.php`).
2. List it in `config/features.php`.
3. Spread `register{Name}Pages()` into `page-config.ts` `basePages`.
4. Call any feature-icon registration from `app.tsx`.

The mono's `resources/js/__tests__/page-config.test.ts` guards that removing the spread removes
the module's page keys.
