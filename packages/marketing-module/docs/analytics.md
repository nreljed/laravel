# Marketing analytics

Status: implemented in `0.6.0`
Parent: [roadmap.md](roadmap.md)
Related: [phase-5-measurement.md](phase-5-measurement.md)

## What the report measures

The marketing dashboard reports what the module observed. Delivery facts come
from the publishing pipeline, engagement comes from each provider's metrics
API, and tracked-link clicks come from the public redirect. Revenue and orders
remain owned by the shop and are not inferred here.

The page supports one period at a time:

| Period | Start | End |
|--------|-------|-----|
| 7 days | Today minus 6 days | End of today |
| 30 days (default) | Today minus 29 days | End of today |
| 90 days | Today minus 89 days | End of today |

Post membership uses `COALESCE(published_at, scheduled_at, created_at)`.
Delivery membership uses `COALESCE(published_at, last_attempted_at,
created_at)`. This keeps a failed delivery in the period in which it was
attempted, even when it never got a `published_at`.

## Latest snapshot rule

`social_post_metrics` stores cumulative counters, one row per delivery per day.
A post with 100 impressions yesterday and 150 today has two snapshots, not a
150-impression delta. Every report therefore reads the **latest snapshot per
delivery** and never sums the snapshot table.

The latest snapshot is attributed to the delivery's event date for the daily
series. A delivery published on Monday keeps its metrics on Monday even when the
most recent snapshot was captured on Friday.

## Metric definitions

| Metric | Definition | Denominator / note |
|--------|------------|--------------------|
| Deliveries | Delivery targets in the period | One post to one account is one delivery |
| Delivered | Targets with `status = published` | Failed, skipped, pending and publishing are not delivered |
| Delivery failures | Targets with `status = failed` | A retryable target stays failed until a later attempt succeeds |
| Success rate | Delivered / (delivered + failed) × 100 | Pending and skipped are excluded |
| Impressions | Sum of latest non-null `impressions` | `null` when no measured delivery has the counter |
| Reach | Sum of latest non-null `reach` | Provider coverage varies; not a substitute for impressions |
| Engagements | Likes + comments + shares + saves + replies + reposts + quotes + bookmarks | Uses whichever counters are non-null; `null` if every counter is null |
| Engagement rate | Engagements / impressions × 100 | `null` when impressions is null or zero |
| Clicks | Sum of latest provider-reported `clicks` | Distinct from tracked-link clicks |
| Click-through rate | Provider clicks / impressions × 100 | `null` when impressions is null or zero |
| Tracked-link clicks | Count of click events in the period | Recorded by the module's public redirect |
| Conversions | Shop-reported conversions attributed to a tracked link | Counted in the selected period by `occurred_at` |

Rates are percentages rounded to one decimal place. A missing denominator
produces `null`, not `0%`. The UI renders it as `—`.

Engagements deliberately include replies, reposts, quotes and bookmarks in
addition to the four core counters. Providers that expose only some of them
still contribute the counters they do expose; missing counters stay null.

## Coverage and freshness

Coverage is calculated over delivered targets in the selected period:

- `with_metrics`: at least one metric snapshot exists.
- `pending`: no snapshot and the sync state is pending.
- `unavailable`: the provider responded successfully but exposed no usable
  metrics.
- `errors`: the metric request failed and may be retried.
- `coverage_rate`: `with_metrics / delivered × 100`.
- `last_synced_at`: the latest successful metric sync for a delivered target.

Coverage is shown beside engagement totals because a low coverage rate means
the totals describe only part of the audience. Collection is disabled by
default with `MARKETING_METRICS_ENABLED=false`; pages can therefore show
delivery data with zero measured deliveries until a consumer enables it.

## Platforms, posts and campaigns

- **Platforms** aggregate deliveries and latest metrics by the delivery
  account's platform key.
- **Top posts** group delivery targets by post and rank by impressions,
  engagements, then delivered count. Only posts with at least one delivered
  target appear.
- **Campaigns** include campaigns with at least one post in the period. A
  campaign's deliveries and metrics are limited to those period posts.
- **Workflows** use the same period for run and failure counts. `active` and
  `total` describe the current workflow catalogue, not the period.

## Tracked links

Tracked links are opt-in per post. Each delivery target can own one short link.
The report groups `marketing_link_clicks` by link inside the period and shows:

- the stored destination URL;
- the tracked short URL;
- the UTM campaign, when present;
- click count and last click time;
- `conversions` counts shop-reported conversions attributed to the link in the period.

The redirect stores a keyed IP hash, a truncated user agent and referrer, and a
country field that is currently unused. Raw IP addresses are never stored.
Attribution is last-click by default.

## CSV exports

`GET /dashboard/marketing/analytics/export` accepts:

```text
dataset=posts|campaigns|links
days=7|30|90
```

The route requires `marketing.analytics.view`, uses the same period and metric
definitions as the page, and streams the response as
`text/csv; charset=UTF-8`.

Exports contain aggregate numbers only: no contact emails, raw IPs, tokens,
provider payloads or click metadata. String cells that begin with `=`, `+`, `-`,
`@`, a tab or a carriage return are prefixed with an apostrophe so spreadsheet
software does not execute them as formulas.

## Known limits

- Provider metric coverage is not uniform. Missing values are genuinely
  unavailable, not zero.
- Daily snapshots do not show intraday curves.
- Revenue aggregation, refunds and multi-touch attribution remain shop-owned.
- A manual "Sync now" action is not exposed; operators use
  `php artisan marketing:sync-metrics` with the normal options.
- Retention cleanup for metrics and click events is deferred to Phase 8.
