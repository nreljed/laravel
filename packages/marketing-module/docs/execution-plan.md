# Marketing module execution plan

Status: Phases 1-8 core shipped through 0.14.0; only deferred product surfaces remain
Last updated: 2026-10-25

## Completed

- Phases 1-5C shipped through v0.6.0.
- Phase 6 (core implemented):
  - Migrations for plans, briefs, experiments, insights.
  - Models: MarketingPlan, MarketingBrief, MarketingExperiment, MarketingInsight.
  - Campaign now links to a plan.
  - Permissions: marketing.plans.manage, marketing.insights.apply.
  - Admin resources: Plans, Briefs, Experiments.
  - Routes/controllers: plans, briefs, experiments CRUD; insights index/refresh/apply; calendar.
  - Insight service with rule-based recommendations and apply-to-draft-brief.
  - Insights and calendar UI pages.
  - Agents: MarketingStrategistAgent, MarketingAnalystAgent.
  - Command: marketing:generate-insights.
  - Tests: MarketingInsightsTest.
- Phase 7 (core implemented):
  - Contact consent, unsubscribe, suppression, bounce tracking.
  - Email channel abstraction (log + Laravel Mail) and BroadcastService.
  - Broadcast model, recipients, admin resource, controller, send action.
  - Public signed unsubscribe route.
  - Permission: marketing.broadcasts.manage.
- Phase 8 (core implemented):
  - Retention pruning command and config.
  - Sync alert command.
  - Existing per-provider rate limits and metric retention config.

## Completed (additional)

- Conversion ingest contract:
  - `marketing_conversions` table and `MarketingConversion` model.
  - `ConversionIngest` contract and `ConversionIngestService`.
  - Last-click attribution and idempotent ingest.
  - Analytics and CSV now report attributed conversions.
  - Documentation in `docs/conversion-ingest.md`.
  - Tests in `ConversionIngestTest`.
- SMS/WhatsApp channels:
  - `SmsChannel` and `WhatsAppChannel` contracts.
  - Log providers and config.
  - Contact SMS/WhatsApp consent and opt-out fields.
  - Multi-channel broadcasts with `channel`, `body_text`.
  - `BroadcastServiceTest`.

## Remaining

- Production SMS/WhatsApp providers when credentials are available.
- Social inbox only if replies/DMs are in scope (deliberately last).
- Ads/audience sync only after the organic loop is reliable (deliberately last).
- Multi-brand/workspace scoping only if the host needs it.
- Verify LinkedIn/Pinterest metric scopes in a consumer before enabling metrics
  there; the module records `unavailable` rather than inventing zeroes.

## Shipped after the original plan

- v0.8.0 conversion ingest; v0.9.0 SMS/WhatsApp; v0.10.0 metric conditions;
  v0.11.0 visual workflow builder; v0.12.0 AI insight analysis; v0.13.0 plan
  approval and workflow execution tests.
- v0.14.0 metric sync governance: per-platform pacing, daily budgets, dead
  letters with replay, Sync health page and workflow run retention. See
  [metric-sync-governance.md](metric-sync-governance.md).

## Phase 7 - Owned audience and lifecycle

- Add email provider abstraction (log/mailgun/ses) and broadcast model.
- Add consent, unsubscribe, suppression, bounce handling.
- Add lifecycle triggers and multi-step workflow delays/conditions/branches.
- Optional SMS/WhatsApp channel abstraction.
- Social inbox only if in scope.

## Phase 8 - Scale and governance

- Per-provider rate limits and sync budgets.
- Dead-letter handling, retry dashboards, alerting.
- Retention commands for metrics/clicks/contact events.
- Multi-brand/workspace scoping if needed.
- Ads only after organic loop is reliable.

## Release discipline

- Keep package/composer versions aligned.
- Run PHPUnit, Pint, composer validate, frontend typecheck, boundary/migration checks.
- Sync mirror to laravel/app/packages/marketing-module.
- Commit/push/tag only when authorized.
