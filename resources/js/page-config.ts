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
    "public/welcome": () => import("./frontend/pages/public/Home"),
    "public/about": () => import("./frontend/pages/public/About"),
    "public/services": () => import("./frontend/pages/public/Services"),
    "public/contact": () => import("./frontend/pages/public/Contact"),
    "public/styleguide": () => import("./frontend/pages/public/StyleGuide"),
    // Blog public templates (forked from the core starter theme):
    "blog/public/index": (() => import("./frontend/pages/blog/public/Index")) as PageImport,
    "blog/public/show": (() => import("./frontend/pages/blog/public/Show")) as PageImport,
  },
};
