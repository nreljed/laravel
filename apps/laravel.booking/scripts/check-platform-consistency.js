#!/usr/bin/env node
/**
 * check-platform-consistency — assert the 3 consumer apps share identical
 * locked versions for the key packages the platform depends on.
 *
 * Why this exists: the platform ships ONE composer + ONE npm artifact and is
 * consumed by laravel (mono), laravel.shop and laravel.booking. If their
 * composer.lock / package-lock.json resolve different versions of shared
 * packages, consumers get subtly divergent behavior that only shows up in
 * production — and drift slowly accumulates because nothing enforces parity.
 * This guard fails the build when that happens.
 *
 * Source of truth: the mono repo (this repo). Shop/booking must match its
 * locked versions exactly.
 *
 * Exit codes: 0 = ok (skips when a sibling app isn't reachable), 1 = failure.
 */

import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const root = path.resolve(__dirname, '..');

// Sibling dirs are repo-relative so this check is portable: standalone repos
// (no apps/ subtree) find only their own root and skip; the monorepo resolves
// all three and enforces parity. Never hardcode machine paths here - CI runs
// on self-hosted AND GitHub-hosted runners.
const repoRoot = path.resolve(__dirname, '..');
const defaultDirs = {
    mono: repoRoot,
    shop: path.join(repoRoot, 'apps', 'laravel.shop'),
    booking: path.join(repoRoot, 'apps', 'laravel.booking'),
};

const apps = [
    { name: 'mono', dir: process.env.CHECK_PLATFORM_MONO ?? defaultDirs.mono },
    { name: 'shop', dir: process.env.CHECK_PLATFORM_SHOP ?? defaultDirs.shop },
    { name: 'booking', dir: process.env.CHECK_PLATFORM_BOOKING ?? defaultDirs.booking },
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
    'laravel/framework',
    'inertiajs/inertia-laravel',
    'tightenco/ziggy',
    'laravel/sanctum',
    'laravel/ai',
    'nreljed/platform',
    'nreljed/blog',
    'phpunit/phpunit',
    'nunomaduro/collision',
    'symfony/console',
    'laravel/telescope',
    'aws/aws-sdk-php',
];

const npmKeyPackages = [
    'react',
    'react-dom',
    '@inertiajs/react',
    '@inertiajs/vite',
    '@inertiajs/core',
    'ziggy-js',
    '@base-ui/react',
    'eslint',
    '@typescript-eslint/parser',
    '@typescript-eslint/eslint-plugin',
    '@eslint-react/eslint-plugin',
    'eslint-plugin-react-hooks',
    '@tailwindcss/vite',
    'vite',
    'typescript',
    'vitest',
    'lexical',
    '@lexical/react',
    'lucide-react',
    'next-themes',
    'zod',
    'tailwindcss',
    '@types/react',
    '@types/react-dom',
    '@vitejs/plugin-react',
];

const errors = [];

function readJson(file) {
    try {
        return JSON.parse(fs.readFileSync(file, 'utf8'));
    } catch {
        return null;
    }
}

function composerLock(appDir) {
    return readJson(path.join(appDir, 'composer.lock'));
}

function composerVersion(lock, pkg) {
    if (!lock) {
        return null;
    }
    for (const section of ['packages', 'packages-dev']) {
        for (const entry of lock[section] ?? []) {
            if (entry.name === pkg) {
                return entry.version;
            }
        }
    }
    return null;
}

function npmVersion(appDir, pkg) {
    const lock = readJson(path.join(appDir, 'package-lock.json'));
    const entry = lock?.packages?.[`node_modules/${pkg}`];
    return entry?.version ?? null;
}

if (reachable.length === 1) {
    console.warn('[check-platform-consistency] no sibling apps reachable — skipping.');
    process.exit(0);
}
for (const pkg of composerKeyPackages) {
    const versions = reachable.map((app) => ({
        name: app.name,
        version: composerVersion(composerLock(app.dir), pkg),
    }));
    const selected = versions[0].version;
    if (selected === null) {
        continue;
    }
    for (const { name, version } of versions.slice(1)) {
        if (version !== null && version !== selected) {
            errors.push(
                `composer ${pkg}: mono=${selected} but ${name}=${version} ` +
                '(all apps must resolve identical versions — run `composer update` in the drifters).'
            );
        }
    }
}

// npm parity.
for (const pkg of npmKeyPackages) {
    const versions = reachable.map((app) => ({
        name: app.name,
        version: npmVersion(app.dir, pkg),
    }));
    const selected = versions[0].version;
    if (selected === null) {
        continue;
    }
    for (const { name, version } of versions.slice(1)) {
        if (version !== null && version !== selected) {
            errors.push(
                `npm ${pkg}: mono=${selected} but ${name}=${version} ` +
                '(all apps must resolve identical versions — run `npm install` in the drifters).'
            );
        }
    }
}

if (errors.length > 0) {
    for (const error of errors) {
        console.error(`[check-platform-consistency] FAIL ${error}`);
    }
    console.error(`[check-platform-consistency] ${errors.length} cross-app drift(s) found.`);
    process.exit(1);
}

console.log(
    `[check-platform-consistency] OK — ${composerKeyPackages.length} composer + ` +
    `${npmKeyPackages.length} npm keys identical across ${reachable.map((a) => a.name).join(', ')}.`
);