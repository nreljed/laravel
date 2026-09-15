import type { FoundationAppConfig, PageImport } from "@nreljed/platform/app";
import { registerCoreTemplates } from "@nreljed/platform/dashboard";
import { registerBlogPages } from "@nreljed/blog/pages";
import { registerShopPages } from "@nreljed/shop-module/pages";

/**
 * Host-owned pages under ./Pages/** — forks of the core starter theme's
 * marketing/blog templates (the theme kit is no longer spread). Keys are the
 * Inertia page names controllers render, lowercased, e.g. Pages/Blog/Public/Index.tsx
 * → blog/public/index.
 */
const localPages: Record<string, PageImport> = Object.fromEntries(
  Object.entries(import.meta.glob("./Pages/**/*.tsx") as Record<string, PageImport>)
    .map(([path, load]) => [path.replace(/^\.\/Pages\/(.+)\.tsx$/, "$1").toLowerCase(), load])
    .filter(([key]) => key !== ""),
);

/**
 * Host-owned public marketing pages override the forked-then-customized templates.
 */
export const pageOverrides: Record<string, PageImport> = {
  "public/about": () => import("./public/about"),
  "public/services": () => import("./public/services"),
  "public/contact": () => import("./public/contact"),
};

/**
 * Feature module page kits are explicit but modular: each module ships a
 * `register{Name}Pages()` export that is spread here exactly once. Core ships
 * the control-panel kit; Posts and Shop contribute their own prefixes
 * (posts/*, shop/*); localPages contributes the host's forked marketing pages.
 */
export const pageConfig: FoundationAppConfig = {
  basePages: {
    ...registerCoreTemplates(),
    ...registerBlogPages(),
    ...registerShopPages(),
    ...localPages,
  },
  pageOverrides,
};
