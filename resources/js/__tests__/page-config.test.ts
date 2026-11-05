import { describe, expect, it } from "vitest";

import { pageConfig } from "../page-config";
import { registerBlogPages } from "@nreljed/blog/pages";
import { registerCoreTemplates } from "@nreljed/platform/dashboard";
import { registerMarketingPages } from "@nreljed/marketing-module/pages";

describe("page-config marketing opt-in", () => {
  it("the mono opts into marketing, so marketing/* keys are present", () => {
    const base = pageConfig.basePages ?? {};
    const marketingKeys = Object.keys(base).filter((k) => k.startsWith("marketing/"));
    expect(marketingKeys.length).toBeGreaterThan(0);
  });

  it("without spreading registerMarketingPages(), no marketing/* key resolves", () => {
    const withoutMarketing: Record<string, unknown> = {
      ...registerCoreTemplates(),
      ...registerBlogPages(),
    };

    const leaked = Object.keys(withoutMarketing).filter((k) => k.startsWith("marketing/"));
    expect(leaked).toEqual([]);

    const marketingKeys = Object.keys(registerMarketingPages());
    expect(marketingKeys.every((k) => k.startsWith("marketing/"))).toBe(true);
    expect(marketingKeys.length).toBeGreaterThan(0);
  });
});
