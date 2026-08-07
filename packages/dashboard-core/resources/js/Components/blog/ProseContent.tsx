import { useCallback, useLayoutEffect, useRef } from "react";
import { sanitizeLexicalTables } from "@/lib/sanitize-lexical-tables";
import { buildCodeGutter, countCodeLines } from "@/lib/code-gutter";

interface ProseContentProps {
  html: string;
}

function wrapTables(container: HTMLElement): void {
  const tables = container.querySelectorAll<HTMLElement>("table");
  for (const table of tables) {
    if (table.parentElement?.classList.contains("table-scroll-wrapper")) continue;
    const wrapper = document.createElement("div");
    wrapper.className = "table-scroll-wrapper";
    table.parentNode?.insertBefore(wrapper, table);
    wrapper.appendChild(table);
  }
}

function attachCodeGutters(container: HTMLElement): void {
  const pres = container.querySelectorAll<HTMLElement>("pre");
  for (const pre of pres) {
    if (pre.dataset.gutter !== undefined) continue;

    const code = pre.querySelector("code");
    const source = code ? code.getHTML() : (pre.textContent ?? "");
    const lineCount = countCodeLines(source);
    const gutter = buildCodeGutter(lineCount);
    if (gutter !== undefined) {
      pre.dataset.gutter = gutter;
    }
  }
}

function attachCopyButtons(container: HTMLElement): void {
  const pres = container.querySelectorAll<HTMLElement>("pre");
  for (const pre of pres) {
    if (pre.dataset.copyAttached === "true") continue;
    pre.dataset.copyAttached = "true";
    pre.style.position = "relative";

    const wrapper = document.createElement("div");
    wrapper.className = "code-block-wrapper";
    pre.parentNode?.insertBefore(wrapper, pre);
    wrapper.appendChild(pre);

    const btn = document.createElement("button");
    btn.className = "code-copy-btn";
    btn.type = "button";
    btn.setAttribute("aria-label", "Copy code");
    btn.innerHTML =
      '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="14" height="14" x="8" y="8" rx="2" ry="2"/><path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2"/></svg>';
    btn.addEventListener("click", async () => {
      const code = pre.querySelector("code");
      const text = code?.textContent ?? pre.textContent ?? "";
      try {
        await navigator.clipboard.writeText(text);
        btn.innerHTML =
          '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>';
        btn.classList.add("copied");
        setTimeout(() => {
          btn.innerHTML =
            '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="14" height="14" x="8" y="8" rx="2" ry="2"/><path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2"/></svg>';
          btn.classList.remove("copied");
        }, 2000);
      } catch {
        /*
        ignore
        */
      }
    });
    wrapper.appendChild(btn);
  }
}

export default function ProseContent({ html }: ProseContentProps) {
  const containerRef = useRef<HTMLDivElement>(null);

  const init = useCallback(() => {
    if (!containerRef.current) {
      return;
    }

    wrapTables(containerRef.current);
    attachCodeGutters(containerRef.current);
    attachCopyButtons(containerRef.current);
    sanitizeLexicalTables(containerRef.current);
  }, []);

  useLayoutEffect(() => {
    init();
  }, [html, init]);

  return (
    <div
      ref={containerRef}
      className="prose prose-neutral dark:prose-invert max-w-none mb-8"
      dangerouslySetInnerHTML={{ __html: html }}
    />
  );
}
