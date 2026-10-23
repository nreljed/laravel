# Phase 5 - Measurement and attribution

Status: 5A and 5B implemented; 5C proposed
Parent: [roadmap.md](roadmap.md)
Last updated: 2026-10-23

## Summary

Phase 4 can publish and report whether a delivery succeeded, but it cannot say
what happened afterwards. Phase 5A now ingests and stores platform metrics, and
Phase 5B records tracked-link clicks. `MarketingAnalytics` and `Analytics.tsx`
still report delivery, campaign, and workflow counts only; the engagement and
link reports arrive with 5C. Revenue remains shop-owned.

Phase 5 closes that gap:

1. **5A** stores platform metrics on a schedule.
2. **5B** tracks links, UTMs, and clicks.
3. **5C** turns the data into period-scoped reporting and replaces the current
   hand-rolled chart with the platform's shadcn chart kit.

The phase is successful when a user can open a published post, see its
engagement for a selected period, see how many tracked-link clicks it produced,
and understand exactly which numbers are missing because the platform did not
expose them.

## Current state

| Piece | Current behaviour |
|-------|-------------------|
| `SocialChannelAdapter::fetchMetrics()` | Implemented by Meta, Instagram, LinkedIn, X, Pinterest; throws on failure |
| Consumers of `fetchMetrics()` | `SocialMetricSync`; no UI yet |
| `social_post_targets` | Stores `external_post_id`, `external_url`, `published_at`, and 5A metric state |
| `marketing_links` | One tracked link per delivery target, with UTMs, short code, and click count |
| `marketing_link_clicks` | Privacy-safe click events behind the public redirect |
| `MarketingAnalytics` | Delivery, platform, campaign, and workflow totals only |
| `Analytics.tsx` | Hand-rolled bar chart; hardcoded emerald success text |
| Scheduling | `marketing:publish-due` every minute; `marketing:sync-metrics` when enabled |

Before 5A, the adapters swallowed HTTP failures and returned an empty array.
Empty was therefore ambiguous: "this platform has no metrics" and "the request
failed" looked the same. 5A removes that ambiguity by throwing
`MetricFetchException` and reserving an empty array for successful no-data.

## Goals

- Collect and persist engagement metrics for published deliveries.
- Normalise provider payloads into one documented vocabulary.
- Preserve null for unavailable metrics; never manufacture a zero.
- Track outbound links with UTMs and record clicks.
- Report delivery and engagement over the same selected period.
- Make coverage and data freshness visible in the UI.
- Keep the HTML/UI work on the platform's existing shadcn/Base UI kit.

## Non-goals

- No email, SMS, newsletter, or broadcast sending in Phase 5.
- No AI-generated strategy or recommendations in Phase 5.
- No ads management, spend reporting, or audience sync.
- No multi-touch attribution. Last-click is the default and must be labelled.
- No revenue ledger. The shop owns orders and revenue.
- No automatic publishing or automatic spend decisions.

## 5A - Metrics ingestion

### Data flow

```text
scheduler
  -> marketing:sync-metrics
    -> SocialMetricSync
      -> select published deliveries that are due for a sync
      -> resolve adapter through SocialChannelRegistry
      -> refresh account token when needed
      -> adapter->fetchMetrics(external_post_id, account)
      -> MetricNormalizer::normalize()
      -> upsert social_post_metrics (one snapshot per delivery per day)
      -> update delivery metric sync state
```

### Schema

#### `social_post_metrics`

One row per delivery per calendar day. Repeated syncs on the same day update the
row and move `captured_at` forward.

```text
id
social_post_target_id   foreign key, cascade on delete
metric_date              date
captured_at              timestamp
impressions              unsigned big integer, nullable
reach                    unsigned big integer, nullable
likes                    unsigned big integer, nullable
comments                 unsigned big integer, nullable
shares                   unsigned big integer, nullable
saves                    unsigned big integer, nullable
clicks                   unsigned big integer, nullable
video_views              unsigned big integer, nullable
replies                  unsigned big integer, nullable
reposts                  unsigned big integer, nullable
quotes                   unsigned big integer, nullable
bookmarks                unsigned big integer, nullable
profile_visits           unsigned big integer, nullable
custom                   json, nullable
raw                      json, nullable
source                   string, nullable
timestamps
unique (social_post_target_id, metric_date)
index (metric_date)
```

`metric_date` uses the application timezone. It is the date the snapshot was
taken, not the date the post was published. `raw` is the provider payload after
secrets and tokens have been stripped. `custom` holds normalised counters that do
not have a first-class column yet.

#### Delivery sync state

Add to `social_post_targets`:

```text
metrics_attempted_at     timestamp, nullable
metrics_synced_at        timestamp, nullable
metrics_status           string, default "pending"  // pending, ok, unavailable, error
metrics_error            string, nullable
```

`metrics_status` describes the latest attempt. `unavailable` means the platform
responded successfully but exposed no usable metrics for that delivery. `error`
means the request failed and may be retried.

#### Models

- `SocialPostMetric` belongs to `SocialPostTarget`.
- `SocialPostTarget` has many `SocialPostMetric` rows and exposes
  `latestMetric()`.
- `SocialPost` exposes metrics through its targets; no duplicate metric columns.

### Canonical metric vocabulary

| Canonical | Meaning | Meta | Instagram | LinkedIn | X | Pinterest |
|-----------|---------|------|-----------|----------|---|-----------|
| `impressions` | Times the content was displayed | `post_impressions` | `impressions` | not exposed | `impression_count` | known analytics key |
| `reach` | Unique accounts reached | not exposed | `reach` | not exposed | not exposed | known analytics key |
| `likes` | Likes or reactions | `post_reactions_by_type_total` | `likes` | `reaction` / `like` | `like_count` | known analytics key |
| `comments` | Comments | not exposed | `comments` | `comment` | not exposed | known analytics key |
| `shares` | Shares | not exposed | `shares` | not exposed | not exposed | known analytics key |
| `saves` | Saves or bookmarks | not exposed | `saved` | not exposed | not exposed | known analytics key |
| `clicks` | Link or post clicks | `post_clicks` | not exposed | not exposed | not exposed | known analytics key |
| `video_views` | Video views | not exposed | `video_views` | not exposed | not exposed | known analytics key |
| `replies` | Replies | not exposed | not exposed | not exposed | `reply_count` | not exposed |
| `reposts` | Reposts | not exposed | not exposed | not exposed | `retweet_count` | not exposed |
| `quotes` | Quote posts | not exposed | not exposed | not exposed | `quote_count` | not exposed |
| `bookmarks` | Bookmarks | not exposed | not exposed | not exposed | `bookmark_count` | not exposed |
| `profile_visits` | Profile visits attributable to the post | not exposed | not exposed | not exposed | not exposed | known analytics key |

Rules:

- A missing key stays `null`. Do not coerce it to `0`.
- A provider that reports reactions maps them into `likes`; do not store both.
- Unknown numeric keys go into `custom` and `raw`, not into a guessed column.
- Adapters must stop filtering out zero values. A real zero is data.
- `fetchMetrics()` must throw on transport, auth, and API errors, and return an
  empty array only when the request succeeded but the platform exposed no
  metrics. Update the contract docblock and every adapter.
- LinkedIn and Pinterest scopes must be audited before enabling sync. If a
  metric requires a scope the module does not request, record `unavailable` with
  a clear error and document the required grant.

### Derived metrics

Derived values are computed at read time, not stored:

```text
engagements = likes + comments + shares + saves
            + replies + reposts + quotes + bookmarks

engagement_rate = engagements / impressions   when impressions > 0
reach_engagement_rate = engagements / reach   when reach > 0
click_through_rate = clicks / impressions     when impressions > 0
save_rate = saves / impressions               when impressions > 0
```

Any denominator that is null or zero yields `null`, not `0`. The UI renders
`null` as `-` or "Not available", never as `0%`.

### `marketing:sync-metrics`

Signature:

```bash
php artisan marketing:sync-metrics
    {--days=30 : Only sync deliveries published within this window}
    {--limit= : Maximum deliveries to process in this run}
    {--platform= : Restrict to one platform key}
    {--force : Ignore the normal freshness window}
    {--dry-run : Report how many deliveries would be synced}
```

Selection:

- `status = published`
- `external_post_id` is not null
- `published_at >= now - days`
- `metrics_attempted_at` is null or older than the configured retry window
- for `metrics_status = ok`, `metrics_synced_at` is older than the freshness window
- ordered by `metrics_attempted_at` null first, then `published_at` ascending
- limited by `max_per_run`

Execution:

- Resolve the adapter from the account's platform.
- Refresh an expiring token through a shared account-refresh service. Extract
  the existing refresh logic from `PostPublisher` rather than duplicating it.
- Call `fetchMetrics()` once per delivery.
- Set `metrics_attempted_at = now` on every attempt, including failures.
- Normalise and upsert the daily snapshot in a transaction.
- Set `metrics_status = ok` and `metrics_synced_at = now`.
- Empty metrics: `metrics_status = unavailable`, keep `metrics_synced_at` null so
  a later run can retry, and store a short reason.
- Exception: `metrics_status = error`, store a sanitised message, and keep the
  previous snapshot intact.
- A rejected grant marks the account expired, matching `PostPublisher`.
- Never log access tokens, refresh tokens, or full provider responses.

Scheduling:

- Run every 6 hours by default, `withoutOverlapping()`.
- Add a configurable per-run cap and a small delay between provider calls.
- Start disabled in `config/marketing.php` until a consumer has verified its
  provider scopes. Enabling is a per-app decision.
- `--force` is for an operator or a manual "Sync now" action, not the scheduler.

### Configuration

```php
'metrics' => [
    'enabled' => env('MARKETING_METRICS_ENABLED', false),
    'sync_days' => (int) env('MARKETING_METRICS_SYNC_DAYS', 30),
    'sync_interval_hours' => (int) env('MARKETING_METRICS_SYNC_INTERVAL_HOURS', 6),
    'retry_after_minutes' => (int) env('MARKETING_METRICS_RETRY_AFTER_MINUTES', 60),
    'max_per_run' => (int) env('MARKETING_METRICS_MAX_PER_RUN', 100),
    'requests_per_minute' => (int) env('MARKETING_METRICS_REQUESTS_PER_MINUTE', 30),
    'retention_days' => (int) env('MARKETING_METRICS_RETENTION_DAYS', 365),
],
```

### 5A implementation notes

- Migrations: `2026_10_23_000000_create_social_post_metrics_table.php` and
  `2026_10_23_000001_add_metrics_state_to_social_post_targets_table.php`.
- Services: `MetricNormalizer`, `AccountTokenRefresher`, `SocialMetricSync`.
- Command: `SyncMarketingMetrics` (`marketing:sync-metrics`), registered by
  `MarketingServiceProvider`.
- Models: `SocialPostMetric`; `SocialPostTarget` exposes `metrics()` and
  `latestMetric()`.
- Tests cover normalisation, adapter error semantics, daily idempotency, retry
  selection, force, dry-run, unavailable data, failed-sync preservation, and
  token refresh.
- `MARKETING_METRICS_ENABLED` remains false by default. The scheduler and sync
  command are inert until a consumer enables them.
- Retention cleanup is intentionally deferred; `retention_days` is reserved for
  the Phase 8 retention command.

## 5B - Links, UTMs, and clicks

### Schema

#### `marketing_links`

```text
id
social_post_id          foreign key, nullable, cascade on delete
social_post_target_id   foreign key, nullable, cascade on delete
destination_url         text
utm_source              string, nullable
utm_medium              string, nullable
utm_campaign            string, nullable
utm_content             string, nullable
utm_term                string, nullable
short_code              string, unique
clicks_count            unsigned big integer, default 0
last_clicked_at         timestamp, nullable
is_active               boolean, default true
timestamps
unique (social_post_target_id)
```

`destination_url` is the canonical URL and is never accepted from a request at
redirect time. The redirect resolves only a stored link. Reject schemes other
than `http` and `https` when creating a link.

#### `marketing_link_clicks`

```text
id
marketing_link_id       foreign key, cascade on delete
clicked_at              timestamp, indexed
ip_hash                 string, nullable
user_agent              string, nullable
referer                 text, nullable
country                 string, nullable
timestamps
```

Do not store a raw IP address. Hash with an application-key HMAC and truncate
the user agent and referrer. Add a retention command in Phase 8.

#### Optional: `marketing_conversions`

Do not build this until the shop contract is agreed. If built:

```text
id
marketing_link_id       foreign key
external_event_id       string, unique
name                    string
value_cents             unsigned big integer, nullable
currency                char(3), nullable
occurred_at             timestamp
metadata                json, nullable
timestamps
```

The module displays these as "shop-reported conversions". It does not become
the revenue source of truth.

#### Post changes

Add to `social_posts`:

```text
track_clicks             boolean, default false
```

The flag records the author's intent even when link creation fails. The presence
of a `marketing_links` row is not a reliable substitute for the flag.

### Link creation

- Add an explicit "Track clicks" option to the post form. Do not silently
  rewrite a user's URL.
- When tracking is enabled, keep the destination on the post for editing and
  generate one tracked link per post and platform target.
- Defaults:
  - `utm_source` = platform key
  - `utm_medium` = `social`
  - `utm_campaign` = campaign slug or campaign name, sanitised
  - `utm_content` = post id plus platform key
- At publish time, the adapter receives the tracked URL in `PostContent`; the
  post record keeps the original destination. Resolve the content per delivery
  target (or inject the target's tracked URL) so `utm_source` and
  `utm_content` can differ by platform.
- If link generation fails, publishing must not fail. Publish the original URL
  and record the failure for the UI.

### 5B implementation notes

- Migrations: `2026_10_23_000002_add_track_clicks_to_social_posts_table.php`,
  `2026_10_23_000003_create_marketing_links_table.php`, and
  `2026_10_23_000004_create_marketing_link_clicks_table.php`.
- Models: `MarketingLink`, `MarketingLinkClick`; `SocialPost` exposes `links()`
  and `track_clicks`; `SocialPostTarget` exposes `link()`.
- Service: `TrackedLinkService` validates destinations, creates or reuses one
  link per delivery target, builds the public URL and UTM destination, and
  records clicks with privacy-safe fields.
- Publishing: `PostPublisher` resolves a tracked URL per target at publish time
  and logs `marketing.link_tracking_failed` without failing the delivery.
- Form: `track_clicks` is an explicit boolean; tracking requires an absolute
  `http` or `https` link.
- Config:

```php
'links' => [
    'prefix' => trim((string) env('MARKETING_LINKS_PREFIX', 'm'), '/'),
    'base_url' => env('MARKETING_LINKS_BASE_URL'),
    'code_length' => (int) env('MARKETING_LINKS_CODE_LENGTH', 8),
    'rate_limit_per_minute' => (int) env('MARKETING_LINKS_RATE_LIMIT', 60),
],
```

- Tracking remains off by default. `MARKETING_LINKS_BASE_URL` is optional and
  falls back to `app.url`; a dedicated short domain is still a deployment
  decision.
- No conversion table or ingest endpoint ships in 5B. The shop contract remains
  an open decision.
- Clicks are stored but not yet displayed; the analytics UI arrives with 5C.

### Redirect route

- Public route outside the authenticated dashboard group, e.g. `/m/{code}`.
- Configurable prefix; default `m`.
- Resolve the link, increment `clicks_count`, insert a click event, then return
  a `302` to the stored destination.
- Unknown or disabled link: `404`.
- Rate limit per IP.
- No open redirect: no destination from query parameters, no URL decoding that
  changes the host, no protocol-relative URLs.
- Do not run the redirect through Inertia.

### Attribution boundary

- Last-click by default.
- The module stores the click and, if the shop reports it, the conversion
  reference. The shop calculates order value and revenue.
- Document the attribution window and the last-click rule in the analytics
  definitions.
- Do not present correlation as causation. A click without a conversion is a
  click, not revenue.

## 5C - Analytics v2

### Backend

`AnalyticsController::index()` accepts `days` and validates it against
`[7, 30, 90]`, defaulting to 30. `MarketingAnalytics::overview(int $days = 30)`
must scope every section to that period. Remove the current mix of all-time
summary, 14-day series, and 30-day workflow totals from the main response.

Suggested response:

```php
[
    'period' => [
        'days' => 30,
        'from' => '2026-09-24',
        'to' => '2026-10-23',
    ],
    'summary' => [
        'posts', 'scheduled', 'published', 'partial', 'failed',
        'deliveries', 'delivered', 'delivery_failures', 'success_rate',
        'impressions', 'reach', 'engagements', 'clicks', 'engagement_rate',
        'click_through_rate',
        'contacts', 'campaigns', 'active_workflows',
    ],
    'coverage' => [
        'deliveries', 'with_metrics', 'unavailable', 'errors', 'last_synced_at',
    ],
    'series' => [
        ['date', 'delivered', 'impressions', 'engagements', 'clicks'],
    ],
    'platforms' => [
        ['platform', 'label', 'delivered', 'failed', 'impressions', 'engagements', 'clicks'],
    ],
    'top_posts' => [
        ['id', 'excerpt', 'platform', 'published_at', 'impressions', 'engagements', 'clicks'],
    ],
    'campaigns' => [
        ['id', 'name', 'status', 'objective', 'posts', 'delivered', 'failed',
         'impressions', 'engagements', 'clicks', 'delivery_rate'],
    ],
    'workflows' => [
        'active', 'total', 'runs', 'failures', 'recent',
    ],
    'links' => [
        ['id', 'label', 'destination', 'short_url', 'clicks', 'conversions'],
    ],
]
```

Additional rules:

- `summary` and `series` use the same `from`/`to`.
- Workflow runs use the same period as the rest of the page.
- If a lifetime total is useful, return it under a separate `lifetime` key and
  label it explicitly in the UI.
- `coverage` is mandatory. Delivery counts without metric coverage are
  misleading.
- Avoid N+1 queries. Aggregate metrics by target and post in grouped queries.
- Add a configurable `metrics.retention_days` cleanup command in Phase 8, not in
  the first release.

### Frontend

The current `Analytics.tsx` uses `Card`, `Badge`, and `Button`, but the chart is
a set of hand-positioned `div` bars and the success text is hardcoded emerald.
Replace that with the platform kit.

Components:

- `Tabs` for 7 / 30 / 90 days. The selected period is a URL query parameter so
  the view is linkable and survives a refresh.
- `ChartContainer` plus `AreaChart` or `BarChart` from `recharts`.
- `ChartTooltip` / `ChartTooltipContent`.
- `Card`, `CardHeader`, `CardContent`, `CardDescription`.
- `StatusBadge` for delivery and sync states.
- `Empty` for no-data and no-coverage states.
- `DataTable` for top posts and links when sorting or pagination is useful;
  otherwise use a semantic table with explicit `text-foreground` cells.
- `Button` with `render` for links; never `asChild`.
- `toast.add({ title, type })` for manual sync feedback; never sonner.

Chart rules:

- Use `var(--chart-1)` through `var(--chart-5)` and the `ChartConfig` helper.
- Use `text-status-success`, `text-status-error`, and `text-status-info` instead
  of `emerald`, `red`, or `blue` utilities.
- Every visible text node needs `text-foreground` or `text-muted-foreground`.
- Charts need an accessible summary; do not rely on color alone.
- Show `-` for null metrics and a coverage note, not a zero-height bar that
  looks like a real zero.
- Keep chart height predictable (`aspect-auto h-72`) and avoid layout shift.
- Do not animate data lines just for decoration.

Suggested layout:

1. Period tabs and "Sync now" action.
2. KPI cards: delivered, success rate, impressions, engagement rate, clicks.
3. Delivery and engagement trend chart.
4. Platform comparison.
5. Top posts table.
6. Campaign performance table.
7. Link performance table.
8. Workflow activity.
9. Coverage and freshness footer.

### CSV export

- Add `GET /dashboard/marketing/analytics/export` with `dataset=posts|campaigns|links`
  and `days=7|30|90`.
- Require `marketing.analytics.view`.
- Stream the response; do not build the file in memory.
- Guard against spreadsheet formula injection by prefixing cells that begin with
  `=`, `+`, `-`, or `@`.
- Export aggregate numbers only. Do not include contact emails, raw IPs, tokens,
  or provider payloads.
- Use the same period and metric definitions as the page.

### Permissions and navigation

- Reading analytics remains `marketing.analytics.view`.
- If a manual "Sync now" button is exposed, add
  `marketing.metrics.sync` with a clear description and update the seeder
  upgrade notes. Do not let a read-only analytics user trigger provider calls.
- Add `POST /dashboard/marketing/analytics/sync` behind that permission, with
  throttling and a small per-request limit.
- The scheduler runs without a user permission.

## Failure handling

| Case | Behaviour |
|------|-----------|
| Provider returns 401/403 | Mark account expired, record sanitised error, stop retrying until reconnect |
| Provider returns 429 | Record error, retry after the configured backoff; do not hammer |
| Provider returns 5xx | Retry on the next run; keep previous snapshot |
| Provider returns no metrics | `unavailable`, no zero-filled row |
| Adapter is missing | `error`, record platform key |
| Link destination is invalid | Reject at creation; never redirect |
| Click write fails | Still redirect; log the failure, never block the visitor |
| Metric upsert fails | Roll back that delivery's transaction; continue other deliveries |

## Tests

PHP:

- Metric normalisation per platform, including null, zero, unknown, and reaction
  mapping.
- `fetchMetrics()` error semantics: throws on transport/auth failure, returns
  empty on successful no-data.
- Sync command selection, freshness, `--force`, `--platform`, `--limit`, and
  `--dry-run`.
- Idempotent daily upsert; a second run updates rather than duplicates.
- Previous snapshot survives a failed sync.
- Token refresh is attempted once and account expiry is recorded on rejection.
- Period scoping for every analytics section.
- Coverage counts.
- Link creation validates scheme and generates a unique code.
- Redirect returns 302, increments the counter, records a click, and rejects
  unknown codes.
- No raw IP is stored.
- Permission checks for analytics and manual sync.

Frontend:

- Typecheck under the consumer strict gate.
- No `asChild`, no `sonner`, no hardcoded status colors.
- Chart renders with null metrics without inventing zeros.
- Period tabs request/receive the same period across all sections.
- Playwright smoke test for the analytics page and a tracked redirect.

Structural:

- `php scripts/migration-check.php`
- `php scripts/frontend-boundary-check.php`
- `vendor/bin/pint --test`

## Rollout

1. Ship migrations, models, normaliser, and sync command behind
   `MARKETING_METRICS_ENABLED=false`.
2. Verify provider scopes in one consumer. Enable metrics for that app only.
3. Run `marketing:sync-metrics --days=30 --dry-run`, then a real backfill with a
   small `--limit`.
4. Confirm coverage and error counts before enabling the scheduler.
5. Ship 5B with tracking off by default; enable per post.
6. Ship 5C with the period-scoped API and shadcn charts.
7. Document metric definitions, coverage limits, and attribution rules in
   `docs/analytics.md`.
8. Bump the module version and follow the normal release/tag/consumer-update
   process. Do not edit consumer mirrors by hand.

## Open decisions

- Daily snapshots versus intraday snapshots. Start daily; revisit if fast-moving
  accounts need intraday curves.
- Exact sync cadence and retention defaults.
- Dedicated short domain versus host path for tracked links.
- Conversion ingest contract: signed HTTP endpoint versus a package-level event
  contract.
- Whether "Sync now" is a separate permission or an operator-only command.
- Whether to display shop-reported value in Phase 5 or defer it until the shop
  contract is implemented.

## Definition of done

- A published delivery has a stored metric row and a visible sync status.
- Unavailable metrics are visibly unavailable, not zero.
- Every report section uses the selected period.
- A tracked link records clicks without storing raw IPs.
- The analytics page uses shadcn charts and platform status tokens.
- All tests and structural checks pass.
- The metric definitions, provider coverage, and attribution boundary are
  documented.
