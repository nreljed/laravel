## Description

<!-- Briefly describe what this PR does -->

## Type of Change

- [ ] Bug fix (non-breaking change that fixes an issue)
- [ ] New feature (non-breaking change that adds functionality)
- [ ] Breaking change (fix or feature that changes existing behavior)
- [ ] Refactoring (no functional changes)
- [ ] Documentation update
- [ ] Dependency update
- [ ] Chore (build, CI, etc.)

## Testing

<!-- The gates below are the ones CI actually runs. Commands are the npm scripts
     from the repo root; `vendor/bin/pint` is the binary (there is no
     `php artisan pint`). -->

- [ ] `npm run check:platform-consistency` (cross-app version parity)
- [ ] `npm run check:core-deps` (core peer coverage)
- [ ] `npm run lint`
- [ ] `npm run typecheck`
- [ ] `npm run typecheck:packages` (compiles `packages/**`, which `typecheck` does not)
- [ ] `npm run format:check`
- [ ] `npm test` (Vitest, workspace packages)
- [ ] `npm run build`
- [ ] `php artisan test` (PHPUnit)
- [ ] `vendor/bin/pint --test`
- [ ] `php vendor/bin/phpstan analyse --no-progress --memory-limit=1G` (if you touched `app/**/*.php`)
- [ ] `php vendor/bin/rector process --dry-run` (if you touched `app/**/*.php`)

> `npm run check:ci` runs all of the above in one pass, plus `php artisan test --parallel --compact`.

## Boundary check

- [ ] My change stays inside the mono-owned tree (`app/`, `bootstrap/`, `config/`, `database/`, `routes/`, `resources/js/`, `tests/`). Changes to `packages/**` or `apps/**` belong in their own repository, not this PR.

## Screenshots (if applicable)

<!-- Add screenshots to help explain your changes -->

## Checklist

- [ ] My commits follow the [Conventional Commits](CONTRIBUTING.md) format
- [ ] I have updated the documentation if needed
- [ ] This PR does not introduce breaking changes (or if it does, I've noted them)
