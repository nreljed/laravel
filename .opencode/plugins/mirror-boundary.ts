import type { Plugin } from "@opencode-ai/plugin"

/**
 * Mirror boundary enforcement for the nreljed monorepo.
 *
 * packages/** are released-package mirrors. Their source of truth is the owning
 * repository (nreljed/platform, nreljed/blog, nreljed/shop-module,
 * nreljed/booking-module) -- never this workspace. A change authored here does
 * not ship: the next `composer update` overwrites it with the released tree.
 *
 * This is not hypothetical. Each package's composer.json drifted to claim a
 * core version the lock did not contain, and cross-app consistency CI failed on
 * a PR that had already been auto-merged.
 *
 * So writes under packages/ are blocked by default. To perform a DELIBERATE
 * mirror sync from the owning repo, set NRELJED_ALLOW_MIRROR_EDIT=1.
 */

const MIRROR_ROOT = "packages"
const ESCAPE_HATCH = "NRELJED_ALLOW_MIRROR_EDIT"

/** Tools that write to a path and therefore can create mirror drift. */
const WRITE_TOOLS = new Set(["edit", "write", "patch", "multiedit"])

/** Exported for behavioural tests; the hook below is the only caller. */
export function relativeToWorktree(filePath: string, worktree: string): string {
  const normalizedRoot = worktree.replace(/\\/g, "/").replace(/\/$/, "")
  const normalizedPath = filePath.replace(/\\/g, "/")
  if (!normalizedPath.startsWith(`${normalizedRoot}/`)) return normalizedPath
  return normalizedPath.slice(normalizedRoot.length + 1)
}

export function isMirrorPath(relativePath: string): boolean {
  return (
    relativePath === MIRROR_ROOT ||
    relativePath.startsWith(`${MIRROR_ROOT}/`)
  )
}

export const MirrorBoundaryPlugin: Plugin = async ({ client, worktree }) => {
  if (process.env[ESCAPE_HATCH] === "1") {
    await client.app.log({
      body: {
        service: "mirror-boundary",
        level: "warn",
        message: `Mirror-write guard DISABLED via ${ESCAPE_HATCH}=1`,
        extra: { worktree },
      },
    })
  }

  return {
    "tool.execute.before": async (input, output) => {
      if (!WRITE_TOOLS.has(input.tool)) return
      if (process.env[ESCAPE_HATCH] === "1") return

      const filePath: unknown = output.args?.filePath
      if (typeof filePath !== "string" || filePath === "") return

      const relativePath = relativeToWorktree(filePath, worktree)
      if (!isMirrorPath(relativePath)) return

      throw new Error(
        [
          `Blocked: ${relativePath} is a released-package mirror, not source.`,
          "",
          "packages/** mirrors are owned by their upstream repositories:",
          "  packages/platform      -> nreljed/platform",
          "  packages/blog          -> nreljed/blog",
          "  packages/shop-module   -> nreljed/shop-module",
          "  packages/booking-module-> nreljed/booking-module",
          "",
          "Author the change in the owning repository, release it, then refresh",
          "this workspace with `composer update nreljed/<pkg>`.",
          "",
          "Note apps/** is NOT blocked here, but that is NOT permission to edit",
          "it: apps/laravel.shop and apps/laravel.booking are mirrors of the",
          "standalone nreljed/laravel.shop and nreljed/laravel.booking repos,",
          "which deploy on their own. CI byte-compares apps/** against those",
          "checkouts (.github/workflows/ci.yml, job `parity`), so a fix",
          "authored here is reported as drift, not shipped. apps/** is absent",
          "from this guard only because root Pint deliberately formats it while",
          "root Pint excludes packages/**.",
          "",
          `For a deliberate mirror sync, re-run with ${ESCAPE_HATCH}=1.`,
        ].join("\n"),
      )
    },
  }
}