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

### There are two copies of this app — check before testing

`C:\Users\nrelj\Local Sites\laravel.shop` is a **standalone copy that nothing
serves**. It is not this workspace app, and results from it describe an app no
visitor reaches. Both trees run `artisan` and both look plausible, which is
exactly why it is worth ten seconds to confirm:

```bash
php artisan shop:env-check        # exits non-zero if this tree is not the served one
php artisan shop:env-check --json # same, for tooling
```

It prints the resolved app path, marks which local site serves it, and flags
any `nreljed/*` package installed from a local working tree rather than the
released artifact. Two traps it covers:

- **Wrong tree.** Only `apps/laravel.shop` (this one) is served.
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
