import type { FoundationAppConfig, PageImport } from "@dashboard/app";
import { registerCoreTemplates } from "@dashboard/dashboard";

/**
 * Single source of truth for the page resolution map.
 * Imported by both app.tsx (client) and ssr.tsx (server) so they
 * always resolve the same pages — preventing hydration mismatches.
 */
export const pageConfig: FoundationAppConfig = {
  basePages: registerCoreTemplates(),
  projectPages: import.meta.glob("@test-posts/Pages/**/*.tsx") as Record<string, PageImport>,
  projectPrefix: "testposts",
  pageOverrides: {
    "public/welcome": () => import("./Pages/Public/Welcome"),
    "public/about": () => import("./Pages/Public/About"),
    "public/services": () => import("./Pages/Public/Services"),
    "public/contact": () => import("./Pages/Public/Contact"),
  },
};
