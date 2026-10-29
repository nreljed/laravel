import { describe, expect, it } from "vitest";
import { HEADING, TRANSFORMERS } from "@lexical/markdown";
import {
  BODY_TRANSFORMERS,
  clampBodyHeadingLevel,
  MAX_BODY_HEADING_LEVEL,
  MIN_BODY_HEADING_LEVEL,
} from "../body-heading";

describe("clampBodyHeadingLevel", () => {
  it("clamps a single hash to H2 because the post title owns the page H1", () => {
    expect(clampBodyHeadingLevel(1)).toBe(2);
  });

  it("keeps H2 and deeper levels unchanged", () => {
    expect(clampBodyHeadingLevel(2)).toBe(2);
    expect(clampBodyHeadingLevel(3)).toBe(3);
    expect(clampBodyHeadingLevel(6)).toBe(6);
  });

  it("caps depths beyond H6", () => {
    expect(clampBodyHeadingLevel(7)).toBe(MAX_BODY_HEADING_LEVEL);
    expect(clampBodyHeadingLevel(99)).toBe(6);
  });

  it("exposes the documented bounds", () => {
    expect(MIN_BODY_HEADING_LEVEL).toBe(2);
    expect(MAX_BODY_HEADING_LEVEL).toBe(6);
  });
});

describe("BODY_TRANSFORMERS", () => {
  it("replaces the stock HEADING transformer while preserving the rest", () => {
    expect(TRANSFORMERS).toContain(HEADING);
    expect(BODY_TRANSFORMERS).not.toContain(HEADING);
    expect(BODY_TRANSFORMERS).toHaveLength(TRANSFORMERS.length);
  });
});
