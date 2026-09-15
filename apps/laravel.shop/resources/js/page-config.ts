import type { FoundationAppConfig, PageImport } from "@dashboard/app";
import { registerCoreTemplates } from "@dashboard/dashboard";
import { registerStarterThemePages } from "@dashboard-themes/starter";
import { registerBlogPages } from "@blog/pages";
import { registerShopPages } from "@shop/pages";

/**
Host-owned public marketing pages override the starter theme's fork-me templates.
*/
export const pageOverrides: Record<string, PageImport> = {
  "public/about": () => import("./public/about"),
  "public/services": () => import("./public/services"),
  "public/contact": () => import("./public/contact"),
};

/**
 * Feature module page kits are explicit but modular: each module ships a
 * `register{Name}Pages()` export that is spread here exactly once. Core ships
 * the control-panel kit; the starter theme ships template pages; Posts and Shop
 * contribute their own prefixes (posts/*, shop/*).
 */
export const pageConfig: FoundationAppConfig = {
  basePages: {
    ...registerCoreTemplates(),
    ...registerStarterThemePages(),
    ...registerBlogPages(),
    ...registerShopPages(),
  },
  pageOverrides,
};
