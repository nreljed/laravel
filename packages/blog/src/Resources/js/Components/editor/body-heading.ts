import { HEADING, TRANSFORMERS, type ElementTransformer, type Transformer } from "@lexical/markdown";
import { $createHeadingNode, type HeadingTagType } from "@lexical/rich-text";

/**
 * Editor body content is rendered beneath the post title, which is the page's
 * single `<h1>`. A markdown H1 typed in the body (`# Title`) would emit a
 * second `<h1>` and break the document outline for search engines, so body
 * headings are clamped to a minimum of H2: `#` and `##` both become H2, while
 * `###`+ keep their level (capped at H6).
 */
export const MIN_BODY_HEADING_LEVEL = 2;
export const MAX_BODY_HEADING_LEVEL = 6;

export function clampBodyHeadingLevel(hashCount: number): number {
  return Math.min(Math.max(hashCount, MIN_BODY_HEADING_LEVEL), MAX_BODY_HEADING_LEVEL);
}

const BODY_HEADING: ElementTransformer = {
  ...HEADING,
  replace: (parentNode, children, match, isImport) => {
    const level = clampBodyHeadingLevel((match[1] ?? "#").length);
    const node = $createHeadingNode(`h${level}` as HeadingTagType);
    node.append(...children);
    parentNode.replace(node);
    if (!isImport) {
      node.select(0, 0);
    }
  },
};

export const BODY_TRANSFORMERS: Transformer[] = TRANSFORMERS.map((transformer) =>
  transformer === HEADING ? BODY_HEADING : transformer,
);
