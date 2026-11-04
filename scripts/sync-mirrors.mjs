#!/usr/bin/env node
/**
 * sync-mirrors - refresh every `packages/<pkg>` workspace mirror from the
 * best available source: vendor/nreljed/<pkg> (Composer-installed, preferred)
 * or the standalone repo clone (fallback for packages not in mono's vendor).
 *
 * Why this exists: `core-update.yml` bumps `composer.lock`, which installs a new
 * `vendor/nreljed/*`, but the pnpm-workspace mirror under `packages/*` is
 * committed to git and was never refreshed. `check-platform-consistency` then
 * compares `packages/<pkg>` against `vendor/nreljed/<pkg>` and fails on any
 * release that changed source files.
 *
 * Standalone repo clones are checked in this order:
 *   1. C:/Users/nrelj/Local Sites/<pkg-name-without-module>  (dev machine)
 *   2. <repo-root>/../<pkg>  (CI runner with sibling clones)
 *
 * Usage:
 *   node scripts/sync-mirrors.mjs            # dry-run: print the plan, change nothing
 *   node scripts/sync-mirrors.mjs --apply    # write the mirrors
 *
 * The dry run is the default on purpose: this tool deletes mirror files that are
 * absent from the source, so an accidental run against a stale vendor/ would
 * silently destroy committed package files. CI passes `--apply` explicitly.
 *
 * The ignore sets are duplicated from scripts/check-platform-consistency.js.
 */

import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const root = path.resolve(__dirname, "..");

const argv = process.argv.slice(2);
const unknown = argv.filter((arg) => arg !== "--apply" && arg !== "--dry-run");
if (unknown.length > 0) {
  console.error(`[sync-mirrors] unknown argument(s): ${unknown.join(", ")}`);
  console.error("[sync-mirrors] usage: node scripts/sync-mirrors.mjs [--apply]");
  process.exit(1);
}
const apply = argv.includes("--apply");

const MIRROR_IGNORED = new Set([
  ".git",
  ".github",
  "node_modules",
  "vendor",
  ".phpunit.cache",
  ".phpunit.result.cache",
  ".phpstan",
  ".kilo",
]);
const MIRROR_IGNORED_FILES = new Set([
  "composer.lock",
  "package-lock.json",
  ".phpunit.result.cache",
]);

// Map package dir name -> standalone repo dir name
const STANDALONE_REPO_MAP = {
  "platform": "platform",
  "blog": "blog",
  "shop-module": "shop-module",
  "marketing-module": "marketing-module",
  "booking-module": "booking-module",
};

const vendorRoot = path.join(root, "vendor", "nreljed");
const packagesRoot = path.join(root, "packages");

// Candidate roots for standalone repo clones
const standaloneRoots = [
  "C:/Users/nrelj/Local Sites",
  path.resolve(root, "..", ".."),
  path.resolve(root, ".."),
];

function normalizeForCompare(buf) {
  const out = Buffer.alloc(buf.length);
  let j = 0;
  for (let i = 0; i < buf.length; i++) {
    if (buf[i] === 13 && buf[i + 1] === 10) continue;
    out[j++] = buf[i];
  }
  let trimmed = out.subarray(0, j);
  if (trimmed.length > 0 && trimmed[trimmed.length - 1] === 10) {
    trimmed = trimmed.subarray(0, trimmed.length - 1);
  }
  return trimmed;
}

function collectFiles(dir, base = dir, out = new Map()) {
  let entries;
  try {
    entries = fs.readdirSync(dir, { withFileTypes: true });
  } catch {
    return out;
  }
  for (const entry of entries) {
    if (MIRROR_IGNORED.has(entry.name)) continue;
    const full = path.join(dir, entry.name);
    if (entry.isDirectory()) {
      collectFiles(full, base, out);
    } else if (entry.isFile()) {
      if (MIRROR_IGNORED_FILES.has(entry.name)) continue;
      const rel = path.relative(base, full).split(path.sep).join("/");
      out.set(rel, fs.readFileSync(full));
    }
  }
  return out;
}

function findSourceDir(pkg) {
  // 1. Prefer mono's vendor/nreljed/<pkg> (Composer-installed, released source of truth)
  const monoVendorDir = path.join(vendorRoot, pkg);
  if (fs.existsSync(monoVendorDir)) return monoVendorDir;

  // 2. Fall back to shop's vendor (for shop-module, marketing-module)
  const shopVendorDir = path.join(root, "apps", "laravel.shop", "vendor", "nreljed", pkg);
  if (fs.existsSync(shopVendorDir)) return shopVendorDir;

  // 3. Fall back to booking's vendor (for booking-module)
  const bookingVendorDir = path.join(root, "apps", "laravel.booking", "vendor", "nreljed", pkg);
  if (fs.existsSync(bookingVendorDir)) return bookingVendorDir;

  // 4. Last resort: standalone repo clone
  const repoName = STANDALONE_REPO_MAP[pkg] || pkg;
  for (const rootDir of standaloneRoots) {
    const candidate = path.join(rootDir, repoName);
    if (fs.existsSync(path.join(candidate, "composer.json")) || fs.existsSync(path.join(candidate, "package.json"))) {
      return candidate;
    }
  }
  return null;
}

function main() {
  if (!fs.existsSync(packagesRoot)) {
    console.log("[sync-mirrors] no packages/ - nothing to do.");
    return;
  }

  let packagesSynced = 0;
  let copiedTotal = 0;
  let deletedTotal = 0;

  for (const entry of fs.readdirSync(packagesRoot, { withFileTypes: true })) {
    if (!entry.isDirectory()) continue;
    const pkg = entry.name;
    const sourceDir = findSourceDir(pkg);
    if (!sourceDir) {
      console.log(`[sync-mirrors] packages/${pkg}: no source found - skipping.`);
      continue;
    }

    if (!sourceDir.replace(/\\/g, "/").includes("/vendor/nreljed/")) {
      console.warn(
        `[sync-mirrors] packages/${pkg}: WARNING using a git clone (${sourceDir}) — ` +
          "export-ignored files and unreleased commits may be copied into the mirror. Prefer `composer update` first, " +
          "or run scripts/sync-package-mirror.mjs, which uses the released tag.",
      );
    }

    const mirrorDir = path.join(packagesRoot, pkg);
    const sourceFiles = collectFiles(sourceDir);
    const mirrorFiles = collectFiles(mirrorDir);

    const writes = [];
    const removals = [];

    for (const [rel, buf] of sourceFiles) {
      const dest = path.join(mirrorDir, rel);
      const current = fs.existsSync(dest) ? fs.readFileSync(dest) : null;
      if (current && normalizeForCompare(current).equals(normalizeForCompare(buf))) continue;
      writes.push({ rel, dest, buf });
    }

    for (const rel of mirrorFiles.keys()) {
      if (sourceFiles.has(rel)) continue;
      removals.push(rel);
    }

    if (apply) {
      for (const { dest, buf } of writes) {
        fs.mkdirSync(path.dirname(dest), { recursive: true });
        fs.writeFileSync(dest, buf);
      }
      for (const rel of removals) {
        fs.rmSync(path.join(mirrorDir, rel), { force: true });
      }
    }

    const verb = apply ? "synced" : "would sync";
    console.log(
      `[sync-mirrors] packages/${pkg}: ${verb} ${writes.length} updated, ${removals.length} removed (from ${sourceDir})`,
    );
    reportPaths(writes.map((w) => `+ ${w.rel}`), removals.map((rel) => `- ${rel}`));

    packagesSynced++;
    copiedTotal += writes.length;
    deletedTotal += removals.length;
  }

  const verb = apply ? "done" : "DRY-RUN (nothing written)";
  console.log(
    `[sync-mirrors] ${verb}: ${packagesSynced} packages, ${copiedTotal} files updated, ${deletedTotal} removed.`,
  );

  if (!apply && copiedTotal + deletedTotal > 0) {
    console.log("[sync-mirrors] re-run with --apply to write these changes.");
  }
}

/** Print at most 20 changed paths, then a count for the remainder. */
function reportPaths(changes, limit = 20) {
  for (const line of changes.slice(0, limit)) {
    console.log(`    ${line}`);
  }
  if (changes.length > limit) {
    console.log(`    ... and ${changes.length - limit} more`);
  }
}

main();