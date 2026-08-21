import { describe, it, expect } from "vitest";
import { buildPageMap, type FoundationAppConfig, type PageImport } from "@/app";

function stubPage(_name: string): PageImport {
  return async () =>
    ({ default: (() => null) as React.ComponentType }) as { default: React.ComponentType<unknown> };
}

describe("buildPageMap", () => {
  it("returns empty map when no config is provided", () => {
    const result = buildPageMap({});
    expect(result).toEqual({});
  });

  it("includes basePages in the map", () => {
    const config: FoundationAppConfig = {
      basePages: {
        "dashboard/index": stubPage("Dashboard"),
        "auth/login": stubPage("Login"),
      },
    };
    const result = buildPageMap(config);
    expect(result["dashboard/index"]).toBeDefined();
    expect(result["auth/login"]).toBeDefined();
  });

  it("prefixes and lowercases projectPages", () => {
    const config: FoundationAppConfig = {
      projectPages: {
        "/src/Pages/Products/Index.tsx": stubPage("Products"),
        "/src/Pages/Orders/Show.tsx": stubPage("OrderShow"),
      },
      projectPrefix: "shop",
    };
    const result = buildPageMap(config);
    expect(result["shop/products/index"]).toBeDefined();
    expect(result["shop/orders/show"]).toBeDefined();
  });

  it("uses default prefix 'project' when projectPrefix is omitted", () => {
    const config: FoundationAppConfig = {
      projectPages: {
        "/src/Pages/Foo.tsx": stubPage("Foo"),
      },
    };
    const result = buildPageMap(config);
    expect(result["project/foo"]).toBeDefined();
  });

  it("pageOverrides override basePages", () => {
    const baseImport = stubPage("OriginalAbout");
    const overrideImport = stubPage("CustomAbout");

    const config: FoundationAppConfig = {
      basePages: { "public/about": baseImport },
      pageOverrides: { "public/about": overrideImport },
    };
    const result = buildPageMap(config);
    expect(result["public/about"]).toBe(overrideImport);
    expect(result["public/about"]).not.toBe(baseImport);
  });

  it("lowercases override keys", () => {
    const overrideImport = stubPage("CustomAbout");
    const config: FoundationAppConfig = {
      pageOverrides: { "Public/About": overrideImport },
    };
    const result = buildPageMap(config);
    expect(result["public/about"]).toBe(overrideImport);
  });

  it("merges basePages, projectPages, and pageOverrides in correct order", () => {
    const originalAbout = stubPage("About");
    const config: FoundationAppConfig = {
      basePages: {
        "dashboard/index": stubPage("Dashboard"),
        "public/about": originalAbout,
      },
      projectPages: {
        "/src/Pages/Shop.tsx": stubPage("Shop"),
      },
      projectPrefix: "myapp",
      pageOverrides: {
        "public/about": stubPage("CustomAbout"),
      },
    };
    const result = buildPageMap(config);

    expect(Object.keys(result)).toContain("dashboard/index");
    expect(Object.keys(result)).toContain("myapp/shop");
    expect(result["public/about"]).not.toBe(originalAbout);
    expect(Object.keys(result)).toHaveLength(3);
  });

  it("filters out projectPages that do not match the /Pages/ pattern", () => {
    const config: FoundationAppConfig = {
      projectPages: {
        "/src/components/Button.tsx": stubPage("Button"),
        "/src/utils/format.ts": stubPage("Format"),
      },
      projectPrefix: "proj",
    };
    const result = buildPageMap(config);
    expect(Object.keys(result)).toHaveLength(0);
  });

  it("handles empty pageOverrides gracefully", () => {
    const config: FoundationAppConfig = {
      basePages: { "dashboard/index": stubPage("Dashboard") },
      pageOverrides: {},
    };
    const result = buildPageMap(config);
    expect(result["dashboard/index"]).toBeDefined();
    expect(Object.keys(result)).toHaveLength(1);
  });
});
