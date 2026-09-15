#!/usr/bin/env node
/**
 * check-core-deps — verify the installed frontend satisfies the core package's
 * peerDependencies and that the core's npm/composer versions don't drift.
 *
 * Why this exists: `npm ci --legacy-peer-deps` (ci.yml) swallows peer ERESOLVe
 * errors, so a missing/out-of-range dependency would only blow up at build time
 * — or worse, pass `npm run build` against a subtly broken tree. This guard
 * reproduces the loud failure deterministically.
 *
 * It also enforces the release invariant that `nreljed/platform`'s
 * composer.json `version` and package.json `version` never drift (they are
 * bumped together by the core release process; the product ships BOTH
 * ecosystems).
 *
 * Exit codes: 0 = ok (skips when vendor core is not installed), 1 = failure.
 */

import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import semver from 'semver';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const root = path.resolve(__dirname, '..');
const coreDir = path.join(root, 'vendor', 'nreljed', 'platform');

const errors = [];

function readJson(file) {
    try {
        return JSON.parse(fs.readFileSync(file, 'utf8'));
    } catch {
        return null;
    }
}

if (!readJson(path.join(coreDir, 'package.json'))) {
    console.warn('[check-core-deps] vendor core not installed — skipping.');
    process.exit(0);
}

const corePkg = readJson(path.join(coreDir, 'package.json'));
const coreComposer = readJson(path.join(coreDir, 'composer.json'));
const appPkg = readJson(path.join(root, 'package.json'));

// 1. npm/composer version parity.
const composerVersion = coreComposer?.version ?? '(missing)';
const npmVersion = corePkg.version;
if (composerVersion !== npmVersion) {
    errors.push(
        `version drift: composer.json=${composerVersion} vs package.json=${npmVersion} ` +
        `(the core release bumps both together — fix in the core repo, tag v${composerVersion})`
    );
}

// 2. Peer dependency coverage.
const declared = { ...(appPkg?.dependencies ?? {}), ...(appPkg?.devDependencies ?? {}) };
const optional = corePkg.peerDependenciesMeta ?? {};
const peers = corePkg.peerDependencies ?? {};

for (const [name, range] of Object.entries(peers)) {
    if (optional[name]?.optional) {
        continue;
    }

    if (!declared[name]) {
        errors.push(
            `${name} is a core peer (${range}) but is not declared in package.json ` +
            'dependencies — `--legacy-peer-deps` is silently hiding this.'
        );
        continue;
    }

    const installed = readJson(path.join(root, 'node_modules', name, 'package.json'));
    if (!installed) {
        errors.push(`${name} is not installed in node_modules (core requires ${range}).`);
    } else if (!semver.satisfies(installed.version, range)) {
        errors.push(`${name}@${installed.version} does not satisfy core peer range ${range}.`);
    }
}

if (errors.length > 0) {
    for (const error of errors) {
        console.error(`[check-core-deps] FAIL ${error}`);
    }
    console.error(`[check-core-deps] ${errors.length} core dependency problem(s) found.`);
    process.exit(1);
}

console.log(
    `[check-core-deps] OK — ${Object.keys(peers).length} core peers satisfied ` +
    `(core v${npmVersion} / composer v${composerVersion}).`
);
