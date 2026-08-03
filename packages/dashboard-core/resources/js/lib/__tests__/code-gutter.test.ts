import { describe, it, expect } from "vitest";
import { countCodeLines, buildCodeGutter } from "@/lib/code-gutter";

describe("countCodeLines", () => {
  it("returns 1 for empty or single-line input", () => {
    expect(countCodeLines("")).toBe(1);
    expect(countCodeLines("<?php")).toBe(1);
    expect(countCodeLines("no newline here")).toBe(1);
  });

  it("counts literal newlines", () => {
    expect(countCodeLines("a\nb\nc")).toBe(3);
    expect(countCodeLines("<?php\n\nnamespace App;")).toBe(3);
  });

  it("counts <br> tags (Lexical exported HTML)", () => {
    expect(countCodeLines("<span>a</span><br><span>b</span>")).toBe(2);
    expect(countCodeLines("<br><br><br>")).toBe(4);
  });

  it("takes the max of <br> and newline counts", () => {
    expect(countCodeLines("<span>a</span><br>b\nc\n")).toBe(3);
  });
});

describe("buildCodeGutter", () => {
  it("returns undefined for single-line blocks", () => {
    expect(buildCodeGutter(1)).toBeUndefined();
  });

  it("builds a newline-joined numbered gutter", () => {
    expect(buildCodeGutter(3)).toBe("1\n2\n3");
    expect(buildCodeGutter(13)).toBe("1\n2\n3\n4\n5\n6\n7\n8\n9\n10\n11\n12\n13");
  });
});
