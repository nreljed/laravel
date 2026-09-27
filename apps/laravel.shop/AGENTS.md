# Shop App

A **thin consumer** of the Nreljed platform. This app is a workspace member of
the `laravel` monorepo — all controllers, middleware, policies, services,
migrations, seeders, and frontend page kits live in the installed packages.

> **Full architecture reference:** read `../../AGENTS.md` at the workspace root
> for the complete package model, feature architecture, and conventions. This
> file only records what is specific to the Shop app.

## Package Model

This app is the live demo/deploy target for the **shop-module** package:

| Package     | Composer              | npm                    | Provides                                 |
| ----------- | --------------------- | ---------------------- | ---------------------------------------- |
| platform    | `nreljed/platform`    | `@nreljed/platform`    | Engine: routes, RBAC, deploy, AI, media  |
| blog        | `nreljed/blog`        | `@nreljed/blog`        | Posts CMS                                |
| shop-module | `nreljed/shop-module` | `@nreljed/shop-module` | E-commerce: products, orders, storefront |

- All PHP packages resolve from **vcs repos** (`github.com/nreljed/*`); pinned by `composer.lock` as the standalone apps are.
- Frontend resolves via **pnpm workspace symlinks** (`@nreljed/*`) — not vendor.
- **Never edit `vendor/` or `packages/*` from this app** — package changes go
  through their own repos/PRs.

## What Lives in THIS App (thin shell only)

```
app/
  Providers/
    AppServiceProvider.php                 # container bindings
    FrontendRouteServiceProvider.php       # GET / → StorefrontController override
    TelescopeServiceProvider.php
  Models/
    User.php                               # extends Nreljed\Platform\Models\User
  Console/Commands/
    GenerateDemoData.php                   # php artisan demo:generate
routes/
  web.php                                  # project-only routes (mostly empty)
database/
  migrations/                              # app-owned migrations only
```

Registration:

- `bootstrap/providers.php` — platform + blog + shop providers
- `config/features.php` — `PublicSite`, `Blog`, `Ai`, `Shop`
- `resources/js/page-config.ts` — `registerCoreTemplates()` + `registerBlogPages()`
  - `registerShopPages()` + host `localPages` + `pageOverrides`

## Workspace-Only

This app is **workspace-only**: it has no standalone git repo, no `.github/`
workflows, and no remote. It deploys as part of the workspace mono. Do not
initialize a nested `.git` here — the mono tracks this tree.

## Dev Environment

- Runs via LocalWP: the `shop-laravel` site's `{site}\app` is a directory
  junction → this workspace `apps/laravel.shop`. Edits hit
  `http://shop-laravel.local/` directly. DB is this app's sqlite.
- Rollback = delete junction + rename preserved `app.standalone` → `app`.
- `public/hot` must NOT exist (causes `ERR_CONNECTION_REFUSED`).
- Always run `npm run build` after code changes.
- CSP middleware applies in production only.

### Two copies of this app — one is served, the other one ships

`C:\Users\nrelj\Local Sites\laravel.shop` is **not a disposable copy**. It is the
standalone checkout of `github.com/nreljed/laravel.shop`, and its `main` branch
is what CI tests, tags, releases, and deploys to production. The two trees have
different jobs, and a change made in the wrong one either does not ship or does
not show up on the local site:

| Tree                        | Path                            | Owns                                                                                                                                        |
| --------------------------- | ------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------- |
| **Served tree** (this app)  | `laravel\app\apps\laravel.shop` | What you browse at `http://shop-laravel.local/`. The Local site's `{site}\app` is a junction to it. Its `vendor/` serves the local sqlite.  |
| **Deploy tree** (canonical) | `laravel.shop`                  | The app's git source of record. App-owned config, tests and `composer.lock` are authoritative **here**; CI and production follow this tree. |

So: author app changes in the **deploy tree** and commit there to ship them;
make them in the served tree to see them on `shop-laravel.local`. When the two
disagree, the deploy tree wins — the served tree is downstream of it, and a
"fix" that only exists locally describes an app no visitor reaches. In
particular `config/roles.php` and the `tests/Feature/Modules/Shop/` suite are
canonical in the deploy tree; a local-only edit there is a pending sync, not a
review finding.

Both trees run `artisan`, both look plausible, and only one is served, so confirm
which one you are in before trusting any result:

```bash
php artisan shop:env-check        # exits non-zero if this tree is not the served one
php artisan shop:env-check --json # same, for tooling
```

It prints the resolved app path, marks which local site serves it, and flags
any `nreljed/*` package installed from a local working tree rather than the
released artifact. Two traps it covers:

- **Wrong tree.** Only `apps/laravel.shop` is served. A test result from the
  deploy tree is still valid evidence for production behaviour — it runs the
  same released package — but it is not what `shop-laravel.local` is serving.
- **Wrong base.** A Composer `path` repository makes `composer update` reuse
  the existing checkout and report success, so `vendor` holds a working tree
  while the lock claims a release. `PackageLockParityTest` fails the build on
  this; the command reports it.

Frontend sources are read from `../../packages/shop-module`, not from
`vendor/nreljed/shop-module` — another pair of trees worth keeping straight.
PHP changes are authored in the separate `nreljed/shop-module` repository and
released from there.

## Commands

```bash
php artisan shop:env-check  # Verify this is the served tree + released packages
npm run build                # tsc + vite build
npm run typecheck            # tsc --noEmit
php artisan migrate          # Run migrations
php artisan test             # PHPUnit
php artisan permissions:list # List all permissions
php artisan demo:generate    # Create realistic demo data (--fresh to reset)
```

See root `../../AGENTS.md` for the full workflow, deploy pipeline, and package
dev loop (edit core clone → tag → `composer update nreljed/platform`).
