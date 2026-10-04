# nreljed/booking-module — Booking & Properties Feature Package

The **booking and properties feature** of the nreljed platform: property
listings, availability calendar, bookings pipeline, guest portal, iCal sync and
payments.

It is a **library**, not an application: there is no `artisan`, no `.env`, no
database. Consumers install it via Composer and wire the frontend through the
pnpm workspace.

## Consumers

| Repo                       | Role                                                          |
| -------------------------- | ------------------------------------------------------------- |
| `nreljed/laravel`          | pnpm workspace mono (`packages/booking-module` is a byte-identical mirror) |
| `nreljed/laravel.booking`  | Booking deploy target                                        |

Apps opt in with `registerBookingPages()` from
`@nreljed/booking-module/booking/pages` and `registerPropertiesPages()` from
`@nreljed/booking-module/properties/pages`. Never hand-edit a consumer's
`vendor/` or `packages/` mirror.

## Package layout

- `src/Booking/` — `Nreljed\BookingModule\Booking`. Provider:
  `BookingServiceProvider`; feature slug `bookings`.
- `src/Properties/` — `Nreljed\BookingModule\Properties`. Provider:
  `PropertiesServiceProvider`; feature slug `properties`.
- `src/Database/` — migrations and seeders.
- `resources/` — frontend kit.
- `booking.aliases.js` — vite/tsconfig alias helper, aliases `@booking` and
  `@properties`; exported as `./booking-aliases`.
- `scripts/frontend-boundary-check.php` — boundary fence (CI-gated).

## Release discipline

- `composer.json` **and** `package.json` each carry a `version`; the `release`
  CI job hard-fails if either disagrees with the git tag. Never bump one only.
- Tag `vX.Y.Z` on `main` → CI creates the GitHub Release automatically, then
  workflow-dispatches `booking-update.yml` on `laravel.booking` (inputs `tag`,
  `branch`). Do **not** hand-create the release.
- A package release *notifies* the consumer; it never deploys anything.

## Verification

These are the exact commands CI runs (`ci.yml`). Run them before tagging.

```bash
composer install --prefer-dist --no-interaction --no-scripts --no-progress
find src tests -name "*.php" -print0 | xargs -0 -n1 php -l > /dev/null
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
