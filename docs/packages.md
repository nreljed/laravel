# Packages & Source of Truth

Read when editing packages, updating core, touching the mirror boundary, or working on the
BYOK AI feature.

## Package Model & Source of Truth (2026-08-24)

**`nreljed/platform` is the standalone product repo and the single source of truth.**
This monorepo became a regular consumer on 2026-08-24:

- composer requires `nreljed/platform: ^0.1` from the VCS repo (was: path repo + `@dev`).
- Develop core via PRs in `nreljed/platform`; release = semver tag → GitHub Release (auto)
  → `core-updated` dispatch to all consumer apps.
- Blog is a sibling standalone package (`nreljed/blog`, slug `posts`):
  `registerBlogPages()` from `@blog/pages`, its own migrations/commands, `blog-updated`
  dispatch. Apps consume both via Composer vcs.
- Burn-in COMPLETE (2026-08-24): the old local core copy, `core-publish.yml`,
  `scripts/core-publish.php` and the `core:publish` script are deleted;
  vitest/eslint/prettier/pre-push configs, Inertia page globs, CSS sources and
  deploy-package core recording all read from `vendor/nreljed/platform` / `composer.lock`.
  Never re-create a local copy of core — package repo only.
- `composer.lock` is the pin of record — commit it whenever core updates.

## Workspace (2026-09-14 — supersedes the frontend-vendor consumption)

This repo's root is now a **pnpm workspace**: `packages/*` (platform, blog, shop-module,
booking-module) + `apps/*` (laravel.shop, laravel.booking brought in; the mono app stays at
root for now). The frontend kit is consumed as **workspace packages**
(`@nreljed/platform`, `@nreljed/blog`) symlinked from `node_modules` → `packages/*` — NOT
vendor. The single `pnpm-lock.yaml` at the root is the npm pin of record. `tsconfig.json`
`paths` map `@nreljed/platform/*` → `packages/platform/resources/js/*` and `@nreljed/blog/*`
→ `packages/blog/src/Resources/js/*` (TS can't resolve the packages' `./*` wildcard
`exports`); the packages' `.d.ts` globs are in `include` because
`packages/platform/.../types/global.d.ts` ships the ambient `route` global + the
`@inertiajs/core` PageProps augmentation the whole app relies on.

**`packages/*` are one-way mirrors of the standalone repos** (source of truth), not
independent copies — keep them in sync with `scripts/sync-package-mirror.mjs` and inspect
with `scripts/diff-package-dir.mjs` (both report missing/differ/excess). The
`packages/platform` mirror tracks the standalone platform clone
(`C:\Users\nrelj\Local Sites\platform`, vX.Y.Z tag) and the blog mirror tracks
`C:\Users\nrelj\Local Sites\blog`. Both tools reference the upstream's **released tree** —
`git archive` of its latest `v*` tag, so `.gitattributes export-ignore` is honored and the
result matches `vendor/nreljed/*` (and the `check-platform-consistency` gate). To land a change
in a mirror, commit **and tag/release** it in the standalone repo, then re-sync.

**Composer/PHP side is unchanged**: `nreljed/platform`/`nreljed/blog` still resolve from VCS
into `vendor/` (dev `path` repos are a pending increment). pnpm 11: `minimumReleaseAge: 0`
and `allowBuilds:` live **top-level in `pnpm-workspace.yaml`**; there is deliberately no
`.npmrc` and no `pnpm` field in `package.json` (pnpm 11 ignores both). The drift guard
(`scripts/check-platform-consistency.js`) reads pnpm-lock importer versions for workspace
apps and `package-lock.json` for any remaining npm apps, plus each app's `composer.lock`.

## Frontend dependency management (workspace era)

Each package's `package.json` declares `peerDependencies` for every runtime npm package it
imports (React, Inertia, Lexical, Base UI, shadcn, etc.) and consuming apps declare them as
direct dependencies. Resolution parity between apps is guaranteed by the **single root
`pnpm-lock.yaml`** (workspace members cannot drift from siblings) — the old per-app
`package-lock.json` + `vite.dedupe.js` setup is gone. To add a runtime npm dependency to a
package, update that package's `package.json` peers AND list it as a direct dependency in
every app that imports it; then `pnpm install` once at the root and commit the lockfile.
Run `pnpm workspace <name> add <pkg>` / `pnpm -w add <pkg>` to modify the root/workspace
manifests cleanly.

## Core development model (vcs install — do NOT hand-edit vendor)

Everything under `vendor/nreljed/platform` is a Composer vcs install (dist zip pinned by
`composer.lock`). It is overwritten on `composer update` — never edit it directly. Core
source lives in the standalone platform repo, cloned locally at
**`C:\Users\nrelj\Local Sites\platform`** (origin `github.com/nreljed/platform`). Dev loop:

1. Edit the **core clone**, commit + push (branch → PR).
2. Tag `vX.Y.Z` → core CI (`ci.yml`) creates the GitHub Release automatically. The publish
   step bumps **both** `composer.json` `version` and `package.json` `version` — the two must
   never drift.
3. Consumers pull: `composer update nreljed/platform && npm run build`.
4. Automated propagation: push to core `main` fires `repository_dispatch` (`core-updated`) to
   consumer repos; their `core-update.yml` opens a `composer update` PR (Shop has this; merge
   → their deploy takes over). Weekly Mon 09:00 UTC cron is the fallback; manual trigger:
   `gh workflow run core-update.yml -R <consumer>`.

- **Fast local iteration**: the vendor copy drives runtime/tests. To test core changes live
  before tagging, temporarily switch this repo's composer entry to a `path` repo,
  `composer update nreljed/platform`, and revert the entry before pushing. There is **no
  `composer reinstall` shortcut** — that only worked for the deleted path-repo model.
  Frontend consumes `vendor/...` too, so core JS edits also need the updated vendor copy.
- Consumer CI clones the private repo via `COMPOSER_AUTH` using `secrets.GH_TOKEN` — that
  token needs **Contents: Read** on `nreljed/platform`.

## AI Integration (BYOK)

Pure **Bring Your Own Key** model — zero platform AI cost. Users manage their own API keys
via Account → AI Keys. See also the `ai-sdk-development` skill and `.ai/rules/agents.md`.

### Architecture

```
vendor/nreljed/platform/src/Ai/   # Routes registered by PlatformServiceProvider (core routes/web.php)
  Agents/
    SeoAgent.php               # SEO metadata generation (structured output)
    ChatAgent.php              # Dashboard chat assistant (streaming)
    ContentAgent.php           # Content generation (structured output)
    Traits/
      ResolvesUserProvider.php # Dynamic BYOK resolution via provider() method
  Concerns/
    LogsAiUsage.php            # Best-effort usage logging trait
  Controllers/
    AiProviderController.php   # CRUD + test connection
  Exceptions/
    MissingAiProviderException.php
  Models/
    AiProvider.php             # Encrypted key storage, provider metadata
    AiUsageLog.php             # Token/cost tracking
  Policies/
    AiProviderPolicy.php       # Owner-only access
  Requests/
    StoreAiProviderRequest.php # Validated key creation
    TestAiProviderRequest.php  # Connection test validation
  Services/
    AiService.php              # Centralized provider resolution + usage logging
```

### SDK Pattern

Agent classes use the `provider()` method for dynamic per-user BYOK (not `#[Provider]`
attributes). The method returns the **driver config key as a string**; the per-user key is
injected into that provider's config via `config()->set()` (see
`ResolvesUserProvider::injectProviderKey()`). It must NOT return
`['driver' => ..., 'key' => ...]` — the SDK treats an array as a failover list, not inline
credentials:

```php
class SeoAgent implements Agent, HasStructuredOutput
{
    use Promptable, ResolvesUserProvider;

    public function provider(): string
    {
        return $this->getResolvedProvider()['driver'];
    }

    public function model(): ?string
    {
        return $this->getResolvedProvider()['model'];
    }

    // Per-user key is injected via config()->set("ai.providers.{driver}.key", ...)
    // inside injectProviderKey() before prompting.

    public function schema(JsonSchema $schema): array { ... }
}
```

### Supported Providers

The authoritative model list lives in `AiProvider::supportedProviders()`. Current catalog:

| Slug        | Name           | Models                                                            |
| ----------- | -------------- | ----------------------------------------------------------------- |
| `openai`    | OpenAI         | gpt-5.6-sol, gpt-5.6-terra, gpt-5.6-luna, gpt-5, o4-mini          |
| `anthropic` | Anthropic      | claude-opus-4-8, claude-sonnet-5, claude-haiku-4-5                |
| `gemini`    | Google Gemini  | gemini-3.6-flash, gemini-3.5-flash-lite, gemini-2.5-pro           |
| `mistral`   | Mistral        | mistral-medium-latest, mistral-small-latest, mistral-large-latest |
| `deepseek`  | DeepSeek       | deepseek-chat, deepseek-reasoner                                  |
| `xai`       | xAI (Grok)     | grok-4.5, grok-4.3, grok-4.20-reasoning                           |
| `ollama`    | Ollama (Local) | configurable                                                      |

### Key Rules

- **BYOK key injection (required)**: The Laravel AI SDK's `provider()` returns a
  `Lab|array|string` _config key_ (e.g. `'openai'`). An array return is a **failover** list
  (provider→model pairs), NOT inline credentials —
  `\Laravel\Ai\Providers\Provider::providerCredentials()` reads `key` from the provider's
  **config**. So per-user keys MUST be set via
  `config()->set("ai.providers.{$driver}.key", $key)` before prompting (see
  `ResolvesUserProvider::injectProviderKey()`). This is safe per-request in PHP; "never use
  `config()->set()`" guidance is wrong for this SDK.
- **Encrypted storage**: Keys stored via `Crypt::encryptString()`, decrypted on demand
- **Owner-only access**: `AiProviderPolicy` ensures users can only manage their own keys
- **Usage logging**: Best-effort via `LogsAiUsage` trait — failures don't block requests
