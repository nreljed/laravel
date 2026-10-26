import { describe, expect, it } from "vitest";

import type { PageImport } from "@nreljed/platform/app";
import { registerCoreTemplates } from "@nreljed/platform/dashboard";
import { registerBlogPages } from "@nreljed/blog/pages";
import { registerMarketingPages } from "@nreljed/marketing-module/pages";
import { registerShopPages } from "@nreljed/shop-module/pages";

/**
Mirrors `resources/js/page-config.ts`: every feature module's page kit is
spread exactly once, host-owned pages under ./Pages/** are registered via a
localPages glob (like the platform's own kits), and the host's marketing
overrides are applied last. This locks the contract that every Inertia page
key a controller renders resolves to a real component — regressions (like the
posts kit never being wired) surface here instead of as runtime "Page not
found" errors.
*/
const coreKit = registerCoreTemplates();
const blogKit = registerBlogPages();
const marketingKit = registerMarketingPages();
const shopKit = registerShopPages();

const localPages: Record<string, PageImport> = Object.fromEntries(
  Object.entries(import.meta.glob("./Pages/**/*.tsx") as Record<string, PageImport>)
    .map(([path, load]) => [path.replace(/^\.\/Pages\/(.+)\.tsx$/, "$1").toLowerCase(), load])
    .filter(([key]) => key !== ""),
);

const pageMap = {
  ...coreKit,
  ...blogKit,
  ...marketingKit,
  ...shopKit,
  ...localPages,
  "public/about": () => import("./public/about"),
  "public/services": () => import("./public/services"),
  "public/contact": () => import("./public/contact"),
};

/**
Every Inertia page key rendered by controllers in the SHOP feature module.
*/
const SHOP_PAGE_KEYS = [
  "shop/attributes/index",
  "shop/brands/index",
  "shop/categories/index",
  "shop/coupons/form",
  "shop/coupons/index",
  "shop/currencies/index",
  "shop/customers/index",
  "shop/customers/show",
  "shop/dashboard",
  "shop/my/orders/index",
  "shop/my/orders/show",
  "shop/orders/checkout",
  "shop/orders/confirmation",
  "shop/orders/form",
  "shop/orders/index",
  "shop/orders/show",
  "shop/products/form",
  "shop/products/index",
  "shop/products/show",
  "shop/products/stockhistory",
  "shop/reports/index",
  "shop/reviews/index",
  "shop/settings/index",
  "shop/shippingmethods/index",
  "shop/shippingzones/form",
  "shop/shippingzones/index",
  "shop/storefront/home",
  "shop/storefront/postcheckoutregister",
  "shop/storefront/productdetail",
  "shop/storefront/productgrid",
  "shop/storefront/shoppingcart",
  "shop/tags/index",
  "shop/taxrates/index",
  "shop/webhooks/calls",
  "shop/webhooks/index",
  "shop/wishlist/index",
] as const;

/**
Keys rendered by the POSTS feature module (admin/editor pages).
*/
const POSTS_PAGE_KEYS = [
  "posts/categories/index",
  "posts/create",
  "posts/dashboard",
  "posts/edit",
  "posts/index",
  "posts/media",
  "posts/tags/index",
] as const;

/**
Keys rendered by the MARKETING feature module.

`marketing/sync-health` is dashed on purpose: the controller renders
`Inertia::render('marketing/sync-health')`, and the kit must produce the same
key (lowercasing alone used to yield `marketing/synchealth`, which threw
"Page not found: marketing/sync-health" at runtime).
*/
const MARKETING_PAGE_KEYS = [
  "marketing/analytics",
  "marketing/calendar",
  "marketing/dashboard",
  "marketing/insights",
  "marketing/sync-health",
  "marketing/workflows/builder",
] as const;

/**
Page names some package still declares in unrouted controller methods. They
must never appear in a route - if one is wired up, its page has to be added to
a kit and this entry removed.

`dashboard/admin/settings` is `AdminController::settings()`, which no route
references (the live settings pages are `dashboard/admin/settings/*`).
*/
const UNROUTED_RENDER_KEYS = new Set(["dashboard/admin/settings"]);

/**
Collect every `Inertia::render('...')` page name declared by an installed
feature package. The app resolves pages with `pageMap[name.toLowerCase()]`,
so the comparison below lowercases both sides.

The PHP sources are pulled in as raw modules (not via node:fs) so the test
also type-checks against the host's browser-only tsconfig.
*/
function renderedPageKeys(): string[] {
  const sources = {
    ...(import.meta.glob("../../vendor/nreljed/{platform,blog,shop-module,marketing-module}/src/**/*.php", {
      query: "?raw",
      import: "default",
      eager: true,
    }) as Record<string, string>),
    ...(import.meta.glob("../../vendor/nreljed/{platform,blog,shop-module,marketing-module}/routes/**/*.php", {
      query: "?raw",
      import: "default",
      eager: true,
    }) as Record<string, string>),
  };

  const keys = new Set<string>();

  for (const source of Object.values(sources)) {
    for (const match of source.matchAll(/Inertia::render\(\s*'([^']+)'/g)) {
      const pageName = match[1];

      if (pageName) keys.add(pageName.toLowerCase());
    }
  }

  return [...keys].toSorted((a, b) => a.localeCompare(b));
}

/**
Keys provided by the host-owned page map (forked marketing pages + blog public templates).
*/
const HOST_PAGE_KEYS = [
  "blog/public/index",
  "blog/public/show",
  "public/about",
  "public/contact",
  "public/services",
  "public/welcome",
] as const;

/**
Representative control-panel keys from the core platform kit.
*/
const CORE_PAGE_KEYS = [
  "auth/forgot-password",
  "auth/login",
  "auth/register",
  "dashboard/account/profile",
  "dashboard/admin/settings/general",
  "dashboard/admin/system-health",
  "dashboard/index",
  "dashboard/notifications/index",
  "dashboard/stats",
  "error",
] as const;

describe("feature page kits", () => {
  it("registers every SHOP controller page key under the shop/ prefix", () => {
    for (const key of SHOP_PAGE_KEYS) {
      expect(pageMap, `missing page map entry for ${key}`).toHaveProperty(key);
    }
  });

  it("registers every POSTS admin page key under the posts/ prefix", () => {
    for (const key of POSTS_PAGE_KEYS) {
      expect(pageMap, `missing page map entry for ${key}`).toHaveProperty(key);
    }
  });

  it("registers every MARKETING page key under the marketing/ prefix", () => {
    for (const key of MARKETING_PAGE_KEYS) {
      expect(pageMap, `missing page map entry for ${key}`).toHaveProperty(key);
    }
  });

  it("registers host-owned pages (public marketing + blog/public templates)", () => {
    for (const key of HOST_PAGE_KEYS) {
      expect(pageMap, `missing page map entry for ${key}`).toHaveProperty(key);
    }
  });

  it("resolves every page name a module controller renders", () => {
    const registered = new Set(Object.keys(pageMap).map((key) => key.toLowerCase()));
    const missing = renderedPageKeys()
      .filter((key) => !UNROUTED_RENDER_KEYS.has(key))
      .filter((key) => !registered.has(key));

    expect(missing, `controllers render pages the map does not provide: ${missing.join(", ")}`).toEqual([]);
  });

  it("registers the core control-panel pages", () => {
    for (const key of CORE_PAGE_KEYS) {
      expect(pageMap, `missing page map entry for ${key}`).toHaveProperty(key);
    }
  });

  it("every registered key resolves to a lazy page import", () => {
    for (const [key, importFn] of Object.entries(pageMap)) {
      expect(typeof importFn, `${key} is not a lazy import`).toBe("function");
    }
  });

  it("no two kits shadow the same key (deterministic merge, no silent override)", () => {
    const kits = [coreKit, blogKit, marketingKit, shopKit, localPages];
    const seen = new Set<string>();
    const collisions: string[] = [];

    for (const kit of kits) {
      for (const key of Object.keys(kit)) {
        if (seen.has(key)) collisions.push(key);
        seen.add(key);
      }
    }

    expect(collisions, `kits collide on: ${collisions.join(", ")}`).toEqual([]);
  });

  it("host marketing overrides replace the shared content with shop-specific pages", () => {
    // The public/about|services|contact keys resolve to host-owned functions
    // (not any module kit) — the overrides live in ./public/*, not ./Pages/**.
    const hostOverride = pageMap["public/about"];
    expect(typeof hostOverride).toBe("function");
    expect(localPages).not.toHaveProperty("public/about");
  });
});
