# Changelog

All notable changes to `nreljed/marketing-module` are documented here.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and
this package adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

Nothing yet.

## [0.2.0] - 2026-10-22

### Added

- **Scheduled posts and a publishing pipeline.** Social posts are composed
  against one or more connected accounts, created as a post plus one delivery
  target per account, and published independently per target.
- `SocialPost` / `SocialPostTarget` models, factories, migrations, enums
  (`PostStatus`, `DeliveryStatus`), `SocialPostPolicy`, and the
  `dashboard.marketing.posts.*` routes.
- `PostPublisher` service and the `marketing:publish-due` command (scheduled
  every minute, `--dry-run` supported, `withoutOverlapping`) with retries
  bounded by `publishing.max_attempts`.
- Approval flow: with `publishing.approval_required` enabled, a due post is
  only published after `approved_at` is set from the row action.
- Deliveries fail gracefully when an account has no stored access token
  (`token_missing`) instead of aborting the run.
- A sixth dashboard stat for scheduled posts, plus `tablesExist`,
  `getScheduledTasks`, and `onUserDelete` wiring.
- Tests: `PostPublisherTest` (14 cases), `ResourceContractTest` coverage for the
  new resource, and a shared `BootsDatabase` concern.

### Fixed

- The Social Accounts "Connect account" header action no longer targets the
  parameterized `dashboard.marketing.accounts.connect` route (which crashed the
  index page); it points at the dashboard, which owns per-platform connect
  buttons.

## [0.1.1] - 2026-10-21

### Fixed

- **The sidebar group now survives a signed-out context.** `getNavItems()`
  returned nothing without an authenticated user, so the platform registry
  emitted the resource descriptors flat and the nav contract (title, url and
  icon per item) broke. The group is now returned whenever there is no user to
  gate on, and still withheld from a signed-in user who may not act in the
  module.

### Changed

- `publishing.approval_required` now defaults to `true` - nothing reaches a
  social audience without a human decision. Set `MARKETING_APPROVAL_REQUIRED=false`
  for straight-through publishing.

## [0.1.0] - 2026-10-21

### Added

- **Social accounts** — connect Meta, Instagram, LinkedIn, X, and Pinterest
  accounts through Socialite; tokens are encrypted in the vault, never stored
  or logged in plain text.
- **Contacts, tags, and segments** — CRUD resources for the audience a campaign
  or automation will reach.
- **Platform adapters** — `SocialChannelAdapter` implementations for Meta,
  Instagram (Instagram Login), LinkedIn, X, and Pinterest, with a shared
  `AbstractSocialAdapter` for HTTP, token hydration, and error normalisation.
- **Marketing dashboard** — the landing page, its Inertia page kit
  (`registerMarketingPages()`), and the `Megaphone` feature icon.
- **Permissions** — `marketing.accounts.manage`, `marketing.contacts.manage`,
  `marketing.tags.manage`, `marketing.segments.manage`.
