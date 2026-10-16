#!/usr/bin/env node
/**
 * check-platform-consistency — assert the consumer apps share identical locked
 * versions for the key packages the platform depends on, AND that every
 * `packages/*` workspace mirror still matches the released upstream tree it is
 * supposed to reflect.
 *
 * Why this exists: the platform ships ONE composer + ONE npm artifact and is
 * consumed by laravel (mono), laravel.shop and laravel.booking. If their
 * composer.lock / package.lock resolve different versions of shared packages,
 * consumers get subtly divergent behavior that only shows up in production —
 * and drift slowly accumulates because nothing enforces parity.
 *
 * This guard also fails on MIRROR DRIFT. `packages/*` are released-package
 * mirrors consumed by the frontend via the pnpm workspace, while PHP resolves
 * from `vendor/nreljed/*`. If one side is refreshed and the other is not, the
 * same repo ships two different versions of the same package with no error from
 * either Composer or pnpm. That is not hypothetical: a package's composer.json
 * once drifted to claim a core version its lock did not contain, and CI only
 * caught it after the PR had already been auto-merged.
 *
 * Source of truth: the mono repo (this repo). Shop/booking must match its
 * locked versions exactly, and mirrors are compared against `vendor/nreljed/*`.
 *
 * Skip policy: an app with no reachable lockfile is reported as SKIPPED, never
 * silently counted as "identical". The banner distinguishes compared from
 * skipped so a guard that compared nothing cannot report success.
 *
 * Exit codes: 0 = ok (skips when a sibling app isn't reachable), 1 = failure.
 */

import fs from "node:fs";
import path from "node:path";
import { execFileSync } from "node:child_process";
import { fileURLToPath } from "node:url";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const root = path.resolve(__dirname, "..");

// Sibling dirs are repo-relative so this check is portable: standalone repos
// (no apps/ subtree) find only their own root and skip; the monorepo resolves
// all three and enforces parity. Never hardcode machine paths here — CI runs
// on self-hosted AND GitHub-hosted runners.
const repoRoot = path.resolve(__dirname, "..");
const defaultDirs = {
  mono: repoRoot,
  shop: path.join(repoRoot, "apps", "laravel.shop"),
  booking: path.join(repoRoot, "apps", "laravel.booking"),
};

const apps = [
  { id: "mono", name: "mono", dir: process.env.CHECK_PLATFORM_MONO ?? defaultDirs.mono },
  { id: "shop", name: "shop", dir: process.env.CHECK_PLATFORM_SHOP ?? defaultDirs.shop },
  {
    id: "booking",
    name: "booking",
    dir: process.env.CHECK_PLATFORM_BOOKING ?? defaultDirs.booking,
  },
];

const reachable = apps.filter((app) => fs.existsSync(app.dir));

// Name whichever app this check runs in so error messages stay clear (the
// equality assertion is symmetric anyway — the label is cosmetic).
for (const app of reachable) {
  if (path.resolve(app.dir) === root) {
    app.name = `${app.name} (this app)`;
  }
}

/**
 * Packages whose locked versions must be identical across all apps.
 * composer side: composer.lock "packages" (+ "packages-dev").
 * npm side: top-level node_modules/<name in package-lock packages>.
 */
const composerKeyPackages = [
  "laravel/framework",
  "inertiajs/inertia-laravel",
  "tightenco/ziggy",
  "laravel/sanctum",
  "laravel/ai",
  "nreljed/platform",
  "nreljed/blog",
  "phpunit/phpunit",
  "nunomaduro/collision",
  "symfony/console",
  "laravel/telescope",
  "aws/aws-sdk-php",
];

const npmKeyPackages = [
  "react",
  "react-dom",
  "@inertiajs/react",
  "@inertiajs/vite",
  "@inertiajs/core",
  "ziggy-js",
  "@base-ui/react",
  "eslint",
  "@typescript-eslint/parser",
  "@typescript-eslint/eslint-plugin",
  "@eslint-react/eslint-plugin",
  "eslint-plugin-react-hooks",
  "@tailwindcss/vite",
  "vite",
  "typescript",
  "vitest",
  "lexical",
  "@lexical/react",
  "lucide-react",
  "next-themes",
  "zod",
  "tailwindcss",
  "@types/react",
  "@types/react-dom",
  "@vitejs/plugin-react",
];

const errors = [];

function readJson(file) {
  try {
    return JSON.parse(fs.readFileSync(file, "utf8"));
  } catch {
    return null;
  }
}

function composerLock(appDir) {
  return readJson(path.join(appDir, "composer.lock"));
}

function composerVersion(lock, pkg) {
  if (!lock) {
    return null;
  }
  for (const section of ["packages", "packages-dev"]) {
    for (const entry of lock[section] ?? []) {
      if (entry.name === pkg) {
        return entry.version;
      }
    }
  }
  return null;
}

/**
 * Resolve a locked npm version. Three layouts exist:
 *
 *  - app-local pnpm-lock.yaml (standalone app repo): the app is the root
 *    importer, so the importer key is '.'.
 *  - workspace pnpm-lock.yaml (the mono): ONE lock at the workspace root with a
 *    per-app importer section keyed by the app's path relative to the workspace
 *    root ('.', 'apps/laravel.shop', 'apps/laravel.booking'). Every consumer
 *    must be read out of THIS file — there is no per-app lock. An earlier
 *    revision looked for `<appDir>/pnpm-lock.yaml`, found none for shop or
 *    booking, and therefore compared every npm key against nothing while still
 *    printing "25 npm keys identical".
 *  - package-lock.json (pre-workspace apps).
 *
 * Versions carry a peer-suffix (e.g. `1.8.0(@base-ui/react@...)(react@19.3.0)`)
 * which must be normalized to the bare version. Workspace links
 * (`link:../../packages/*`) return null — never compared, because a link has no
 * version; mirror freshness is asserted separately by the mirror-drift check.
 */
function pnpmImporterVersion(lockLines, importerKey, pkg) {
  const importersIdx = lockLines.findIndex((line) => line.trim() === "importers:");
  if (importersIdx === -1) {
    return null;
  }
  const escaped = importerKey.replace(/[.*+?^${}()|[\]\\]/g, "\\$&");
  const importerRe = new RegExp(`^ {2}(?:'${escaped}'|${escaped}):$`);

  let start = -1;
  for (let i = importersIdx + 1; i < lockLines.length; i++) {
    // A non-indented line means the importers block is over.
    if (/^\S/.test(lockLines[i])) break;
    if (importerRe.test(lockLines[i])) {
      start = i;
      break;
    }
  }
  if (start === -1) {
    return null;
  }

  // Importer keys sit at indent 2; their bodies are deeper than that.
  const keyRe = /^ {6}(?:'([^']+)'|([^\s:]+)):$/;
  const versionRe = /^ {8}version: (.+)$/;
  for (let i = start + 1; i < lockLines.length; i++) {
    const line = lockLines[i];
    if (/^\S/.test(line) || /^ {2}\S/.test(line)) break;

    const nameMatch = line.match(keyRe);
    if (!nameMatch) continue;
    const name = nameMatch[1] ?? nameMatch[2];
    if (name !== pkg) continue;

    for (let j = i + 1; j < Math.min(i + 4, lockLines.length); j++) {
      const versionMatch = lockLines[j].match(versionRe);
      if (versionMatch) {
        const version = versionMatch[1].trim();
        if (version.startsWith("link:")) return null;
        const paren = version.indexOf("(");
        return paren === -1 ? version : version.slice(0, paren);
      }
    }
    return null;
  }
  return null;
}

function readLines(file) {
  try {
    return fs.readFileSync(file, "utf8").split(/\r?\n/);
  } catch {
    return null;
  }
}

/** Importer key for an app inside the workspace: '.' for the root, else its relative path. */
function importerKeyFor(appDir) {
  const rel = path.relative(repoRoot, path.resolve(appDir)).split(path.sep).join("/");
  return rel === "" ? "." : rel;
}

function npmVersion(appDir, pkg) {
  const lock = readJson(path.join(appDir, "package-lock.json"));
  const entry = lock?.packages?.[`node_modules/${pkg}`];
  return entry?.version ?? null;
}

function lockedNpmVersion(appDir, pkg) {
  const localLock = path.join(appDir, "pnpm-lock.yaml");
  if (fs.existsSync(localLock)) {
    return pnpmImporterVersion(readLines(localLock) ?? [], ".", pkg);
  }
  const workspaceLock = path.join(repoRoot, "pnpm-lock.yaml");
  if (fs.existsSync(workspaceLock)) {
    return pnpmImporterVersion(readLines(workspaceLock) ?? [], importerKeyFor(appDir), pkg);
  }
  return npmVersion(appDir, pkg);
}

if (reachable.length === 1) {
  console.warn("[check-platform-consistency] no sibling apps reachable — skipping.");
  process.exit(0);
}

/**
 * Compare one key across apps, counting how many apps actually produced a
 * version. An app that resolves null is SKIPPED, never counted as agreeing —
 * reporting a skipped key as "identical" is how this guard came to look green
 * while comparing 25 keys against nothing.
 */
function compareAcrossApps(label, keyPackages, resolveVersion, fixHint) {
  let compared = 0;
  let skipped = 0;

  for (const pkg of keyPackages) {
    const versions = reachable.map((app) => ({
      name: app.name,
      version: resolveVersion(app.dir, pkg),
    }));
    const selected = versions[0].version;
    if (selected === null) {
      skipped++;
      continue;
    }
    let resolvedForThisKey = 1;
    for (const { name, version } of versions.slice(1)) {
      if (version === null) {
        skipped++;
        continue;
      }
      resolvedForThisKey++;
      if (version !== selected) {
        errors.push(
          `${label} ${pkg}: mono=${selected} but ${name}=${version} ` +
            `(all apps must resolve identical versions — ${fixHint}).`,
        );
      }
    }
    compared += resolvedForThisKey;
  }

  return { compared, skipped };
}

/**
 * Trees that legitimately differ between a mirror and a vendor install, so
 * comparing them byte-for-byte would produce guaranteed false positives.
 *   - composer.lock / package-lock.json: locks are per-consumer by definition.
 *   - node_modules / vendor: install output, never tracked.
 *   - .github: CI config belongs to the owning repo, not the mirror payload.
 *   - .phpunit.cache / .phpstan / .phpunit.result.cache: local tool caches.
 */
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

/**
 * Normalize away differences that are artifacts of the checkout platform rather
 * than real content drift, so they cannot drown out a genuine difference:
 *   - CRLF vs LF: git autocrlf on Windows rewrites line endings on checkout, so
 *     a mirror can never be byte-identical to a dist zip without normalizing.
 *   - a missing final newline: several upstream files legitimately end without
 *     one, and most editors add it.
 * Anything still differing after normalization is reported verbatim.
 */
function normalizeForCompare(buf) {
  let out = Buffer.alloc(buf.length);
  let j = 0;
  for (let i = 0; i < buf.length; i++) {
    if (buf[i] === 13 && buf[i + 1] === 10) continue;
    out[j++] = buf[i];
  }
  out = out.subarray(0, j);
  if (out.length > 0 && out[out.length - 1] === 10) {
    out = out.subarray(0, out.length - 1);
  }
  return out;
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

function diffTrees(mirrorDir, vendorDir) {
  const mirror = collectFiles(mirrorDir);
  const vendor = collectFiles(vendorDir);
  const onlyMirror = [];
  const onlyVendor = [];
  const contentDiffers = [];

  for (const [rel, buf] of mirror) {
    if (!vendor.has(rel)) {
      onlyMirror.push(rel);
    } else if (!normalizeForCompare(vendor.get(rel)).equals(normalizeForCompare(buf))) {
      contentDiffers.push(rel);
    }
  }
  for (const rel of vendor.keys()) {
    if (!mirror.has(rel)) onlyVendor.push(rel);
  }
  return { onlyMirror, onlyVendor, contentDiffers };
}

/**
 * Locate the released copy of a package. The mono root's vendor/ is declared the
 * source of truth, so it wins; otherwise fall back to any consumer that has it.
 */
function findVendorCopy(leafName) {
  for (const app of reachable) {
    const candidate = path.join(app.dir, "vendor", "nreljed", leafName);
    if (fs.existsSync(candidate)) return { app, dir: candidate };
  }
  return null;
}

function checkMirrorDrift() {
  const packagesDir = path.join(repoRoot, "packages");
  if (!fs.existsSync(packagesDir)) {
    return { checked: 0, skipped: 0 };
  }

  const mirrors = fs
    .readdirSync(packagesDir, { withFileTypes: true })
    .filter((e) => e.isDirectory())
    .map((e) => e.name);
  let checked = 0;
  let skipped = 0;

  for (const leaf of mirrors) {
    const mirrorDir = path.join(packagesDir, leaf);
    const vendor = findVendorCopy(leaf);
    if (!vendor) {
      skipped++;
      continue;
    }

    // The two manifests inside a package must agree with each other. A
    // mismatch is a release bug even if both happen to match vendor.
    const mirrorComposer = readJson(path.join(mirrorDir, "composer.json"))?.version ?? null;
    const mirrorPackage = readJson(path.join(mirrorDir, "package.json"))?.version ?? null;
    const vendorComposer = readJson(path.join(vendor.dir, "composer.json"))?.version ?? null;
    const vendorPackage = readJson(path.join(vendor.dir, "package.json"))?.version ?? null;

    const notes = [];
    if (mirrorComposer && mirrorPackage && mirrorComposer !== mirrorPackage) {
      errors.push(
        `mirror packages/${leaf}: composer.json version ${mirrorComposer} != ` +
          `package.json version ${mirrorPackage} (these must never drift).`,
      );
    }
    if (vendorComposer && mirrorComposer && vendorComposer !== mirrorComposer) {
      notes.push(`composer version mirror=${mirrorComposer} vendor=${vendorComposer}`);
    }
    if (vendorPackage && mirrorPackage && vendorPackage !== mirrorPackage) {
      notes.push(`package version mirror=${mirrorPackage} vendor=${vendorPackage}`);
    }

    const { onlyMirror, onlyVendor, contentDiffers } = diffTrees(mirrorDir, vendor.dir);
    checked++;

    if (onlyMirror.length || onlyVendor.length || contentDiffers.length || notes.length) {
      errors.push(
        `mirror packages/${leaf} has drifted from ${vendor.app.name}'s ` +
          `vendor/nreljed/${leaf}. ` +
          notes.join("; ") +
          ` only-in-mirror=${onlyMirror.length} ` +
          `only-in-vendor=${onlyVendor.length} ` +
          `content-differs=${contentDiffers.length}` +
          ` [e.g. ${[...onlyVendor, ...onlyMirror, ...contentDiffers].slice(0, 5).join(", ")}]. ` +
          "Refresh the mirror from the owning repo (/sync-mirror) — never hand-edit " +
          "version strings to make this pass.",
      );
    }
  }

  return { checked, skipped };
}

/**
 * Files that legitimately differ between an apps/ workspace member and its
 * standalone upstream repo, because the workspace deliberately overrides vendor
 * resolution:
 *   - tsconfig.json / vite.config.js / vitest.config.js: the workspace aliases
 *     @nreljed/* to ../../packages/*, the standalone repo aliases them to
 *     ./vendor/nreljed/*.
 *   - resources/css/app.css: same reason - its @source/@import target the
 *     ./vendor/nreljed/* tree upstream and ../../packages/* in the workspace.
 *   - the lockfiles, which are per-consumer by definition.
 * NOTE: composer.json / package.json are deliberately NOT in this set. Their
 * dependency blocks are the contract that must not drift, so they are compared
 * semantically instead (see APP_MANIFEST_FIELDS) - which still reports a real
 * version bump while ignoring key order, indentation and trailing blank lines.
 */
const APP_INTEGRATION_FILES = new Set([
  "tsconfig.json",
  "vite.config.js",
  "vitest.config.js",
  "resources/css/app.css",
  "composer.lock",
  "package-lock.json",
  "pnpm-lock.yaml",
]);

/**
 * Fields of a manifest that define the app's real dependency contract. Compared
 * with canonical JSON so reordering/indentation is not drift, but a changed
 * version range is. `@nreljed/*` is excluded because the workspace legitimately
 * redeclares those as `workspace:*` instead of the vendor-published range.
 */
const APP_MANIFEST_FIELDS = {
  "composer.json": { fields: ["require", "require-dev", "autoload", "autoload-dev", "extra"] },
  "package.json": {
    fields: ["dependencies", "devDependencies", "engines", "packageManager"],
    ignoreKey: (key) => key.startsWith("@nreljed/"),
  },
};

function canonicalJson(value, ignoreKey) {
  if (Array.isArray(value)) {
    return `[${value.map((item) => canonicalJson(item, ignoreKey)).join(",")}]`;
  }
  if (value && typeof value === "object") {
    const keys = Object.keys(value)
      .sort()
      .filter((key) => !ignoreKey || !ignoreKey(key));
    return `{${keys.map((key) => `${JSON.stringify(key)}:${canonicalJson(value[key], ignoreKey)}`).join(",")}}`;
  }
  return JSON.stringify(value) ?? "null";
}

/**
 * Compare a manifest semantically. Returns true/false when the file is a known
 * manifest, or null to mean "not a manifest, fall back to byte comparison".
 */
function manifestMatches(rel, upstreamBuf, mineBuf) {
  const spec = APP_MANIFEST_FIELDS[rel];
  if (!spec) return null;
  let left;
  let right;
  try {
    left = JSON.parse(upstreamBuf.toString("utf8"));
    right = JSON.parse(mineBuf.toString("utf8"));
  } catch {
    return null;
  }
  for (const field of spec.fields) {
    if (
      canonicalJson(left[field] ?? null, spec.ignoreKey) !==
      canonicalJson(right[field] ?? null, spec.ignoreKey)
    ) {
      return false;
    }
  }
  return true;
}

/** Machine-local state and generated output that is never meaningful to compare. */
const APP_IGNORED_DIRS = new Set([
  ...MIRROR_IGNORED,
  "storage",
  "public",
  "bootstrap",
  ".vscode",
  ".opencode",
  ".playwright-mcp",
  ".kilo",
  "dist",
  "build",
  "sql",
]);
const APP_IGNORED_FILES = new Set([".env", ".phpunit.result.cache", "database.sqlite"]);

function collectAppFiles(dir, base = dir, out = new Map()) {
  let entries;
  try {
    entries = fs.readdirSync(dir, { withFileTypes: true });
  } catch {
    return out;
  }
  for (const entry of entries) {
    if (APP_IGNORED_DIRS.has(entry.name)) continue;
    const full = path.join(dir, entry.name);
    if (entry.isDirectory()) {
      collectAppFiles(full, base, out);
    } else if (entry.isFile()) {
      if (APP_IGNORED_FILES.has(entry.name)) continue;
      const rel = path.relative(base, full).split(path.sep).join("/");
      out.set(rel, fs.readFileSync(full));
    }
  }
  return out;
}

/**
 * Auto-discover a standalone upstream checkout by matching the git origin URL,
 * so the fork check works on a developer machine without anyone remembering to
 * set env vars. Nothing is hardcoded to a machine path: we walk up from the
 * workspace root and accept a sibling directory only if it is a real git repo
 * whose origin is the expected repository. On CI there are no siblings, so this
 * returns null and the check reports SKIPPED exactly as before.
 */
function discoverUpstream(appId) {
    const expected = `nreljed/laravel.${appId}`;
    const seen = new Set();
    let dir = repoRoot;
    for (let depth = 0; depth < 3; depth++) {
        const parent = path.dirname(dir);
        if (!parent || parent === dir) break;
        let entries;
        try {
            entries = fs.readdirSync(parent, { withFileTypes: true });
        } catch {
            dir = parent;
            continue;
        }
        for (const entry of entries) {
            if (!entry.isDirectory() || seen.has(entry.name)) continue;
            const candidate = path.join(parent, entry.name);
            seen.add(entry.name);
            if (!fs.existsSync(path.join(candidate, '.git'))) continue;
            let remote;
            try {
                remote = execFileSync('git', ['-C', candidate, 'remote', 'get-url', 'origin'], {
                    encoding: 'utf8',
                    stdio: ['ignore', 'pipe', 'ignore'],
                }).trim();
            } catch {
                continue;
            }
            if (remote.replace(/\.git$/, '').endsWith(expected)) return candidate;
        }
        dir = parent;
    }
    return null;
}

/**
 * Compare each apps/ workspace member against its standalone upstream checkout,
 * so a workspace copy can never silently become a fork. Upstream locations come
 * from the environment when set, otherwise they are auto-discovered by git
 * origin. CI has no second checkout and reports SKIPPED rather than failing.
 * A missing upstream is always visible in the summary, so a run that compared
 * nothing never reads as quietly green.
 */
function checkAppUpstreamDrift() {
    let compared = 0;
    let skipped = 0;

    for (const app of reachable) {
        if (app.id === 'mono') continue;
        const envKey = `CHECK_PLATFORM_${app.id.toUpperCase()}_UPSTREAM`;
        const upstreamDir = process.env[envKey] || discoverUpstream(app.id);
        if (!upstreamDir || !fs.existsSync(upstreamDir)) {
            skipped++;
            console.log(
                `[check-platform-consistency] SKIP upstream-drift ${app.id} — ` +
                `set ${envKey} to compare against the standalone repo`
            );
            continue;
        }

    const mine = collectAppFiles(app.dir);
    const upstream = collectAppFiles(upstreamDir);
    const drifted = [];
    const missing = [];
    const extra = [];

    for (const [rel, buf] of upstream) {
      if (APP_INTEGRATION_FILES.has(rel)) continue;
      if (!mine.has(rel)) {
        missing.push(rel);
        continue;
      }
      const mineBuf = mine.get(rel);
      const semantic = manifestMatches(rel, buf, mineBuf);
      const equal =
        semantic === null
          ? normalizeForCompare(buf).equals(normalizeForCompare(mineBuf))
          : semantic;
      if (!equal) drifted.push(rel);
    }
    for (const rel of mine.keys()) {
      if (APP_INTEGRATION_FILES.has(rel)) continue;
      if (!upstream.has(rel)) extra.push(rel);
    }

    compared++;
    const total = drifted.length + missing.length + extra.length;
    if (total > 0) {
      errors.push(
        `apps/${path.basename(app.dir)} has forked from its standalone repo ` +
          `(${total} file(s)): content-differs=${drifted.length} ` +
          `missing-from-workspace=${missing.length} only-in-workspace=${extra.length}` +
          (drifted.length ? ` [differs e.g. ${drifted.slice(0, 4).join(", ")}]` : "") +
          (missing.length ? ` [missing e.g. ${missing.slice(0, 4).join(", ")}]` : "") +
          (extra.length ? ` [extra e.g. ${extra.slice(0, 4).join(", ")}]` : "") +
          ". Re-sync apps/ from the standalone repo, or land the change upstream and sync back.",
      );
    } else {
      console.log(
        `[check-platform-consistency] OK upstream-drift ${app.id} — matches ${upstreamDir}`,
      );
    }
  }

  return { compared, skipped };
}

const composerStats = compareAcrossApps(
  "composer",
  composerKeyPackages,
  (dir, pkg) => composerVersion(composerLock(dir), pkg),
  "run `composer update` in the drifters",
);

const npmStats = compareAcrossApps(
  "npm",
  npmKeyPackages,
  lockedNpmVersion,
  "run `pnpm install` at the workspace root",
);

const mirrorStats = checkMirrorDrift();
const appStats = checkAppUpstreamDrift();

if (errors.length > 0) {
  for (const error of errors) {
    console.error(`[check-platform-consistency] FAIL ${error}`);
  }
  console.error(
    `[check-platform-consistency] ${errors.length} drift(s) found across ` +
      `${reachable.map((a) => a.name).join(", ")}.`,
  );
  process.exit(1);
}

const appNames = reachable.map((a) => a.name).join(", ");
console.log(
  `[check-platform-consistency] OK — ${appNames}\n` +
    `  composer : ${composerStats.compared} resolved, ${composerStats.skipped} skipped ` +
    `of ${composerKeyPackages.length} keys\n` +
    `  npm      : ${npmStats.compared} resolved, ${npmStats.skipped} skipped ` +
    `of ${npmKeyPackages.length} keys\n` +
    `  mirrors  : ${mirrorStats.checked} compared against vendor, ${mirrorStats.skipped} skipped\n` +
    `  apps     : ${appStats.compared} compared against upstream, ${appStats.skipped} skipped`,
);
