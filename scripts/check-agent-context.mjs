#!/usr/bin/env node
/**
 * Agent-harness validator.
 *
 * The harness (AGENTS.md, opencode.json, skills, agents, commands, plugins) is
 * source code that ships into every session, and until now nothing tested it.
 * This repo has strong CODE-boundary enforcement -- FoundationCheck,
 * mirror-boundary.ts, check-platform-consistency.js -- and zero
 * KNOWLEDGE-boundary enforcement. The result was verified rot: two plugin test
 * suites tracked in git that no glob reached, three dead `.ai/rules` globs, and
 * an ownership claim contradicted by the CI workflow that re-injected itself on
 * every compaction.
 *
 * Node built-ins only, no npm install, so it can run in a dedicated fast CI job
 * and as a pre-commit step. Every check reports what it looked at, because a
 * validator whose scope is implicit is the same rot it is meant to prevent.
 *
 * Usage:
 *   node scripts/check-agent-context.mjs            # all checks
 *   node scripts/check-agent-context.mjs --only=skills,plugins
 *   node scripts/check-agent-context.mjs --json
 *
 * Exit 0 = no errors. Warnings never fail the run; see SEVERITY notes below.
 */

import { readdirSync, readFileSync, statSync, existsSync } from "node:fs"
import { join, dirname, extname, basename, resolve, relative, sep, isAbsolute } from "node:path"
import { fileURLToPath, pathToFileURL } from "node:url"

const ROOT = join(dirname(fileURLToPath(import.meta.url)), "..")

/** True when `abs` is ROOT or lives beneath it. */
const isInsideRepo = (abs) => {
    const rel = relative(resolve(ROOT), resolve(abs))
    return rel === "" || (!rel.startsWith("..") && !rel.startsWith(`..${sep}`) && !isAbsolute(rel))
}

const AGENTS_MD_MAX_LINES = 400
const AGENTS_MD_MAX_BYTES = 32 * 1024
const SKILL_NAME_MAX = 64
const SKILL_DESCRIPTION_MAX = 1024
const SESSION_CONTEXT_MAX_BYTES = 16 * 1024

/**
 * Severity policy.
 *
 * `error` fails the run. `warn` prints and is tracked, because a validator that
 * blocks work on a pre-existing condition gets deleted instead of fixed. The
 * AGENTS.md size budget is `warn` until the split lands in Phase 3, then flips
 * to `error` -- see the check for the note.
 */
const SEVERITY = {
    agentsSize: "warn",
}

const BUILTIN_COMMANDS = new Set(["init", "undo", "redo", "share", "help"])

/**
 * OpenCode hook / event names this validator recognises. An unrecognised key in
 * a plugin is almost always a typo or a renamed upstream event; either way the
 * hook silently never fires, which is the failure mode that bit this repo
 * before (see the mirror-boundary truncation note in .opencode/test/).
 */
const KNOWN_EVENTS = new Set([
    "tool.execute.before",
    "tool.execute.after",
    "event",
    "chat.message",
    "chat.params",
    "permission.ask",
    "experimental.session.compacting",
    "experimental.text.filter",
    "auth.provider",
])

/** Top-level permission domains, per the opencode config schema. */
const PERMISSION_DOMAINS = new Set(["read", "edit", "glob", "grep", "list", "bash", "task", "skill", "webfetch"])

/**
 * Permission domains whose value must be a single string. These are documented
 * as scalars; a nested map under them is silently ignored by the config loader,
 * so a permission that looks like it is scoped is in fact global.
 */
const SCALAR_ONLY_PERMISSION_KEYS = new Set(["task"])

// ---------------------------------------------------------------------------
// small helpers
// ---------------------------------------------------------------------------

const read = (rel) => readFileSync(join(ROOT, rel), "utf8")

/** Directories never descended into: dependency trees and mirrored subtrees. */
const IGNORED_DIRS = new Set(["node_modules", ".git", "vendor", "public", "storage", ".kilo"])

function walk(dir, out = []) {
    const abs = join(ROOT, dir)
    if (!existsSync(abs)) return out
    for (const entry of readdirSync(abs, { withFileTypes: true })) {
        if (IGNORED_DIRS.has(entry.name)) continue
        const rel = `${dir}/${entry.name}`
        if (entry.isDirectory()) walk(rel, out)
        else out.push(rel)
    }
    return out
}

/**
 * Scoped instruction files this repo owns. Mirrors under packages/ and apps/
 * are excluded on purpose -- they are upstream-owned trees and their own
 * AGENTS.md files belong to those repos.
 */
const SCOPED_AGENT_FILES = ["packages/AGENTS.md", "resources/js/AGENTS.md", "tests/AGENTS.md", "scripts/AGENTS.md"]

// opencode accepts BOTH the singular and plural directory spellings for agents and
// commands (`.opencode/agent/` or `.opencode/agents/`, `command/` or `commands/`).
// Scanning only one spelling silently skips every file in the other, so an agent
// can be entirely unvalidated and still look covered. Collect from both.
const AGENT_DIRS = [".opencode/agent", ".opencode/agents"]
const COMMAND_DIRS = [".opencode/command", ".opencode/commands"]
const agentFiles = () => [...new Set(AGENT_DIRS.flatMap((d) => walk(d)))].filter((f) => extname(f) === ".md").sort()
const commandFiles = () => [...new Set(COMMAND_DIRS.flatMap((d) => walk(d)))].filter((f) => extname(f) === ".md").sort()

function listDirs(dir) {
    const abs = join(ROOT, dir)
    if (!existsSync(abs)) return []
    return readdirSync(abs, { withFileTypes: true })
        .filter((e) => e.isDirectory())
        .map((e) => e.name)
}

/**
 * Tolerant JSON/JSONC reader. OpenCode accepts comments and trailing commas in
 * its config; `JSON.parse` does not, and a validator that cannot read the file
 * it exists to check is worse than no validator.
 */
function parseJsonc(text, label) {
    let out = ""
    let inString = false
    let inLine = false
    let inBlock = false
    for (let i = 0; i < text.length; i++) {
        const c = text[i]
        const n = text[i + 1]
        if (inLine) {
            if (c === "\n") {
                inLine = false
                out += c
            }
            continue
        }
        if (inBlock) {
            if (c === "*" && n === "/") {
                inBlock = false
                i++
            }
            continue
        }
        if (inString) {
            out += c
            if (c === "\\") {
                out += text[++i] ?? ""
            } else if (c === '"') {
                inString = false
            }
            continue
        }
        if (c === '"') {
            inString = true
            out += c
            continue
        }
        if (c === "/" && n === "/") {
            inLine = true
            i++
            continue
        }
        if (c === "/" && n === "*") {
            inBlock = true
            i++
            continue
        }
        out += c
    }
    try {
        return { value: JSON.parse(out.replace(/,(\s*[}\]])/g, "$1")), error: null }
    } catch (e) {
        return { value: null, error: `${label}: ${e.message}` }
    }
}

/**
 * Split `---\n...\n---` YAML frontmatter without a YAML dependency. Returns
 * null when there is no frontmatter, which is itself a finding for the callers
 * that require it.
 */
function frontmatter(rel) {
    const text = read(rel)
    if (!text.startsWith("---")) return null
    const end = text.indexOf("\n---", 3)
    if (end === -1) return null
    const block = text.slice(3, end)
    const data = {}
    let key = null
    for (const line of block.split("\n")) {
        if (/^\s/.test(line) || !line.trim()) continue
        const m = line.match(/^([A-Za-z0-9_.-]+):\s*(.*)$/)
        if (!m) continue
        key = m[1]
        let v = m[2].trim()
        if (v.startsWith('"') && v.endsWith('"')) v = v.slice(1, -1)
        if (v.startsWith("'") && v.endsWith("'")) v = v.slice(1, -1)
        data[key] = v
    }
    return { data, raw: block, body: text.slice(end + 4) }
}

// ---------------------------------------------------------------------------
// checks
// ---------------------------------------------------------------------------

const CHECKS = []
const check = (name, severity, fn) => CHECKS.push({ name, severity, fn })

check("agents-size", SEVERITY.agentsSize, () => {
    const issues = []
    const rel = "AGENTS.md"
    if (!existsSync(join(ROOT, rel))) return [`${rel} is missing`]
    const bytes = statSync(join(ROOT, rel)).size
    const lines = read(rel).split("\n").length
    if (lines > AGENTS_MD_MAX_LINES) {
        issues.push(
            `${rel} is ${lines} lines (budget ${AGENTS_MD_MAX_LINES}). Long agent context degrades every session; split it into scoped files and keep AGENTS.md as an index.`,
        )
    }
    if (bytes > AGENTS_MD_MAX_BYTES) {
        issues.push(`${rel} is ${bytes} bytes (budget ${AGENTS_MD_MAX_BYTES})`)
    }
    return issues
})

check("config-parse", "error", () => {
    const issues = []
    for (const rel of ["opencode.json", ".opencode/opencode.json"]) {
        if (!existsSync(join(ROOT, rel))) {
            issues.push(`${rel} is missing`)
            continue
        }
        const { error } = parseJsonc(read(rel), rel)
        if (error) issues.push(error)
    }
    return issues
})

check("config-instructions", "error", () => {
    const issues = []
    const rel = ".opencode/opencode.json"
    if (!existsSync(join(ROOT, rel))) return issues
    const { value } = parseJsonc(read(rel), rel)
    const instructions = value?.instructions ?? []
    if (!Array.isArray(instructions)) return ["instructions[] must be an array"]

    // OpenCode rule resolution (verified against opencode.ai/docs/rules, Oct 2026):
//
//   1. Local files, traversing UP from the cwd. The FIRST match wins.
//   2. ~/.config/opencode/AGENTS.md
//   3. ~/.claude/CLAUDE.md (unless disabled)
//
// Consequence: with the cwd at the repo root, only the ROOT AGENTS.md is
// loaded. A nested resources/js/AGENTS.md is never picked up automatically.
//
// `instructions[]` is NOT the fix for that. The docs state "All instruction
// files are combined with your AGENTS.md files" -- it is a flat, always-loaded
// list. Listing every scoped file there would pull all of them into EVERY
// session, which is the opposite of scoping and defeats the context budget.
//
// The documented mechanism for keeping AGENTS.md concise is LAZY loading: name
// the file in AGENTS.md and instruct the agent to Read it when relevant. So the
// requirement enforced here is that every scoped file is REFERENCED from the
// root AGENTS.md, not that it is registered in instructions[].
const scopedAgents = SCOPED_AGENT_FILES.filter((f) => existsSync(join(ROOT, f)))
    for (const entry of instructions) {
        if (typeof entry !== "string") {
            issues.push(`instructions[] entry is not a string: ${JSON.stringify(entry)}`)
            continue
        }
        if (!existsSync(join(ROOT, entry))) {
            issues.push(`instructions[] points at a missing file: ${entry}`)
        }
    }
    const listed = new Set(instructions.map((i) => String(i).replace(/\\/g, "/")))
    for (const f of listed) {
        if (SCOPED_AGENT_FILES.includes(f)) {
            issues.push(
                `${f} is listed in instructions[], which is a flat always-loaded list -- it will be injected into EVERY session, not just sessions working under its directory. Reference it from the root AGENTS.md for lazy loading instead.`,
            )
        }
    }
    // Each scoped file must be reachable from the root AGENTS.md, otherwise
    // nothing ever tells an agent it exists.
    if (scopedAgents.length > 0) {
        if (!existsSync(join(ROOT, "AGENTS.md"))) {
            issues.push("scoped AGENTS.md files exist but there is no root AGENTS.md to reference them from")
        } else {
            const root = readFileSync(join(ROOT, "AGENTS.md"), "utf8")
            for (const f of scopedAgents) {
                const base = f.split("/").pop()
                if (!root.includes(f) && !root.includes(base)) {
                    issues.push(
                        `${f} exists but the root AGENTS.md never mentions it. OpenCode traverses UP from the cwd and stops at the first AGENTS.md, so a nested file is only ever read if the root file points an agent at it.`,
                    )
                }
            }
        }
    }
    return issues
})

check("permission-shape", "error", () => {
    const issues = []
    const rel = ".opencode/opencode.json"
    if (!existsSync(join(ROOT, rel))) return issues
    const { value } = parseJsonc(read(rel), rel)
    const perm = value?.permission
    if (!perm) return ["permission block is missing from .opencode/opencode.json"]
    for (const [domain, rules] of Object.entries(perm)) {
        if (!PERMISSION_DOMAINS.has(domain)) {
            issues.push(`unknown permission domain "${domain}" (expected one of: ${[...PERMISSION_DOMAINS].join(", ")})`)
        }
        if (typeof rules === "string") {
            if (!SCALAR_ONLY_PERMISSION_KEYS.has(domain)) {
                issues.push(`permission.${domain} is a bare string; the schema expects a map keyed by glob`)
            }
            continue
        }
        if (typeof rules !== "object" || rules === null) {
            issues.push(`permission.${domain} must be a string or an object, got ${typeof rules}`)
            continue
        }
        if (SCALAR_ONLY_PERMISSION_KEYS.has(domain)) {
            issues.push(
                `permission.${domain} must be a single string; a nested map is silently ignored, so the entry looks scoped but applies globally`,
            )
        }
        for (const [pattern, action] of Object.entries(rules)) {
            if (!["allow", "ask", "deny"].includes(action)) {
                issues.push(`permission.${domain}["${pattern}"] = ${JSON.stringify(action)} (expected allow|ask|deny)`)
            }
        }
    }
    if (perm.bash && typeof perm.bash === "object" && !("*in perm.bash")) {
        issues.push(
            'permission.bash has no "*" catch-all; unmatched patterns fall through to the default',
        )
    }
    return issues
})

check("shell-permission-explicit", "warn", () => {
    // Advisory, not a defect: whether to gate shell access is a policy choice,
    // and this repo's agents legitimately need composer/npm/git. Flagged so the
    // decision is visible rather than accidental.
    const rel = ".opencode/opencode.json"
    if (!existsSync(join(ROOT, rel))) return []
    const { value } = parseJsonc(read(rel), rel)
    if (value?.permission?.bash === undefined) {
        return [
            "permission.bash is unset here; shell access inherits the global default. If that is intentional, consider stating it in the scoped agents/AGENTS.md rather than leaving it implicit.",
        ]
    }
    return []
})

check("skill-frontmatter", "error", () => {
    const issues = []
    for (const dir of ["skills"]) {
        for (const name of listDirs(`.agents/${dir}`).concat(listDirs(`.opencode/${dir}`))) {
            const found = []
            if (existsSync(join(ROOT, ".agents", dir, name, "SKILL.md"))) found.push(`.agents/${dir}`)
            if (existsSync(join(ROOT, ".opencode", dir, name, "SKILL.md"))) found.push(`.opencode/${dir}`)
            for (const base of found) {
                const rel = `${base}/${name}/SKILL.md`
                const fm = frontmatter(rel)
                if (!fm) {
                    issues.push(`${rel} has no YAML frontmatter; the skill cannot be discovered`)
                    continue
                }
                const declared = fm.data.name
                if (!declared) {
                    issues.push(`${rel} frontmatter has no name`)
                } else {
                    if (declared !== name) issues.push(`${rel} declares name "${declared}" but lives in directory "${name}"`)
                    if (!/^[a-z0-9]+(-[a-z0-9]+)*$/.test(declared)) {
                        issues.push(`${rel} name "${declared}" is not kebab-case`)
                    }
                    if (declared.length > SKILL_NAME_MAX) issues.push(`${rel} name exceeds ${SKILL_NAME_MAX} chars`)
                }
                const description = fm.data.description
                if (!description) {
                    issues.push(`${rel} frontmatter has no description; routing depends on it`)
                } else if (description.length > SKILL_DESCRIPTION_MAX) {
                    issues.push(`${rel} description is ${description.length} chars (budget ${SKILL_DESCRIPTION_MAX})`)
                }
            }
        }
    }
    return issues
})

check("skill-shadow", "warn", () => {
    const issues = []
    const project = listDirs(".agents/skills").map((n) => ({ n, where: ".agents/skills" }))
        .concat(listDirs(".opencode/skills").map((n) => ({ n, where: ".opencode/skills" })))
    const seen = new Map()
    for (const { n, where } of project) {
        if (seen.has(n)) {
            issues.push(
                `skill "${n}" is defined in both ${seen.get(n)} and ${where}; the higher-precedence copy silently wins`,
            )
        }
        seen.set(n, where)
    }
    const globalDir = join(process.env.USERPROFILE ?? "", ".config", "opencode", "skills")
    if (existsSync(globalDir)) {
        for (const { n, where } of project) {
            if (existsSync(join(globalDir, n))) {
                issues.push(
                    `skill "${n}" also exists as a GLOBAL skill; ${where} takes precedence, but any other tool reading only the global dir sees the other copy`,
                )
            }
        }
    }
    return issues
})

check("skill-routable", "warn", () => {
    const issues = []
    for (const base of [".agents/skills", ".opencode/skills"]) {
        for (const name of listDirs(base)) {
            const rel = `${base}/${name}/SKILL.md`
            if (!existsSync(join(ROOT, rel))) continue
            const fm = frontmatter(rel)
            const description = fm?.data?.description ?? ""
            const body = fm?.body ?? ""
            if (description.length < 40) {
                issues.push(`${rel} description is very short (${description.length} chars); a thin description will not route reliably`)
            }
            // A trigger clause can be phrased many ways -- "apply whenever...",
            // "activate when...", "triggers for...", "use for...". All of them
            // route. Only flag descriptions with no conditional or trigger
            // phrasing at all.
            const hasTrigger =
                /\bwhenever\b/i.test(description) ||
                /\bwhen\b/i.test(description) ||
                /\btrigger/i.test(description) ||
                /\bapply\b/i.test(description) ||
                /\bactivate\b/i.test(description) ||
                /\bfor\b/i.test(description)
            if (!hasTrigger) {
                issues.push(`${rel} description states no trigger condition; routing needs an explicit "when ..." clause`)
            }
            if (body.trim().length === 0) {
                issues.push(`${rel} has frontmatter but no body`)
            }
        }
    }
    return issues
})

check("command-frontmatter", "error", () => {
    const issues = []
    for (const rel of commandFiles()) {
        const name = basename(rel, ".md")
        if (BUILTIN_COMMANDS.has(name)) {
            issues.push(`command "${name}" shadows a built-in opencode command (${[...BUILTIN_COMMANDS].join(", ")})`)
        }
        const fm = frontmatter(rel)
        if (!fm) {
            issues.push(`${rel} has no frontmatter; commands need at least a description`)
        } else if (!fm.data.description) {
            issues.push(`${rel} frontmatter has no description`)
        }
    }
    return issues
})

check("plugin-parse", "error", async () => {
    const issues = []
    for (const rel of walk(".opencode/plugins").filter((f) => [".ts", ".js", ".mjs"].includes(extname(f)))) {
        try {
            // Node >= 22.6 strips types natively, so importing is a real parse
            // and not a regex approximation. A syntactically truncated module
            // throws here even though `opencode debug config` still lists it.
            const mod = await import(pathToFileURL(join(ROOT, rel)).href)
            const exported = Object.keys(mod).filter((k) => k.endsWith("Plugin") || k === "default")
            if (exported.length === 0) {
                issues.push(`${rel} exports no *Plugin symbol`)
            } else if (exported.includes("default")) {
                // default export is fine for an opencode plugin; do not require a named *Plugin export
            }
        } catch (e) {
            issues.push(`${rel} failed to load: ${e.message}`)
        }
    }
    return issues
})

check("plugin-events", "error", () => {
    const issues = []
    for (const rel of walk(".opencode/plugins").filter((f) => [".ts", ".js", ".mjs"].includes(extname(f)))) {
        const text = read(rel)
        // Hook keys look like "event.name" inside a returned object literal.
        const keys = [...text.matchAll(/"([a-z][a-zA-Z0-9_]*(?:\.[a-zA-Z0-9_]+)+)"\s*:/g)].map((m) => m[1])
        for (const key of new Set(keys)) {
            if (!KNOWN_EVENTS.has(key) && !key.startsWith("ai.") && !key.startsWith("tool.")) {
                issues.push(`${rel} registers hook "${key}", which is not a known opencode event; it will never fire`)
            }
        }
    }
    return issues
})

check("agent-gates", "error", () => {
    const issues = []
    for (const rel of agentFiles()) {
        const fm = frontmatter(rel)
        if (!fm) {
            issues.push(`${rel} has no frontmatter`)
            continue
        }
        const mode = fm.data.mode ?? ""
        if (mode !== "all" && mode !== "primary" && mode !== "subagent" && mode !== "build") {
            issues.push(`${rel} declares mode "${mode || "(none)"}"; expected all|primary|subagent|build`)
        }
        const perm = fm.raw.match(/permission:\s*([\s\S]*?)(?=\n[a-z_-]+:|$)/)
        if (!perm || !/edit:\s*deny/.test(perm[1])) {
            issues.push(`${rel} does not set permission edit: deny; a read-only reviewer that can edit will edit`)
        }
        // Non-empty is the requirement. Testing whether the prose happens to
        // contain the word "description" flagged every real agent description and
        // passed an empty one, which is the opposite of the check's intent.
        const description = String(fm.data.description ?? "").trim()
        if (!description) {
            issues.push(`${rel} frontmatter has no description`)
        } else if (description.length < 40) {
            issues.push(
                `${rel} description is ${description.length} chars; it must say what the agent does AND when to reach for it, or it will never be selected`,
            )
        }
    }
    return issues
})

check("agent-registered", "error", () => {
    const agents = agentFiles().map((f) => basename(f, ".md"))
    if (agents.length === 0) return []
    const issues = []
    const { value } = parseJsonc(read(".opencode/opencode.json"), ".opencode/opencode.json")
    const task = value?.permission?.task
    if (task === undefined) {
        issues.push(`agents exist (${agents.join(", ")}) but permission.task is unset; there is no agent gate to scope them`)
        return issues
    }
    if (typeof task === "object" && task !== null) {
        for (const a of agents) {
            if (!(a in task)) issues.push(`agent "${a}" is not covered by permission.task`)
        }
    }
    return issues
})

check("reference-resolve", "error", () => {
    const issues = []
    const rel = ".opencode/opencode.json"
    if (!existsSync(join(ROOT, rel))) return issues
    const { value } = parseJsonc(read(rel), rel)
    for (const [name, ref] of Object.entries(value?.references ?? {})) {
        if (!ref?.path) {
            issues.push(`references.${name} has no path`)
            continue
        }
        const abs = join(ROOT, ".opencode", ref.path)
        if (!existsSync(abs)) {
            issues.push(`references.${name} -> ${ref.path} does not resolve from .opencode/`)
        } else if (!statSync(abs).isDirectory()) {
            issues.push(`references.${name} -> ${ref.path} is not a directory`)
        } else if (!isInsideRepo(abs)) {
            // External references must point at a real upstream checkout. Without
            // this, a path with one `../` too many can land on an unrelated
            // directory that merely happens to exist (e.g. ../../packages from
            // .opencode/ hits a stale Local Sites\laravel\packages\shop-module)
            // and pass silently while pointing an agent at the wrong tree.
            const isRepo = existsSync(join(abs, ".git")) || existsSync(join(abs, "composer.json"))
            if (!isRepo) {
                issues.push(
                    `references.${name} -> ${ref.path} resolves outside the repo to ${abs}, which is not a repository checkout (no .git or composer.json). This usually means one too many "../" segments -- use "../" to stay inside this repo.`,
                )
            }
        }
    }
    return issues
})

check("mirror-reference-coverage", "warn", () => {
    // If the harness tells an agent that apps/** and the module packages are
    // upstream mirrors, every one of those mirrors needs a reachable reference,
    // or the instruction is unactionable. This is the check that would have
    // caught the 2026-10 ownership correction being only half-applied.
    const issues = []
    const { value } = parseJsonc(read(".opencode/opencode.json"), ".opencode/opencode.json")
    const configured = new Set(Object.keys(value?.references ?? {}))
    const required = {
        platform: "packages/platform",
        blog: "packages/blog",
        "shop-module": "packages/shop-module",
        "booking-module": "packages/booking-module",
        "laravel.shop": "apps/laravel.shop",
        "laravel.booking": "apps/laravel.booking",
    }
    for (const [name, mirror] of Object.entries(required)) {
        if (!existsSync(join(ROOT, mirror))) continue
        if (!configured.has(name)) {
            issues.push(
                `${mirror} exists and is an upstream mirror, but references.${name} is not configured; an agent told to edit it upstream has no configured path to reach it`,
            )
        }
    }
    return issues
})

check("placeholder-scan", "error", () => {
    const issues = []
    const targets = [
        ...(existsSync(join(ROOT, "AGENTS.md")) ? ["AGENTS.md"] : []),
        "opencode.json",
        ".opencode/opencode.json",
        ...commandFiles(),
        ...agentFiles(),
        ...walk(".agents/skills"),
        ...walk(".opencode/skills"),
    ].filter((f) => [".md", ".json"].includes(extname(f)))
    for (const rel of new Set(targets)) {
        const text = read(rel)
        text.split("\n").forEach((line, i) => {
            const codeSpans = [...line.matchAll(/`([^`]*)`/g)].map((m) => m[1])
            const withoutCode = line.replace(/`[^`]*`/g, "")
            if (/\b(TODO|FIXME|XXX)\b/.test(withoutCode)) {
                issues.push(`${rel}:${i + 1} contains a TODO/FIXME/XXX marker`)
            }
            for (const span of codeSpans) {
                // `{{ ... }}` is real syntax in this codebase, not a template
                // hole: Blade escaping ({{ $user->bio }}) and GitHub Actions
                // expressions (${{ vars.RUNNER_CI || 'self-hosted' }}) both
                // appear in real guidance. Only flag a span that looks like an
                // unfilled placeholder -- a bare identifier, no PHP, no GH
                // expression operators.
                for (const inner of span.matchAll(/\{\{([^}]*)\}\}/g)) {
                    const body = inner[1].trim()
                    if (body === "") continue
                    const isSyntax =
                        body.includes("$") ||
                        body.includes("->") ||
                        body.includes("||") ||
                        body.includes("&&") ||
                        /\b(json_encode|echo)\b/.test(body) ||
                        /^['"]/.test(body) ||
                        /\b(secrets|vars|github|env|steps|needs|inputs|job|runner|strategy|matrix)\./.test(body) ||
                        /\$\{\{/.test(body)
                    if (!isSyntax) {
                        issues.push(`${rel}:${i + 1} contains an unrendered {{placeholder}} in a code span: {{${body}}}`)
                    }
                }
            }
        })
    }
    return issues
})

check("session-context-budget", "warn", () => {
    const issues = []
    const rel = ".opencode/plugins/session-context.ts"
    if (!existsSync(join(ROOT, rel))) return issues
    const text = read(rel)
    const m = text.match(/const\s+\w+\s*=\s*`([\s\S]*?)`\.trim\(\)/)
    if (!m) return issues
    const bytes = Buffer.byteLength(m[1], "utf8")
    if (bytes > SESSION_CONTEXT_MAX_BYTES) {
        issues.push(
            `injected session context is ${bytes} bytes (budget ${SESSION_CONTEXT_MAX_BYTES}); this is re-read on every compaction, so trim it before adding`,
        )
    }
    if (/WORKSPACE-ONLY|MONO-OWNED/.test(m[1])) {
        issues.push(
            "injected session context reintroduces the retracted WORKSPACE-ONLY / MONO-OWNED ownership claim for the mirrored trees",
        )
    }
    return issues
})

check("boundary-truth", "error", () => {
    // Ownership claims are prose, so they rot silently. This asserts the specific
    // claim that was wrong here, against the evidence that disproved it.
    const issues = []
    const files = ["AGENTS.md", ".opencode/plugins/session-context.ts", ".opencode/plugins/mirror-boundary.ts", ".github/PULL_REQUEST_TEMPLATE.md"]
        .filter((f) => existsSync(join(ROOT, f)))
    for (const rel of files) {
        const text = read(rel)
        if (/\b(?:are|is)\s+WORKSPACE-ONLY\b/i.test(text)) {
            issues.push(`${rel} asserts the mirrored trees are WORKSPACE-ONLY; CI's parity job byte-compares apps/** against the standalone repos`)
        }
        if (/MONO-OWNED/i.test(text)) {
            issues.push(`${rel} uses "MONO-OWNED" for packages/** or apps/**, which are upstream mirrors`)
        }
        if (/they have no upstream repo/i.test(text)) {
            issues.push(`${rel} claims apps/** has no upstream repo; nreljed/laravel.shop and nreljed/laravel.booking are real repos`)
        }
    }
    return issues
})

check("version-claims", "warn", () => {
    const issues = []
    if (!existsSync(join(ROOT, "AGENTS.md"))) return issues
    const text = read("AGENTS.md")
    const pkg = existsSync(join(ROOT, "package.json")) ? JSON.parse(read("package.json")) : {}
    const expected = {
        react: /React\s*\|\s*([0-9][0-9.]*)/.exec(text)?.[1],
        laravel: /Laravel\s*\|\s*([0-9][0-9.x]*)/.exec(text)?.[1],
    }
    const declaredReact = (pkg.dependencies?.react ?? pkg.devDependencies?.react ?? "").match(/[\d.]+/)?.[0]
    if (expected.react && declaredReact && !declaredReact.startsWith(expected.react.split(".")[0])) {
        issues.push(`AGENTS.md claims React ${expected.react} but package.json declares ${declaredReact}`)
    }
    return issues
})

// ---------------------------------------------------------------------------
// runner
// ---------------------------------------------------------------------------

async function main() {
    const argv = process.argv.slice(2)
    const only = argv.find((a) => a.startsWith("--only="))?.slice("--only=".length)
    const asJson = argv.includes("--json")
    const selected = only ? new Set(only.split(",").map((s) => s.trim())) : null

    const results = []
    for (const c of CHECKS) {
        if (selected && !selected.has(c.name)) continue
        let issues = []
        let error = null
        try {
            issues = await c.fn()
        } catch (e) {
            error = e
        }
        results.push({ name: c.name, severity: c.severity, issues, error })
    }

    const errors = results.filter((r) => r.error || (r.severity === "error" && r.issues.length > 0))
    const warnings = results.filter((r) => !r.error && r.severity === "warn" && r.issues.length > 0)
    const passed = results.filter((r) => !r.error && r.issues.length === 0)

    if (asJson) {
        console.log(JSON.stringify({ results, errorCount: errors.length, warningCount: warnings.length }, null, 2))
        process.exit(errors.length > 0 ? 1 : 0)
    }

    console.log("Agent harness check\n")
    for (const r of results) {
        if (r.error) {
            console.log(`  ERROR  ${r.name} -- check threw: ${r.error.message}`)
            continue
        }
        if (r.issues.length === 0) {
            console.log(`  ok     ${r.name}`)
            continue
        }
        const tag = r.severity === "error" ? "FAIL  " : "warn  "
        console.log(`  ${tag} ${r.name} (${r.issues.length})`)
        for (const i of r.issues) console.log(`           - ${i}`)
    }
    console.log(
        `\n${passed.length} passed, ${errors.length} failed, ${warnings.length} warned (of ${results.length} checks)`,
    )
    if (errors.length > 0) {
        console.log("\nErrors block the run. Warnings are tracked, not fatal.")
    }
    process.exit(errors.length > 0 ? 1 : 0)
}

await main()