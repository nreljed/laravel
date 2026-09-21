/**
 * Blog frontend alias + refresh helper.
 *
 * Single source of truth for the blog package's vendor path that apps must
 * wire into Vite and tsconfig. Import from a consuming app WITHOUT writing
 * vendor paths by hand:
 *
 *   import { blogAliases, blogRefreshGlobs } from './vendor/nreljed/blog/blog.aliases.js';
 *
 * The alias is self-locating: it resolves relative to this file, so the
 * package directory rename is a no-op for consumers.
 *
 * Aliases emitted:
 *   '@blog' -> blog package resources/js (posts dashboard/editor page kit)
 *
 * Blog pages import shared platform components through the app's '@dashboard' /
 * '@' aliases (declared by @nreljed/platform platform.aliases.js).
 */
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = import.meta.dirname ?? path.dirname(fileURLToPath(import.meta.url));

export const blogAliases = () => ({
    '@blog': path.resolve(__dirname, 'src/Resources/js'),
});

export const blogRefreshGlobs = () => [
    'vendor/nreljed/blog/src/Resources/js/**',
];