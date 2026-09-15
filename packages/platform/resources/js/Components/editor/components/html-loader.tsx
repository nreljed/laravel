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
      // @lexical/code-core reads only `data-language` on import — legacy HTML that
      // carries `class="language-*"` (Prism style) would silently fall back to the
      // default language. Normalize the class into `data-language` first, then
      // propagate it from a language-carrying `<code>` to its wrapping `<pre>`
      // (the PreImportRule reads the attribute from the `<pre>` element).
      for (const el of dom.querySelectorAll<HTMLElement>(
        "pre[class*='language-'], code[class*='language-']",
      )) {
        if (el.dataset.language) continue;
        const match = String(el.getAttribute("class") ?? "").match(/language-([a-zA-Z0-9_+#.-]+)/);
        if (match) el.dataset.language = match[1]!;
      }
      for (const pre of dom.querySelectorAll<HTMLElement>("pre")) {
        if (pre.dataset.language) continue;
        const lang = pre.querySelector<HTMLElement>("code")?.dataset.language;
        if (lang) pre.dataset.language = lang;
      }
      const nodes = $generateNodesFromDOM(editor, dom);
      $insertNodes(nodes);
    });
  }, [editor, html]);

  return null;
}
