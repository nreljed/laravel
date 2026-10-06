import type { FoundationAppConfig, PageImport } from "@nreljed/platform/app";
import { registerBlogPages } from "@nreljed/blog/pages";
import { registerCoreTemplates } from "@nreljed/platform/dashboard";

/**
Single source of truth for the page resolution map.
Imported by the client entry (app.tsx) so the full page registry
lives in one place. Core/foundation pages are registered via
registerCoreTemplates(); feature modules register their own kits
(registerBlogPages) — both spread here. Project theme overrides are
declared in pageOverrides and always win.
*/
export const pageConfig: FoundationAppConfig = {
  basePages: {
    ...registerCoreTemplates(),
    ...registerBlogPages(),
  },
  pageOverrides: {
    // Project-owned THEME pages — override core's template pages, applied last.
    // Marketing site:
    "public/welcome": () => import("./Pages/Public/Welcome"),
    "public/about": () => import("./Pages/Public/About"),
    "public/services": () => import("./Pages/Public/Services"),
    "public/contact": () => import("./Pages/Public/Contact"),
    // Blog public templates (forked from the core starter theme):
    "blog/public/index": (() => import("./Pages/Blog/Public/Index")) as PageImport,
    "blog/public/show": (() => import("./Pages/Blog/Public/Show")) as PageImport,
  },
};
