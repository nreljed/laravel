# nreljed/platform — Engine Package

The **engine** of the nreljed platform. This repository is the single source of
truth for everything consumers treat as infrastructure: routes, middleware, RBAC,
the admin/dashboard control panel, notifications, media, the AI (BYOK) feature,
the deploy pipeline, and the shared React component kit.

It is a **library**, not an application: there is no `artisan`, no `.env`, no
database. Consumers (the `laravel`, `laravel.shop`, and `laravel.booking` apps)
install it via Composer and wire the frontend through the pnpm workspace.

## Consumers

| Repo                    | Role                                                        |
| ----------------------- | ----------------------------------------------------------- |
| `nreljed/laravel`       | pnpm workspace mono: `apps/*` + `packages/*` mirrors         |
| `nreljed/laravel.shop`  | Shop deploy target                                          |
| `nreljed/laravel.booking` | Booking deploy target                                     |

In the mono, `packages/platform` is a **byte-identical mirror** of this repo.
Consumers update with `composer update nreljed/platform`; never hand-edit the
mirror.

## Release discipline

Releases originate **here**, never in a consumer.

- `composer.json` **and** `package.json` each carry a `version`, and CI
  (`release` job) hard-fails if either disagrees with the git tag. Never bump
  one and forget the other.
- Tag `vX.Y.Z` on `main` → CI creates the GitHub Release automatically and fires
  the `core-updated` `repository_dispatch` to every consumer. Do **not**
  hand-create the release; the tag pipeline owns it.
- The dispatch list comes from the `CORE_CONSUMERS` repo variable (defaults to
  the three apps above), so adding a consumer does not require editing the
  workflow.
- **Contract freeze:** CI fails if `src/Contracts/FeatureProvider.php` changes
  without a MAJOR version bump. Changing the contract is a breaking change by
  definition — bump major or don't touch it.
- A package release *notifies* consumers; it never deploys anything.

Do not bump a version for a change that turns out not to be one. An audit gap
that was never a gap gets no release.

## Verification

These are the exact commands CI runs (`ci.yml`). Run the relevant ones locally
before tagging.

```bash
# PHP
find src tests -name "*.php" -print0 | xargs -0 -n1 php -l > /dev/null
composer validate --no-check-all
composer install --prefer-dist --no-interaction --no-scripts --no-progress
php vendor/phpunit/phpunit/phpunit
vendor/bin/pint --test

# Frontend — strict flags, because consumers compile this tree under them
npm install --no-audit --no-fund
npx tsc --noEmit -p tsconfig.strict-gate.json
```

`tsconfig.strict-gate.json` replicates the gate a consumer applies. Sources must
stay strict-clean here, or every consumer update fails. Same reasoning for Pint:
consumers run `vendor/bin/pint --test` repo-wide and the package repo's own CI
does not reformat, so an unformatted file here breaks their CI.

Runners are **self-hosted** by default (`vars.RUNNER_CI`). PHP and Composer are
preinstalled; `setup-php` and the Composer cache only run on GitHub-hosted.

## Ownership boundaries

- `src/` — platform code. This is the engine: routes, controllers, services,
  models, policies, middleware, AI agents, deploy.
- `resources/js/` — the **component kit** and platform pages (`Pages/`). Apps
  consume these; they do not fork them.
- `resources/css/core.css` — design tokens. Consumers override tokens in their
  own `@layer base`; **never** edit `core.css` for a single app's tweak. If a
  theme needs a new token or component, it belongs here.
- `config/` — the canonical `core.php`, `roles.php`, `admin.php`, `features.php`,
  `media.php` catalogs. Apps may override config, not fork these files.
- `core-manifest.json` — the boundary-fence manifest (`app_owned_files`,
  forbidden paths, overridable config). Keep it accurate when adding surface.
- `core-compat.json` — declared consumer compatibility.

Do not add app-specific routes, migrations, or pages here. Anything an app owns
belongs in the app.

## Frontend page resolution

Inertia resolves pages by **lowercased** component name, and **nothing is
auto-registered**. Each consumer builds its own explicit page map in
`resources/js/page-config.ts`:

- `basePages` — the core kit this app opts into (`registerCoreTemplates()`).
- `projectPages` — namespaced app pages.
- `pageOverrides` — swaps any core-owned page for an app page (applied last, wins).

Controllers render lowercase names: `Inertia::render('dashboard/index')`,
`'public/about'`. Shipping a new core page means it becomes *available*, not
*forced* — consumers opt in.

## Feature architecture

Features are self-contained and register through `FeatureRegistry`. See
`docs/feature-architecture.md` and the `feature-architecture` skill. Each
feature declares its own permissions, nav items, and shared data; the core
permission catalog merges with the app delta and the enabled features.

## Before you push

- [ ] `composer.json` and `package.json` versions agree
- [ ] `composer validate --no-check-all` exits `0`
- [ ] `vendor/bin/pint --test` passes
- [ ] `npx tsc --noEmit -p tsconfig.strict-gate.json` passes
- [ ] PHPUnit passes
- [ ] If you touched `src/Contracts/FeatureProvider.php`, you made a MAJOR bump
- [ ] `composer.lock` is committed — it is the pin of record

## Agents

`.agents/skills/` holds repo skills; `.opencode/` holds OpenCode config, plugins,
and commands. Use `/release-package` before tagging rather than improvising the
version-bump sequence.