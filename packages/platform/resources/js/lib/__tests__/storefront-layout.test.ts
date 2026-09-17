import { describe, expect, it } from "vitest";
import type { ComponentType, ReactNode } from "react";
import { registerStorefrontLayout, resolveStorefrontLayout } from "../storefront-layout";

const LayoutOne: ComponentType<{ children?: ReactNode }> = () => null;
const LayoutTwo: ComponentType<{ children?: ReactNode }> = () => null;

describe("storefront layout registry", () => {
  it("returns null when no layout has been registered", () => {
    expect(resolveStorefrontLayout()).toBeNull();
  });

  it("returns the registered layout", () => {
    registerStorefrontLayout(LayoutOne);
    expect(resolveStorefrontLayout()).toBe(LayoutOne);
  });

  it("last registration wins", () => {
    registerStorefrontLayout(LayoutOne);
    registerStorefrontLayout(LayoutTwo);
    expect(resolveStorefrontLayout()).toBe(LayoutTwo);
  });
});