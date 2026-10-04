#!/usr/bin/env node
/**
 * Markdown link validator.
 *
 * Docs rot silently: a renamed file leaves `docs/index.md` pointing at nothing,
 * and nobody notices until an agent follows the link and gets a 404 in its
 * context. This checks that every relative Markdown link resolves to a real
 * file, and that any `#anchor` in the target actually exists as a heading.
 *
 * Scope:
 *   - docs/** and root-level *.md
 *   - docs/archive/** is EXCLUDED. Archived docs are frozen historical
 *     snapshots; their links record what was true when they were written and
 *     are not maintained. Including them produces permanent unfixable noise,
 *     which is how link checkers get disabled. Their prose is preserved for
 *     archaeology, not navigation.
 *
 * Not checked (correctly):
 *   - http(s)://, mailto:, and other absolute URLs -- verified by the browser,
 *     not by a filesystem walk, and a network check would be slow and flaky.
 *   - in-page anchors on the SAME file are checked; cross-file anchors are
 *     checked by slugifying the target's headings.
 *
 * Node built-ins only. Usage:
 *   node scripts/check-doc-links.mjs
 *   node scripts/check-doc-links.mjs --json
 */

import { readdirSync, readFileSync, existsSync, statSync } from "node:fs"
import { join, dirname, extname, resolve as resolvePath, relative, sep } from "node:path"
import { fileURLToPath } from "node:url"

const ROOT = join(dirname(fileURLToPath(import.meta.url)), "..")
const IGNORED_DIRS = new Set(["node_modules", ".git", "vendor", ".kilo"])

const SCAN_DIRS = ["docs"]
const SCAN_ROOT_FILES = ["AGENTS.md", "CONTRIBUTING.md", "DEPLOY.md", "PLAN.md", "README.md"]

/** Directories whose links are historical records, not navigation. */
const EXCLUDED = ["docs/archive"]

function walk(dir, out = []) {
    const abs = join(ROOT, dir)
    if (!existsSync(abs)) return out
    for (const e of readdirSync(abs, { withFileTypes: true })) {
        if (IGNORED_DIRS.has(e.name)) continue
        const rel = `${dir}/${e.name}`
        if (e.isDirectory()) walk(rel, out)
        else if (extname(rel) === ".md") out.push(rel)
    }
    return out
}

/** GitHub-style heading slug: lowercase, strip punctuation, spaces to dashes. */
function slugify(heading) {
    return heading
        .trim()
        .toLowerCase()
        .replace(/`/g, "")
        .replace(/\[([^\]]*)\]\([^)]*\)/g, "$1")
        .replace(/[*_~]/g, "")
        .replace(/[^\p{L}\p{N}\s-]/gu, "")
        .replace(/\s+/g, "-")
}

/** Headings in a markdown document, as slugs, plus any explicit #anchor ids. */
function anchorSet(file) {
    const text = readFileSync(join(ROOT, file), "utf8")
    const set = new Set()
    for (const m of text.matchAll(/^#{1,6}\s+(.*)$/gm)) set.add(slugify(m[1]))
    // Explicit HTML anchors, e.g. <a id="deploy"></a>
    for (const m of text.matchAll(/<a\s+(?:name|id)=["']([^"']+)["']/gi)) set.add(m[1])
    return set
}

const anchorCache = new Map()
function anchorsFor(file) {
    if (!anchorCache.has(file)) anchorCache.set(file, anchorSet(file))
    return anchorCache.get(file)
}

/**
 * Strip a Markdown link target down to its real path plus optional anchor.
 * Handles `foo.md#bar`, `<foo.md>`, and titles ("foo.md "the title"").
 */
function parseTarget(raw) {
    let t = raw.trim()
    if (t.startsWith("<")) t = t.slice(1, t.replace(/>$/, ""))
    else {
        // Drop an optional "title" suffix.
        t = t.replace(/\s+["'(].*$/, "")
    }
    const hash = t.indexOf("#")
    if (hash === -1) return { path: t, anchor: null }
    return { path: t.slice(0, hash), anchor: t.slice(hash + 1) || null }
}

function isExternal(t) {
    return /^[a-z][a-z0-9+.-]*:/i.test(t) || t.startsWith("//")
}

function main() {
    const asJson = process.argv.includes("--json")
    const files = [
        ...SCAN_ROOT_FILES.filter((f) => existsSync(join(ROOT, f))),
        ...walk("docs"),
    ].filter((f) => !EXCLUDED.some((x) => f.startsWith(`${x}/`)))

    const problems = []
    let links = 0
    let external = 0

    for (const file of files) {
        const text = readFileSync(join(ROOT, file), "utf8")
        const lines = text.split("\n")
        lines.forEach((line, i) => {
            // Inline [text](target); skip images ![alt](src) only if we later
            // want to check image paths -- for links, both are the same shape.
            for (const m of line.matchAll(/!?\[[^\]]*\]\(([^)\s]+(?:\s+"[^"]*")?)\)/g)) {
                const raw = m[1]
                if (isExternal(raw)) {
                    external++
                    continue
                }
                links++
                const { path, anchor } = parseTarget(raw)
                if (path === "") {
                    // Pure in-page anchor: #section
                    if (anchor && !anchorsFor(file).has(anchor)) {
                        problems.push({ file, line: i + 1, target: raw, note: `no heading '${anchor}' in this file` })
                    }
                    continue
                }
                // Resolve relative to the containing file.
                const candidates = path.startsWith("/")
                    ? [join(ROOT, path.replace(/^\//, ""))]
                    : [resolvePath(ROOT, dirname(file), path.replace(/\//g, sep)), join(ROOT, path.replace(/\//g, sep))]
                const hit = candidates.find((c) => existsSync(c) && statSync(c).isFile())
                if (!hit) {
                    problems.push({ file, line: i + 1, target: raw, note: "link target does not exist" })
                    continue
                }
                if (anchor) {
                    const relTarget = relative(ROOT, hit).replace(/\\/g, "/")
                    if (extname(relTarget) === ".md" && !anchorsFor(relTarget).has(anchor)) {
                        problems.push({
                            file,
                            line: i + 1,
                            target: raw,
                            note: `link target exists but has no heading '${anchor}'`,
                        })
                    }
                }
            }
        })
    }

    const excludedCount = walk("docs")
        .filter((f) => EXCLUDED.some((x) => f.startsWith(`${x}/`)))
        .length

    if (asJson) {
        console.log(JSON.stringify({ files: files.length, links, external, excluded: excludedCount, problems }, null, 2))
        process.exit(problems.length > 0 ? 1 : 0)
    }

    console.log("Doc link check\n")
    console.log(`  ${files.length} documents, ${links} local links, ${external} external (skipped)`)
    console.log(`  ${excludedCount} archived documents excluded (historical records, not maintained)\n`)
    if (problems.length === 0) {
        console.log("  ok  every local link resolves")
    } else {
        console.log(`  FAIL  ${problems.length} broken link(s)\n`)
        const byFile = new Map()
        for (const p of problems) {
            if (!byFile.has(p.file)) byFile.set(p.file, [])
            byFile.get(p.file).push(p)
        }
        for (const [file, list] of [...byFile].sort()) {
            console.log(`  ${file}`)
            for (const p of list) console.log(`      ${p.line}: ${p.target}\n            -> ${p.note}`)
            console.log("")
        }
    }
    process.exit(problems.length > 0 ? 1 : 0)
}

main()