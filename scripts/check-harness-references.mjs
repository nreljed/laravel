#!/usr/bin/env node
/**
 * Path-reference validator for the agent harness.
 *
 * Agent instructions are only useful if the paths they name still exist. A
 * backticked `app/Models/Page.php` in AGENTS.md is a promise to the agent, and a
 * promise that silently stopped being true costs a session every time.
 *
 * This repo demonstrated the failure before the check existed:
 *   - `.ai/rules/index.md` pointed at `C:/actions-runner-core/**` after the
 *     runners moved to `C:/runners/<repo>` on 2026-08-31
 *   - two `.ai/rules` globs named `src/Ai/Agents/**` and
 *     `src/Concerns/InteractsWithFeatures.php`, which do not exist in this app
 *     (the files live under `vendor/nreljed/platform/src/...`), so those rules
 *     could never fire
 *   - AGENTS.md cited `vendor/nreljed/platform/PlatformServiceProvider.php` for
 *     the rate limits, a path that never existed -- the real file is
 *     `vendor/nreljed/platform/src/PlatformServiceProvider.php`
 *
 * WHAT IS CHECKED, and why the scope is narrow:
 *
 *  1. `.ai/rules/**` frontmatter `applies_to` globs. This is the contract that
 *     decides whether a rule fires at all, so a stale glob silently disables a
 *     rule. Prose paths inside rule bodies are NOT checked: that text is dense
 *     operational notes that deliberately quote *wrong* shapes to warn them off
 *     (e.g. the `Resources/js/js/` double-nesting trap).
 *
 *  2. Backticked paths in AGENTS.md and `.opencode/command/*.md`. These are
 *     curated, human-maintained navigation docs whose every reference is a
 *     promise to the agent.
 *
 *  3. Bare filenames resolve by basename against the package mirrors, because
 *     AGENTS.md documents the core package by bare name (`core.css`,
 *     `dashboard.ts`) and those files live several levels down in
 *     `packages/platform/**`.
 *
 * Deliberately NOT checked:
 *  - `.agents/skills/**` -- imported from upstream skill repos; they describe
 *    that package's layout, not this app's. Checking them produced ~2100
 *    findings that were all false.
 *  - prose truth. Deciding that a sentence is wrong requires knowing intent.
 *
 * Node built-ins only. Usage:
 *   node scripts/check-harness-references.mjs
 *   node scripts/check-harness-references.mjs --json
 */

import { readdirSync, readFileSync, existsSync } from "node:fs"
import { join, dirname, extname, resolve as resolvePath, relative, isAbsolute, sep, basename } from "node:path"
import { homedir } from "node:os"
import { fileURLToPath } from "node:url"

const ROOT = join(dirname(fileURLToPath(import.meta.url)), "..")
const IGNORED_DIRS = new Set(["node_modules", ".git", "vendor", ".kilo", "public", "storage"])

/**
 * Package roots searched for bare filenames. AGENTS.md documents the core
 * package by bare name, so a reference like `core.css` is live if the file
 * exists anywhere inside one of these trees.
 */
const PACKAGE_ROOTS = [
    "packages/platform",
    "packages/blog",
    "packages/shop-module",
    "packages/booking-module",
    "vendor/nreljed/platform",
    "vendor/nreljed/blog",
    "vendor/nreljed/shop-module",
    "vendor/nreljed/booking-module",
]

/**
 * Paths that are legitimately referenced but must not exist, with the reason.
 * Every entry is a deliberate decision; adding to this list is how the validator
 * stops telling the truth, so each states why.
 */
const ALLOWED_MISSING = new Map([
    // Stated as deliberately absent by the harness text itself.
    ["public/hot", "must NOT exist -- Vite dev server marker; asserted absent"],
    [".npmrc", "deliberately absent -- pnpm 11 ignores it; settings live in pnpm-workspace.yaml"],
    ["package-lock.json", "workspace era -- the single root pnpm-lock.yaml is the npm pin of record"],
    ["vite.dedupe.js", "workspace era -- pnpm keeps one resolved copy; dedupe file deleted"],
    ["core-publish.yml", "deleted in the 2026-08-24 core burn-in; releases are git tags now"],
    ["scripts/core-publish.php", "deleted in the 2026-08-24 core burn-in"],
    // CI workflows that live in the standalone upstream repo, not the composer dist.
    ["core-update.yml", "ships from the nreljed/platform repo, not the vendored dist"],
    ["deploy.zip", "build artifact produced by deploy-package.yml, never committed"],
    ["deploy.php", "build artifact produced by deploy-package.yml, never committed"],
    // Superseded or config-file references that are not repo paths.
    ["playwright-mcp.json", "Playwright MCP is configured in ~/.config/opencode/opencode.jsonc"],
    ["~/.config/opencode/opencode.jsonc", "global user config, resolved from HOME"],
    ["github.com/nreljed/platform", "repository URL, not a filesystem path"],
    // A directory on the deployed server, created by the 2026-09-14 archive
    // of legacy root .env backups. Never present in the repository.
    ["legacy-root-env-backups-2026-09-14/", "server-side archive directory, not in the repo"],
])

/** Curated navigation docs whose backticked paths are promises to the agent. */
const PROSE_TARGETS = ["AGENTS.md", ".opencode/command/release-package.md", ".opencode/command/sync-mirror.md", ".opencode/command/fleet-check.md", ".github/PULL_REQUEST_TEMPLATE.md"]

/* ------------------------------------------------------------------ */
/* Classify a backticked span                                           */
/* ------------------------------------------------------------------ */

/**
 * Token classes that routinely contain a dot or a slash but are not paths.
 * Each rule here suppresses a real false-positive class seen in this repo.
 */
function isNotAPath(span) {
    const s = span.trim()
    if (!s || s.length > 200) return true
    if (s.includes("://")) return true // URL with scheme
    if (s.includes("..")) return true // elided path, e.g. packages/platform/.../global.d.ts
    if (s.includes("*") || s.includes("$") || s.includes("{") || s.includes("|")) return true
    if (s.includes("<") || s.includes(">")) return true // <metavariable>
    if (s.includes("\\")) return true // PHP namespace: Laravel\Ai\Enums\Lab
    if (s.includes("@")) return true // email address
    if (/^[A-Z_][A-Z0-9_]*$/.test(s)) return true // ENV_VAR
    if (s.startsWith("-")) return true // CLI flag
    if (/^\.\/[\w-]+$/.test(s)) return true // package export subpath: ./booking-aliases
    if (s.startsWith(".") && !s.includes("/")) return true // dotfile: .npmrc, .d.ts
    if (/^[A-Za-z-]+\s*:\s/.test(s) || /^[\w./@-]+\s*:\s/.test(s)) return true // "Accept: json", "pkg: ^0.1"
    if (/^v[\dX][\dXxYyZz.]*$/i.test(s)) return true // version or tag: v0.1.x, vX.Y.Z
    if (/^[\^~>=<]?\s*[\dX][\dXxYyZz.*+-]*$/i.test(s)) return true // semver range: ^6.0.0
    if (/^nreljed\/[\w.-]+$/.test(s)) return true // git repo slug
    // Middleware / PHP facade syntax: permission:feature.view, Inertia::render(
    if (/^[a-z-]+:[a-z]/.test(s)) return true
    if (/^[A-Z]\w*::/.test(s)) return true
    if (s.includes("(") && !s.includes(")")) return true // unbalanced code span
    if (s.includes("[") && !s.includes("]")) return true
    // Absolute, extensionless, all-lowercase with a trailing slash: a route
    // prefix (/deploy/, /api/notifications/), not a directory on disk.
    if (s.startsWith("/") && !/\.[A-Za-z0-9_]{1,6}$/.test(s)) return true
    // A bare directory name is never a specific promise. "src/", "database/",
    // "blog/" are prefixes used to enumerate trees or copy lists, and checking
    // them produced noise, not signal.
    if (/^[\w.-]+\/$/.test(s)) return true
    if (/^(resolve|import|export|defineConfig|loader|compiler|module|preserve|plugins|build)\.[a-z]/i.test(s)) {
        return true // config key: resolve.alias, defineConfig({})
    }
    // Dotted lowercase identifier with no separator: a permission slug
    // (posts.view), a shared prop key (auth.user) or a domain (laravel.shop).
    if (/^[a-z][\w-]*(\.[a-z][\w-]*)+$/.test(s)) return true
    // host.tld/... with no scheme
    if (/^[\w-]+(\.[a-z]{2,})(\/|$)/.test(s)) return true
    return false
}

/** Does this span reference a file or directory (as opposed to a route or prose)? */
function looksLikePath(span) {
    if (isNotAPath(span)) return false
    let s = span.trim()
    if (/\s/.test(s)) {
        // Path plus arguments, e.g. `vendor/bin/pint --test`. Only the first
        // token can be a path; the rest are flags.
        return looksLikePath(s.split(/\s+/)[0])
    }
    s = s.replace(/^["'`]|["'`]$/g, "")
    const hasSep = s.includes("/")
    const hasExt = /\.[A-Za-z0-9_]{1,6}$/.test(s)
    if (!hasSep && !hasExt) return false // a bare word is prose
    if (hasSep && !hasExt) {
        // Extensionless: a web route (/dashboard/admin) unless it has a
        // filename-looking segment or a trailing slash (a directory).
        if (!s.endsWith("/") && s.split("/").every((seg) => !/[_.\-A-Z0-9]/.test(seg) || seg === seg.toLowerCase())) {
            if (s.split("/").filter(Boolean).every((seg) => /^[a-z0-9-]+$/.test(seg))) return false
        }
    }
    return true
}

/** Strip trailing punctuation and `file:line` suffixes. */
function normalise(span) {
    return span
        .trim()
        .replace(/^["'`]|["'`]$/g, "")
        .replace(/[.,;:)]+$/, "")
        .replace(/:\d+(-\d+)?$/, "")
        .trim()
}

/* ------------------------------------------------------------------ */
/* Resolution                                                          */
/* ------------------------------------------------------------------ */

function walk(dir, out = [], depth = 0) {
    const abs = join(ROOT, dir)
    if (!existsSync(abs) || depth > 12) return out
    let entries
    try {
        entries = readdirSync(abs, { withFileTypes: true })
    } catch {
        return out
    }
    for (const e of entries) {
        if (IGNORED_DIRS.has(e.name)) continue
        // dir === "." must yield "name", not "./name", or every path-segment
        // comparison downstream silently fails.
        const rel = dir === "." ? e.name : `${dir}/${e.name}`
        // Directories are indexed too: a trailing-slash reference such as
        // `resources/js/` is a legitimate promise and must be resolvable.
        if (e.isDirectory()) {
            out.push(`${rel}/`)
            walk(rel, out, depth + 1)
        } else out.push(rel)
    }
    return out
}

/** Every indexed file in the repo and the package mirrors, as posix paths. */
let allFiles = null
/** Bare filenames seen anywhere, for bare-name lookup. */
let basenameIndex = null

function getIndex() {
    if (allFiles) return allFiles
    allFiles = new Set()
    // walk(".") covers the whole tree except IGNORED_DIRS (notably vendor/).
    for (const f of walk(".")) allFiles.add(f)
    // The Composer-installed package trees are indexed explicitly, because
    // walk(".") refuses to descend into vendor/. Starting *at* each package
    // root bypasses that skip, so their internals are referenceable.
    for (const root of PACKAGE_ROOTS) {
        for (const f of walk(root)) allFiles.add(f)
    }
    basenameIndex = new Set([...allFiles].map((f) => basename(f)))
    return allFiles
}

/**
 * Resolve a reference. Returns the matched path, or null.
 *
 * Resolution order: tilde -> absolute -> containing file -> repo root ->
 * suffix match against the indexed workspace files. The suffix match is what
 * makes package-owned references work: AGENTS.md writes `config/roles.php`,
 * which lives at `vendor/nreljed/platform/config/roles.php`, and that reference
 * is live precisely because the file exists somewhere in the workspace. It still
 * catches genuine rot -- `vendor/nreljed/platform/PlatformServiceProvider.php`
 * matches nothing, because the real file has a `src/` segment.
 */
function resolveRef(ref, fromFile) {
    const win = ref.replace(/\//g, sep)
    if (win.startsWith("~")) {
        const p = join(homedir(), win.slice(1).replace(/^[\\/]/, ""))
        return existsSync(p) ? p : null
    }
    if (isAbsolute(win)) return existsSync(win) ? win : null

    const fileDir = dirname(fromFile)
    for (const c of [join(ROOT, win), resolvePath(ROOT, fileDir, win)]) {
        if (c.startsWith(ROOT) && existsSync(c)) return c
    }

    const index = getIndex()
    const raw = win.replace(/\\/g, "/").replace(/^\.\//, "")
    const trailing = raw.endsWith("/")
    const want = trailing ? raw.replace(/\/$/, "") : raw
    if (!want) return null
    if (!want.includes("/") && basenameIndex.has(want)) return want
    for (const f of index) {
        const norm = f.replace(/\/$/, "")
        if (norm === want) return f
        // A directory reference (trailing slash) must match a directory entry.
        if (trailing && f.endsWith("/") && norm.endsWith(`/${want}`)) return f
        if (!trailing && !f.endsWith("/") && norm.endsWith(`/${want}`)) return f
    }
    return null
}

/* ------------------------------------------------------------------ */
/* Checks                                                              */
/* ------------------------------------------------------------------ */

/**
 * Extract the `paths:` / `applies_to:` globs from a rule's YAML frontmatter.
 *
 * Scoped to the frontmatter block so ordinary prose lists are not mistaken for
 * globs, and quotes are optional because the rule files use both styles
 * (`- 'scripts/**'` and `- vite.config.js`). Both styles must be handled: an
 * earlier quote-only matcher silently skipped 5 of 13 globs, including
 * `.github/workflows/restore.yml` and `scripts/deploy-runner.php`.
 */
function extractRuleGlobs(text) {
    const m = /^---\r?\n([\s\S]*?)\r?\n---/.exec(text)
    if (!m) return []
    const block = m[1]
    const keyMatch = /^\s*(?:paths|applies_to)\s*:\s*$/m.exec(block)
    const out = []
    if (keyMatch) {
        // Block-sequence form: the key is alone on its line, items are indented
        // list entries beneath it.
        const after = block.slice(keyMatch.index + keyMatch[0].length)
        for (const line of after.split(/\r?\n/)) {
            if (!/^\s+-\s/.test(line)) {
                if (line.trim() === "") continue
                break // dedented: the list ended
            }
            out.push(line)
        }
    } else {
        // Inline or multi-entry form.
        for (const line of block.split(/\r?\n/)) out.push(line)
    }
    const globs = []
    for (const line of out) {
        const item = /^\s*-\s*(.*?)\s*$/.exec(line)
        if (!item) continue
        const raw = item[1].replace(/^['"]|['"]$/g, "").trim()
        if (!raw || raw.startsWith("#")) continue
        if (/:\s/.test(raw) && !raw.includes("/")) continue // some other key: value
        globs.push(raw)
    }
    return globs
}

function checkRuleFrontmatter(problems) {
    const rulesDir = ".ai/rules"
    if (!existsSync(join(ROOT, rulesDir))) return 0
    let checked = 0
    for (const file of walk(rulesDir).filter((f) => f.endsWith(".md"))) {
        const text = readFileSync(join(ROOT, file), "utf8")
        for (const glob of extractRuleGlobs(text)) {
            const line = text.slice(0, text.indexOf(glob)).split("\n").length
            checked++
            if (/^(https?:|[A-Za-z]:|\/|~)/.test(glob)) {
                // Absolute path, possibly on the dev machine (runners, tooling).
                // Probe the literal prefix: "C:/runners/platform/**" exists as a
                // directory even though the globbed string never will.
                const base = glob.replace(/\\/g, sep).replace(/[*?].*$/, "").replace(/[/\\]$/, "")
                if (!existsSync(base)) {
                    problems.push({ file, line, ref: glob, note: `absolute path does not exist: ${base}` })
                }
                continue
            }
            // Wildcard-free glob: a literal path, so existence is the test.
            if (!/[*?]/.test(glob)) {
                if (!existsSync(join(ROOT, glob.replace(/\//g, sep)))) {
                    problems.push({ file, line, ref: glob, note: "path does not exist" })
                }
                continue
            }
            // Wildcard glob: probe the directory prefix, then confirm at least
            // one real file matches. The prefix must be a DIRECTORY -- for a
            // wildcard-free glob it would be the file itself, and walking a
            // file yields nothing.
            let base = glob.split("*")[0].replace(/[/\\]$/, "")
            if (base.includes(".")) base = base.slice(0, base.lastIndexOf("/") + 1).replace(/[/\\]$/, "")
            if (!base) base = "."
            if (!existsSync(join(ROOT, base.replace(/\//g, sep)))) {
                problems.push({ file, line, ref: glob, note: `glob base path does not exist: ${base}` })
                continue
            }
            const re2 = globToRegExp(glob)
            const all = walk(base === "." ? "." : base)
            if (!all.some((f) => re2.test(f.replace(/\/$/, "").replace(/\//g, "/")))) {
                problems.push({ file, line, ref: glob, note: "glob matches no file on disk" })
            }
        }
    }
    return checked
}

function globToRegExp(glob) {
    const escaped = glob.replace(/[.+^${}()|[\]\\]/g, "\\$&")
    return new RegExp("^" + escaped.replace(/\*\*/g, " ").replace(/\*/g, "[^/]*").replace(/ /g, ".*") + "$")
}

function checkProsePaths(problems) {
    const files = PROSE_TARGETS.filter((f) => existsSync(join(ROOT, f)))
    for (const d of [".opencode/command"]) {
        for (const f of walk(d).filter((f) => f.endsWith(".md"))) if (!files.includes(f)) files.push(f)
    }
    let scanned = 0
    for (const rel of files) {
        const lines = readFileSync(join(ROOT, rel), "utf8").split("\n")
        lines.forEach((line, i) => {
            for (const m of line.matchAll(/`([^`\n]+)`/g)) {
                const ref = normalise(m[1])
                if (!looksLikePath(ref)) continue
                scanned++
                if (ALLOWED_MISSING.has(ref)) continue
                if (resolveRef(ref, rel)) continue
                problems.push({ file: rel, line: i + 1, ref, note: "unresolvable" })
            }
        })
    }
    return { files: files.length, scanned }
}

function main() {
    const asJson = process.argv.includes("--json")
    const problems = []
    const globs = checkRuleFrontmatter(problems)
    const prose = checkProsePaths(problems)

    if (asJson) {
        console.log(JSON.stringify({ globs, prose, problems, allowed: [...ALLOWED_MISSING.keys()] }, null, 2))
        process.exit(problems.length > 0 ? 1 : 0)
    }

    console.log("Harness reference check\n")
    console.log(`  ${globs} rule globs, ${prose.scanned} prose path references in ${prose.files} files`)
    console.log(`  ${ALLOWED_MISSING.size} references explicitly allowed as intentionally absent\n`)

    if (problems.length === 0) {
        console.log("  ok  every rule glob and referenced path resolves")
    } else {
        console.log(`  FAIL  ${problems.length} broken reference(s)\n`)
        const byFile = new Map()
        for (const p of problems) {
            if (!byFile.has(p.file)) byFile.set(p.file, [])
            byFile.get(p.file).push(p)
        }
        for (const [file, list] of [...byFile].sort()) {
            console.log(`  ${file}`)
            for (const p of list) console.log(`      ${p.line}: ${p.ref}\n            -> ${p.note}`)
            console.log("")
        }
        console.log("  If a reference is intentionally absent (another machine, a build")
        console.log("  artifact, an upstream repo), add it to ALLOWED_MISSING with a reason.")
    }
    process.exit(problems.length > 0 ? 1 : 0)
}

main()