/**
 * Shop module frontend alias + refresh helper.
 *
 * Single source of truth for the package's vendor path that apps must wire
 * into Vite and tsconfig. Import from a consuming app WITHOUT writing vendor
 * paths by hand:
 *
 *   import { shopAliases, shopRefreshGlobs } from './vendor/nreljed/shop-module/shop.aliases.js';
 *
 * The alias is self-locating: it resolves relative to this file, so the
 * package directory rename is a no-op for consumers.
 *
 * Aliases emitted:
 *   '@shop' -> module resources/js (shop dashboard + storefront page kit)
 *
 * Shop pages import shared platform components through the app's '@dashboard' /
 * '@' aliases (declared by @nreljed/platform platform.aliases.js).
 */
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = import.meta.dirname ?? path.dirname(fileURLToPath(import.meta.url));

export const shopAliases = () => ({
    '@shop': path.resolve(__dirname, 'src/Resources/js'),
});

export const shopRefreshGlobs = () => [
    'vendor/nreljed/shop-module/src/Resources/js/**',
];