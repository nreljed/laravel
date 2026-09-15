#!/usr/bin/env node
/**
 * switch-composer-repos — toggle the nreljed/* package repositories in each
 * consumer app's composer.json between local `path` repos (dev iteration) and
 * GitHub `vcs` repos (CI / release builds).
 *
 * Why this exists: the workspace owns packages/* as real source, so in dev we
 * want `composer update nreljed/platform` to resolve straight from
 * packages/platform (no tag round-trip). Release artifacts must stay
 * self-contained, so before a release build the apps flip back to vcs tags.
 *
 * Usage:
 *   node scripts/switch-composer-repos.js            # show current mode for each app
 *   node scripts/switch-composer-repos.js path       # dev: resolve from packages/*
 *   node scripts/switch-composer-repos.js vcs        # prod/CI: resolve from GitHub tags
 *   node scripts/switch-composer-repos.js path shop  # filter to specific apps
 *
 * After switching you must re-resolve the touched packages so the lock's
 * content-hash tracks composer.json:
 *   composer update nreljed/platform nreljed/blog [nreljed/shop-module] [...]
 *
 * Exit codes: 0 = ok, 1 = failure.
 */

import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const root = path.resolve(__dirname, '..');

const defaultDirs = {
    mono: root,
    shop: path.join(root, 'apps', 'laravel.shop'),
    booking: path.join(root, 'apps', 'laravel.booking'),
};

const packages = {
    platform: { dir: 'packages/platform', repo: 'https://github.com/nreljed/platform.git' },
    blog: { dir: 'packages/blog', repo: 'https://github.com/nreljed/blog.git' },
    'shop-module': { dir: 'packages/shop-module', repo: 'https://github.com/nreljed/shop-module.git' },
    'booking-module': { dir: 'packages/booking-module', repo: 'https://github.com/nreljed/booking-module.git' },
};

const apps = [
    { name: 'mono', dir: process.env.SWITCH_COMPOSER_MONO ?? defaultDirs.mono, names: ['platform', 'blog'] },
    { name: 'shop', dir: process.env.SWITCH_COMPOSER_SHOP ?? defaultDirs.shop, names: ['platform', 'blog', 'shop-module'] },
    { name: 'booking', dir: process.env.SWITCH_COMPOSER_BOOKING ?? defaultDirs.booking, names: ['platform', 'blog', 'booking-module'] },
];

const mode = process.argv[2];
const filter = process.argv.slice(3);
const reachable = apps.filter((app) => fs.existsSync(path.join(app.dir, 'composer.json')));
const selected = filter.length > 0 ? reachable.filter((app) => filter.includes(app.name)) : reachable;

function posix(relative) {
    return relative.split(path.sep).join('/');
}

function relativePackagesDir(app, pkg) {
    const from = path.relative(app.dir, path.join(root, packages[pkg].dir));
    return posix(from);
}

function currentMode(composer, app) {
    for (const repo of composer.repositories ?? []) {
        for (const name of app.names) {
            if (repo.url?.includes(`nreljed/${name}.git`)) {
                return 'vcs';
            }
            if (repo.url?.includes(`packages/${packages[name].dir.replace('packages/', '')}`)) {
                return 'path';
            }
        }
    }
    return 'unknown';
}

function indentOf(text) {
    const match = text.match(/\n(\s+)"repositories"/);
    return match ? match[1] : '    ';
}

function buildRepositories(app, composer) {
    const existing = composer.repositories
        ? composer.repositories.filter(
              (repo) => !app.names.some((name) => repo.url?.includes(`nreljed/${name}.git`) || repo.url?.includes(`packages/${packages[name].dir.replace('packages/', '')}`))
          )
        : [];
    if (mode === 'vcs') {
        for (const name of app.names) {
            existing.push({ type: 'vcs', url: packages[name].repo });
        }
    } else {
        for (const name of app.names) {
            existing.push({ type: 'path', url: relativePackagesDir(app, name) });
        }
    }
    return existing;
}

if (!mode) {
    for (const app of reachable) {
        const composer = JSON.parse(fs.readFileSync(path.join(app.dir, 'composer.json'), 'utf8'));
        console.log(`[switch-composer-repos] ${app.name}: repositories = ${currentMode(composer, app)}`);
    }
    console.log('[switch-composer-repos] usage: node scripts/switch-composer-repos.js <path|vcs> [app...]');
    process.exit(0);
}

if (mode !== 'path' && mode !== 'vcs') {
    console.error(`[switch-composer-repos] unknown mode "${mode}" — expected "path" or "vcs".`);
    process.exit(1);
}

for (const app of selected) {
    const file = path.join(app.dir, 'composer.json');
    const source = fs.readFileSync(file, 'utf8');
    const composer = JSON.parse(source);
    const before = currentMode(composer, app);
    if (before === mode) {
        console.log(`[switch-composer-repos] ${app.name}: already in ${mode} mode — nothing to do.`);
        continue;
    }
    composer.repositories = buildRepositories(app, composer);
    const indent = indentOf(source);
    fs.writeFileSync(file, `${JSON.stringify(composer, null, indent.length)}\n`);
    const names = app.names.join(' ');
    console.log(`[switch-composer-repos] ${app.name}: ${before} -> ${mode}. Next: composer update ${names} (updates the lock's content-hash).`);
}

if (selected.length === 0) {
    console.warn('[switch-composer-repos] no app matched the filter.');
}