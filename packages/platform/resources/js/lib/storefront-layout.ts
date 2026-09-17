import type { ComponentType, ReactNode } from "react";

/**
 * A storefront chrome component: the full-page shell every storefront page
 * renders. Host apps call {@link registerStorefrontLayout} once at bootstrap
 * with their own layout (designers edit ONE file); feature modules that ship
 * storefront pages resolve it via {@link resolveStorefrontLayout} instead of
 * hard-importing a module-owned layout. When no layout is registered, the
 * caller falls back to its default chrome.
 */
export type StorefrontLayoutComponent = ComponentType<{ children?: ReactNode }>;

let registeredStorefrontLayout: StorefrontLayoutComponent | null = null;

/**
 * Register the app-level storefront chrome. Must be called before any
 * storefront page renders (i.e. in the client entry, next to the page-config
 * wiring). Calling it again replaces the previous layout — last call wins.
 */
export function registerStorefrontLayout(layout: StorefrontLayoutComponent): void {
    registeredStorefrontLayout = layout;
}

/**
 * Resolve the app-registered storefront chrome, or null when the app has not
 * registered one (callers then render their own default).
 */
export function resolveStorefrontLayout(): StorefrontLayoutComponent | null {
    return registeredStorefrontLayout;
}