# nreljed/blog — Blog Feature Package

The **blog feature** of the nreljed platform: posts, categories, tags, content
blocks, scheduled publishing, public blog pages and SEO generation.

It is a **library**, not an application: there is no `artisan`, no `.env`, no
database. Consumers install it via Composer and wire the frontend through the
pnpm workspace.

## Consumers

| Repo                     | Role                                                     |
| ------------------------ | -------------------------------------------------------- |
| `nreljed/laravel`        | pnpm workspace mono (`packages/blog` is a byte-identical mirror) |
| `nreljed/laravel.shop`   | Shop deploy target                                       |
| `nreljed/laravel.booking`| Booking deploy target                                    |

Apps opt in with `registerBlogPages()` from `@nreljed/blog/pages` (rendered
under `posts/*`). Never hand-edit a consumer's `vendor/` or `packages/` mirror.

## Package layout

- `src/` — `Nreljed\Blog` (Controllers, Models, Requests, Policies, Services,
  Console, Jobs, Observers, Routes, Database, Resources). Provider:
  `BlogServiceProvider`; feature slug `posts`.
- `database/migrations/` — the **canonical** posts/categories/tags migrations.
  Apps must not re-own these.
- `src/Resources/js/` — frontend kit (`pages.ts`, `Pages/`, `Components/`),
  exported as `./pages` and `./blog-aliases`.
- `blog.aliases.js` — vite/tsconfig alias helper, alias `@blog`.
- `src/Resources/views/` — Blade mail/pagination views.
- `scripts/frontend-boundary-check.php` — boundary fence (CI-gated).
- `docs/` — blog and lexical notes.

## Release discipline

- `composer.json` **and** `package.json` each carry a `version`; the `release`
  CI job hard-fails if either disagrees with the git tag. Never bump one only.
- Tag `vX.Y.Z` on `main` → CI creates the GitHub Release automatically and
  dispatches `blog-updated` to `laravel`, `laravel.shop` and `laravel.booking`.
  Do **not** hand-create the release.
- A package release *notifies* consumers; it never deploys anything.

## Verification

These are the exact commands CI runs (`ci.yml`). Run them before tagging.

```bash
composer install --prefer-dist --no-interaction --no-scripts --no-progress
find src tests scripts -name "*.php" -print0 | xargs -0 -n1 php -l > /dev/null
composer validate --no-check-publish
composer audit --no-interaction
php scripts/frontend-boundary-check.php
php vendor/bin/phpunit
```

## Ownership boundaries

- Keep app-specific routes, migrations and pages out of this repo — anything an
  app owns belongs in the app.
- Migrations here are canonical; do not duplicate them in a consumer.
- The frontend is a kit, not a page set: consumers opt in to pages explicitly.

## Before you push

- [ ] `composer.json` and `package.json` versions agree
- [ ] `composer validate --no-check-publish` exits `0`
- [ ] `php scripts/frontend-boundary-check.php` passes
- [ ] PHPUnit passes
- [ ] `composer.lock` is committed — it is the pin of record
