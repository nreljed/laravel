const LEXICAL_DEFAULT_HEADER_BG = "rgb(242,243,245)";

function normalizeColor(value: string): string {
  return value.replaceAll(/\s+/g, "").toLowerCase();
}

/**
 * Neutralize hardcoded inline styles that Lexical's TableCellNode.exportDOM()
 * writes into stored HTML:
 *
 *  - `border: 1px solid black` on every cell (always present) — removed so the
 *    theme border (var(--border), boosted in dark mode) applies instead.
 *  - `background-color: rgb(242, 243, 245)` on header cells without an explicit
 *    color (Lexical's light-mode default) — removed only for the default so an
 *    author-set `var(--cell-bg-*)` is preserved.
 */
export function sanitizeLexicalTables(container: HTMLElement): void {
  for (const cell of container.querySelectorAll<HTMLElement>("th, td")) {
    cell.style.removeProperty("border");

    if (cell.tagName !== "TH") {
      continue;
    }

    const bg = cell.style.backgroundColor;
    if (bg && normalizeColor(bg) === LEXICAL_DEFAULT_HEADER_BG) {
      cell.style.removeProperty("background-color");
    }
  }
}
