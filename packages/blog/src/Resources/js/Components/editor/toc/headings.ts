import { $getRoot, $isElementNode, type LexicalNode } from "lexical";
import { $isHeadingNode, type HeadingTagType } from "@lexical/rich-text";

export interface HeadingEntry {
  key: string;
  tag: HeadingTagType;
  text: string;
  index: number;
}

export const HEADING_LEVEL: Record<HeadingTagType, number> = {
  h1: 1,
  h2: 2,
  h3: 3,
  h4: 4,
  h5: 5,
  h6: 6,
};

export function headingLevel(tag: HeadingTagType): number {
  return HEADING_LEVEL[tag];
}

export interface TocTreeItem {
  key: string;
  tag: HeadingTagType;
  text: string;
  level: number;
  number: string;
  children: TocTreeItem[];
}

/**
 * Build the nested outline tree a ToC block shows, mirroring the algorithm the
 * platform's save-time content builder uses for the public renderer:
 *
 * - headings deeper than `maxDepth` are dropped from the outline
 * - empty-text headings are skipped (no navigable label)
 * - a heading nests under the closest lower level already seen (h1 → h3 nests
 *   under the h1); a level with no lower ancestor becomes a top-level item
 * - each item gets a hierarchical number ("1", "1.2", "1.2.3") matching the
 *   CSS-counter numbering the public renderer applies when `data-toc-numbers="1"`.
 */
export function buildTocTree(headings: HeadingEntry[], maxDepth: number): TocTreeItem[] {
  const root: TocTreeItem = {
    key: "",
    tag: "h1",
    text: "",
    level: 0,
    number: "",
    children: [],
  };
  const lastByLevel = new Map<number, TocTreeItem>([[0, root]]);

  for (const heading of headings) {
    if (heading.text.trim() === "") {
      continue;
    }

    const level = headingLevel(heading.tag);
    if (level > maxDepth) {
      continue;
    }

    let parent: TocTreeItem = root;
    for (let l = level - 1; l >= 1; l--) {
      const candidate = lastByLevel.get(l);
      if (candidate) {
        parent = candidate;
        break;
      }
    }

    const item: TocTreeItem = {
      key: heading.key,
      tag: heading.tag,
      text: heading.text,
      level,
      number: "",
      children: [],
    };
    parent.children.push(item);
    lastByLevel.set(level, item);
  }

  const assignNumbers = (items: TocTreeItem[], prefix: string): void => {
    items.forEach((item, index) => {
      item.number = prefix === "" ? `${index + 1}` : `${prefix}.${index + 1}`;
      assignNumbers(item.children, item.number);
    });
  };
  assignNumbers(root.children, "");

  return root.children;
}

export function $getHeadings(): HeadingEntry[] {
  const headings: HeadingEntry[] = [];
  const root = $getRoot();
  const children = root.getChildren();
  let index = 0;

  const collect = (node: LexicalNode) => {
    if ($isHeadingNode(node)) {
      headings.push({
        key: node.getKey(),
        tag: node.getTag(),
        text: node.getTextContent(),
        index,
      });
      index++;
    } else if ($isElementNode(node)) {
      for (const child of node.getChildren()) {
        collect(child);
      }
    }
  };

  for (const child of children) {
    collect(child);
  }

  return headings;
}

export const HEADING_INDENT: Record<HeadingTagType, string> = {
  h1: "pl-0",
  h2: "pl-3",
  h3: "pl-6",
  h4: "pl-9",
  h5: "pl-12",
  h6: "pl-15",
};

export const HEADING_SIZE: Record<HeadingTagType, string> = {
  h1: "font-bold text-base",
  h2: "font-semibold text-sm",
  h3: "font-medium text-sm",
  h4: "text-sm",
  h5: "text-xs",
  h6: "text-xs",
};