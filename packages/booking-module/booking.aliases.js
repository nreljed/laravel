/**
 * Booking module frontend alias + refresh helper.
 *
 * Single source of truth for the package's vendor paths that apps must wire
 * into Vite and tsconfig. Import from a consuming app WITHOUT writing vendor
 * paths by hand:
 *
 *   import { bookingAliases, bookingRefreshGlobs } from './vendor/nreljed/booking-module/booking.aliases.js';
 *
 * The aliases are self-locating: they resolve relative to this file, so the
 * package directory rename is a no-op for consumers.
 *
 * Aliases emitted:
 *   '@booking'    -> module Booking resources/js (bookings pipeline page kit)
 *   '@properties' -> module Properties resources/js (property listings page kit)
 *
 * Booking pages import shared platform components through the app's
 * '@dashboard' / '@' aliases (declared by @nreljed/platform platform.aliases.js).
 */
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = import.meta.dirname ?? path.dirname(fileURLToPath(import.meta.url));

export const bookingAliases = () => ({
    '@booking': path.resolve(__dirname, 'src/Booking/Resources/js'),
    '@properties': path.resolve(__dirname, 'src/Properties/Resources/js'),
});

export const bookingRefreshGlobs = () => [
    'vendor/nreljed/booking-module/src/Booking/Resources/js/**',
    'vendor/nreljed/booking-module/src/Properties/Resources/js/**',
];