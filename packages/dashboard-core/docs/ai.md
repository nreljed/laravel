# AI (BYOK Agents)

Build-your-own-key AI agents built on **Laravel AI** (`laravel/ai`). Users configure their own
provider keys in the account → Integrations page; agents resolve the key per user at runtime.

## Models

| Model        | Table           | Notes                                                                                                                                                                                                                                                             |
| ------------ | --------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `AiProvider` | `ai_providers`  | Per-user provider config: `name` (slug, e.g. `openai`), `label`, `model`, `api_key` (encrypted via `Crypt::encryptString()`), `is_active`, `is_default`. `getDecryptedKey()`, `getMaskedKey()`, scopes `active()` / `forProvider()`, `supportedProviders()` list. |
| `AiUsageLog` | `ai_usage_logs` | Best-effort usage logging per request (user, agent, provider, tokens, model, duration).                                                                                                                                                                           |

## Services

- **`AiService`** — `resolveForUser(User $user, ?string $providerName = null): array` returns
  `[provider, model, key]` for the user's active/default provider (throws
  `MissingAiProviderException` when none). `logUsage(...)` records a usage row.
- **`ResolvesUserProvider`** trait (agents) — `forUser($user)` + `injectProviderKey()`
  resolve and inject the BYOK key into the agent's provider config. Never mutates global
  config at runtime — passes `provider:` + `key:` arrays per call (thread-safe).
- **`LogsAiUsage`** concern — wraps agent calls, records usage best-effort (failures never
  block the request).

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
  keys, `test()` endpoint validating connectivity. Authorized via `AiProviderPolicy`.
- Account "Integrations" page (`dashboard/account/integrations.tsx`) — add OpenAI/Anthropic/
  etc. key, pick model, set default, test, delete. Key is masked after save.
- SeoAgent UI lives in the Blog post form (SEO tab, "Generate" + bulk actions).

## Patterns (per `AGENTS.md` — Laravel AI SDK)

- **BYOK**: use `provider()` method (not `#[Provider]` attribute) for per-user key resolution.
- **Thread safety**: never `config()->set()` at runtime — pass `provider:` + `key:` arrays.
- **Structured output**: `HasStructuredOutput` + `schema(JsonSchema $schema)`.
- **Streaming**: `$agent->stream()` for real-time responses.
- **Usage logging**: `LogsAiUsage` — best-effort, failures don't block requests.

## Related

- [Settings](settings.md) — provider keys are per-user, not global settings.
- [Blog](blog.md) — SEO generation consumes `SeoAgent`.
