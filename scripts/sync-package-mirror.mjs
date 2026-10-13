#!/usr/bin/env node
/**
 * sync-package-mirror — reconcile a workspace mirror dir (packages/*) with a
 * standalone package repo so the mirror exactly equals the repo's tracked
 * file set at the same content hash.
 *
 * Why one-way repo→mirror: the standalone repos (nreljed/platform, nreljed/blog,
 * shopping/shop-module, ...) are the source of truth and keep their own release
 * cadence. The workspace mirrors exist so the monorepo apps can consume them via
 * pnpm `workspace:*` and the Vite/tsconfig path maps. Driving the mirror one-way
 * from the repo guarantees the apps test/build against the *published* content,
 * never a divergent mirror copy.
 *
 * Usage:
 *   node scripts/sync-package-mirror.mjs <repo-dir> <mirror-dir> [--dry-run]
 *
 * Exit codes: 0 = mirror already consistent (or synced cleanly), 1 = error.
 * Honors `diff-package-dir.mjs` semantics: tracked = `git ls-files` of the repo;
 * node_modules/vendor/.git are never removed or counted.
 *
 * CAVEAT — this drives the mirror from the upstream's WORKING TREE, not from the
 * tree Composer installs. Copying a CRLF checkout, or an upstream commit that is
 * ahead of the last release tag, will desync the mirror from `vendor/nreljed/*` and
 * fail `check-platform-consistency`. Run it only against an upstream that sits on
 * its released tag, then prove parity with `npm run check:platform-consistency`.
 */
import fs from "node:fs";
import path from "node:path";
import crypto from "node:crypto";
import { execSync } from "node:child_process";

const args = process.argv.slice(2);
const dryRun = args.includes("--dry-run");
const positional = args.filter((a) => a !== "--dry-run");

if (positional.length !== 2) {
  console.error("usage: node scripts/sync-package-mirror.mjs <repo-dir> <mirror-dir> [--dry-run]");
  process.exit(1);
}

const repo = path.resolve(positional[0]);
const mirror = path.resolve(positional[1]);

const IGNORE = new Set(["node_modules", "vendor", ".git"]);

function tracked(repoDir) {
  return execSync(`git -C "${repoDir}" ls-files`, { encoding: "utf8" })
    .split(/\r?\n/)
    .filter(Boolean)
    .map((f) => f.replace(/\//g, path.sep));
}

function hash(file) {
  return crypto.createHash("sha256").update(fs.readFileSync(file)).digest("hex");
}

function readAll(dir, root) {
  const out = [];
  for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
    if (IGNORE.has(entry.name)) continue;
    const f = path.join(dir, entry.name);
    const rel = path.relative(root, f);
    if (entry.isDirectory()) {
      out.push(...readAll(f, root));
    } else {
      out.push(rel);
    }
  }
  return out;
}

const trackedFiles = new Set(tracked(repo));

const missing = [];
const differ = [];
const excess = [];

for (const rel of trackedFiles) {
  const m = path.join(mirror, rel);
  const r = path.join(repo, rel);
  if (!fs.existsSync(m)) {
    missing.push(rel);
  } else if (hash(m) !== hash(r)) {
    differ.push(rel);
  }
}

const mirrorFiles = readAll(mirror, mirror);
for (const rel of mirrorFiles) {
  if (!trackedFiles.has(rel)) {
    excess.push(rel);
  }
}

if (!dryRun && missing.length === 0 && differ.length === 0 && excess.length === 0) {
  console.log(`[sync-package-mirror] OK — ${path.basename(mirror)} already matches ${path.basename(repo)}.`);
  process.exit(0);
}

if (dryRun) {
  console.log(`[sync-package-mirror] DRY-RUN would sync ${path.basename(mirror)} <- ${path.basename(repo)}`);
  console.log(`  missing  : ${missing.length}`);
  console.log(`  differ   : ${differ.length}`);
  console.log(`  excess   : ${excess.length}`);
  for (const rel of [...missing, ...differ]) console.log(`    + ${rel}`);
  for (const rel of excess) console.log(`    - ${rel}`);
  process.exit(0);
}

for (const rel of missing) copy(repo, mirror, rel);
for (const rel of differ) copy(repo, mirror, rel);
for (const rel of excess) remove(mirror, rel);

console.log(`[sync-package-mirror] OK — ${path.basename(mirror)} synced to ${path.basename(repo)}: +${missing.length} copied, +${differ.length} overwritten, -${excess.length} removed.`);

function copy(srcRoot, dstRoot, rel) {
  const src = path.join(srcRoot, rel);
  const dst = path.join(dstRoot, rel);
  fs.mkdirSync(path.dirname(dst), { recursive: true });
  fs.copyFileSync(src, dst);
}

function remove(root, rel) {
  const target = path.join(root, rel);
  fs.rmSync(target, { force: true });
  // Prune empty parent dirs so we don't leave shells behind.
  let dir = path.dirname(target);
  while (dir.startsWith(root) && dir !== root) {
    try {
      fs.rmdirSync(dir);
    } catch {
      break;
    }
    dir = path.dirname(dir);
  }
}