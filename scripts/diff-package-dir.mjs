#!/usr/bin/env node
/**
 * diff-package-dir — report how a workspace mirror dir (packages/*) differs from a
 * standalone package repo's RELEASED tree.
 *
 * The reference is `git archive <latest-release-tag>` of the upstream repo — the same
 * tree Composer installs into `vendor/nreljed/*`. That means `.gitattributes
 * export-ignore` is honored (so a file that ships in the repo but not in the Composer
 * install, like blog's AGENTS.md, is correctly absent), and a commit ahead of the last
 * release tag is NOT counted. Content is EOL-normalized (CRLF→LF, final newline
 * stripped) exactly like `check-platform-consistency`, so a CRLF checkout never reads
 * as drift. The result therefore agrees with the authoritative gate.
 *
 * Usage:
 *   node scripts/diff-package-dir.mjs <mirror-dir> <repo-dir> [--ref <git-ref>]
 *   --ref  compare against an explicit git ref instead of the latest v* tag.
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
let explicitRef = null;
const positional = [];
for (let i = 0; i < argv.length; i++) {
  if (argv[i] === "--ref") {
    explicitRef = argv[++i];
    continue;
  }
  positional.push(argv[i]);
}

if (positional.length !== 2) {
  console.error("usage: node scripts/diff-package-dir.mjs <mirror-dir> <repo-dir> [--ref <git-ref>]");
  process.exit(1);
}

const mirror = path.resolve(positional[0]);
const repo = path.resolve(positional[1]);

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
  const sort = (a, b) => a.localeCompare(b);

  console.log(
    JSON.stringify(
      {
        ref,
        missing: missing.sort(sort),
        differ: differ.sort(sort),
        excess: excess.sort(sort),
      },
      null,
      2,
    ),
  );
} finally {
  cleanup();
}
