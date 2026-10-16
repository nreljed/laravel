import { describe, expect, it } from "vitest";
import { demoteBodyHeadings } from "./demote-headings";

describe("demoteBodyHeadings", () => {
  it("demotes a leading h1 to h2", () => {
    expect(demoteBodyHeadings("<h1>Title</h1>")).toBe("<h2>Title</h2>");
  });

  it("keeps attributes when demoting", () => {
    expect(demoteBodyHeadings('<h1 class="lead">Title</h1>')).toBe('<h2 class="lead">Title</h2>');
  });

  it("demotes every h1 in the body", () => {
    expect(demoteBodyHeadings("<h1>A</h1><p>x</p><h1>B</h1>")).toBe("<h2>A</h2><p>x</p><h2>B</h2>");
  });

  it("leaves h2-h6 and heading text untouched", () => {
    const html = "<h2>Two</h2><h3>Three</h3><p>the h1 tag is fine</p>";
    expect(demoteBodyHeadings(html)).toBe(html);
  });

  it("does not touch escaped headings inside code blocks", () => {
    const html = "<pre><code>&lt;h1&gt;x&lt;/h1&gt;</code></pre>";
    expect(demoteBodyHeadings(html)).toBe(html);
  });
});
