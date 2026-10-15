import { createContext, useContext } from "react";

// The active `site-*` theme slug, provided by SiteRoot. Portaled UI (Base UI
// menus, dialogs) mounts on document.body — outside SiteRoot's
// `data-site-theme` wrapper — so it does not inherit the scoped token
// variables. Consumers re-apply the slug to the portaled element so the
// theme tokens (not the neutral :root fallback) resolve inside the portal.
export const SiteThemeContext = createContext<string>("cb360");

export function useSiteTheme(): string {
  return useContext(SiteThemeContext);
}
