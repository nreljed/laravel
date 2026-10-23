# Marketing module roadmap

Status: 5A, 5B and 5C implemented in `0.6.0`
Owner: marketing module
Last updated: 2026-10-23

This roadmap describes what the module ships today, what is still missing, and
the order in which the missing pieces should be built. It is deliberately
conservative: the module records only what it can observe, and it does not claim
attribution it cannot prove.

## Principles

- **Observed, not invented.** Delivery facts come from the publishing pipeline.
  Engagement comes from the platform. Revenue belongs to the shop.
- **Null is not zero.** A platform that does not expose a metric is "unavailable",
  not a zero. The UI must distinguish the two.
- **Human approval by default.** Publishing and spend decisions stay with a
  person. Automation may draft, schedule, and recommend.
- **Provider differences are normalised at the edge.** Adapters translate a
  platform response into the module's metric vocabulary; the reporting layer
  never learns a provider's field names.
- **One period per report.** Summary, series, platforms, campaigns, and workflows
  must describe the same date range. Lifetime totals are shown separately when
  needed.
- **Additive migrations and reversible schema.** Consumers upgrade with
  `composer update` plus `php artisan migrate`.
- **Documentation ships with the phase.** A phase is not done until its data
  model, metric definitions, permissions, and operating notes are written down.

## Shipped phases

| Phase | Outcome | Status |
|-------|---------|--------|
| 1 | Social accounts, OAuth connect, contacts, tags, segments | shipped |
| 2 | Composer, scheduling, approvals, per-account deliveries, retries | shipped |
| 3 | JPEG media upload on posts | shipped |
| 4 | Campaigns, workflow automations, delivery analytics | shipped in `0.4.x` |
| 5A | Scheduled metric ingestion and daily snapshots | implemented |
| 5B | Tracked links, UTMs, public redirects, and click events | implemented |
| 5C | Period-scoped analytics, coverage, Recharts UI, and CSV exports | shipped in `0.6.0` |

Phase 4 deliberately stops at delivery reporting. Phase 5A stores platform
metrics, Phase 5B records clicks, and Phase 5C reports engagement, click-through
and link performance over the same selected period. Revenue remains shop-owned.

## Current gaps

| Gap | Consequence |
|-----|-------------|
| No conversion ingest contract | Link clicks are reported, but the shop still owns conversions and revenue |
| Contacts are not a channel | Contacts and segments have no email or SMS send path |
| Shallow workflow engine | Two triggers, four actions, no delays, branches, or metric conditions |
| No content planning loop | No calendar, briefs, variants, experiments, or AI recommendations |
| No operational guardrails | No per-provider metric rate limits, sync alerting, or retention policy |

## Phase 5 - Measurement and attribution

**Outcome:** the module can answer "what happened after we published?" with
trustworthy, period-scoped numbers.

### 5A - Metrics ingestion

Implemented:

- `social_post_metrics` daily snapshots keyed by delivery (`social_post_target_id`)
  and `metric_date`.
- Canonical counters plus the sanitised raw provider payload.
- Sync state on deliveries (last attempt, last success, last error).
- `marketing:sync-metrics` with `--days`, `--limit`, `--platform`, `--force`,
  and `--dry-run`.
- Scheduled with `withoutOverlapping()`, disabled by default, every 6 hours when
  enabled, with a per-run cap and request pacing.
- Meta, Instagram, LinkedIn, X, and Pinterest responses normalise into one
  vocabulary. Missing values stay null; real zeroes are preserved.
- Metric coverage is recorded per delivery so the UI can say "metrics
  unavailable" instead of showing a misleading zero.

Still required before enabling in production:

- Verify provider scopes in one consumer. LinkedIn and Pinterest are the most
  likely to return unavailable metrics with the current grants.
- Run a small real backfill and confirm coverage/error counts.
- 5C now exposes the recorded coverage and freshness in the analytics UI.

### 5B - Links and attribution

Implemented:

- Tracked links with UTM parameters: `utm_source`, `utm_medium`,
  `utm_campaign`, `utm_content`, `utm_term`.
- A public redirect endpoint with stable short codes, open-redirect protection,
  rate limiting, and no raw-IP storage.
- Click events with privacy-safe fields (hashed IP, truncated user agent and
  referrer) plus aggregate counts for fast reporting.
- Per-post tracking opt-in. The original destination stays on the post; each
  delivery target gets its own tracked URL with the target platform as
  `utm_source`.

Still required:

- Define a conversion-ingest contract for the shop. Revenue remains shop-owned;
  the module stores a reference and reported value only.
- Keep attribution last-click by default. Any other model is an explicit,
  documented decision.
- Click and link performance is reported in 5C; conversions still show `0` until
  the contract exists.
- Add click retention cleanup in Phase 8.

### 5C - Analytics v2

Implemented in `0.6.0`:

- Every report is scoped to one selected period: 7, 30, or 90 days.
- Engagement, click-through, campaign, platform and tracked-link views sit next
  to delivery reporting.
- Top posts and campaign performance tables use the latest cumulative snapshot
  per delivery rather than summing daily snapshots.
- Coverage and freshness indicators show measured, pending, unavailable and
  error counts.
- The hand-rolled bar chart is replaced by the platform's shadcn
  `ChartContainer` and Recharts primitives.
- CSV export is available for posts, campaigns, and links, with spreadsheet
  formula-injection protection.
- Metric definitions and denominators are documented in
  [analytics.md](analytics.md).

**Exit criteria:** a published post has a stored metric snapshot, the dashboard
shows engagement for the same period as delivery, a tracked link records clicks,
and the documentation explains what each number means and what it does not.

Detailed implementation notes: [phase-5-measurement.md](phase-5-measurement.md).

## Phase 6 - Strategy and optimisation

**Outcome:** the module can move from a goal to a reviewed plan, then from
observed performance to a recommended next action.

### 6A - Strategy planner

- Add a marketing plan model: objective, audience, channel mix, cadence,
  content pillars, KPI targets, constraints, and approval state.
- Add a BYOK `MarketingStrategistAgent` that produces a structured plan from
  business context, active campaigns, audience segments, and historical metrics.
- Require human approval before a plan creates campaigns or posts.
- Store prompt version, model, cost, and the evidence used for each plan.

### 6B - Content calendar and briefs

- Add a calendar view over scheduled and draft posts.
- Add content briefs and reusable templates.
- Generate channel-specific variants from one brief, respecting each adapter's
  capabilities.
- Keep every generated draft editable and attributable to its brief.

### 6C - Experiments and timing

- Add A/B experiments for copy, creative, and send time.
- Compute best-time suggestions from the module's own metric history, not from
  a static template.
- Define success metrics before an experiment starts; do not retrofit them.
- Stop or promote a variant only through an explicit rule and an audit record.

### 6D - Analyst and recommendation loop

- Add a scheduled `MarketingAnalystAgent` that produces structured insights:
  period, KPIs, anomalies, hypotheses, evidence, recommended actions, and
  confidence.
- Store insights and surface them on the dashboard with an "apply" path that
  creates a draft change, never an automatic publish.
- Add weekly digest delivery through the platform notification system.

**Exit criteria:** a user can approve a plan, generate drafts, schedule them,
review an evidence-backed weekly insight, and apply a recommendation without the
module publishing or spending on its own.

## Phase 7 - Owned audience and lifecycle

**Outcome:** contacts and segments become an addressable audience instead of a
read-only list.

- Add an email provider abstraction and a newsletter/broadcast model.
- Add consent, unsubscribe, suppression, and bounce handling before the first
  send. Consent is a prerequisite, not a follow-up.
- Add lifecycle triggers: signup, purchase, inactivity, segment entry/exit.
- Add multi-step workflow steps with delays, conditions, and branch outcomes.
- Optionally add SMS/WhatsApp through the same channel abstraction.
- Add a social inbox only if replies and direct messages are in scope; it is a
  separate product surface, not a reporting feature.

## Phase 8 - Scale and governance

- Per-provider rate limits and sync budgets.
- Dead-letter handling, retry dashboards, and alerting.
- Data retention and deletion for metrics, clicks, and contact events.
- Multi-brand/workspace scoping if the host needs it.
- Ads/audience sync and spend reporting only after the organic loop is reliable.
- Permission and audit review for every new automation that can act on a
  customer's behalf.

## Sequencing

1. Phase 5A must land before Phase 6. AI recommendations without engagement data
   are generic advice.
2. Phase 5B ships after 5A; clicks are useful even before conversion ingest.
3. Phase 5C followed the first two so the UI was built on stable data.
4. Phase 6 starts with the planner and calendar; experiments come after there is
   enough history to compare.
5. Phase 7 starts with consent and email infrastructure, not with a send button.
6. Phase 8 is continuous, but rate limits and retention should be added as soon
   as metric volume justifies them.

## Documentation debt to close alongside the phases

- Add architecture, data-model, workflow-semantics, analytics-definition, and
  operations docs under `docs/`.
- Record ADRs for the attribution boundary, metric definitions, and the
  no-auto-publish rule.
- Keep README's phase table and this roadmap in sync on every release.
- The stale `MarketingServiceProvider` "next phase" wording and the `^0.1`
  install constraint in `AGENTS.md` were corrected during this documentation
  pass.

## Non-goals

- The module does not become a revenue ledger. The shop owns orders and revenue.
- The module does not auto-publish AI output by default.
- The module does not invent engagement for providers that do not expose it.
- The module does not become a full CRM. Contacts stay deliberately thin.
- Ads management is not part of Phase 5 or Phase 6.

## Open decisions

- Daily snapshots versus intraday snapshots for fast-moving accounts.
- Sync cadence and retention defaults.
- Whether clicks are tracked on the host path or a dedicated short domain.
- The shop-to-module conversion contract and its idempotency key.
- Whether a manual "Sync now" action needs its own permission.
- Which AI plan/insight outputs are safe to auto-apply; the default is none.
