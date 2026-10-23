# Changelog

All notable changes to `nreljed/marketing-module` are documented here.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and
this package adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.4.1] - 2026-10-23

### Fixed

- Creating or updating a campaign or workflow now returns to its index instead
  of re-rendering the form, so the saved row is visible immediately.
- A campaign with only an end date no longer fails the `after_or_equal`
  comparison against an empty start date.
- A workflow using the default contact-status action now submits the visible
  default (`Lead`) instead of failing validation because the select was never
  touched.
- Installation and upgrade notes now run migrations before the seeder and name
  the platform seeder explicitly.

## [0.4.0] - 2026-10-22

### Added

- **Campaigns.** Posts can be grouped under a campaign with an objective,
  status and optional window, while keeping their own schedule and deliveries.
  New `campaigns` resource (`marketing.campaigns.manage`), a nullable
  `campaign_id` on posts with `nullOnDelete`, and a campaign picker in the
  composer.
- **Workflow automations.** `WorkflowEngine` reacts to `contact_created` and
  `post_published`, optionally gated on an existing contact tag, and applies
  add/remove tag, contact-status or webhook actions. Every attempt is recorded
  in `workflow_runs` with success/failure; a failed action never fails the
  request that triggered it. New workflows resource
  (`marketing.workflows.manage`).
- **Analytics.** A `marketing/analytics` page and `MarketingAnalytics` service
  report delivery totals, success rate, per-platform breakdown, a 14-day
  delivery series, campaign performance, and recent workflow runs
  (`marketing.analytics.view`).
- Campaign and workflow counts on the marketing dashboard, an analytics link in
  the sidebar, and a `workflow_runs` health check.
- Tests: `WorkflowEngineTest` (conditions, actions, webhooks, failure logging),
  `MarketingAnalyticsTest`, and descriptor coverage for both new resources.

### Upgrade notes

- Run `php artisan migrate` for the four new migrations.
- Re-run `php artisan db:seed --class='Nreljed\Platform\Database\Seeders\RolesAndPermissionsSeeder'` so the three
  new permissions exist; `*` roles only expand at seed time.

### Changed

- The composer's publishing section gains an optional campaign selector.
- `SocialPost` exposes a `campaign` relation.
- Marketing navigation now names Analytics explicitly; campaigns and workflows
  arrive through the resource registry.

## [0.3.1] - 2026-10-22

### Fixed

- **Gallery images show their real size after reopening a post.** Normalised
  post media now carries `size`, and the edit form falls back to the stored
  file's size for media saved before the payload included it.

## [0.3.0] - 2026-10-22

### Added

- **Media upload on posts.** The composer now has a gallery field backed by
  `POST`/`DELETE` `dashboard.marketing.posts.media.*` endpoints. Uploads are
  stored as JPEG (social platforms reject WebP), recorded in the media library
  under the `social` folder, and normalised to the `{url, path, mime, title}`
  shape the adapters already consume. Posts accept up to four images.
- `PostMedia` support class for the upload profile, normalisation and the
  managed-folder check; `PostMediaController` for upload/delete.
- `SocialCapabilities` now rejects a media item whose MIME type is not in
  `allowedMimeTypes`, and video where `supportsVideo` is false, before the
  platform API ever sees the post.
- Tests: `PostMediaTest` (normalisation, URL/path handling, folder scoping,
  profile shape) and `SocialCapabilitiesTest` (MIME/video acceptance).

### Changed

- `nreljed/platform` media pipeline honours a profile's `outputExtension`
  (`jpg`/`png` now encode as JPEG/PNG instead of always WebP); responsive
  variants remain WebP-only.

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
