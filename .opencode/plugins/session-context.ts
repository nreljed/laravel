import type { Plugin } from "@opencode-ai/plugin"

/**
 * Durable context across session compaction.
 *
 * Compaction is where hard-won fleet knowledge dies. The default continuation
 * prompt summarizes "what was being done" but drops the invariants that are not
 * visible in the diff -- which package owns a file, which tree is authoritative,
 * what must never be edited in place.
 *
 * This hook ADDS those facts to the continuation context (rather than
 * replacing output.prompt, so OpenCode's own compaction instructions survive).
 * It also instructs the summarizer to carry forward real task progress.
 *
 * SCOPE: this repo is the root `laravel` app. It CONSUMES two upstream packages --
 * nreljed/platform (the engine) and nreljed/blog (the blog module). But four
 * package trees are mirrored here (shop-module and booking-module are mirrored
 * for the workspace apps, not consumed by this app), and two whole apps are
 * mirrored here as well. Mirrored is not the same as consumed, and neither is
 * the same as owned -- see the ownership section below.
 * Earlier revisions of this text described apps/laravel.shop as the served tree
 * and laravel.shop as the deploy tree; that is true of the SHOP app, not of this
 * one, and stating it here misled work on the root app.
 */

const FLEET_CONTEXT = `
## nreljed fleet invariants (do not lose these across compaction)

### This repo's role
- This is the root \`laravel\` app: a thin shell + theme consuming two upstream
  packages. It is NOT the package source.
  - \`nreljed/platform\` = the engine (routes, RBAC, deploy, notifications,
    media, shared UI kit). Clone: \`C:/Users/nrelj/Local Sites/platform\`.
  - \`nreljed/blog\` = the blog module (posts, categories, tags, content
    blocks, scheduled publishing). Clone: \`C:/Users/nrelj/Local Sites/blog\`.
- PHP side resolves from \`vendor/nreljed/*\` (Composer vcs, pinned by
  \`composer.lock\`). Frontend side resolves from the pnpm workspace mirrors
  \`packages/platform\` + \`packages/blog\` (mapped via \`tsconfig.json\` paths;
  \`vite.config.js\` deliberately has no aliases).
- The two sides must move in lockstep. A \`composer.json\` and \`package.json\`
  inside the same package must always carry the SAME \`version\`; a mismatch is
  a release bug, not a cosmetic one.

### Ownership boundaries
- \`packages/**\` are RELEASED-PACKAGE MIRRORS. Source of truth is the owning
  repo. Never author a fix there -- edit upstream, tag, then \`composer update\`.
  Mirrors must stay byte-identical to the released tree; root Pint and ESLint
  deliberately exclude \`packages/**\` so nothing silently reformats them.
- ALL SIX of these trees are upstream mirrors with standalone repos, NOT
  workspace-only code. This is the single most-repeated wrong belief in this
  repo's history; an earlier revision of this very file asserted the opposite
  and was re-injected on every compaction.
  - \`packages/shop-module\`  -> \`nreljed/shop-module\`
  - \`packages/booking-module\`-> \`nreljed/booking-module\`
  - \`apps/laravel.shop\`     -> \`nreljed/laravel.shop\`   (deploys on its own)
  - \`apps/laravel.booking\`  -> \`nreljed/laravel.booking\`
  Proof, not convention: \`.github/workflows/ci.yml\` clones BOTH standalone app
  repos and byte-compares them against \`apps/**\`, and
  \`apps/laravel.shop/composer.json\` requires \`nreljed/shop-module\` from
  \`git@github.com:nreljed/shop-module.git\`. Clones live at
  \`C:/Users/nrelj/Local Sites/{platform,blog,shop-module,booking-module,laravel.shop,laravel.booking}\`.
  So a "quick fix" authored in \`apps/**\` or in a module package does NOT ship
  -- the standalone repo deploys, and the next sync overwrites it.
- BOUNDARY NUANCE (do not "simplify" this away): the two mirror trees are
  deliberately governed DIFFERENTLY, and the difference is intentional.
  - \`packages/**\` -- excluded from root Pint AND ESLint, because they must stay
    byte-identical to the released tree and a formatter would break parity.
  - \`apps/**\` -- covered by root Pint, lint, typecheck and tests, because they
    share the root pnpm workspace and a root toolchain change can break them.
    That is coverage of a SHARED TOOLCHAIN, not ownership of their content.
  When adding a tool to the root hooks, copy the boundary from Pint for PHP
  (excludes \`packages\` only) and from lint-staged/ESLint for TS/TSX (excludes
  \`packages\` AND \`apps\`) -- never assume one shared rule for both.
- The root app itself owns: \`bootstrap/app.php\`, \`routes/web.php\`,
  \`resources/js/**\` (entry + theme), \`app/**\`. Enforced by
  \`vendor/nreljed/platform/core-manifest.json\` via \`php
  scripts/foundation-check.php\` -- run it after touching boundary files.

### Release + deploy chain
platform/blog tag -> core CI auto-release + \`repository_dispatch\` (\`core-updated\`
/ \`blog-updated\`) -> consumer \`core-update.yml\` (dispatch + weekly cron) ->
validated LOCK-ONLY PR -> push -> deploy. \`composer.lock\` is the pin of record
and must be committed. A package release notifies consumers; it never deploys.
A merged PR is not a successful sync -- check the actual run conclusions.

### Drift guards (know what they do and do not cover)
- \`node scripts/check-platform-consistency.js\` compares Composer + npm pins
  across apps AND diffs \`packages/**\` mirrors against \`vendor/nreljed/*\`. It is
  in the pre-push hook and CI. \`npm run check:core-deps\` verifies the package
  declares every runtime npm peer the app imports. Run both before pushing a
  package bump.

### Local environment traps
- \`public/hot\` must NOT exist (causes \`ERR_CONNECTION_REFUSED\`).
- Run \`npm run build\` after frontend changes; LocalWP serves the production
  build. There is no \`composer reinstall\` shortcut.
- A Composer \`path\` repository makes \`composer update\` silently reuse a
  working tree while the lock claims a release -- \`PackageLockParityTest\` fails
  CI on this. Verify with \`composer show -i\` before trusting a version bump.

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
