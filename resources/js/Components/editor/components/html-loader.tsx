import { useEffect, useRef } from "react";
import { useLexicalComposerContext } from "@lexical/react/LexicalComposerContext";
import { $getRoot, $insertNodes } from "lexical";
import { $generateNodesFromDOM } from "@lexical/html";

export function HtmlLoader({ html }: { html: string }) {
  const [editor] = useLexicalComposerContext();
  const lastLoadedRef = useRef<string | null>(null);

  useEffect(() => {
    if (lastLoadedRef.current === html) return;
    lastLoadedRef.current = html;
    if (!html) return;

    editor.update(() => {
      const root = $getRoot();
      const children = root.getChildren();
      const currentHtml = children.length === 0 && !html;

      if (!currentHtml) {
        root.clear();
      }

      const parser = new DOMParser();
      const dom = parser.parseFromString(html, "text/html");
      for (const th of dom.querySelectorAll("th")) {
        th.style.removeProperty("background-color");
      }
      const nodes = $generateNodesFromDOM(editor, dom);
      $insertNodes(nodes);
    });
  }, [editor, html]);

  return null;
}
