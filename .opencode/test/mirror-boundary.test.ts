/**
 * Behavioural tests for the mirror-boundary plugin.
 *
 * Run from the repo root:
 *   bun run .opencode/test/mirror-boundary.test.ts
 *
 * Kept out of plugins/ on purpose -- OpenCode auto-loads every file in that
 * directory as a plugin.
 *
 * These exist because "the plugin loaded" is not evidence that it works. A
 * star-slash sequence inside a block comment in mirror-boundary.ts silently
 * truncated the module; the plugin still appeared in `opencode debug config`,
 * because that command only reports discovered file paths. Parsing plus a real
 * behavioural assertion is the only trustworthy check.
 */

import {
  isMirrorPath,
  relativeToWorktree,
} from "../plugins/mirror-boundary.ts"

const WORKTREE = "C:/Users/nrelj/Local Sites/laravel/app"

const cases: [string, boolean, string][] = [
  ["packages/platform/composer.json", true, "mirror file"],
  ["packages\\shop-module\\src\\A.php", true, "Windows backslash path"],
  ["packages", true, "mirror root itself"],
  ["apps/laravel.shop/composer.json", false, "apps/ is MONO-OWNED"],
  ["apps/laravel.booking/routes/web.php", false, "apps/ allowed"],
  ["app/Models/User.php", false, "app/ allowed"],
  ["resources/js/app.tsx", false, "resources/ allowed"],
  ["packagesfoo/x.php", false, "prefix must be a real path segment"],
]

let failures = 0

for (const [path, expected, label] of cases) {
  const actual = isMirrorPath(relativeToWorktree(path, WORKTREE))
  const ok = actual === expected
  if (!ok) failures++
  console.log(
    `${ok ? "PASS" : "FAIL"}  block=${String(actual).padEnd(5)} want=${String(expected).padEnd(5)} ${label}`,
  )
}

// A path outside the worktree must not be relativised into a mirror path.
const outside = relativeToWorktree("C:/other/place/packages/x.php", WORKTREE)
const outsideOk = !isMirrorPath(outside)
if (!outsideOk) failures++
console.log(
  `${outsideOk ? "PASS" : "FAIL"}  outside-worktree path is not treated as a mirror (${outside})`,
)

console.log(failures === 0 ? "\nALL PASS" : `\n${failures} FAILURE(S)`)
process.exit(failures === 0 ? 0 : 1)