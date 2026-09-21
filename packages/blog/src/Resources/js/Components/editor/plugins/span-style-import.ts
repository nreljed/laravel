import {
  $isTextNode,
  type DOMConversionFn,
  type DOMConversionMap,
  type TextFormatType,
} from "lexical";

const CASE_FORMATS: ReadonlySet<string> = new Set(["lowercase", "uppercase", "capitalize"]);

/**
 * Overrides the core `<span>` HTML import for this editor. The default span
 * importer only restores format *bits* (bold/italic/underline/…) from span
 * inline styles — inline `color` and `background-color` are silently dropped,
 * so text/highlight colors set in the toolbar vanish on the next load of the
 * same content.
 *
 * This converter re-implements the core span behavior AND preserves
 * `color` / `background-color` onto the TextNode's `__style`, matching what
 * the editor exports (`<span style="color: …">` / `"background-color: …"`).
 */
const convertSpanPreservingTextStyle: DOMConversionFn<HTMLElement> = (element) => {
  const style = element.style;
  const fontWeight = style.fontWeight;
  const textDecoration = style.textDecoration.split(" ");
  const hasBold = fontWeight === "700" || fontWeight === "bold";
  const hasStrikethrough = textDecoration.includes("line-through");
  const hasItalic = style.fontStyle === "italic";
  const hasUnderline = textDecoration.includes("underline");
  const verticalAlign = style.verticalAlign;
  const textTransform = style.textTransform;
  const color = style.color;
  const backgroundColor = style.backgroundColor;

  return {
    forChild: (node) => {
      if (!$isTextNode(node)) {
        return node;
      }
      if (hasBold && !node.hasFormat("bold")) {
        node.toggleFormat("bold");
      }
      if (hasStrikethrough && !node.hasFormat("strikethrough")) {
        node.toggleFormat("strikethrough");
      }
      if (hasItalic && !node.hasFormat("italic")) {
        node.toggleFormat("italic");
      }
      if (hasUnderline && !node.hasFormat("underline")) {
        node.toggleFormat("underline");
      }
      if (verticalAlign === "sub" && !node.hasFormat("subscript")) {
        node.toggleFormat("subscript");
      }
      if (verticalAlign === "super" && !node.hasFormat("superscript")) {
        node.toggleFormat("superscript");
      }
      if (CASE_FORMATS.has(textTransform) && !node.hasFormat(textTransform as TextFormatType)) {
        node.toggleFormat(textTransform as TextFormatType);
      }

      const parts: string[] = [];
      if (color && color !== "transparent") {
        parts.push(`color: ${color};`);
      }
      if (backgroundColor && backgroundColor !== "transparent") {
        parts.push(`background-color: ${backgroundColor};`);
      }
      if (parts.length > 0) {
        node.setStyle([node.getStyle().trim(), parts.join(" ")].filter(Boolean).join(" "));
      }
      return node;
    },
    node: null,
  };
};

/**
 * Editor-level `html.import` override map consumed by `defineExtension`.
 * Registration order makes it win over the core span importer at equal
 * priority (the HTMLConfig import map is applied after node conversions).
 */
export const SPAN_STYLE_IMPORT: DOMConversionMap = {
  span: () => ({
    conversion: convertSpanPreservingTextStyle,
    priority: 0,
  }),
};