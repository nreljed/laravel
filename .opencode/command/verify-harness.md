---
description: Run the agent-harness gates and prove the instruction/validator layer is sound
agent: build
---

# Verify the harness

The four gates that protect the **agent harness** - `.opencode/**`, `.ai/rules/**`,
`evals/**`, and the `AGENTS.md` layer. Distinct from `/fleet-check`, which proves
the *application* is coherent. Run this after touching any config-time file.

## 1. The four gates

```bash
npm run check:context
npm run check:references
npm run check:docs
npm run check:skills
```

What each proves:

| Gate | Fails when |
| --- | --- |
| `check:context` | An ownership claim, version claim, mirror reference, or `AGENTS.md` budget/scoping rule is wrong |
| `check:references` | A `.ai/rules` frontmatter glob or a curated doc path resolves to nothing |
| `check:docs` | A maintained doc has a broken local link or a dead `#anchor` |
| `check:skills` | A skill's description no longer triggers its documented cases, or coverage is incomplete |

`check:context` reports **errors and warnings** separately. Errors block; warnings
are tracked, not fatal. Current standing: one warning - `shell` has no permission
policy in `.opencode/opencode.json`.

## 2. Plugin behaviour, not just discovery

"Plugin appears in `opencode debug config`" only proves it was found. Prove it
works:

```bash
npx vitest run .opencode/test/
```

Covers `mirror-boundary.ts` (blocks writes to mirror paths) and `session-context.ts`
(the fleet ownership context).

## 3. Prove each gate can actually fail

A check that cannot fail is worse than no check. For any gate you changed, mutate
its input and confirm it fires, then restore:

```bash
# example: break a rule glob and confirm check:references catches it
# example: point a reference at one "../" too many and confirm check:context catches it
```

Restore the file and re-run the gate to confirm green. Record which mutation fired.

## 4. Restart opencode

`.opencode/**`, `.agents/skills/**`, `opencode.json`, and agent/command files are
**config-time** - they are read once at startup and are NOT hot-reloaded. After
changing any of them, tell the user to quit and restart opencode, or the running
session keeps the stale config.

## Reporting rules

- Quote real output. Never paraphrase a failure as success.
- Distinguish "ran and passed" from "did not run".
- If a gate cannot run, say which and why - do not silently skip it.
- If you loosened an allowlist to make something pass, say so explicitly; that is
  a finding, not a fix.