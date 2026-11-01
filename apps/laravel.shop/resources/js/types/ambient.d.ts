// Ambient declarations for packages whose published type definitions are
// unavailable in the npm install. Mirrors the monorepo's pnpm-resolved types.

declare module "libphonenumber-js" {
  export type CountryCode = string;
}
declare module "libphonenumber-js/min" {
  export type CountryCode = string;
}
