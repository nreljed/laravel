/**
 * Behavioural tests for the session-context plugin.
 *
 * Run from the repo root:
 *   bun run .opencode/test/session-context.test.ts
 *
 * Same rationale as mirror-boundary.test.ts: OpenCode reporting a plugin in
 * `opencode debug config` only proves the file was DISCOVERED. A syntactically
 * truncated module still appears there. These tests import the module and
 * exercise the hook, which is the only trustworthy check.
 *
 * The scope assertions matter as much as the hook assertion. An earlier revision
 * asserted that apps/laravel.shop was "the SERVED tree" and laravel.shop was
 * "the DEPLOY tree". That is true of the shop app and FALSE of this repo, so it
 * was actively misleading injected guidance. These checks stop it creeping back.
 */

import { SessionContextPlugin } from "../plugins/session-context.ts"

let failures = 0

function check(ok: boolean, label: string): void {
  if (!ok) failures++
  console.log(`${ok ? "PASS" : "FAIL"}  ${label}`)
}

const hooks = (await SessionContextPlugin({})) as Record<
  string,
  (input: unknown, output: unknown) => Promise<void>
>
const compacting = hooks["experimental.session.compacting"]

check(typeof compacting === "function", "hook is registered and callable")

const output = { context: ["pre-existing context"] }
await compacting({}, output)
const context = output.context as string[]
const injected = context[1] ?? ""

check(injected.length > 0, "hook appends fleet context")
check(context[0] === "pre-existing context", "existing context is preserved")

const assertions: [string, boolean][] = [
  ["mentions the platform engine", injected.includes("nreljed/platform")],
  ["mentions the blog module", injected.includes("nreljed/blog")],
  ["states this repo is the root laravel app", /root `?laravel`? app/i.test(injected)],
  ["keeps the packages/** mirror rule", injected.includes("packages/**")],
  ["keeps the composer.lock pin-of-record rule", injected.includes("composer.lock")],
  ["keeps the public/hot trap", injected.includes("public/hot")],
  ["keeps the path-repository trap", /path/i.test(injected) && /lock claims a release/i.test(injected)],
  [
    "does NOT claim apps/laravel.shop is the served tree",
    !/served tree/i.test(injected),
  ],
  [
    "does NOT hardcode the external laravel.shop deploy tree",
    !/Local Sites\/laravel\.shop/i.test(injected),
  ],
  ["does NOT direct the agent at shop:env-check", !/shop:env-check/.test(injected)],
]

for (const [label, ok] of assertions) check(ok, label)

// A non-array context must not throw -- the hook is defensive by design.
let threw = false
try {
  await compacting({}, { context: "not-an-array" })
} catch {
  threw = true
}
check(!threw, "tolerates a non-array context without throwing")

console.log(failures === 0 ? "\nALL PASS" : `\n${failures} FAILURE(S)`)
process.exit(failures === 0 ? 0 : 1)
