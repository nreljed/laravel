/**
 * Marketing module frontend alias + refresh helper.
 *
 * Single source of truth for the package's vendor path that apps must wire
 * into Vite and tsconfig. Import from a consuming app WITHOUT writing vendor
 * paths by hand:
 *
 *   import { marketingAliases, marketingRefreshGlobs } from './vendor/nreljed/marketing-module/marketing.aliases.js';
 *
 * The alias is self-locating: it resolves relative to this file, so the
 * package directory rename is a no-op for consumers.
 *
 * Aliases emitted:
 *   '@marketing' -> module resources/js (marketing dashboard page kit)
 *
 * Marketing pages import shared platform components through the app's
 * '@nreljed/platform' alias (declared by @nreljed/platform platform.aliases.js).
 */
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = import.meta.dirname ?? path.dirname(fileURLToPath(import.meta.url));

export const marketingAliases = () => ({
    '@marketing': path.resolve(__dirname, 'src/Resources/js'),
});

export const marketingRefreshGlobs = () => [
    'vendor/nreljed/marketing-module/src/Resources/js/**',
];
