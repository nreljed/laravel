# platform

Shared dashboard platform for Laravel applications — dashboard, admin panel, auth, account,
notifications, settings, email, AI (BYOK), and the unified deploy pipeline. Consuming
apps keep only domain-specific code on top (public pages, domain features, custom
dashboard pages). Blog, shop and booking features ship as separate packages.

## Docs

| Doc                                             | Covers                                          |
| ----------------------------------------------- | ----------------------------------------------- |
| [index.md](docs/index.md)                       | Modules, quick install, config reference        |
| [architecture.md](docs/architecture.md)         | Package layout, boot sequence, routes, services |
| [feature-provider.md](docs/feature-provider.md) | `FeatureProvider` contract + `FeatureRegistry`  |
| [deploy.md](docs/deploy.md)                     | Unified server-side deploy pipeline             |
| [commands.md](docs/commands.md)                 | Artisan commands                                |
| [notifications.md](docs/notifications.md)   | In-app, push, email channels + templates        |
| [settings.md](docs/settings.md)             | DB-backed settings service                       |
| [ai.md](docs/ai.md)                         | BYOK AI agents (Laravel AI)                      |
| [frontend.md](docs/frontend.md)             | React/Inertia frontend kit                       |

## Installation

### Development (path repository)

```json
{
  "repositories": [{ "type": "path", "url": "C:/Users/nrelj/Local Sites/platform" }]
}
```

```bash
composer require nreljed/platform:@dev
```

### Production (VCS / GitHub)

```json
{ "repositories": [{ "type": "vcs", "url": "https://github.com/nreljed/platform.git" }] }
```

```bash
composer require nreljed/platform
```

The provider is auto-discovered via `extra.laravel.providers`. Publish config with
`php artisan vendor:publish --tag=core-config`.

## Frontend wiring

Consume the workspace package (`@nreljed/platform: "workspace:*"` in a pnpm workspace), wire
its source aliases (`@nreljed/platform` + `@nreljed/platform/*`) into `vite.config.js` +
`tsconfig.json`, add the JS dir to Tailwind `@source`, and build your page map in `app.tsx`
with `registerCoreTemplates()` (see [docs/frontend.md](docs/frontend.md)). Core never
auto-registers pages.

## Tests

```bash
vendor/bin/phpunit
```