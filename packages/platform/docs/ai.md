# AI (BYOK Agents)

Build-your-own-key AI agents built on **Laravel AI** (`laravel/ai`). Users configure their own
provider keys in the account → Integrations page; agents resolve the key per user at runtime.

## Models

| Model             | Table                | Notes                                                                                                                                                                                                                                                                                          |
| ----------------- | -------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `AiProvider`      | `ai_providers`       | Per-user provider config: `provider` (slug, e.g. `openai`), `api_key_encrypted` (written through the `api_key` attribute via `Crypt::encryptString()`), `preferred_model`, `is_active`. `getDecryptedKey()`, `getMaskedKey()`, scopes `active()` / `forProvider()`, `supportedProviders()` list. |
| `AiUsageLog`      | `ai_usage_logs`      | Best-effort usage logging per request: user, agent, provider, model, `input_tokens`, `output_tokens`, `cost_cents` (fractional cents), `latency_ms`, `error`. Error rows are excluded from rollups.                                                                                             |
| `AiUsageSetting`  | `ai_usage_settings`  | One row per user (`user_id` is the primary key) holding the soft daily caps: `cap_requests`, `cap_cents` (spend in cents). The legacy `cap_tokens` column still exists but is **ignored** — never read or written.                                                                               |

## Services

- **`AiService`** — `resolveForUser(User $user, ?string $providerName = null): array` returns
  `[provider, model, key]` for the user's active/default provider (throws
  `MissingAiProviderException` when none). `logUsage(...)` records a usage row, pricing it via
  `AiPricingCatalog` when the caller does not supply a cost. `getCaps()` / `updateCaps()` /
  `exceedsCaps()` / `capMessage()` implement the spend cap (below), and
  `getUsageSnapshot()` feeds the Integrations usage UI.
- **`AiPricingCatalog`** — the single source of truth for provider rates (below).
- **`ResolvesUserProvider`** trait (agents) — `forUser($user)` + `injectProviderKey()`
  resolve and inject the BYOK key into the agent's provider config. `provider()` returns the
  SDK **config key string** for the user's driver, and `injectProviderKey()` sets
  `ai.providers.{driver}.key` — an array return from `provider()` is a *failover list*, not a
  key, so the `config()->set()` injection is required (safe: one PHP process per request).
- **`EnforcesAiCaps`** trait (agents) — `ensureWithinAiCaps($user)`, thrown from every agent
  prompt entry point so no agent can reach the SDK while over budget.
- **`LogsAiUsage`** concern — wraps agent calls, records usage best-effort (failures never
  block the request).

## Cost accounting & the spend cap

Usage is bounded by two soft daily caps per user: **requests** and **spend**. The daily
ceiling lives on `AiUsageSetting`; today's usage is derived from `ai_usage_logs`.

- **Pricing.** `AiPricingCatalog` pins integer **cents per 1M tokens**
  (input/output/cache-read) for the six paid providers — openai, anthropic, gemini, mistral,
  deepseek, xai. A provider absent from the catalog (`ollama`, or anything unknown) prices at
  `0.0`; the request cap still bounds that traffic. Rates are estimates — provider price sheets
  drift, so update this one class.
- **Fractional cents.** `cost_cents` is `decimal(14,4)`, not an integer: a small call costs a
  fraction of a cent, and rounding per event would silently zero out a day of small calls and
  make the spend cap unreachable. `AiPricingCatalog::costCents()` returns a `float`.
- **One accounting choke point.** `AiService::logUsage()` prices every event itself when the
  caller passes no `costCents`, so all writers (including `LogsAiUsage`) get identical
  accounting without changes.
- **Enforcement is triplicated on purpose**, because three paths can reach a provider:
  1. `AiTaskRunner` — `assertWithinCaps()` runs in `prompt()`, `stream()` **before**
     `prepare()` (a capped user's key is never decrypted) and in `queue()` at dispatch time.
     `InvokeAiTask` delegates back to `AiTaskRunner::prompt()`, so queued runs are covered too.
  2. `EnforcesAiCaps` — the agents' own entry points (`ChatAgent::chat()` / `streamChat()`,
     `ContentAgent::generate()` / `improve()`, `SeoAgent::generateSeo()`) call the SDK directly;
     without the trait they bypassed the cap entirely.
  3. `AiProviderController::usageCap()` — validates and persists the caps, clamped in
     `updateCaps()`.
- **Semantics.** A request's cost is unknowable until the provider answers, so the cap is a
  *soft ceiling* that trips **between** requests and can overshoot by one in-flight call.
  Defaults: `DEFAULT_CAP_REQUESTS` (100) and `DEFAULT_CAP_COST_CENTS` (500 = $5/day); the UI
  caps spend at `CAP_COST_MAX` ($1000/day). `capMessage()` renders the breached amount and is
  shared by both enforcement paths so the wording cannot drift.

## Agents

| Agent          | Class                        | Purpose                                                                                                                                               |
| -------------- | ---------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------- |
| `ChatAgent`    | `Ai/Agents/ChatAgent.php`    | Dashboard chat assistant (cost analysis, navigation, data interpretation). Implements `Agent` + `Promptable`, `chat(User, message, ?conversationId)`. |
| `ContentAgent` | `Ai/Agents/ContentAgent.php` | Content generation/rewriting for posts and products.                                                                                                  |
| `SeoAgent`     | `Ai/Agents/SeoAgent.php`     | SEO metadata generation (meta title/description/focus keyword) — drives the Blog `seo/generate` + `seo/bulk` endpoints.                               |

All agents implement `Laravel\Ai\Contracts\Agent` with the `Promptable` trait and use the
BYOK traits above. Structured output is available via `HasStructuredOutput` +
`schema(JsonSchema $schema)` where needed.

## Controller & UI

- `AiProviderController` (`/dashboard/account/integrations`) — CRUD for the user's provider
  keys, `test()` endpoint validating connectivity, `usage()` returning the usage snapshot, and
  `usage-cap` persisting the daily request + spend caps. Authorized via `AiProviderPolicy`.
- Account "Integrations" page (`dashboard/account/integrations.tsx`) — add OpenAI/Anthropic/
  etc. key, pick model, set default, test, delete. Key is masked after save. The usage panel
  shows today's requests, tokens and **spend** per provider, the progress bars against each
  cap, and a cap dialog with "Requests per day" + "Spend per day (USD)".
- SeoAgent UI lives in the Blog post form (SEO tab, "Generate" + bulk actions).

## Patterns (per `AGENTS.md` — Laravel AI SDK)

- **BYOK key resolution**: `provider()` returns the SDK **config key string** for the user's
  driver (not a `#[Provider]` attribute).
- **BYOK key injection (required)**: `injectProviderKey()` does `config()->set('ai.providers.{driver}.key', $key)`
  before prompting. An array returned from `provider()` is a **failover list** of
  provider→model pairs, not a key, so it cannot carry the BYOK key. Mutating config is safe and
  intended here — one PHP process per request.
- **Structured output**: `HasStructuredOutput` + `schema(JsonSchema $schema)`.
- **Streaming**: `$agent->stream()` for real-time responses.
- **Usage logging**: `LogsAiUsage` — best-effort, failures don't block requests.
- **Cap enforcement**: a new agent entry point must call `ensureWithinAiCaps($user)`
  (`EnforcesAiCaps`) right after `forUser($user)`; any other path to a provider must go through
  `AiTaskRunner` or call `AiService::exceedsCaps()` directly.

## Related

- [Settings](settings.md) — provider keys are per-user, not global settings.
- [Blog](https://github.com/nreljed/blog) — SEO generation consumes `SeoAgent`.
