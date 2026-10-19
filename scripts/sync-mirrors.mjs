#!/usr/bin/env node
/**
 * sync-mirrors — refresh every `packages/<pkg>` workspace mirror from the
 * installed `vendor/nreljed/<pkg>` tree, which is the released source of truth.
 *
 * Why this exists: `core-update.yml` bumps `composer.lock`, which installs a new
 * `vendor/nreljed/*`, but the pnpm-workspace mirror under `packages/*` is
 * committed to git and was never refreshed. `check-platform-consistency` then
 * compares `packages/<pkg>` against `vendor/nreljed/<pkg>` and fails on any
 * release that changed source files — so the job could never pass and the mono
 * silently stayed a core release behind. This script is that missing half.
 *
 * It copies vendor -> mirror honoring the SAME ignore sets the gate uses, and
 * deletes mirror files the release no longer ships, so repo state after this
 * script is byte-identical to the gate's view of vendor.
 *
 * The ignore sets are duplicated from scripts/check-platform-consistency.js
 * (which is a top-level-executing ESM file and cannot be imported). If a gate
 * ignore is added there, add it here too.
 */

import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const root = path.resolve(__dirname, "..");

// Keep in sync with MIRROR_IGNORED / MIRROR_IGNORED_FILES in
// scripts/check-platform-consistency.js.
const MIRROR_IGNORED = new Set([
  ".git",
  ".github",
  "node_modules",
  "vendor",
  ".phpunit.cache",
  ".phpunit.result.cache",
  ".phpstan",
]);
const MIRROR_IGNORED_FILES = new Set([
  "composer.lock",
  "package-lock.json",
  ".phpunit.result.cache",
]);

const vendorRoot = path.join(root, "vendor", "nreljed");
const packagesRoot = path.join(root, "packages");

/** Collapse CRLF->LF and a trailing newline, matching the gate's normalizer. */
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

function main() {
  if (
    !fs.existsSync(path.join(root, "vendor", "nreljed")) ||
    !fs.existsSync(path.join(root, "packages"))
  ) {
    console.log("[sync-mirrors] no vendor/nreljed or packages/ — nothing to do.");
    return;
  }

  let packagesSynced = 0;
  let copiedTotal = 0;
  let deletedTotal = 0;

  for (const entry of fs.readdirSync(path.join(root, "packages"), { withFileTypes: true })) {
    if (!entry.isDirectory()) continue;
    const pkg = entry.name;
    const vendorDir = path.join(root, "vendor", "nreljed", pkg);
    if (!fs.existsSync(vendorDir)) continue;

    const mirrorDir = path.join(root, "packages", pkg);
    const vendorFiles = collectFiles(vendorDir);
    const mirrorFiles = collectFiles(mirrorDir);

    let copied = 0;
    let deleted = 0;

    for (const [rel, buf] of vendorFiles) {
      const dest = path.join(mirrorDir, rel);
      const current = fs.existsSync(dest) ? fs.readFileSync(dest) : null;
      if (current && normalizeForCompare(current).equals(normalizeForCompare(buf))) continue;
      fs.mkdirSync(path.dirname(dest), { recursive: true });
      fs.writeFileSync(dest, buf);
      copied++;
    }

    for (const rel of mirrorFiles.keys()) {
      if (vendorFiles.has(rel)) continue;
      fs.rmSync(path.join(mirrorDir, rel), { force: true });
      deleted++;
    }

    console.log(`[sync-mirrors] packages/${pkg}: ${copied} updated, ${deleted} removed`);
  }
}

main();
