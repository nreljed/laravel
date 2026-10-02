import type { Plugin } from "@opencode-ai/plugin"

/**
 * Durable context across session compaction.
 *
 * Compaction is where hard-won fleet knowledge dies. The default continuation
 * prompt summarizes "what was being done" but drops the invariants that are not
 * visible in the diff -- which tree is deployed, which tree is authoritative,
 * what must never be edited in place.
 *
 * This hook ADDS those facts to the continuation context (rather than
 * replacing output.prompt, so OpenCode's own compaction instructions survive).
 * It also instructs the summarizer to carry forward real task progress.
 */

const FLEET_CONTEXT = `
## nreljed fleet invariants (do not lose these across compaction)

### Two trees, one served
- SERVED tree: \`C:/Users/nrelj/Local Sites/laravel/app/apps/laravel.shop\`.
  The Local site \`shop-laravel.local\` serves THIS tree via a junction. Its
  \`vendor/\` holds the local sqlite.
- DEPLOY tree (canonical source of record): \`C:/Users/nrelj/Local Sites/laravel.shop\`.
  CI, tags, releases and production follow THIS tree. App-owned config, tests
  and composer.lock are authoritative there.
- Verify with \`php artisan shop:env-check\` before trusting any tree.
- A change made only in the served tree describes an app no visitor reaches.

### Ownership boundaries
- \`packages/**\` are RELEASED-PACKAGE MIRRORS. Source of truth is the owning
  repo (\`C:\\Users\\nrelj\\Local Sites\\{platform,blog,shop-module,booking-module}\`).
  Never author a fix here; edit upstream, tag, then \`composer update\`.
- \`apps/laravel.shop\` and \`apps/laravel.booking\` are WORKSPACE-ONLY and
  MONO-OWNED. They are NOT separate repositories -- do not defer them to an
  upstream, and do not exclude them from root correctness gates (Pint, lint,
  typecheck, tests) without evidence.
- Root Pint excludes \`packages\` ONLY. \`apps\` is intentionally included.

### Release + deploy chain
platform tag -> core CI auto-release + \`repository_dispatch\` (core-updated) ->
consumer \`core-update.yml\` (dispatch + weekly cron) -> validated LOCK-ONLY PR ->
push -> deploy. \`composer.lock\` is the pin of record and must be committed.
A package release notifies consumers; it never deploys.

### Local environment traps
- \`public/hot\` must NOT exist (causes \`ERR_CONNECTION_REFUSED\`).
- Run \`npm run build\` after frontend changes; LocalWP serves the production build.
- A Composer \`path\` repository makes \`composer update\` silently reuse a
  working tree while the lock claims a release -- \`PackageLockParityTest\` fails
  CI on this. Verify with \`composer show -i\` or \`shop:env-check\`.

### Reporting task progress
When compacting, preserve as structured progress: the current task and its
status (completed / in progress / pending / blocked), the files actively being
changed, key decisions already made and WHY, verification commands already run
and their results, and the concrete next steps. Prefer specific file paths and
commands over prose summary. Do not drop a blocker that is still unresolved.
`.trim()

export const SessionContextPlugin: Plugin = async () => {
  return {
    "experimental.session.compacting": async (_input, output) => {
      if (Array.isArray(output.context)) {
        output.context.push(FLEET_CONTEXT)
      }
    },
  }
}