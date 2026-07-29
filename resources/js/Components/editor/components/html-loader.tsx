import { useEffect, useRef } from "react";
import { useLexicalComposerContext } from "@lexical/react/LexicalComposerContext";
import { $getRoot, $insertNodes } from "lexical";
import { $generateNodesFromDOM } from "@lexical/html";

export function HtmlLoader({ html }: { html: string }) {
  const [editor] = useLexicalComposerContext();
  const loadedRef = useRef(false);

  useEffect(() => {
    if (loadedRef.current) return;
    if (!html) return;

    loadedRef.current = true;

    editor.update(() => {
      const root = $getRoot();
      root.clear();

      const parser = new DOMParser();
      const dom = parser.parseFromString(html, "text/html");
      // Strip background-color from <th> elements to prevent stale header colors
      // from persisting across re-renders (Laravel stores inline styles in DB).
      for (const th of dom.querySelectorAll("th")) {
        th.style.removeProperty("background-color");
      }
      const nodes = $generateNodesFromDOM(editor, dom);
      $insertNodes(nodes);
    });
  }, [editor, html]);

  return null;
}
