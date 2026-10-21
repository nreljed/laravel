# Changelog

All notable changes to `nreljed/marketing-module` are documented here.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and
this package adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

Nothing yet.

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
