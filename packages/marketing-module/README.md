# Marketing Module (`nreljed/marketing-module`)

A self-contained marketing-automation feature provider for the Laravel dashboard — social
accounts (OAuth), contacts, tags, audience segments, and the platform adapters that will
publish to them.

**Namespace:** `Nreljed\MarketingModule\` (PSR-4 → `src/`)
**Composer package:** `nreljed/marketing-module` (private GitHub repo, installed via VCS)
**npm package:** `@nreljed/marketing-module`
**Feature slug:** `marketing`
**Routes:** `/dashboard/marketing/*` (named `dashboard.marketing.*`)

## Status

Phase 1 — accounts, contacts, tags, segments, and the OAuth connect flow.
Phase 2 — the composer, scheduled publishing, per-account deliveries and approvals.
Phase 3 — media upload on posts.
Phase 4 — campaigns, workflow automations and delivery analytics.

| Area | State |
|------|-------|
| Social accounts + OAuth connect (Meta, Instagram, LinkedIn, X, Pinterest) | shipped |
| Contacts, tags, segments (CRUD) | shipped |
| Composer, scheduling, approvals, publishing + retries | shipped |
| Media upload on posts (up to 4 JPEG images, adapter-ready media payload) | shipped |
| Campaigns, workflow automations, analytics | shipped |

Publishing runs on the scheduler (`marketing:publish-due`, once a minute). Each post
fans out to one delivery per selected account, so a five-platform post can succeed on
four and be retried on the fifth. Retryable failures follow the configured backoff
(`marketing.publishing.retry_backoff`), and posts can be held behind an approval gate
(`marketing.publishing.approval_required`, overridable per post).

## Requirements

| Requirement | Version |
|-------------|---------|
| PHP | ^8.4 |
| laravel/framework | ^13.0 |
| nreljed/platform | ^0.1 |
| laravel/socialite | ^5.31 |
| socialiteproviders/instagram | ^5.2 |
| socialiteproviders/pinterest | ^4.3 |

## Installation

```bash
composer config repositories.marketing vcs https://github.com/nreljed/marketing-module.git
composer require nreljed/marketing-module:^0.4
php artisan migrate
php artisan db:seed --class='Nreljed\Platform\Database\Seeders\RolesAndPermissionsSeeder'
```

1. **Composer:** declare the package as a VCS repository (private; requires a GitHub token,
   e.g. `COMPOSER_AUTH`).
2. **Provider:** register `Nreljed\MarketingModule\MarketingServiceProvider` in
   `bootstrap/providers.php`.
3. **Feature registration:** add the provider class to the `providers` array in
   `config/features.php` so the shared `FeatureRegistry` aggregates its nav, permissions,
   dashboard data, and admin resources.
4. **Migrations / config:** auto-loaded — `loadMigrationsFrom(__DIR__.'/../database/migrations')`
   and `mergeConfigFrom(..., 'marketing')`.

### Upgrading to 0.4

Campaigns, workflows and analytics add three permissions. Re-run the seeder after the
update so they reach the database and the `*` roles pick them up:

```bash
php artisan migrate
php artisan db:seed --class='Nreljed\Platform\Database\Seeders\RolesAndPermissionsSeeder'
```

Without the seeder an existing admin can still reach nothing new, because permissions are
stored rows rather than config lookups.

### Frontend wiring (host)

- **Page registration:** the host imports `registerMarketingPages()` from
  `@nreljed/marketing-module/pages` and spreads it into its page map, so pages render under
  `marketing/*` Inertia component keys.
- **Icons:** call `registerMarketingFeatureIcons()` once at app bootstrap so the nav icon
  resolves from the shared feature-icon registry.
- **Vite alias:** `@nreljed/marketing-module` → the package's `src/Resources/js/` (also in
  `tsconfig.json` paths).

## Configuration

`config/marketing.php` holds the driver map, the OAuth app credentials and scopes per
platform, publishing defaults, media settings, and audience defaults.

```php
'marketing' => [
    'oauth' => [
        'apps' => [
            'instagram' => [
                'driver' => 'instagram',
                'client_id' => env('INSTAGRAM_CLIENT_ID'),
                'client_secret' => env('INSTAGRAM_CLIENT_SECRET'),
                'redirect' => env('APP_URL').'/dashboard/marketing/accounts/callback/instagram',
                'scopes' => ['instagram_business_basic', 'instagram_business_content_publish'],
            ],
        ],
    ],
],
```

Credentials set here win over a host `services.<driver>` entry, but only when they are
non-empty — an app that keeps its credentials in `services/` keeps working. The OAuth
`redirect` always points at the module's own callback.

## Permissions

| Permission | Grants |
|------------|--------|
| `marketing.accounts.manage` | connect, list, and manage social accounts |
| `marketing.contacts.manage` | manage contacts |
| `marketing.tags.manage` | manage contact tags |
| `marketing.segments.manage` | manage audience segments |
| `marketing.posts.manage` | compose, schedule, approve, and publish posts |
| `marketing.campaigns.manage` | create and edit campaign groupings |
| `marketing.workflows.manage` | create and edit audience and publishing automations |
| `marketing.analytics.view` | see delivery, campaign, and workflow reporting |

Access is enforced by policies, not by a blanket route middleware, so a user with only one
of these permissions still sees — and can reach — only that area.

## Architecture

| Piece | Role |
|-------|------|
| `MarketingServiceProvider` | the `FeatureProvider`: config, permissions, nav, resources, policies, routes, Socialite extensions |
| `SocialChannelRegistry` | resolves a platform key to its adapter |
| `SocialAuthService` | merges OAuth config, builds the Socialite driver, upserts the account |
| `TokenVault` | encrypts and masks provider tokens (`Crypt`, app key) |
| `SocialChannelAdapter` | one platform's rules: scopes, discovery, publishing, error mapping |
| `AbstractSocialAdapter` | shared HTTP, token hydration, and error normalisation |
| `PostPublisher` | due-post selection, capability gates, retries/backoff, approval gate, delivery roll-up |
| `WorkflowEngine` | evaluates active workflow rules, applies tag/status actions or webhooks, and writes a run record per outcome |
| `MarketingAnalytics` | delivery, platform, campaign, and workflow totals for the analytics page |
| `PublishDuePosts` | `marketing:publish-due` — the scheduler entry point (`--dry-run` supported) |

Tokens are encrypted before they touch the database and never leave the vault in plain
text — not in logs, not in API payloads, not in a delivery record.

## Development workflow

```bash
# After editing package PHP, re-install into the app:
composer update nreljed/marketing-module --no-scripts

# Frontend: rebuild (the app runs the production build — no `npm run dev`)
npm run build

# Quality gate (from the package directory)
php vendor/bin/phpunit
php scripts/frontend-boundary-check.php
php scripts/migration-check.php
vendor/bin/pint --test
```
