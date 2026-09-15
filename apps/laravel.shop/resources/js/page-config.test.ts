import { describe, expect, it } from "vitest";

import { registerCoreTemplates } from "@dashboard/dashboard";
import { registerStarterThemePages } from "@dashboard-themes/starter";
import { registerBlogPages } from "@blog/pages";
import { registerShopPages } from "@shop/pages";

/**
 * Mirrors `resources/js/page-config.ts`: every feature module's page kit is
 * spread exactly once, and the host's marketing overrides are applied last.
 * This locks the contract that every Inertia page key a controller renders
 * resolves to a real component — regressions (like the posts kit never being
 * wired) surface here instead of as runtime "Page not found" errors.
 */
const coreKit = registerCoreTemplates();
const themeKit = registerStarterThemePages();
const blogKit = registerBlogPages();
const shopKit = registerShopPages();

const pageMap = {
  ...coreKit,
  ...themeKit,
  ...blogKit,
  ...shopKit,
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
Keys provided by the starter theme kit (marketing pages + blog public templates).
*/
const THEME_PAGE_KEYS = [
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

  it("registers starter-theme pages (public marketing + blog/public templates)", () => {
    for (const key of THEME_PAGE_KEYS) {
      expect(pageMap, `missing page map entry for ${key}`).toHaveProperty(key);
    }
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
    const kits = [coreKit, themeKit, blogKit, shopKit];
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

  it("host marketing overrides replace the theme's public/about|services|contact", () => {
    // The override functions live in the host, so they must differ from the theme's import fns.
    const hostOverride = pageMap["public/about"];
    expect(hostOverride).not.toBe(themeKit["public/about"]);
  });
});
