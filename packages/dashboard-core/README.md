# dashboard-core

Shared dashboard platform for Laravel applications — dashboard, admin panel, auth, account,
notifications, settings, email, blog, AI (BYOK), and the unified deploy pipeline. Consuming
apps keep only domain-specific code on top.

## Docs

| Doc                                             | Covers                                          |
| ----------------------------------------------- | ----------------------------------------------- |
| [index.md](docs/index.md)                       | Modules, quick install, config reference        |
| [architecture.md](docs/architecture.md)         | Package layout, boot sequence, routes, services |
| [feature-provider.md](docs/feature-provider.md) | `FeatureProvider` contract + `FeatureRegistry`  |
| [deploy.md](docs/deploy.md)                     | Unified server-side deploy pipeline             |
| [commands.md](docs/commands.md)                 | Artisan commands                                |
| [notifications.md](docs/notifications.md)       | In-app, push, email channels + templates        |
| [settings.md](docs/settings.md)                 | DB-backed settings service                      |
| [blog.md](docs/blog.md)                         | Blog feature (in-core provider)                 |
| [ai.md](docs/ai.md)                             | BYOK AI agents (Laravel AI)                     |
| [frontend.md](docs/frontend.md)                 | React/Inertia frontend kit                      |

## Installation

### Development (path repository)

```json
{
  "repositories": [{ "type": "path", "url": "../laravel/packages/dashboard-core" }]
}
```

```bash
composer require nreljed/dashboard-core:@dev
```

### Production (VCS / GitHub)

```json
{ "repositories": [{ "type": "vcs", "url": "https://github.com/nreljed/dashboard-core.git" }] }
```

```bash
composer require nreljed/dashboard-core
```

The provider is auto-discovered via `extra.laravel.providers`. Publish config with
`php artisan vendor:publish --tag=core-config`.

## Frontend wiring

Add a `@dashboard` alias to `vite.config.js` + `tsconfig.json`, add the JS dir to Tailwind
`@source`, and build your page map in `app.tsx` with `registerCoreTemplates()` (see
[docs/frontend.md](docs/frontend.md)). Core never auto-registers pages.

## Tests

```bash
vendor/bin/phpunit -c packages/dashboard-core/phpunit.xml.dist
```
