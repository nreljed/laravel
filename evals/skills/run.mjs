#!/usr/bin/env node
/**
 * Skill routing eval runner.
 *
 * Validates that every skill still advertises the vocabulary its routing depends
 * on. See ./cases.mjs for why this is a description-quality eval rather than a
 * model-behaviour eval.
 *
 * Reads .agents/skills/<name>/SKILL.md (project skills) and
 * .opencode/skills/<name>/SKILL.md if that directory exists (vendored global
 * skills). Fails if a case expects a skill that does not exist, if a required
 * term is missing from a description, or if a disambiguation case finds its
 * forbidden vocabulary present.
 *
 * Also reports routing coverage: skills with no case at all, which are skills
 * nobody has asserted should ever match anything.
 *
 * Usage:
 *   node evals/skills/run.mjs
 *   node evals/skills/run.mjs --json
 */

import { readdirSync, readFileSync, existsSync } from "node:fs"
import { join, dirname, extname } from "node:path"
import { fileURLToPath } from "node:url"
import { CASES } from "./cases.mjs"

const ROOT = join(dirname(fileURLToPath(import.meta.url)), "..", "..")

const SKILL_DIRS = [".agents/skills", ".opencode/skills"]

/** Load name -> { description, file } for every discoverable skill. */
function loadSkills() {
    const skills = new Map()
    for (const dir of SKILL_DIRS) {
        const abs = join(ROOT, dir)
        if (!existsSync(abs)) continue
        for (const entry of readdirSync(abs, { withFileTypes: true })) {
            if (!entry.isDirectory()) continue
            const file = join(abs, entry.name, "SKILL.md")
            if (!existsSync(file)) continue
            const text = readFileSync(file, "utf8")
            const fm = /^---\r?\n([\s\S]*?)\r?\n---/.exec(text)
            if (!fm) continue
            const name = /^name:\s*(.+)$/m.exec(fm[1])?.[1]?.trim() ?? entry.name
            const description = /^description:\s*([\s\S]*?)(?=\n[a-z_-]+:|$)/m.exec(fm[1])?.[1]?.trim() ?? ""
            skills.set(name, { name, description, file: `${dir}/${entry.name}/SKILL.md` })
        }
    }
    return skills
}

/** Lowercase, drop punctuation and collapse whitespace. */
function norm(s) {
    return s
        .toLowerCase()
        .replace(/[`"'*_]/g, "")
        .replace(/[^a-z0-9+#.\s-]/g, " ")
        .replace(/\s+/g, " ")
        .trim()
}

function main() {
    const asJson = process.argv.includes("--json")
    const skills = loadSkills()
    const results = []

    for (const c of CASES) {
        const skill = skills.get(c.expect)
        if (!skill) {
            results.push({ ...c, pass: false, reason: `skill "${c.expect}" does not exist` })
            continue
        }
        // The router sees name + description before loading the body.
        const haystack = norm(`${skill.name} ${skill.description}`)

        const missing = (c.requiredTerms ?? []).filter((t) => !haystack.includes(norm(t)))
        // Forbidden vocabulary that leaked into this skill's description.
        const leaked = (c.absentTerms ?? []).filter((t) => haystack.includes(norm(t)))

        let reason = null
        if (missing.length > 0) {
            reason = `description is missing routing term(s): ${missing.join(", ")}`
        } else if (leaked.length > 0) {
            reason = `description advertises unrelated vocabulary: ${leaked.join(", ")}`
        }
        results.push({ ...c, pass: reason === null, reason, missing, leaked })
    }

    const covered = new Set(CASES.map((c) => c.expect))
    const uncovered = [...skills.keys()].filter((n) => !covered.has(n)).sort()
    const failed = results.filter((r) => !r.pass)

    if (asJson) {
        console.log(JSON.stringify({ skills: skills.size, cases: results.length, failed: failed.length, results, uncovered }, null, 2))
        process.exit(failed.length > 0 ? 1 : 0)
    }

    console.log("Skill routing eval\n")
    console.log(`  ${skills.size} skills discovered, ${results.length} cases\n`)
    for (const r of results) {
        console.log(`  ${r.pass ? "ok  " : "FAIL"}  ${r.id.padEnd(30)} -> ${r.expect}`)
        if (r.reason) console.log(`          ${r.reason}`)
    }
    console.log("")
    if (uncovered.length > 0) {
        console.log(`  warn  ${uncovered.length} skill(s) have no routing case: ${uncovered.join(", ")}`)
        console.log("        A skill with no case is a skill nobody asserted should match.")
    }
    if (failed.length === 0) {
        console.log(`\n  ok  all ${results.length} routing cases hold`)
    } else {
        console.log(`\n  FAIL  ${failed.length}/${results.length} routing cases broken`)
        console.log("  A failure means the skill's description no longer carries the words")
        console.log("  the router matches on. Fix the description, or fix the case if the")
        console.log("  skill was intentionally renamed or rescoped.")
    }
    process.exit(failed.length > 0 ? 1 : 0)
}

main()