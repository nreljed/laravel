#!/usr/bin/env node
/**
 * sync-package-mirror — reconcile a workspace mirror dir (packages/*) with a
 * standalone package repo's RELEASED tree.
 *
 * Why one-way repo→mirror: the standalone repos (nreljed/platform, nreljed/blog,
 * nreljed/shop-module, ...) are the source of truth and keep their own release
 * cadence. The workspace mirrors exist so the monorepo apps can consume them via
 * pnpm `workspace:*` and the Vite/tsconfig path maps. Driving the mirror from the
 * upstream guarantees the apps test/build against the *published* content, never a
 * divergent mirror copy.
 *
 * Reference = `git archive <latest-release-tag>` (export-ignore honored) — the same
 * tree Composer installs into `vendor/nreljed/*`. This is deliberately NOT the raw
 * working tree: that would copy a CRLF checkout, export-ignored files (e.g. blog's
 * AGENTS.md), and unreleased commits, and desync the mirror from the gate. Files are
 * written exactly as the release contains them, and parity is proven with
 * `npm run check:platform-consistency` (mirror vs vendor).
 *
 * Usage:
 *   node scripts/sync-package-mirror.mjs <repo-dir> <mirror-dir> [--dry-run] [--ref <git-ref>]
 *   --ref  sync from an explicit git ref instead of the latest v* tag.
 *
 * Exit codes: 0 = mirror already consistent (or synced cleanly), 1 = error.
 * node_modules/vendor/.git are never removed or counted.
 */
import fs from "node:fs";
import os from "node:os";
import path from "node:path";
import crypto from "node:crypto";
import { execFileSync } from "node:child_process";

const IGNORE_DIRS = new Set([
  ".git",
  ".github",
  "node_modules",
  "vendor",
  ".phpunit.cache",
  ".phpunit.result.cache",
  ".phpstan",
]);
const IGNORE_FILES = new Set(["composer.lock", "package-lock.json", ".phpunit.result.cache"]);

const argv = process.argv.slice(2);
const dryRun = argv.includes("--dry-run");
let explicitRef = null;
const positional = [];
for (let i = 0; i < argv.length; i++) {
  if (argv[i] === "--dry-run") continue;
  if (argv[i] === "--ref") {
    explicitRef = argv[++i];
    continue;
  }
  positional.push(argv[i]);
}

if (positional.length !== 2) {
  console.error(
    "usage: node scripts/sync-package-mirror.mjs <repo-dir> <mirror-dir> [--dry-run] [--ref <git-ref>]",
  );
  process.exit(1);
}

const repo = path.resolve(positional[0]);
const mirror = path.resolve(positional[1]);

/** Latest `v*` release tag (version-sorted), else HEAD. */
function latestReleaseRef(repoDir) {
  try {
    const out = execFileSync("git", ["-C", repoDir, "tag", "-l", "v*", "--sort=-v:refname"], {
      encoding: "utf8",
      stdio: ["ignore", "pipe", "ignore"],
    }).trim();
    if (out) return out.split(/\r?\n/)[0].trim();
  } catch {
    // fall through to HEAD
  }
  return "HEAD";
}

/** Materialize `git archive <ref>` (export-ignore honored) into a temp dir. */
function materializeRef(repoDir, ref) {
  const tmp = fs.mkdtempSync(path.join(os.tmpdir(), "pkg-release-"));
  const tree = path.join(tmp, "tree");
  fs.mkdirSync(tree);
  const tarPath = path.join(tmp, "release.tar");
  fs.writeFileSync(tarPath, execFileSync("git", ["-C", repoDir, "archive", "--format=tar", ref], {
    maxBuffer: 512 * 1024 * 1024,
  }));
  execFileSync("tar", ["-xf", tarPath, "-C", tree], { maxBuffer: 512 * 1024 * 1024 });
  fs.rmSync(tarPath, { force: true });
  return { tree, cleanup: () => fs.rmSync(tmp, { recursive: true, force: true }) };
}

/** EOL-normalize like check-platform-consistency: drop CRLF, strip one trailing LF. */
function normalize(buf) {
  const out = Buffer.alloc(buf.length);
  let j = 0;
  for (let i = 0; i < buf.length; i++) {
    if (buf[i] === 13 && buf[i + 1] === 10) continue;
    out[j++] = buf[i];
  }
  let end = j;
  if (end > 0 && out[end - 1] === 10) end--;
  return out.subarray(0, end);
}

function sha(buf) {
  return crypto.createHash("sha256").update(normalize(buf)).digest("hex");
}

function walk(dir, root = dir, out = []) {
  for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
    if (entry.isDirectory()) {
      if (IGNORE_DIRS.has(entry.name)) continue;
      walk(path.join(dir, entry.name), root, out);
    } else if (entry.isFile()) {
      if (IGNORE_FILES.has(entry.name)) continue;
      out.push(path.relative(root, path.join(dir, entry.name)));
    }
  }
  return out;
}

const ref = explicitRef ?? latestReleaseRef(repo);
const { tree, cleanup } = materializeRef(repo, ref);

try {
  const refFiles = new Set(walk(tree));
  const missing = [];
  const differ = [];

  for (const rel of refFiles) {
    const m = path.join(mirror, rel);
    if (!fs.existsSync(m)) {
      missing.push(rel);
    } else if (sha(fs.readFileSync(m)) !== sha(fs.readFileSync(path.join(tree, rel)))) {
      differ.push(rel);
    }
  }

  const excess = walk(mirror).filter((rel) => !refFiles.has(rel));

  if (!dryRun && missing.length === 0 && differ.length === 0 && excess.length === 0) {
    console.log(
      `[sync-package-mirror] OK — ${path.basename(mirror)} already matches ${path.basename(repo)} @ ${ref}.`,
    );
    process.exit(0);
  }

  if (dryRun) {
    console.log(
      `[sync-package-mirror] DRY-RUN would sync ${path.basename(mirror)} <- ${path.basename(repo)} @ ${ref}`,
    );
    console.log(`  missing  : ${missing.length}`);
    console.log(`  differ   : ${differ.length}`);
    console.log(`  excess   : ${excess.length}`);
    for (const rel of [...missing, ...differ]) console.log(`    + ${rel}`);
    for (const rel of excess) console.log(`    - ${rel}`);
    process.exit(0);
  }

  for (const rel of [...missing, ...differ]) {
    const dst = path.join(mirror, rel);
    fs.mkdirSync(path.dirname(dst), { recursive: true });
    fs.copyFileSync(path.join(tree, rel), dst);
  }
  for (const rel of excess) {
    remove(mirror, rel);
  }

  console.log(
    `[sync-package-mirror] OK — ${path.basename(mirror)} synced to ${path.basename(repo)} @ ${ref}: ` +
      `+${missing.length} copied, +${differ.length} overwritten, -${excess.length} removed.`,
  );
} finally {
  cleanup();
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
