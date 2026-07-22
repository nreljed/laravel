# Testing

## Stack

| Tool         | Purpose                |
| ------------ | ---------------------- |
| PHPUnit      | PHP tests              |
| Vitest       | TypeScript/React tests |
| ESLint       | Linting                |
| Laravel Pint | PHP code style         |
| TypeScript   | Type checking          |

## Commands

```bash
php artisan test              # PHPUnit
npm run test                  # Vitest
npm run typecheck             # tsc --noEmit
npm run lint                  # ESLint
vendor/bin/pint               # Laravel Pint
npm run check:ci              # Full CI pipeline
```

## PHPUnit Config

Tests use SQLite in-memory database:

```xml
<server name="DB_CONNECTION" value="sqlite"/>
<server name="DB_DATABASE" value=":memory:"/>
```

Telescope, Pulse, and Nightwatch are disabled in tests.

## Vitest Config

- Environment: jsdom
- Setup: `resources/js/test-setup.ts`
- Coverage: v8 provider
- Excludes: `__tests__/`, `Components/ui/`

## CI Pipeline

`npm run check:ci` runs:

1. `npm run lint` — ESLint
2. `npm run typecheck` — TypeScript
3. `npm run format:check` — Prettier
4. `npm run test` — Vitest
5. `vendor/bin/pint` — Laravel Pint
6. `php artisan test` — PHPUnit
