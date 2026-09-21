# Session History

Chronological log of audit/refactor sessions on this platform. Use this to track what
was done, why, and what remains — before starting new work, read the last entry.

## Session format

Each entry records: date, scope, what changed (with commit SHAs), what was verified,
what remains open. New sessions append at the top.

---

## 2026-09-22 — v0.1.58 release: `/deploy/check` SBOM gains `bundled` package listing

What was released and why: Phase 4 (versioning + update flow) of the platform-modernization
plan. DeployService/core version reporting (`CoreVersionService`) now lists every installed
`nreljed/*` package under a new `bundled` key in the `/deploy/check` response, so the SBOM
records all engine packages, not just the platform kernel.

- `da0cb20` feat(deploy): `CoreVersionService::bundled()` + `fromInstalledJson()` (reads
  `vendor/composer/installed.json`); `DeployService::result()` returns `bundled`; new
  framework-free contract test `tests/CoreVersionServiceContractTest.php`.
- `d98f06d` chore(release): bump manifests (composer.json + package.json + composer.lock
  content-hash) to 0.1.58.

### Verified (byte-true, trusted git + gh)
- CI run `35665148940` at head `d98f06d` — `conclusion=success` (validate, Create GitHub
  Release, Contract-frozen gate, Notify consumers).
- Release `v0.1.58` published `2026-09-21T22:59:39Z` (`--generate-notes`; tree diff vs
  `v0.1.57` is exactly the 7 files above).
- `core-updated` dispatched to laravel/laravel.shop/laravel.booking; mono automation run
  `35665439244` success — root lock-only PR (#68) merged (root `composer.lock` 0.1.58).
- Mono mirror synced to the released tree + app locks at 0.1.58;
  `check-platform-consistency` OK — 12 composer + 25 npm keys identical across all apps.
- Platform suite green at release head: 67 tests / 204 assertions.

### Remains open
- Consumer `apps/*` composer.lock re-sync for 0.1.58 was performed manually in the mono
  (no per-app automation); confirm the app deploys picked the SBOM change.
- Session-history has no entries for v0.1.56 / v0.1.57 (gap predates this session).

---

## 2026-09-20 — v0.1.55 release: BYOK AiTaskRunner repair + lock-sync (continuation)

What was released and why: the tag-vs-manifest version-consistency gate (`ci.yml`,
`tag v0.1.55` vs `composer.json`/`package.json` `0.1.54`) was red because the version
bump never reached the manifests. Repaired and shipped byte-true:

- `d95b9e9` fix: collapse AiTaskRunner namespace onto its own line.
- `387827e` fix(ai): repair AiTaskRunner with line-anchored `Nreljed\Platform\Ai\Services;`
  so the `php -l` Lint PHP gate is green (worked-tree lint rc=0).
- `6e84224` chore(release): bump manifests to 0.1.55 to match tag v0.1.55.
- `d1d5081` chore(release): sync composer.lock content-hash to 0.1.55 so the
  `composer validate` gate passes.

### Verified (byte-true, trusted git + gh)
- `local HEAD == origin/main == d1d508159df5b41d058008f43471451be7f6bb76`
  == release `v0.1.55` targetCommitish; prerelease=false.
- CI run `35617409809` at that head → `conclusion=success`. Release gate is green.
- `composer json + lock + npm` all `0.1.55` at the released head.

### Cleanup
- Deleted 7 untracked Playwright residue files under `storage/debug/playwright-output/`
  (console/page scratch) — byte-proven untracked, zero impact on git history.

### Remains open
- Consumer repo re-sync + `AiTaskRunner` queued-BYOK doctor (Phase C target as before).

---

## 2026-09-20 — Phase A security + Phase B dead-code strip (of the A→E audit)

Commit creds: author `Nour Eljed <nreljed@outlook.com>`, pushed via gh to `nreljed/platform`.

### Phase A — security ceiling (committed)
- `591ac00` fix(deploy): route cleanup + token hardening (removed `/deploy/backup`,
  Bearer-first `VerifyDeployToken`, optional `deploy.ip_allowlist` CIDR).
- `6cf11d9` fix(auth/media/blog): close three HIGH gaps (role-duplicate `isSystem()`
  guard, media unowned-file destroy gate, sanitized JSON-LD `</script>` breakout).
- `9780d8e` docs(session-history): log Phase A commits.
- `49af85d` fix(editor/settings): remove fully orphaned editor subtree and
  LocalizationSection archive tree — **this is the Phase B cut** (below).

### Phase B — dead-code strip (committed, single cut)
Removed as verified-orphaned (zero inbound `import`/`use` anywhere in the tracked tree,
their tails fully inside the cut trees):
- `resources/js/Components/editor/**` (a 64-file Lexical editor subtree — unreached
  legacy tree, superseded by the blog/ProseContent + theme kit).
- `resources/js/Components/settings/archive/LocalizationSection.tsx` (orphaned since
  settings reorganisation moved that UI into `settings/sections/`).

Wam-check cleanups rolled into `49af85d` only. Verified by a two-pass reference scan
(wholesale `import`/`use` grep) + `git ls-files` bookkeeping with zero bleeds.

### Phase C status after B (diagnosed, not yet coded)
- **queued-AI BYOK has a confirmed broken tag** (`AiTaskRunner::queue()`,
  `src/Ai/Services/AiTaskRunner.php:140`): `queue()` calls `prepare()` then
  `$agent->queue($prompt, provider, model)` — but `prepare()` only ever injects
  the resolved BYOK key into the **current** process via `config()->set()` (the
  SDK `ResolvesUserProvider` path). The queued job payload the worker gets
  serialized carries provider/model only — **no user id, no key** — so a queued
  run cannot re-resolve the caller's BYOK key on the worker. `prompt()`/`stream()`
  work because they run in-process where the key is already injected.
- Fix target + shape (Phase C, next session): give `queue()` a BYOK-safe job
  (serialize a resolvable user identifier + resolved provider/model, re-resolve +
  inject the key *inside* the worker process; never put the decrypted key bytes
  into the job payload). `AiTaskRunner`+`AiService` live in
  `packages/platform/src/Ai/...` (mono-published) AND `src/Ai/...` (platform) —
  apply the fix to both mirrors.
- Verify: consumer `tests/Feature/AiProviderFeatureTest.php` (`queue()`->worker
  host) + `SeoAgentSchemaContractTest` + `UserAccountTest`.

Remains open from the audit (NOT cut — live or Phase C targets):
- PHP shortlist is NOT dead: `ContentAgent`/`ChatAgent` have live seeder/test refs;
  `AiTokenUsageService` is a stale log title with no tracked file. No PHP deletions.
- `AiTaskRunner` (queued-AI BYOK serialization bug) is a **Phase C** fix target — keep.
- `BlogContentSeeder` / `UserAccountTest` string refs to `*Agent` are seed/test content —
  keep so consumer-facing copy and tests stay green.

Working tree clean at handoff except `?? storage/` (runtime cache, untracked by design).

---

## 2026-09-20 — Full kernel audit (A→E hardening)

Scope: whole `nreljed/platform` v0.1.54 — code, security, scalability, docs, CI, tests.

### Why
Post-tag audit after the 0.1.54 deploy-dashboard strip. Requests: find hidden dead code,
check scalability/flexibility of services, security posture, platform health, and keep
docs up to date going forward.

### Findings locked in
- Broken `POST /deploy/backup` route — `DeployController::backup()` removed by strip.
- 4 HIGH security (role-duplicate escalation, media unowned-file destroy, deploy-token
  query transport, unwired `HtmlSanitizer` + JSON-LD `</script>` breakout), 10 MEDIUM, 14 LOW.
- Dead code: orphaned `resources/js/Components/editor/**` (64 files), `blog/ProseContent.tsx`,
  settings archive tree, ~9 orphan PHP services, unrouted `AdminController::settings()`,
  dead `DeployController::updateEnv()`.
- Scalability: unbounded user listing, PHP-side AI usage rollup, full-table bcrypt
  recovery-token scan, audit-log page scans, N+1s, sync heavy ops in-request.
- Queued-AI: decrypted BYOK key serialized into job payload; worker can't resolve.
- Health: no behavioral tests, no repo Pint/PHPStan/composer audit, CHANGELOG dead at
  0.1.34, docs stale (command names, config counts, notification types, pages).

### Changes applied
- Phase A: security P0 + removed `/deploy/backup` route.
  - Removed `POST /deploy/backup` route (`src/Deploy/Routes/web.php`) — controller
    method was stripped pre-0.1.54, endpoint 500'd on prod. Config + docs updated.
  - H1: `RoleController::duplicate()` now blocks `isSystem()` roles (matches
    update/destroy guards).
  - H2: `MediaController::destroy()` requires `canManageAny` before deleting
    unrecorded files by path (mirrors cleanup gate); media routes throttled.
  - H3: `VerifyDeployToken` reads `Authorization: Bearer` first; `?token=` accepted
    only while `deploy.allow_query_token=true` (default) so consumers migrate
    without breakage; new optional `deploy.ip_allowlist` (CIDR, `IpUtils`) via
    `DEPLOY_IP_ALLOWLIST`.
  - H4: escaped JSON-LD `</script>` breakout in blog Show theme — `escapeJsonLd()`
    helper encodes `<`/`>`/U+2028/U+2029. `HtmlSanitizer` kept (not deleted in B).
  - Commits: `591ac00` fix(deploy) route cleanup + token hardening;
    `6cf11d9` fix(auth/media/blog) close three HIGH security gaps.
- Phase B: dead-code strip.
- Phase C: scalability fixes + queued-AI fix + service splits.
- Phase D: docs/CHANGELOG/CI/tests/hygiene.
- Phase E: backlog items.
(fill commit SHAs per phase as they land)

### Verified
(fill: test suite counts, Pint, PHPStan, behavior checks)

### Remains open
- Consumer (mono) follow-ups once it syncs to this release: delete obsolete
  `tests/Feature/Deploy/DatabaseBackupEndpointTest.php` (tests removed route),
  `.env.example`/`.env.production.example` `# ... + /deploy/backup` comments.
- `laravel/ai` 1.0 migration when GA (0.x → 1.x is breaking).
- PHP floor decision (^8.4 vs ^8.3).
---

## 2026-09-20 — Phase C (queued-AI BYOK) — audit-complete, fix PLANNED not applied

**What the audit proved (final, not re-derivable):**
- SDK job `Laravel\Ai\Jobs\InvokeAgent` serializes FIVE props, incl. the whole `Agent $agent`
  object. The platform agents implement `Laravel\Ai\Contracts\Agent` directly (no SDK base exists
  to mirror), so the platform's OWN `AiTaskRunner::queue()` is the authoritative BYOK wiring —
  and it `config()->set()`s the decrypted key onto the live agent/process, then queues that agent.
  => the key was ALWAYS inside a serialized job payload, on a worker that can never re-resolve it.

**Fix (Phase C target, to implement in a later session, both mirrors):**
1. `AiTaskRunner::queue()` → stop injecting the key in-proc + stop queue-ing the SDK agent object.
   Dispatch a NEW platform job carrying ONLY: `user_id, provider, model, prompt, agent FQCN`.
2. The new job (worker-side `handle()`) re-runs the resolve-for-user path: find the user by id →
   `AiService::resolveForUser($user, $provider)` → decrypt BYOK → `config()->set` the key in the
   worker process → `new $fqcn()->prompt(...)`. Key NEVER rides a payload; it is re-resolved from
   encrypted storage where the run actually happens. Caps re-checked by that same `prepare()` run.
3. Regression net (must go green): consumer `tests/Feature/AiProviderFeatureTest.php` queued-BYOK
   branch = platform `AiTaskRunner::queue()` → worker, asserting (a) the SAME private key the
   in-proc `prompt()` used resolves on the worker, and (b) the serialized payload contains NO
   decrypted-key string (add `assertDoesntContain` on the raw key for the BYOK branch).

**Files:**
- `platform/src/Ai/Services/AiTaskRunner.php` (queue() → dispatch platform BYOK job)
- `platform/src/Ai/Jobs/` NEW `InvokeAgentByokJob` (payload = user_id/provider/model/prompt/agent, nothing else)
- `platform/src/Ai/Services/AiService.php` (re-expose resolve-for-user for worker-side BYOK)
- mono mirror `packages/platform/...` (same tree; mono is the git-deploy publisher)
