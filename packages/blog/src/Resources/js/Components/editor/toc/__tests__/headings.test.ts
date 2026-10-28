import { describe, expect, it } from "vitest";
import { buildTocTree, headingLevel, type HeadingEntry, type TocTreeItem } from "../headings";

function heading(tag: string, text: string): HeadingEntry {
  return {
    key: `${tag}-${text}`,
    tag: tag as HeadingEntry["tag"],
    text,
    index: 0,
  };
}

describe("headingLevel", () => {
  it("maps heading tags to their numeric level", () => {
    expect(headingLevel("h1")).toBe(1);
    expect(headingLevel("h2")).toBe(2);
    expect(headingLevel("h6")).toBe(6);
  });
});

describe("buildTocTree", () => {
  it("returns an empty tree for no headings", () => {
    expect(buildTocTree([], 6)).toEqual([]);
  });

  it("nests children under the closest lower level", () => {
    const tree = buildTocTree(
      [heading("h2", "Section"), heading("h3", "Sub section"), heading("h2", "Another")],
      6,
    );

    expect(tree).toHaveLength(2);
    expect(tree[0]?.text).toBe("Section");
    expect(tree[0]?.children).toHaveLength(1);
    expect(tree[0]?.children[0]?.text).toBe("Sub section");
    expect(tree[1]?.text).toBe("Another");
    expect(tree[1]?.children).toHaveLength(0);
  });

  it("falls back to the closest lower level for gaps (h1 + h3)", () => {
    const tree = buildTocTree([heading("h1", "Story"), heading("h3", "Deep point")], 6);

    expect(tree).toHaveLength(1);
    expect(tree[0]?.text).toBe("Story");
    expect(tree[0]?.children[0]?.text).toBe("Deep point");
  });

  it("splits siblings sharing a nested level under the correct parents", () => {
    const tree = buildTocTree(
      [heading("h2", "A"), heading("h3", "A1"), heading("h3", "A2"), heading("h2", "B")],
      6,
    );

    expect(tree[0]?.text).toBe("A");
    expect(tree[0]?.children.map((c: TocTreeItem) => c.text)).toEqual(["A1", "A2"]);
    expect(tree[1]?.children).toHaveLength(0);
  });

  it("filters headings deeper than maxDepth from the outline", () => {
    const tree = buildTocTree(
      [heading("h2", "Visible"), heading("h3", "Deeper"), heading("h4", "Deepest")],
      3,
    );

    // Deepest (h4) is above maxDepth 3 and must not appear anywhere.
    const texts = tree.map((item: TocTreeItem) => item.text);
    expect(texts).toEqual(["Visible"]);
    expect(tree[0]?.children.map((c: TocTreeItem) => c.text)).toEqual(["Deeper"]);
    expect(tree[0]?.children[0]?.children).toHaveLength(0);
  });

  it("filters to a single top level when maxDepth equals the first heading level", () => {
    const tree = buildTocTree([heading("h2", "Top"), heading("h3", "Nested")], 2);

    expect(tree).toHaveLength(1);
    expect(tree[0]?.text).toBe("Top");
    expect(tree[0]?.children).toHaveLength(0);
  });

  it("skips headings with empty text", () => {
    const tree = buildTocTree([heading("h2", "   "), heading("h2", "Real")], 6);

    expect(tree).toHaveLength(1);
    expect(tree[0]?.text).toBe("Real");
  });

  it("assigns hierarchical numbers matching CSS counter numbering", () => {
    const tree = buildTocTree(
      [
        heading("h1", "Chapter"),
        heading("h2", "Part"),
        heading("h3", "Deep"),
        heading("h2", "Part Two"),
      ],
      6,
    );

    const numbers: string[] = [];
    const collect = (items: TocTreeItem[]): void => {
      items.forEach((item) => {
        numbers.push(`${item.text}:${item.number}`);
        collect(item.children);
      });
    };
    collect(tree);

    expect(numbers).toEqual(["Chapter:1", "Part:1.1", "Deep:1.1.1", "Part Two:1.2"]);
  });
});