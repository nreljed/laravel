/**
 * Platform frontend alias + refresh helper.
 *
 * Single source of truth for the vendor paths apps must wire into Vite and
 * tsconfig. Import from a consuming app WITHOUT hand-writing vendor paths:
 *
 *   import {
 *       platformAliases,
 *       platformRefreshGlobs,
 *       platformDedupeHelper,
 *   } from './vendor/nreljed/platform/platform.aliases.js';
 *
 * The aliases are self-locating: they resolve relative to this file, so the
 * package directory rename is a no-op for consumers.
 *
 * Aliases emitted:
 *   '@dashboard'    -> platform resources/js (component kit + control panel)
 *   '@'             -> platform resources/js (legacy alias; kept for the
 *                       Windows FS workaround — never point it elsewhere or
 *                       every platform component import breaks)
 *
 * Feature modules (blog, shop, booking...) ship their OWN alias helpers and
 * apps spread them alongside this one.
 */
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = import.meta.dirname ?? path.dirname(fileURLToPath(import.meta.url));

const platformJsRoot = path.resolve(__dirname, 'resources/js');

export const platformAliases = () => ({
    '@dashboard': platformJsRoot,
    '@': platformJsRoot,
});

export const platformRefreshGlobs = () => [
    'vendor/nreljed/platform/resources/js/**',
    'vendor/nreljed/platform/src/**/Resources/js/**',
];

export const platformDedupeHelper = () => path.resolve(__dirname, 'vite.dedupe.js');