import { describe, expect, it } from "vitest";
import { normalizeVersion } from "../versions";

describe("normalizeVersion", () => {
  it("strips a leading v prefix", () => {
    expect(normalizeVersion("v0.1.3")).toBe("0.1.3");
    expect(normalizeVersion("v0.9.2")).toBe("0.9.2");
  });

  it("is case-insensitive for the prefix", () => {
    expect(normalizeVersion("V0.1.3")).toBe("0.1.3");
  });

  it("leaves unprefixed versions untouched", () => {
    expect(normalizeVersion("0.1.3")).toBe("0.1.3");
  });

  it("trims surrounding whitespace", () => {
    expect(normalizeVersion("  v0.1.3  ")).toBe("0.1.3");
  });

  it("handles null and undefined", () => {
    expect(normalizeVersion(null)).toBe("");
    expect(normalizeVersion(undefined)).toBe("");
  });

  it("makes equivalent versions comparable", () => {
    expect(normalizeVersion("0.1.3")).toBe(normalizeVersion("v0.1.3"));
  });
});
